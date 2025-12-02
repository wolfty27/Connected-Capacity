<?php

/**
 * Google Vertex AI Configuration
 *
 * This configuration file controls the Vertex AI (Gemini) integration
 * for the AI-Assisted Scheduling explanation layer.
 *
 * Authentication: Uses Application Default Credentials (ADC)
 * - On Cloud Run: Automatically uses the service account assigned to the service
 * - Locally: Use `gcloud auth application-default login`
 *
 * @see https://cloud.google.com/vertex-ai/docs/start/introduction-unified-platform
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Vertex AI
    |--------------------------------------------------------------------------
    |
    | When disabled, the LlmExplanationService will use the rules-based
    | fallback provider instead of calling Vertex AI.
    |
    */
    'enabled' => env('VERTEX_AI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | GCP Project Configuration
    |--------------------------------------------------------------------------
    |
    | The GCP project ID and location where Vertex AI is enabled.
    | Location should match where your Gemini model is deployed.
    |
    */
    'project_id' => env('VERTEX_AI_PROJECT_ID'),
    'location' => env('VERTEX_AI_LOCATION', 'us-central1'),

    /*
    |--------------------------------------------------------------------------
    | Gemini Model Configuration
    |--------------------------------------------------------------------------
    |
    | The Gemini model to use for generating explanations.
    | Supported models: gemini-1.5-pro, gemini-1.5-flash, gemini-1.0-pro
    |
    */
    'model' => env('VERTEX_AI_MODEL', 'gemini-1.5-pro'),

    /*
    |--------------------------------------------------------------------------
    | Generation Config
    |--------------------------------------------------------------------------
    |
    | Parameters controlling the model's output generation behavior.
    | - temperature: Lower = more deterministic (0.0-2.0)
    | - max_output_tokens: Maximum tokens in response
    | - top_p: Nucleus sampling threshold
    | - top_k: Top-k sampling limit
    |
    */
    'generation_config' => [
        'temperature' => (float) env('VERTEX_AI_TEMPERATURE', 0.3),
        'maxOutputTokens' => (int) env('VERTEX_AI_MAX_OUTPUT_TOKENS', 512),
        'topP' => (float) env('VERTEX_AI_TOP_P', 0.8),
        'topK' => (int) env('VERTEX_AI_TOP_K', 40),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Settings
    |--------------------------------------------------------------------------
    |
    | Content safety filters for Vertex AI responses.
    | Threshold options: BLOCK_NONE, BLOCK_LOW_AND_ABOVE,
    |                    BLOCK_MEDIUM_AND_ABOVE, BLOCK_ONLY_HIGH
    |
    */
    'safety_settings' => [
        [
            'category' => 'HARM_CATEGORY_HARASSMENT',
            'threshold' => env('VERTEX_AI_SAFETY_THRESHOLD', 'BLOCK_MEDIUM_AND_ABOVE'),
        ],
        [
            'category' => 'HARM_CATEGORY_HATE_SPEECH',
            'threshold' => env('VERTEX_AI_SAFETY_THRESHOLD', 'BLOCK_MEDIUM_AND_ABOVE'),
        ],
        [
            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
            'threshold' => env('VERTEX_AI_SAFETY_THRESHOLD', 'BLOCK_MEDIUM_AND_ABOVE'),
        ],
        [
            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
            'threshold' => env('VERTEX_AI_SAFETY_THRESHOLD', 'BLOCK_MEDIUM_AND_ABOVE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Timeouts and Retries
    |--------------------------------------------------------------------------
    |
    | HTTP timeout for Vertex AI requests and retry configuration.
    |
    */
    'timeout_seconds' => (int) env('VERTEX_AI_TIMEOUT_SECONDS', 30),
    'max_retries' => (int) env('VERTEX_AI_MAX_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Requests per minute limit to prevent exceeding Vertex AI quotas.
    | This is enforced client-side before making API calls.
    |
    */
    'rate_limit_rpm' => (int) env('VERTEX_AI_RATE_LIMIT_RPM', 60),

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Controls what gets logged for audit and debugging purposes.
    | IMPORTANT: Never log prompts or responses as they may contain derived PHI.
    |
    */
    'logging' => [
        'enabled' => env('VERTEX_AI_LOGGING_ENABLED', true),
        'log_channel' => env('VERTEX_AI_LOG_CHANNEL', 'stack'),
    ],
];
