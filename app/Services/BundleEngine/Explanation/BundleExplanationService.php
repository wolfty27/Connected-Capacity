<?php

namespace App\Services\BundleEngine\Explanation;

use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;
use App\Services\Llm\DTOs\ExplanationResponseDTO;
use App\Services\Llm\Exceptions\VertexAiAuthException;
use App\Services\Llm\Exceptions\VertexAiException;
use App\Services\Llm\Exceptions\VertexAiRateLimitException;
use App\Services\Llm\Exceptions\VertexAiTimeoutException;
use App\Services\Llm\VertexAi\VertexAiClient;
use App\Services\Llm\VertexAi\VertexAiConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BundleExplanationService
 *
 * Main orchestrator for generating AI explanations of bundle scenario selections.
 *
 * Responsibilities:
 * - Tries Vertex AI first (if enabled and available)
 * - Falls back to rules-based provider on errors
 * - Logs all explanation requests for audit
 * - Measures response times
 *
 * Usage:
 *   $service = app(BundleExplanationService::class);
 *   $explanation = $service->explainScenario($profile, $scenario);
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 6
 */
class BundleExplanationService
{
    private VertexAiConfig $config;
    private BundleExplanationPromptBuilder $promptBuilder;
    private RulesBasedBundleExplanationProvider $fallbackProvider;
    private ?VertexAiClient $vertexAiClient = null;

    public function __construct(
        ?BundleExplanationPromptBuilder $promptBuilder = null,
        ?RulesBasedBundleExplanationProvider $fallbackProvider = null
    ) {
        $this->config = VertexAiConfig::fromConfig();
        $this->promptBuilder = $promptBuilder ?? new BundleExplanationPromptBuilder();
        $this->fallbackProvider = $fallbackProvider ?? new RulesBasedBundleExplanationProvider();

        if ($this->config->isEnabled()) {
            $this->vertexAiClient = new VertexAiClient($this->config);
        }
    }

    /**
     * Generate explanation for why a scenario was selected.
     *
     * @param PatientNeedsProfile $profile Patient's needs profile
     * @param ScenarioBundleDTO $scenario The selected scenario
     * @param array $alternativeScenarios Other scenarios for comparison context
     * @param int|null $requestedBy User ID who requested the explanation
     * @return ExplanationResponseDTO
     */
    public function explainScenario(
        PatientNeedsProfile $profile,
        ScenarioBundleDTO $scenario,
        array $alternativeScenarios = [],
        ?int $requestedBy = null
    ): ExplanationResponseDTO {
        $startTime = microtime(true);

        // If Vertex AI is not configured, use fallback directly
        if (!$this->config->isEnabled() || !$this->vertexAiClient) {
            $explanation = $this->fallbackProvider->generateExplanation($profile, $scenario);
            $this->logExplanationAttempt($profile, $scenario, 'rules_based', 'vertex_ai_disabled', $requestedBy, $explanation);
            return $explanation;
        }

        // Try Vertex AI
        try {
            $explanation = $this->generateWithVertexAi($profile, $scenario, $alternativeScenarios, $startTime);
            $this->logExplanationAttempt($profile, $scenario, 'vertex_ai', 'success', $requestedBy, $explanation);
            return $explanation;

        } catch (VertexAiTimeoutException $e) {
            Log::warning('Vertex AI timeout for bundle explanation, using fallback', [
                'patient_ref' => $this->promptBuilder->generatePatientRef($profile->patientId),
                'scenario' => $scenario->title,
                'error' => $e->getMessage(),
            ]);

            $explanation = $this->fallbackProvider->generateExplanation($profile, $scenario);
            $this->logExplanationAttempt($profile, $scenario, 'rules_based', 'vertex_ai_timeout', $requestedBy, $explanation);
            return $explanation;

        } catch (VertexAiRateLimitException $e) {
            Log::warning('Vertex AI rate limited for bundle explanation', [
                'patient_ref' => $this->promptBuilder->generatePatientRef($profile->patientId),
                'error' => $e->getMessage(),
            ]);

            $explanation = $this->fallbackProvider->generateExplanation($profile, $scenario);
            $this->logExplanationAttempt($profile, $scenario, 'rules_based', 'vertex_ai_rate_limited', $requestedBy, $explanation);
            return $explanation;

        } catch (VertexAiAuthException $e) {
            Log::error('Vertex AI authentication failed for bundle explanation', [
                'error' => $e->getMessage(),
            ]);

            $explanation = $this->fallbackProvider->generateExplanation($profile, $scenario);
            $this->logExplanationAttempt($profile, $scenario, 'rules_based', 'vertex_ai_auth_error', $requestedBy, $explanation);
            return $explanation;

        } catch (VertexAiException $e) {
            Log::error('Vertex AI error for bundle explanation, using fallback', [
                'patient_ref' => $this->promptBuilder->generatePatientRef($profile->patientId),
                'error' => $e->getMessage(),
            ]);

            $explanation = $this->fallbackProvider->generateExplanation($profile, $scenario);
            $this->logExplanationAttempt($profile, $scenario, 'rules_based', 'vertex_ai_error', $requestedBy, $explanation);
            return $explanation;

        } catch (\Exception $e) {
            Log::error('Unexpected error in bundle explanation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $explanation = $this->fallbackProvider->generateExplanation($profile, $scenario);
            $this->logExplanationAttempt($profile, $scenario, 'rules_based', 'unexpected_error', $requestedBy, $explanation);
            return $explanation;
        }
    }

    /**
     * Generate explanation using Vertex AI.
     */
    private function generateWithVertexAi(
        PatientNeedsProfile $profile,
        ScenarioBundleDTO $scenario,
        array $alternativeScenarios,
        float $startTime
    ): ExplanationResponseDTO {
        // Build PII-safe prompt
        $promptPayload = $this->promptBuilder->buildPromptPayload(
            $profile,
            $scenario,
            $alternativeScenarios
        );

        // Call Vertex AI
        $response = $this->vertexAiClient->generateContent($promptPayload);

        // Calculate response time
        $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        return new ExplanationResponseDTO(
            shortExplanation: $response['short_explanation'] ?? 'Bundle scenario generated based on clinical profile.',
            detailedPoints: $response['key_factors'] ?? $response['detailed_points'] ?? [],
            confidenceLabel: $response['clinical_alignment'] ?? 'Profile Aligned',
            source: 'vertex_ai',
            generatedAt: Carbon::now(),
            responseTimeMs: $responseTimeMs,
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
        PatientNeedsProfile $profile,
        ScenarioBundleDTO $scenario,
        string $source,
        string $status,
        ?int $requestedBy,
        ExplanationResponseDTO $explanation
    ): void {
        try {
            // Check if the table exists before attempting to insert
            if (!$this->explanationLogTableExists()) {
                Log::debug('Bundle explanation log table not found, skipping audit log');
                return;
            }

            DB::table('bundle_explanation_logs')->insert([
                'patient_id' => $profile->patientId,
                'scenario_id' => $scenario->scenarioId,
                'scenario_axis' => $scenario->primaryAxis->value,
                'explanation_source' => $source,
                'status' => $status,
                'response_time_ms' => $explanation->responseTimeMs,
                'requested_by' => $requestedBy,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Don't let logging failures impact the main flow
            Log::debug('Failed to log bundle explanation attempt', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if the explanation log table exists.
     */
    private function explanationLogTableExists(): bool
    {
        try {
            return \Schema::hasTable('bundle_explanation_logs');
        } catch (\Exception $e) {
            return false;
        }
    }
}

