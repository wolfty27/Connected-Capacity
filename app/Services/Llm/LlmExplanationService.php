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
}
