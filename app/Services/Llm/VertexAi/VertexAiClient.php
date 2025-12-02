<?php

namespace App\Services\Llm\VertexAi;

use App\Services\Llm\Exceptions\VertexAiAuthException;
use App\Services\Llm\Exceptions\VertexAiException;
use App\Services\Llm\Exceptions\VertexAiRateLimitException;
use App\Services\Llm\Exceptions\VertexAiTimeoutException;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * VertexAiClient
 *
 * Low-level HTTP client for Google Vertex AI (Gemini) API calls.
 *
 * Authentication: Uses Application Default Credentials (ADC)
 * - On Cloud Run: Automatically uses the service account assigned to the service
 * - Locally: Uses credentials from `gcloud auth application-default login`
 *
 * Features:
 * - Automatic token refresh via ADC
 * - Configurable timeouts and retries
 * - Client-side rate limiting
 * - Proper error handling with specific exception types
 *
 * @see https://cloud.google.com/vertex-ai/docs/generative-ai/model-reference/gemini
 */
class VertexAiClient
{
    private VertexAiConfig $config;
    private ?GuzzleClient $httpClient = null;

    public function __construct(VertexAiConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Generate content via Vertex AI Gemini.
     *
     * @param array $promptPayload The structured prompt payload
     * @return array Parsed response with 'short_explanation', 'detailed_points', 'confidence_label'
     *
     * @throws VertexAiTimeoutException When request times out
     * @throws VertexAiRateLimitException When rate limit is exceeded
     * @throws VertexAiAuthException When authentication fails
     * @throws VertexAiException For other errors
     */
    public function generateContent(array $promptPayload): array
    {
        if (!$this->config->isEnabled()) {
            throw new VertexAiException('Vertex AI is not enabled or configured.');
        }

        // Check client-side rate limit before making request
        if ($this->isRateLimited()) {
            throw new VertexAiRateLimitException(
                'Client-side rate limit exceeded. Try again later.',
                429
            );
        }

        $requestBody = $this->buildRequestBody($promptPayload);
        $lastException = null;

        // Retry loop
        for ($attempt = 1; $attempt <= $this->config->maxRetries; $attempt++) {
            try {
                $response = $this->executeRequest($requestBody);

                // Record successful request for rate limiting
                $this->recordRateLimitUsage();

                return $this->parseResponse($response);
            } catch (VertexAiAuthException $e) {
                // Auth errors should not be retried (except once for token refresh)
                if ($attempt === 1) {
                    // Clear any cached client to force re-authentication
                    $this->httpClient = null;
                    $lastException = $e;
                    continue;
                }
                throw $e;
            } catch (VertexAiTimeoutException $e) {
                $lastException = $e;
                // Log and continue to next retry
                $this->logWarning('Vertex AI timeout, retrying...', [
                    'attempt' => $attempt,
                    'max_retries' => $this->config->maxRetries,
                ]);
            } catch (VertexAiRateLimitException $e) {
                // Rate limit errors from API should not be retried immediately
                throw $e;
            } catch (VertexAiException $e) {
                $lastException = $e;
                $this->logWarning('Vertex AI error, retrying...', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw $lastException ?? new VertexAiException('Vertex AI request failed after retries.');
    }

    /**
     * Build the request body for Vertex AI generateContent API.
     */
    private function buildRequestBody(array $promptPayload): array
    {
        $systemInstruction = $promptPayload['system_instruction'] ?? '';
        unset($promptPayload['system_instruction']);

        // Combine system instruction with context into a single user message
        $userMessage = $systemInstruction;
        if (!empty($promptPayload)) {
            $userMessage .= "\n\nContext:\n" . json_encode($promptPayload, JSON_PRETTY_PRINT);
        }

        return [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userMessage],
                    ],
                ],
            ],
            'generationConfig' => $this->config->generationConfig,
            'safetySettings' => $this->config->safetySettings,
        ];
    }

    /**
     * Execute the HTTP request to Vertex AI.
     *
     * @throws VertexAiTimeoutException
     * @throws VertexAiAuthException
     * @throws VertexAiRateLimitException
     * @throws VertexAiException
     */
    private function executeRequest(array $requestBody): array
    {
        try {
            $client = $this->getHttpClient();

            $response = $client->post($this->config->getEndpointUrl(), [
                'json' => $requestBody,
                'timeout' => $this->config->timeoutSeconds,
                'connect_timeout' => 10,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (ConnectException $e) {
            throw new VertexAiTimeoutException(
                'Vertex AI request timed out after ' . $this->config->timeoutSeconds . 's',
                0,
                $e
            );
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $statusCode = $response?->getStatusCode() ?? 0;
            $body = $response ? json_decode($response->getBody()->getContents(), true) : null;
            $errorMessage = $body['error']['message'] ?? $e->getMessage();

            if ($statusCode === 429) {
                throw new VertexAiRateLimitException(
                    'Vertex AI rate limit exceeded',
                    429,
                    $e,
                    $errorMessage
                );
            }

            if ($statusCode === 401 || $statusCode === 403) {
                throw new VertexAiAuthException(
                    'Vertex AI authentication failed. Ensure ADC is configured correctly.',
                    $statusCode,
                    $e,
                    $errorMessage
                );
            }

            throw new VertexAiException(
                'Vertex AI request failed: ' . $errorMessage,
                $statusCode,
                $e,
                $errorMessage
            );
        }
    }

    /**
     * Parse the Vertex AI response and extract the explanation JSON.
     *
     * @throws VertexAiException If response cannot be parsed
     */
    private function parseResponse(array $response): array
    {
        // Check for candidates in response
        $candidates = $response['candidates'] ?? [];
        if (empty($candidates)) {
            throw new VertexAiException('No candidates in Vertex AI response.');
        }

        $candidate = $candidates[0];

        // Check finish reason
        $finishReason = $candidate['finishReason'] ?? 'UNKNOWN';
        if ($finishReason === 'SAFETY') {
            throw new VertexAiException(
                'Response blocked by Vertex AI safety filters.',
                0,
                null,
                'Content blocked due to safety settings'
            );
        }

        // Extract text from response
        $text = $candidate['content']['parts'][0]['text'] ?? null;
        if (empty($text)) {
            throw new VertexAiException('Empty text in Vertex AI response.');
        }

        // Extract JSON from response (may be wrapped in markdown code blocks)
        $json = $this->extractJsonFromText($text);

        if ($json === null) {
            throw new VertexAiException('Failed to extract JSON from Vertex AI response.');
        }

        // Validate expected fields
        return $this->validateAndNormalizeResponse($json);
    }

    /**
     * Extract JSON from text that may be wrapped in markdown code blocks.
     */
    private function extractJsonFromText(string $text): ?array
    {
        // Try to extract from markdown code block
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
            $jsonString = $matches[1];
        } elseif (preg_match('/\{.*\}/s', $text, $matches)) {
            // Try to find raw JSON object
            $jsonString = $matches[0];
        } else {
            return null;
        }

        $parsed = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logWarning('Failed to parse JSON from Vertex AI', [
                'json_error' => json_last_error_msg(),
            ]);
            return null;
        }

        return $parsed;
    }

    /**
     * Validate and normalize the parsed response to expected format.
     */
    private function validateAndNormalizeResponse(array $json): array
    {
        return [
            'short_explanation' => $json['short_explanation'] ?? 'Unable to generate explanation.',
            'detailed_points' => $json['detailed_points'] ?? [],
            'confidence_label' => $json['confidence_label'] ?? 'Unknown',
        ];
    }

    /**
     * Get or create the HTTP client with ADC authentication.
     */
    private function getHttpClient(): GuzzleClient
    {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }

        try {
            // Create credentials using Application Default Credentials
            $credentials = ApplicationDefaultCredentials::getCredentials(
                $this->config->getScopes()
            );

            // Create middleware for automatic token refresh
            $middleware = new AuthTokenMiddleware($credentials);

            // Create handler stack with auth middleware
            $stack = HandlerStack::create();
            $stack->push($middleware);

            // Create HTTP client with auth middleware
            $this->httpClient = new GuzzleClient([
                'handler' => $stack,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            return $this->httpClient;
        } catch (\Exception $e) {
            throw new VertexAiAuthException(
                'Failed to initialize ADC credentials. ' .
                'Locally: run `gcloud auth application-default login`. ' .
                'On Cloud Run: ensure service account has Vertex AI User role.',
                401,
                $e
            );
        }
    }

    /**
     * Check if we've exceeded the client-side rate limit.
     */
    private function isRateLimited(): bool
    {
        $key = $this->getRateLimitKey();
        $count = Cache::get($key, 0);

        return $count >= $this->config->rateLimitRpm;
    }

    /**
     * Record a request for rate limiting purposes.
     */
    private function recordRateLimitUsage(): void
    {
        $key = $this->getRateLimitKey();
        $current = Cache::get($key, 0);
        Cache::put($key, $current + 1, 120); // Expire after 2 minutes
    }

    /**
     * Get the cache key for rate limiting (includes minute for per-minute tracking).
     */
    private function getRateLimitKey(): string
    {
        return 'vertex_ai_rate_limit_' . now()->format('Y-m-d-H-i');
    }

    /**
     * Log a warning message.
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->config->loggingEnabled) {
            Log::channel($this->config->logChannel)->warning($message, $context);
        }
    }
}
