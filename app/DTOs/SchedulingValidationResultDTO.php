<?php

namespace App\DTOs;

/**
 * Data Transfer Object for scheduling validation results.
 *
 * Used by SchedulingEngine to return validation status
 * with blocking errors and non-blocking warnings.
 */
class SchedulingValidationResultDTO
{
    /**
     * @param bool $isValid Whether the assignment is valid (no blocking errors)
     * @param array $errors Blocking errors that prevent assignment
     * @param array $warnings Non-blocking warnings (e.g., capacity warnings)
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = [],
        public readonly array $warnings = []
    ) {}

    /**
     * Create a valid result with optional warnings.
     */
    public static function valid(array $warnings = []): self
    {
        return new self(
            isValid: true,
            errors: [],
            warnings: $warnings
        );
    }

    /**
     * Create an invalid result with errors.
     */
    public static function invalid(array $errors, array $warnings = []): self
    {
        return new self(
            isValid: false,
            errors: $errors,
            warnings: $warnings
        );
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
