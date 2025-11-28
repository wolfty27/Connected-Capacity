<?php

namespace App\DTOs;

/**
 * Data Transfer Object for an unscheduled service within a patient's care bundle.
 *
 * Represents the gap between required and scheduled care for a specific service type.
 */
class UnscheduledServiceDTO
{
    public function __construct(
        public readonly int $serviceTypeId,
        public readonly string $serviceTypeName,
        public readonly string $category,
        public readonly string $color,
        public readonly float $required,
        public readonly float $scheduled,
        public readonly string $unitType, // 'hours' | 'visits'
        public readonly ?int $careBundleServiceId = null
    ) {}

    /**
     * Get remaining units (required - scheduled).
     */
    public function getRemaining(): float
    {
        return max(0, $this->required - $this->scheduled);
    }

    /**
     * Check if service has unscheduled needs.
     */
    public function hasUnscheduledNeeds(): bool
    {
        return $this->getRemaining() > 0;
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'service_type_id' => $this->serviceTypeId,
            'service_type_name' => $this->serviceTypeName,
            'category' => $this->category,
            'color' => $this->color,
            'required' => $this->required,
            'scheduled' => $this->scheduled,
            'remaining' => $this->getRemaining(),
            'unit_type' => $this->unitType,
            'care_bundle_service_id' => $this->careBundleServiceId,
        ];
    }
}
