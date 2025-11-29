<?php

namespace App\DTOs;

class UnscheduledServiceDTO
{
    public function __construct(
        public readonly int $serviceTypeId,
        public readonly string $serviceTypeName,
        public readonly string $category,
        public readonly string $color,
        public readonly float $required,
        public readonly float $scheduled,
        public readonly string $unitType // 'hours' or 'visits'
    ) {}

    /**
     * Calculate remaining units needed
     */
    public function getRemaining(): float
    {
        return max(0, $this->required - $this->scheduled);
    }

    /**
     * Check if this service has unscheduled needs
     */
    public function hasUnscheduledNeeds(): bool
    {
        return $this->getRemaining() > 0;
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentage(): float
    {
        if ($this->required <= 0) {
            return 100;
        }
        return min(100, ($this->scheduled / $this->required) * 100);
    }

    /**
     * Convert to array for API response
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
            'completion_percentage' => round($this->getCompletionPercentage(), 1),
        ];
    }
}
