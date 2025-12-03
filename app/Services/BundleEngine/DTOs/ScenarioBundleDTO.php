<?php

namespace App\Services\BundleEngine\DTOs;

use App\Services\BundleEngine\Enums\ScenarioAxis;

/**
 * ScenarioBundleDTO
 *
 * Represents a complete scenario bundle proposal for a patient.
 * Contains all services, costs, and patient-experience context.
 *
 * This is a pure data container - no business logic.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 4.4
 */
class ScenarioBundleDTO
{
    public function __construct(
        // === Scenario Identity ===
        /** @var string Unique scenario ID (UUID format) */
        public readonly string $scenarioId,

        /** @var int Patient ID (for reference, not sent to LLM) */
        public readonly int $patientId,

        /** @var ScenarioAxis Primary axis for this scenario */
        public readonly ScenarioAxis $primaryAxis,

        // === Scenario Labeling ===
        /** @var string Dynamic scenario title (e.g., "Recovery-Focused Care") */
        public readonly string $title,

        /** @var string Patient-experience description (1-3 sentences) */
        public readonly string $description,

        // === Service Lines ===
        /** @var array<ScenarioServiceLine> All services in this scenario */
        public readonly array $serviceLines = [],

        /** @var array<ScenarioAxis> Secondary axes (if hybrid) */
        public readonly array $secondaryAxes = [],

        /** @var string|null Subtitle or short descriptor */
        public readonly ?string $subtitle = null,

        /** @var string Single emoji icon for UI */
        public readonly string $icon = '📋',

        // === Cost Annotations ===
        /** @var float Estimated weekly cost */
        public readonly float $weeklyEstimatedCost = 0.0,

        /** @var float Reference cap for comparison ($5000/week default) */
        public readonly float $referenceCap = 5000.0,

        /** @var string Cost status: 'within_cap', 'near_cap', 'over_cap' */
        public readonly string $costStatus = 'within_cap',

        /** @var float Percentage of cap used (0-100+) */
        public readonly float $capUtilization = 0.0,

        /** @var string|null Patient-centered cost note */
        public readonly ?string $costNote = null,

        // === Operational Metrics ===
        /** @var float Total weekly hours */
        public readonly float $totalWeeklyHours = 0.0,

        /** @var int Total weekly visits */
        public readonly int $totalWeeklyVisits = 0,

        /** @var float Percentage of visits that are in-person */
        public readonly float $inPersonPercentage = 100.0,

        /** @var float Percentage of visits that are virtual */
        public readonly float $virtualPercentage = 0.0,

        /** @var int Number of different disciplines involved */
        public readonly int $disciplineCount = 0,

        // === Scenario Context ===
        /** @var array{emphasis: string, approach: string, consideration: string} Trade-offs for this scenario */
        public readonly array $tradeOffs = [],

        /** @var array<string> Key benefits of this scenario */
        public readonly array $keyBenefits = [],

        /** @var array<string> Patient goals this scenario supports */
        public readonly array $patientGoalsSupported = [],

        /** @var array<string> Risks addressed by this scenario */
        public readonly array $risksAddressed = [],

        // === Safety & Compliance ===
        /** @var bool Meets minimum safety requirements */
        public readonly bool $meetsSafetyRequirements = true,

        /** @var array<string>|null Any safety warnings */
        public readonly ?array $safetyWarnings = null,

        /** @var bool Has been validated by rules engine */
        public readonly bool $isValidated = false,

        // === Source & Confidence ===
        /** @var string Source of scenario: 'rule_engine', 'template', 'ai_proposed', 'coordinator' */
        public readonly string $source = 'rule_engine',

        /** @var string Confidence level: 'high', 'medium', 'low' */
        public readonly string $confidenceLevel = 'medium',

        /** @var string|null Notes about data quality or limitations */
        public readonly ?string $confidenceNotes = null,

        // === AI Explanation (Phase 2) ===
        /** @var string|null AI-generated explanation if available */
        public readonly ?string $aiExplanation = null,

        /** @var bool Whether AI explanation has been generated */
        public readonly bool $hasAiExplanation = false,

        // === Metadata ===
        /** @var string|null When this scenario was generated */
        public readonly ?string $generatedAt = null,

        /** @var int|null Ordinal position for display (1=recommended) */
        public readonly ?int $displayOrder = null,

        /** @var bool Is this the recommended/default scenario */
        public readonly bool $isRecommended = false,
    ) {}

    /**
     * Get cost status label for UI.
     */
    public function getCostStatusLabel(): string
    {
        return match ($this->costStatus) {
            'within_cap' => 'Within Reference',
            'near_cap' => 'Near Reference',
            'over_cap' => 'Over Reference',
            default => 'Unknown',
        };
    }

    /**
     * Get cost status badge class for UI.
     */
    public function getCostStatusBadgeClass(): string
    {
        return match ($this->costStatus) {
            'within_cap' => 'success',
            'near_cap' => 'warning',
            'over_cap' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get service lines by category.
     *
     * @return array<string, array<ScenarioServiceLine>>
     */
    public function getServiceLinesByCategory(): array
    {
        $grouped = [];
        foreach ($this->serviceLines as $line) {
            $category = $line->serviceCategory;
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $line;
        }
        return $grouped;
    }

    /**
     * Get service lines by priority.
     *
     * @return array<string, array<ScenarioServiceLine>>
     */
    public function getServiceLinesByPriority(): array
    {
        $grouped = [
            'core' => [],
            'recommended' => [],
            'optional' => [],
        ];

        foreach ($this->serviceLines as $line) {
            $grouped[$line->priorityLevel][] = $line;
        }

        return $grouped;
    }

    /**
     * Get core/safety-critical services only.
     *
     * @return array<ScenarioServiceLine>
     */
    public function getCoreServices(): array
    {
        return array_filter(
            $this->serviceLines,
            fn(ScenarioServiceLine $line) => $line->priorityLevel === 'core' || $line->isSafetyCritical
        );
    }

    /**
     * Get services by discipline.
     *
     * @return array<string, array<ScenarioServiceLine>>
     */
    public function getServiceLinesByDiscipline(): array
    {
        $grouped = [];
        foreach ($this->serviceLines as $line) {
            $discipline = $line->discipline;
            if (!isset($grouped[$discipline])) {
                $grouped[$discipline] = [];
            }
            $grouped[$discipline][] = $line;
        }
        return $grouped;
    }

    /**
     * Check if scenario includes a specific service category.
     */
    public function hasServiceCategory(string $category): bool
    {
        foreach ($this->serviceLines as $line) {
            if ($line->serviceCategory === $category) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get unique disciplines in this scenario.
     *
     * @return array<string>
     */
    public function getUniqueDisciplines(): array
    {
        $disciplines = [];
        foreach ($this->serviceLines as $line) {
            $disciplines[$line->discipline] = true;
        }
        return array_keys($disciplines);
    }

    /**
     * Convert to array for API/UI consumption.
     */
    public function toArray(): array
    {
        return [
            'scenario_id' => $this->scenarioId,
            'patient_id' => $this->patientId,
            'axis' => [
                'primary' => [
                    'value' => $this->primaryAxis->value,
                    'label' => $this->primaryAxis->getLabel(),
                    'emoji' => $this->primaryAxis->getEmoji(),
                ],
                'secondary' => array_map(fn(ScenarioAxis $axis) => [
                    'value' => $axis->value,
                    'label' => $axis->getLabel(),
                ], $this->secondaryAxes),
            ],
            'label' => [
                'title' => $this->title,
                'subtitle' => $this->subtitle,
                'description' => $this->description,
                'icon' => $this->icon,
            ],
            'services' => array_map(fn(ScenarioServiceLine $line) => $line->toArray(), $this->serviceLines),
            'cost' => [
                'weekly_estimate' => $this->weeklyEstimatedCost,
                'reference_cap' => $this->referenceCap,
                'status' => $this->costStatus,
                'status_label' => $this->getCostStatusLabel(),
                'status_badge' => $this->getCostStatusBadgeClass(),
                'cap_utilization' => round($this->capUtilization, 1),
                'note' => $this->costNote,
            ],
            'operations' => [
                'weekly_hours' => round($this->totalWeeklyHours, 1),
                'weekly_visits' => $this->totalWeeklyVisits,
                'in_person_percentage' => round($this->inPersonPercentage, 1),
                'virtual_percentage' => round($this->virtualPercentage, 1),
                'discipline_count' => $this->disciplineCount,
                'disciplines' => $this->getUniqueDisciplines(),
            ],
            'context' => [
                'trade_offs' => $this->tradeOffs,
                'key_benefits' => $this->keyBenefits,
                'patient_goals' => $this->patientGoalsSupported,
                'risks_addressed' => $this->risksAddressed,
            ],
            'safety' => [
                'meets_requirements' => $this->meetsSafetyRequirements,
                'warnings' => $this->safetyWarnings,
                'validated' => $this->isValidated,
            ],
            'source' => [
                'type' => $this->source,
                'confidence' => $this->confidenceLevel,
                'confidence_notes' => $this->confidenceNotes,
            ],
            'ai' => [
                'explanation' => $this->aiExplanation,
                'has_explanation' => $this->hasAiExplanation,
            ],
            'meta' => [
                'generated_at' => $this->generatedAt,
                'display_order' => $this->displayOrder,
                'is_recommended' => $this->isRecommended,
            ],
        ];
    }

    /**
     * Convert to de-identified array for LLM consumption.
     *
     * Excludes patient_id and other identifying information.
     */
    public function toDeidentifiedArray(): array
    {
        $array = $this->toArray();
        unset($array['patient_id']);
        return $array;
    }

    /**
     * Get a summary string for logging/debugging.
     */
    public function getSummary(): string
    {
        return sprintf(
            "%s (%s) - %d services, $%.0f/week (%s)",
            $this->title,
            $this->primaryAxis->value,
            count($this->serviceLines),
            $this->weeklyEstimatedCost,
            $this->costStatus
        );
    }

    /**
     * Create a minimal scenario for testing.
     */
    public static function minimal(int $patientId, ScenarioAxis $axis): self
    {
        return new self(
            scenarioId: (string) \Illuminate\Support\Str::uuid(),
            patientId: $patientId,
            primaryAxis: $axis,
            title: $axis->getLabel(),
            description: $axis->getDescription(),
            serviceLines: [],
            icon: $axis->getEmoji(),
            tradeOffs: $axis->getTradeOffs(),
            source: 'template',
            confidenceLevel: 'low',
            generatedAt: now()->toIso8601String(),
        );
    }
}

