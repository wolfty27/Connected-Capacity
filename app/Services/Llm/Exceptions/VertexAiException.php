<?php

namespace App\Services\Llm\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for Vertex AI errors.
 */
class VertexAiException extends Exception
{
    protected ?string $vertexAiErrorMessage;

    public function __construct(
        string $message = 'Vertex AI request failed',
        int $code = 0,
        ?Throwable $previous = null,
        ?string $vertexAiErrorMessage = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->vertexAiErrorMessage = $vertexAiErrorMessage;
    }

    public function getVertexAiErrorMessage(): ?string
    {
        return $this->vertexAiErrorMessage;
    }
}
