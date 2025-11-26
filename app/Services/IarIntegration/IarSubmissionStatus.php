<?php

namespace App\Services\IarIntegration;

/**
 * IR-008: IAR Submission Status
 */
class IarSubmissionStatus
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ERROR = 'error';

    public function __construct(
        public readonly string $submissionId,
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly ?\DateTimeInterface $submittedAt = null,
        public readonly ?\DateTimeInterface $processedAt = null,
        public readonly array $validationErrors = [],
    ) {}

    public function isComplete(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_ERROR]);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function toArray(): array
    {
        return [
            'submission_id' => $this->submissionId,
            'status' => $this->status,
            'message' => $this->message,
            'submitted_at' => $this->submittedAt?->format('c'),
            'processed_at' => $this->processedAt?->format('c'),
            'validation_errors' => $this->validationErrors,
            'is_complete' => $this->isComplete(),
            'is_successful' => $this->isSuccessful(),
        ];
    }
}
