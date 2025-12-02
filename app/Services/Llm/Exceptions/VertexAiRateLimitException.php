<?php

namespace App\Services\Llm\Exceptions;

use Throwable;

/**
 * Exception thrown when Vertex AI rate limit is exceeded.
 */
class VertexAiRateLimitException extends VertexAiException
{
    public function __construct(
        string $message = 'Vertex AI rate limit exceeded',
        int $code = 429,
        ?Throwable $previous = null,
        ?string $vertexAiErrorMessage = null
    ) {
        parent::__construct($message, $code, $previous, $vertexAiErrorMessage);
    }
}
