<?php

namespace App\Services\Llm\VertexAi;

/**
 * VertexAiConfig
 *
 * Immutable value object containing all Vertex AI configuration.
 * Configuration is loaded from config/vertex_ai.php and environment variables.
 *
 * Authentication uses Application Default Credentials (ADC):
 * - On Cloud Run: Uses the service account assigned to the Cloud Run service
 * - Locally: Uses credentials from `gcloud auth application-default login`
 *
 * @see https://cloud.google.com/docs/authentication/application-default-credentials
 */
class VertexAiConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ?string $projectId,
        public readonly string $location,
        public readonly string $model,
        public readonly array $generationConfig,
        public readonly array $safetySettings,
        public readonly int $timeoutSeconds,
        public readonly int $maxRetries,
        public readonly int $rateLimitRpm,
        public readonly bool $loggingEnabled,
        public readonly string $logChannel,
    ) {}

    /**
     * Create configuration from Laravel config.
     */
    public static function fromConfig(): self
    {
        return new self(
            enabled: config('vertex_ai.enabled', false),
            projectId: config('vertex_ai.project_id'),
            location: config('vertex_ai.location', 'us-central1'),
            model: config('vertex_ai.model', 'gemini-1.5-pro'),
            generationConfig: config('vertex_ai.generation_config', []),
            safetySettings: config('vertex_ai.safety_settings', []),
            timeoutSeconds: config('vertex_ai.timeout_seconds', 30),
            maxRetries: config('vertex_ai.max_retries', 2),
            rateLimitRpm: config('vertex_ai.rate_limit_rpm', 60),
            loggingEnabled: config('vertex_ai.logging.enabled', true),
            logChannel: config('vertex_ai.logging.log_channel', 'stack'),
        );
    }

    /**
     * Get the Vertex AI REST API endpoint URL for generateContent.
     *
     * Format: https://{LOCATION}-aiplatform.googleapis.com/v1/projects/{PROJECT}/locations/{LOCATION}/publishers/google/models/{MODEL}:generateContent
     */
    public function getEndpointUrl(): string
    {
        return sprintf(
            'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent',
            $this->location,
            $this->projectId,
            $this->location,
            $this->model
        );
    }

    /**
     * Check if Vertex AI is properly configured and enabled.
     *
     * For ADC authentication, we only need:
     * - enabled flag set to true
     * - valid project_id
     *
     * No credentials path is required as ADC handles authentication automatically.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->projectId);
    }

    /**
     * Get the OAuth2 scopes required for Vertex AI API access.
     */
    public function getScopes(): array
    {
        return ['https://www.googleapis.com/auth/cloud-platform'];
    }

    /**
     * Get a sanitized version of config for logging (no sensitive data).
     */
    public function toLogArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'project_id' => $this->projectId ? substr($this->projectId, 0, 10) . '...' : null,
            'location' => $this->location,
            'model' => $this->model,
            'timeout_seconds' => $this->timeoutSeconds,
            'max_retries' => $this->maxRetries,
            'rate_limit_rpm' => $this->rateLimitRpm,
        ];
    }
}
