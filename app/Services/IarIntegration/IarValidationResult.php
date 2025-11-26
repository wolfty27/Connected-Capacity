<?php

namespace App\Services\IarIntegration;

/**
 * IR-008: IAR Validation Result
 */
class IarValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}

    public static function valid(array $warnings = []): self
    {
        return new self(valid: true, errors: [], warnings: $warnings);
    }

    public static function invalid(array $errors, array $warnings = []): self
    {
        return new self(valid: false, errors: $errors, warnings: $warnings);
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
