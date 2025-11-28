<?php

namespace App\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Data Transfer Object for a patient's unscheduled care requirements.
 *
 * Used by CareBundleAssignmentPlanner to represent patients who have
 * care bundle services that are not yet fully scheduled.
 */
class RequiredAssignmentDTO implements Arrayable
{
    /**
     * @param int $patientId
     * @param string $patientName
     * @param string|null $rugCategory
     * @param array $riskFlags Array of risk flags (e.g., ['warning', 'dangerous'])
     * @param UnscheduledServiceDTO[] $services Array of unscheduled services
     * @param int|null $carePlanId
     * @param int|null $careBundleTemplateId
     */
    public function __construct(
        public readonly int $patientId,
        public readonly string $patientName,
        public readonly ?string $rugCategory,
        public readonly array $riskFlags,
        public readonly array $services,
        public readonly ?int $carePlanId = null,
        public readonly ?int $careBundleTemplateId = null
    ) {}

    /**
     * Get total remaining hours across all services.
     */
    public function getTotalRemainingHours(): float
    {
        return collect($this->services)
            ->filter(fn($s) => $s->unitType === 'hours')
            ->sum(fn($s) => $s->getRemaining());
    }

    /**
     * Get total remaining visits across all services.
     */
    public function getTotalRemainingVisits(): float
    {
        return collect($this->services)
            ->filter(fn($s) => $s->unitType === 'visits')
            ->sum(fn($s) => $s->getRemaining());
    }

    /**
     * Check if patient has any unscheduled needs.
     */
    public function hasUnscheduledNeeds(): bool
    {
        return collect($this->services)->contains(fn($s) => $s->hasUnscheduledNeeds());
    }

    /**
     * Get services filtered to only those with remaining needs.
     *
     * @return UnscheduledServiceDTO[]
     */
    public function getServicesWithNeeds(): array
    {
        return collect($this->services)
            ->filter(fn($s) => $s->hasUnscheduledNeeds())
            ->values()
            ->all();
    }

    /**
     * Get priority level based on risk flags and remaining needs.
     * Higher number = higher priority.
     */
    public function getPriorityLevel(): int
    {
        $priority = 0;

        // Risk flags increase priority
        if (in_array('dangerous', $this->riskFlags)) {
            $priority += 10;
        }
        if (in_array('warning', $this->riskFlags)) {
            $priority += 5;
        }

        // More remaining hours increases priority
        $priority += min(5, (int) ceil($this->getTotalRemainingHours() / 4));

        return $priority;
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'patient_id' => $this->patientId,
            'patient_name' => $this->patientName,
            'rug_category' => $this->rugCategory,
            'risk_flags' => $this->riskFlags,
            'services' => array_map(fn($s) => $s->toArray(), $this->getServicesWithNeeds()),
            'care_plan_id' => $this->carePlanId,
            'care_bundle_template_id' => $this->careBundleTemplateId,
            'total_remaining_hours' => $this->getTotalRemainingHours(),
            'total_remaining_visits' => $this->getTotalRemainingVisits(),
            'priority_level' => $this->getPriorityLevel(),
        ];
    }
}
