<?php

namespace App\Services\Llm\Exceptions;

use Throwable;

/**
 * Exception thrown when Vertex AI authentication fails.
 *
 * This typically indicates:
 * - ADC credentials not configured (locally: run `gcloud auth application-default login`)
 * - Service account missing Vertex AI User role
 * - Project ID mismatch
 */
class VertexAiAuthException extends VertexAiException
{
    public function __construct(
        string $message = 'Vertex AI authentication failed',
        int $code = 401,
        ?Throwable $previous = null,
        ?string $vertexAiErrorMessage = null
    ) {
        parent::__construct($message, $code, $previous, $vertexAiErrorMessage);
    }
}
