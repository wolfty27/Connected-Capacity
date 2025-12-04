<?php

namespace App\Services\Llm;

use App\Services\Llm\Contracts\ExplanationProviderInterface;
use App\Services\Llm\DTOs\ExplanationResponseDTO;
use App\Services\Llm\Exceptions\VertexAiAuthException;
use App\Services\Llm\Exceptions\VertexAiException;
use App\Services\Llm\Exceptions\VertexAiRateLimitException;
use App\Services\Llm\Exceptions\VertexAiTimeoutException;
use App\Services\Llm\Fallback\RulesBasedExplanationProvider;
use App\Services\Llm\VertexAi\VertexAiClient;
use App\Services\Llm\VertexAi\VertexAiConfig;
use App\Services\Scheduling\DTOs\AssignmentSuggestionDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LlmExplanationService
 *
 * Main orchestrator for generating assignment explanations.
 *
 * Responsibilities:
 * - Tries Vertex AI first (if enabled and available)
 * - Falls back to rules-based provider on errors
 * - Logs all explanation requests for audit
 * - Measures response times
 *
 * Usage:
 *   $service = app(LlmExplanationService::class);
 *   $explanation = $service->getExplanation($suggestion);
 */
class LlmExplanationService
{
    private VertexAiConfig $config;
    private PromptBuilder $promptBuilder;
    private RulesBasedExplanationProvider $fallbackProvider;
    private ?VertexAiClient $vertexAiClient = null;

    public function __construct(
        PromptBuilder $promptBuilder,
        RulesBasedExplanationProvider $fallbackProvider
    ) {
        $this->config = VertexAiConfig::fromConfig();
        $this->promptBuilder = $promptBuilder;
        $this->fallbackProvider = $fallbackProvider;

        if ($this->config->isEnabled()) {
            $this->vertexAiClient = new VertexAiClient($this->config);
        }
    }

    /**
     * Get explanation for an assignment suggestion.
     *
     * Tries Vertex AI first, falls back to rules-based if unavailable.
     *
     * @param AssignmentSuggestionDTO $suggestion The suggestion to explain
     * @param int|null $requestedBy User ID who requested the explanation
     * @return ExplanationResponseDTO The generated explanation
     */
    public function getExplanation(
        AssignmentSuggestionDTO $suggestion,
        ?int $requestedBy = null
    ): ExplanationResponseDTO {
        $startTime = microtime(true);

        // Handle "no match" case - always use rules-based
        if ($suggestion->matchStatus === 'none' || !$suggestion->suggestedStaffId) {
            $explanation = $this->getNoMatchExplanation($suggestion);
            $this->logExplanationAttempt($suggestion, 'rules_based', 'no_match_case', $requestedBy, $explanation);
            return $explanation;
        }

        // If Vertex AI is not configured, use fallback directly
        if (!$this->config->isEnabled() || !$this->vertexAiClient) {
            $explanation = $this->fallbackProvider->generateExplanation($suggestion);
            $this->logExplanationAttempt($suggestion, 'rules_based', 'vertex_ai_disabled', $requestedBy, $explanation);
            return $explanation;
        }

        // Try Vertex AI
        try {
            $explanation = $this->generateWithVertexAi($suggestion, $startTime);
            $this->logExplanationAttempt($suggestion, 'vertex_ai', 'success', $requestedBy, $explanation);
            return $explanation;

        } catch (VertexAiTimeoutException $e) {
            $this->logWarning('Vertex AI timeout, using fallback', [
                'patient_ref' => $this->promptBuilder->generatePatientRef($suggestion->patientId),
                'service_type' => $suggestion->serviceTypeCode,
                'error' => $e->getMessage(),
            ]);

            $explanation = $this->fallbackProvider->generateExplanation($suggestion);
            $this->logExplanationAttempt($suggestion, 'rules_based', 'vertex_ai_timeout', $requestedBy, $explanation);
            return $explanation;

        } catch (VertexAiRateLimitException $e) {
            $this->logWarning('Vertex AI rate limited, using fallback', [
                'patient_ref' => $this->promptBuilder->generatePatientRef($suggestion->patientId),
                'error' => $e->getMessage(),
            ]);

            $explanation = $this->fallbackProvider->generateExplanation($suggestion);
            $this->logExplanationAttempt($suggestion, 'rules_based', 'vertex_ai_rate_limited', $requestedBy, $explanation);
            return $explanation;

        } catch (VertexAiAuthException $e) {
            $this->logError('Vertex AI authentication failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $explanation = $this->fallbackProvider->generateExplanation($suggestion);
            $this->logExplanationAttempt($suggestion, 'rules_based', 'vertex_ai_auth_error', $requestedBy, $explanation);
            return $explanation;

        } catch (VertexAiException $e) {
            $this->logError('Vertex AI error, using fallback', [
                'patient_ref' => $this->promptBuilder->generatePatientRef($suggestion->patientId),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $explanation = $this->fallbackProvider->generateExplanation($suggestion);
            $this->logExplanationAttempt($suggestion, 'rules_based', 'vertex_ai_error', $requestedBy, $explanation);
            return $explanation;

        } catch (\Exception $e) {
            $this->logError('Unexpected error in LLM explanation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $explanation = $this->fallbackProvider->generateExplanation($suggestion);
            $this->logExplanationAttempt($suggestion, 'rules_based', 'unexpected_error', $requestedBy, $explanation);
            return $explanation;
        }
    }

    /**
     * Generate explanation using Vertex AI.
     */
    private function generateWithVertexAi(
        AssignmentSuggestionDTO $suggestion,
        float $startTime
    ): ExplanationResponseDTO {
        // Build PII-safe prompt
        $promptPayload = $this->promptBuilder->buildPromptPayload($suggestion);

        // Call Vertex AI
        $response = $this->vertexAiClient->generateContent($promptPayload);

        // Calculate response time
        $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        return new ExplanationResponseDTO(
            shortExplanation: $response['short_explanation'] ?? 'Explanation generated.',
            detailedPoints: $response['detailed_points'] ?? [],
            confidenceLabel: $response['confidence_label'] ?? 'Unknown',
            source: 'vertex_ai',
            generatedAt: Carbon::now(),
            responseTimeMs: $responseTimeMs,
        );
    }

    /**
     * Get explanation for why no staff match was found.
     */
    public function getNoMatchExplanation(AssignmentSuggestionDTO $suggestion): ExplanationResponseDTO
    {
        return $this->fallbackProvider->generateNoMatchExplanation(
            $suggestion->exclusionReasons ?? []
        );
    }

    /**
     * Check if Vertex AI is enabled and available.
     */
    public function isVertexAiEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Get the current configuration (sanitized for logging).
     */
    public function getConfigSummary(): array
    {
        return $this->config->toLogArray();
    }

    /**
     * Log explanation attempt to audit table.
     *
     * IMPORTANT: This logs ONLY IDs and status, NEVER prompts or responses.
     */
    private function logExplanationAttempt(
        AssignmentSuggestionDTO $suggestion,
        string $source,
        string $status,
        ?int $requestedBy,
        ExplanationResponseDTO $explanation
    ): void {
        try {
            DB::table('llm_explanation_logs')->insert([
                'patient_id' => $suggestion->patientId,
                'staff_id' => $suggestion->suggestedStaffId,
                'service_type_id' => $suggestion->serviceTypeId,
                'organization_id' => $suggestion->organizationId,
                'requested_by' => $requestedBy,
                'source' => $source,
                'status' => $status,
                'confidence_score' => $suggestion->confidenceScore,
                'match_status' => $suggestion->matchStatus,
                'candidates_evaluated' => $suggestion->candidatesEvaluated,
                'candidates_passed' => $suggestion->candidatesPassed,
                'response_time_ms' => $explanation->responseTimeMs,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Don't let logging failures break the main flow
            $this->logError('Failed to log explanation attempt', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log a warning message.
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->config->loggingEnabled) {
            Log::channel($this->config->logChannel)->warning("[LlmExplanationService] {$message}", $context);
        }
    }

    /**
     * Log an error message.
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->config->loggingEnabled) {
            Log::channel($this->config->logChannel)->error("[LlmExplanationService] {$message}", $context);
        }
    }

    // ==========================================
    // Weekly Summary (Scheduler 2.0)
    // ==========================================

    /**
     * Generate a weekly AI summary for the scheduler overview.
     * 
     * Provides a natural language summary of:
     * - Staffing situation (capacity vs demand)
     * - Key risks and attention areas
     * - Suggested priorities for the week
     * 
     * @param array $weeklyMetrics Aggregated metrics for the week
     * @param int|null $requestedBy User ID requesting the summary
     * @return array{summary: string, highlights: array, priorities: array, source: string}
     */
    public function generateWeeklySummary(array $weeklyMetrics, ?int $requestedBy = null): array
    {
        $startTime = microtime(true);

        // Build the summary prompt
        $summaryPrompt = $this->buildWeeklySummaryPrompt($weeklyMetrics);

        // If Vertex AI is disabled or unavailable, use rules-based fallback
        if (!$this->config->isEnabled() || !$this->vertexAiClient) {
            return $this->generateRulesBasedWeeklySummary($weeklyMetrics);
        }

        // Try Vertex AI
        try {
            $response = $this->vertexAiClient->generateContent($summaryPrompt);
            
            $result = [
                'summary' => $response['summary'] ?? $this->buildFallbackSummaryText($weeklyMetrics),
                'highlights' => $response['highlights'] ?? [],
                'priorities' => $response['priorities'] ?? [],
                'source' => 'vertex_ai',
                'response_time_ms' => (int) round((microtime(true) - $startTime) * 1000),
            ];

            $this->logWeeklySummaryAttempt('vertex_ai', 'success', $requestedBy, $result);
            return $result;

        } catch (\Exception $e) {
            $this->logWarning('Vertex AI weekly summary failed, using fallback', [
                'error' => $e->getMessage(),
            ]);

            $result = $this->generateRulesBasedWeeklySummary($weeklyMetrics);
            $this->logWeeklySummaryAttempt('rules_based', 'vertex_ai_error', $requestedBy, $result);
            return $result;
        }
    }

    /**
     * Build the prompt for weekly summary generation.
     */
    private function buildWeeklySummaryPrompt(array $metrics): array
    {
        $prompt = "You are an AI scheduling assistant for a home healthcare organization. 
Generate a brief, actionable weekly scheduling summary based on these metrics:

STAFFING:
- Total Staff: {$metrics['total_staff']}
- Available Capacity: {$metrics['available_hours']}h
- Scheduled Hours: {$metrics['scheduled_hours']}h
- Net Capacity: {$metrics['net_capacity']}h

CARE DEMAND:
- Unscheduled Care: {$metrics['unscheduled_hours']}h ({$metrics['unscheduled_visits']} visits)
- Patients Needing Care: {$metrics['patients_needing_care']}

AI SUGGESTIONS:
- Total Suggestions: {$metrics['total_suggestions']}
- Strong Matches: {$metrics['strong_matches']}
- Moderate Matches: {$metrics['moderate_matches']}
- Weak/No Match: {$metrics['weak_matches']}

METRICS:
- Time-to-First-Service: {$metrics['tfs_hours']}h (target: <24h)
- Missed Care Rate: {$metrics['missed_care_rate']}% (target: 0%)

Provide a response in this JSON format:
{
  \"summary\": \"A 2-3 sentence overview of the week's scheduling situation\",
  \"highlights\": [\"Key insight 1\", \"Key insight 2\", \"Key insight 3\"],
  \"priorities\": [\"Priority action 1\", \"Priority action 2\", \"Priority action 3\"]
}";

        return [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 500,
            ],
        ];
    }

    /**
     * Generate rules-based weekly summary (fallback).
     */
    private function generateRulesBasedWeeklySummary(array $metrics): array
    {
        $summary = $this->buildFallbackSummaryText($metrics);
        $highlights = $this->buildFallbackHighlights($metrics);
        $priorities = $this->buildFallbackPriorities($metrics);

        return [
            'summary' => $summary,
            'highlights' => $highlights,
            'priorities' => $priorities,
            'source' => 'rules_based',
            'response_time_ms' => 0,
        ];
    }

    /**
     * Build fallback summary text based on metrics.
     */
    private function buildFallbackSummaryText(array $metrics): string
    {
        $netCapacity = $metrics['net_capacity'] ?? 0;
        $unscheduledHours = $metrics['unscheduled_hours'] ?? 0;
        $strongMatches = $metrics['strong_matches'] ?? 0;
        $totalSuggestions = $metrics['total_suggestions'] ?? 0;

        $capacityStatus = $netCapacity > $unscheduledHours 
            ? 'sufficient capacity' 
            : ($netCapacity < 0 ? 'over capacity' : 'tight capacity');

        $aiStatus = $totalSuggestions > 0
            ? "{$strongMatches} of {$totalSuggestions} AI suggestions are high-confidence"
            : 'No AI suggestions available';

        return "This week shows {$capacityStatus} with {$unscheduledHours}h of care still to schedule. {$aiStatus}.";
    }

    /**
     * Build fallback highlights based on metrics.
     */
    private function buildFallbackHighlights(array $metrics): array
    {
        $highlights = [];

        // Capacity highlight
        $netCapacity = $metrics['net_capacity'] ?? 0;
        if ($netCapacity < 0) {
            $highlights[] = "⚠️ Team is {$netCapacity}h over capacity - consider overtime or SSPO support";
        } elseif ($netCapacity < 20) {
            $highlights[] = "Capacity is tight with only {$netCapacity}h available";
        } else {
            $highlights[] = "✓ Good capacity buffer of {$netCapacity}h available";
        }

        // AI suggestions highlight
        $strongMatches = $metrics['strong_matches'] ?? 0;
        if ($strongMatches > 0) {
            $highlights[] = "{$strongMatches} visits can be auto-assigned with high confidence";
        }

        // TFS highlight
        $tfsHours = $metrics['tfs_hours'] ?? 0;
        if ($tfsHours > 24) {
            $highlights[] = "⚠️ TFS at {$tfsHours}h exceeds 24h target";
        } elseif ($tfsHours > 0) {
            $highlights[] = "✓ TFS on track at {$tfsHours}h";
        }

        return array_slice($highlights, 0, 3);
    }

    /**
     * Build fallback priorities based on metrics.
     */
    private function buildFallbackPriorities(array $metrics): array
    {
        $priorities = [];

        // Priority 1: Handle high-confidence suggestions
        $strongMatches = $metrics['strong_matches'] ?? 0;
        $moderateMatches = $metrics['moderate_matches'] ?? 0;
        if ($strongMatches + $moderateMatches > 0) {
            $priorities[] = "Review and approve " . ($strongMatches + $moderateMatches) . " AI-suggested assignments";
        }

        // Priority 2: Address weak/no matches
        $weakMatches = $metrics['weak_matches'] ?? 0;
        if ($weakMatches > 0) {
            $priorities[] = "Manually resolve {$weakMatches} visits with no strong staff match";
        }

        // Priority 3: Capacity management
        $netCapacity = $metrics['net_capacity'] ?? 0;
        if ($netCapacity < 0) {
            $priorities[] = "Redistribute workload or request SSPO support";
        } elseif (($metrics['unscheduled_hours'] ?? 0) > 50) {
            $priorities[] = "Schedule remaining " . ($metrics['unscheduled_hours'] ?? 0) . "h of unscheduled care";
        }

        // Priority 4: TFS improvement if needed
        if (($metrics['tfs_hours'] ?? 0) > 24) {
            $priorities[] = "Prioritize first visits to improve TFS metric";
        }

        return array_slice($priorities, 0, 3);
    }

    /**
     * Log weekly summary attempt.
     */
    private function logWeeklySummaryAttempt(string $source, string $status, ?int $requestedBy, array $result): void
    {
        try {
            DB::table('llm_explanation_logs')->insert([
                'patient_id' => null,
                'staff_id' => null,
                'service_type_id' => null,
                'organization_id' => null,
                'requested_by' => $requestedBy,
                'source' => $source,
                'status' => 'weekly_summary_' . $status,
                'confidence_score' => null,
                'match_status' => null,
                'candidates_evaluated' => null,
                'candidates_passed' => null,
                'response_time_ms' => $result['response_time_ms'] ?? 0,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Don't let logging failures break the main flow
            $this->logError('Failed to log weekly summary attempt', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
