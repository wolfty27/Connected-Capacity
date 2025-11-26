<?php

namespace App\Services\IarIntegration;

/**
 * IR-008: IAR API Response wrapper
 */
class IarResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $submissionId,
        public readonly ?string $message,
        public readonly array $data = [],
        public readonly array $errors = [],
        public readonly int $httpStatus = 200,
    ) {}

    public static function success(string $submissionId, string $message = 'Success', array $data = []): self
    {
        return new self(
            success: true,
            submissionId: $submissionId,
            message: $message,
            data: $data,
            errors: [],
            httpStatus: 200,
        );
    }

    public static function failure(string $message, array $errors = [], int $httpStatus = 400): self
    {
        return new self(
            success: false,
            submissionId: null,
            message: $message,
            data: [],
            errors: $errors,
            httpStatus: $httpStatus,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'submission_id' => $this->submissionId,
            'message' => $this->message,
            'data' => $this->data,
            'errors' => $this->errors,
            'http_status' => $this->httpStatus,
        ];
    }
}
