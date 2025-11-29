<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * Data Transfer Object for travel-aware scheduling validation results.
 *
 * Extends scheduling validation to include travel time information
 * and earliest/latest possible times for an assignment.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class TravelValidationResultDTO
{
    /**
     * @param bool $isValid Whether the assignment is valid (considering travel)
     * @param array $errors Blocking errors that prevent assignment
     * @param array $warnings Non-blocking warnings
     * @param Carbon|null $earliestStart Earliest possible start time (after travel from previous)
     * @param Carbon|null $latestEnd Latest possible end time (before travel to next)
     * @param int|null $travelFromPrevious Travel minutes from previous assignment
     * @param int|null $travelToNext Travel minutes to next assignment
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly ?Carbon $earliestStart = null,
        public readonly ?Carbon $latestEnd = null,
        public readonly ?int $travelFromPrevious = null,
        public readonly ?int $travelToNext = null
    ) {}

    /**
     * Create a valid result.
     */
    public static function valid(
        array $warnings = [],
        ?Carbon $earliestStart = null,
        ?Carbon $latestEnd = null,
        ?int $travelFromPrevious = null,
        ?int $travelToNext = null
    ): self {
        return new self(
            isValid: true,
            errors: [],
            warnings: $warnings,
            earliestStart: $earliestStart,
            latestEnd: $latestEnd,
            travelFromPrevious: $travelFromPrevious,
            travelToNext: $travelToNext
        );
    }

    /**
     * Create an invalid result.
     */
    public static function invalid(
        array $errors,
        array $warnings = [],
        ?Carbon $earliestStart = null,
        ?Carbon $latestEnd = null,
        ?int $travelFromPrevious = null,
        ?int $travelToNext = null
    ): self {
        return new self(
            isValid: false,
            errors: $errors,
            warnings: $warnings,
            earliestStart: $earliestStart,
            latestEnd: $latestEnd,
            travelFromPrevious: $travelFromPrevious,
            travelToNext: $travelToNext
        );
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
            'earliest_start' => $this->earliestStart?->format('H:i'),
            'latest_end' => $this->latestEnd?->format('H:i'),
            'travel_from_previous_minutes' => $this->travelFromPrevious,
            'travel_to_next_minutes' => $this->travelToNext,
        ];
    }
}
