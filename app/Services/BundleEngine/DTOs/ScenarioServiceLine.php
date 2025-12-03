<?php

namespace App\Services\BundleEngine\DTOs;

/**
 * ScenarioServiceLine
 *
 * Represents a single service within a scenario bundle.
 * Each line describes what service is needed, how often, for how long, and why.
 *
 * This is a pure data container - no business logic.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 4.3
 */
class ScenarioServiceLine
{
    public function __construct(
        // === REQUIRED PARAMETERS (must come first) ===
        /** @var string Service type/category (e.g., 'nursing', 'psw', 'therapy') */
        public readonly string $serviceCategory,

        /** @var string Service name for display */
        public readonly string $serviceName,

        /** @var int Frequency count (e.g., 3 for "3 times per week") */
        public readonly int $frequencyCount,

        /** @var string Frequency period: 'day', 'week', 'month', 'episode' */
        public readonly string $frequencyPeriod,

        /** @var int Duration per visit in minutes */
        public readonly int $durationMinutes,

        /** @var string Discipline: 'rn', 'rpn', 'psw', 'pt', 'ot', 'slp', 'sw', 'dietitian', 'css' */
        public readonly string $discipline,

        // === OPTIONAL PARAMETERS ===
        /** @var int|null Service module ID from database (null for AI-proposed) */
        public readonly ?int $serviceModuleId = null,

        /** @var string|null Service code for billing/tracking */
        public readonly ?string $serviceCode = null,

        /** @var int|null Estimated duration of service in weeks (null = ongoing) */
        public readonly ?int $estimatedWeeks = null,

        /** @var bool Requires specialized skills (e.g., wound care, IV) */
        public readonly bool $requiresSpecialization = false,

        /** @var string|null Specific specialization required */
        public readonly ?string $specialization = null,

        /** @var string Delivery mode: 'in_person', 'virtual', 'hybrid', 'automated' */
        public readonly string $deliveryMode = 'in_person',

        /** @var bool Requires same-worker continuity */
        public readonly bool $requiresContinuity = false,

        /** @var string|null Time of day preference: 'morning', 'afternoon', 'evening', 'any' */
        public readonly ?string $timePreference = null,

        /** @var float Cost per visit (hourly for PSW, per-visit for nursing) */
        public readonly float $costPerVisit = 0.0,

        /** @var float Estimated weekly cost */
        public readonly float $weeklyEstimatedCost = 0.0,

        /** @var string|null Cost tier: 'low', 'medium', 'high' */
        public readonly ?string $costTier = null,

        /** @var string Priority level: 'core', 'recommended', 'optional' */
        public readonly string $priorityLevel = 'recommended',

        /** @var bool Is this a safety-critical service */
        public readonly bool $isSafetyCritical = false,

        /** @var array|null Risks addressed by this service */
        public readonly ?array $risksAddressed = null,

        /** @var string|null Why this service is included */
        public readonly ?string $clinicalRationale = null,

        /** @var string|null Patient goal supported by this service */
        public readonly ?string $patientGoalSupported = null,

        /** @var array|null Assessment items that justify this service */
        public readonly ?array $justifyingFactors = null,

        /** @var string|null How this service contributes to the scenario axis */
        public readonly ?string $axisContribution = null,

        /** @var bool Can this service be modified by the coordinator */
        public readonly bool $isModifiable = true,

        /** @var string|null Alternative if this service is unavailable */
        public readonly ?string $alternative = null,
    ) {}

    /**
     * Get frequency as human-readable string.
     */
    public function getFrequencyLabel(): string
    {
        $count = $this->frequencyCount;
        $period = match ($this->frequencyPeriod) {
            'day' => 'daily',
            'week' => $count === 1 ? 'per week' : 'times per week',
            'month' => $count === 1 ? 'per month' : 'times per month',
            'episode' => 'one-time',
            default => 'per ' . $this->frequencyPeriod,
        };

        if ($this->frequencyPeriod === 'day') {
            return $count === 1 ? 'Once daily' : "{$count} times daily";
        }
        if ($this->frequencyPeriod === 'episode') {
            return 'One-time';
        }

        return $count === 1 ? "Once {$period}" : "{$count} {$period}";
    }

    /**
     * Get duration as human-readable string.
     */
    public function getDurationLabel(): string
    {
        if ($this->durationMinutes < 60) {
            return "{$this->durationMinutes} min";
        }

        $hours = floor($this->durationMinutes / 60);
        $minutes = $this->durationMinutes % 60;

        if ($minutes === 0) {
            return "{$hours} hr" . ($hours > 1 ? 's' : '');
        }

        return "{$hours} hr {$minutes} min";
    }

    /**
     * Get delivery mode label.
     */
    public function getDeliveryModeLabel(): string
    {
        return match ($this->deliveryMode) {
            'in_person' => 'In-Person',
            'virtual' => 'Virtual',
            'hybrid' => 'Hybrid',
            'automated' => 'Automated',
            default => ucfirst($this->deliveryMode),
        };
    }

    /**
     * Get discipline label.
     */
    public function getDisciplineLabel(): string
    {
        return match ($this->discipline) {
            'rn' => 'Registered Nurse',
            'rpn' => 'Registered Practical Nurse',
            'psw' => 'Personal Support Worker',
            'pt' => 'Physiotherapist',
            'ot' => 'Occupational Therapist',
            'slp' => 'Speech Language Pathologist',
            'sw' => 'Social Worker',
            'dietitian' => 'Dietitian',
            'css' => 'Community Support Service',
            default => strtoupper($this->discipline),
        };
    }

    /**
     * Get priority badge class for UI.
     */
    public function getPriorityBadgeClass(): string
    {
        return match ($this->priorityLevel) {
            'core' => 'danger',
            'recommended' => 'primary',
            'optional' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Calculate total visits per week.
     */
    public function getWeeklyVisits(): float
    {
        return match ($this->frequencyPeriod) {
            'day' => $this->frequencyCount * 7,
            'week' => $this->frequencyCount,
            'month' => $this->frequencyCount / 4.33,
            'episode' => 0, // One-time, doesn't contribute to weekly
            default => $this->frequencyCount,
        };
    }

    /**
     * Calculate total hours per week.
     */
    public function getWeeklyHours(): float
    {
        return ($this->getWeeklyVisits() * $this->durationMinutes) / 60;
    }

    /**
     * Convert to array for API/UI consumption.
     */
    public function toArray(): array
    {
        return [
            'service_module_id' => $this->serviceModuleId,
            'service_category' => $this->serviceCategory,
            'service_name' => $this->serviceName,
            'service_code' => $this->serviceCode,
            'frequency' => [
                'count' => $this->frequencyCount,
                'period' => $this->frequencyPeriod,
                'label' => $this->getFrequencyLabel(),
            ],
            'duration' => [
                'minutes' => $this->durationMinutes,
                'label' => $this->getDurationLabel(),
            ],
            'estimated_weeks' => $this->estimatedWeeks,
            'discipline' => [
                'code' => $this->discipline,
                'label' => $this->getDisciplineLabel(),
            ],
            'specialization' => [
                'required' => $this->requiresSpecialization,
                'type' => $this->specialization,
            ],
            'delivery' => [
                'mode' => $this->deliveryMode,
                'label' => $this->getDeliveryModeLabel(),
                'continuity' => $this->requiresContinuity,
                'time_preference' => $this->timePreference,
            ],
            'cost' => [
                'per_visit' => $this->costPerVisit,
                'weekly_estimate' => $this->weeklyEstimatedCost,
                'tier' => $this->costTier,
            ],
            'priority' => [
                'level' => $this->priorityLevel,
                'safety_critical' => $this->isSafetyCritical,
                'badge_class' => $this->getPriorityBadgeClass(),
            ],
            'clinical' => [
                'rationale' => $this->clinicalRationale,
                'patient_goal' => $this->patientGoalSupported,
                'risks_addressed' => $this->risksAddressed,
                'justifying_factors' => $this->justifyingFactors,
            ],
            'scenario' => [
                'axis_contribution' => $this->axisContribution,
                'modifiable' => $this->isModifiable,
                'alternative' => $this->alternative,
            ],
            'calculated' => [
                'weekly_visits' => round($this->getWeeklyVisits(), 1),
                'weekly_hours' => round($this->getWeeklyHours(), 2),
            ],
        ];
    }

    /**
     * Create from a service module (database record).
     *
     * @param array $moduleData Service module data from database
     * @param array $overrides Override default values
     */
    public static function fromServiceModule(array $moduleData, array $overrides = []): self
    {
        return new self(
            serviceCategory: $overrides['category'] ?? $moduleData['category'] ?? 'unknown',
            serviceName: $overrides['name'] ?? $moduleData['name'] ?? 'Unknown Service',
            frequencyCount: $overrides['frequency_count'] ?? $moduleData['default_frequency_count'] ?? 1,
            frequencyPeriod: $overrides['frequency_period'] ?? $moduleData['default_frequency_period'] ?? 'week',
            durationMinutes: $overrides['duration_minutes'] ?? $moduleData['default_duration'] ?? 60,
            discipline: $overrides['discipline'] ?? $moduleData['discipline'] ?? 'psw',
            serviceModuleId: $moduleData['id'] ?? null,
            serviceCode: $moduleData['code'] ?? null,
            deliveryMode: $overrides['delivery_mode'] ?? $moduleData['default_delivery_mode'] ?? 'in_person',
            costPerVisit: $overrides['cost_per_visit'] ?? $moduleData['cost_per_visit'] ?? 0.0,
            priorityLevel: $overrides['priority_level'] ?? 'recommended',
            clinicalRationale: $overrides['clinical_rationale'] ?? null,
        );
    }
}

