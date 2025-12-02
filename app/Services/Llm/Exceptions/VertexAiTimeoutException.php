<?php

namespace App\Services\Llm\Exceptions;

use Throwable;

/**
 * Exception thrown when a Vertex AI request times out.
 */
class VertexAiTimeoutException extends VertexAiException
{
    public function __construct(
        string $message = 'Vertex AI request timed out',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
