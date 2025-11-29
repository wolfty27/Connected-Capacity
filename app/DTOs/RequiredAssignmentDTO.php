<?php

namespace App\DTOs;

class RequiredAssignmentDTO
{
    /**
     * @param UnscheduledServiceDTO[] $services
     * @param string[] $riskFlags
     */
    public function __construct(
        public readonly int $patientId,
        public readonly string $patientName,
        public readonly ?string $rugCategory,
        public readonly array $riskFlags,
        public readonly array $services,
        public readonly ?int $carePlanId,
        public readonly ?int $careBundleTemplateId
    ) {}

    /**
     * Check if patient has any unscheduled needs
     */
    public function hasUnscheduledNeeds(): bool
    {
        foreach ($this->services as $service) {
            if ($service->hasUnscheduledNeeds()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get total remaining hours (for weekly-scheduled services)
     */
    public function getTotalRemainingHours(): float
    {
        $total = 0;
        foreach ($this->services as $service) {
            if ($service->unitType === 'hours') {
                $total += $service->getRemaining();
            }
        }
        return $total;
    }

    /**
     * Get total remaining visits (for fixed-visit services)
     */
    public function getTotalRemainingVisits(): float
    {
        $total = 0;
        foreach ($this->services as $service) {
            if ($service->unitType === 'visits') {
                $total += $service->getRemaining();
            }
        }
        return $total;
    }

    /**
     * Get only services with unscheduled needs
     */
    public function getServicesWithNeeds(): array
    {
        return array_filter($this->services, fn($s) => $s->hasUnscheduledNeeds());
    }

    /**
     * Calculate priority level (1=highest, 3=lowest)
     * Based on risk flags and unscheduled amount
     */
    public function getPriorityLevel(): int
    {
        // High priority if has high-risk flags
        $highRiskFlags = ['high_fall_risk', 'clinical_instability', 'wandering', 'ED_risk'];
        if (count(array_intersect($this->riskFlags, $highRiskFlags)) > 0) {
            return 1;
        }

        // Medium priority if significant unscheduled care
        $remainingHours = $this->getTotalRemainingHours();
        if ($remainingHours >= 10) {
            return 2;
        }

        // Low priority otherwise
        return 3;
    }

    /**
     * Convert to array for API response
     */
    public function toArray(): array
    {
        return [
            'patient_id' => $this->patientId,
            'patient_name' => $this->patientName,
            'rug_category' => $this->rugCategory,
            'risk_flags' => $this->riskFlags,
            'services' => array_map(fn($s) => $s->toArray(), $this->services),
            'care_plan_id' => $this->carePlanId,
            'care_bundle_template_id' => $this->careBundleTemplateId,
            'total_remaining_hours' => $this->getTotalRemainingHours(),
            'total_remaining_visits' => $this->getTotalRemainingVisits(),
            'priority_level' => $this->getPriorityLevel(),
            'has_unscheduled_needs' => $this->hasUnscheduledNeeds(),
        ];
    }
}
