<?php

namespace App\Services;

use App\Models\CareBundle;
use App\Models\CareBundleTemplate;
use App\Models\CareBundleTemplateService;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Repositories\ServiceRateRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * CostEngine
 *
 * Calculates bundle costs using the rate card system and provides
 * budget validation and cost rationale.
 *
 * Key responsibilities:
 * - Calculate weekly expected bundle cost from rate card
 * - Validate bundles against $5,000/week envelope
 * - Provide transparent rationale: RUG → tier → intensity → cost
 * - Snapshot rates when creating service assignments
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class CostEngine
{
    /**
     * Default weekly cap in cents ($5,000).
     */
    public const DEFAULT_WEEKLY_CAP_CENTS = 500000;

    /**
     * Warning threshold (10% over cap).
     */
    public const WARNING_THRESHOLD = 1.10;

    public function __construct(
        protected ServiceRateRepository $rateRepository
    ) {}

    /**
     * Evaluate a care bundle template for a patient and calculate costs.
     *
     * @param CareBundleTemplate $template The template to evaluate
     * @param Patient $patient The patient
     * @param int|null $organizationId Optional SPO organization for rate lookup
     * @return BundleEvaluation
     */
    public function evaluateTemplate(
        CareBundleTemplate $template,
        Patient $patient,
        ?int $organizationId = null
    ): BundleEvaluation {
        $today = Carbon::today();
        $services = $template->services()->with('serviceType')->get();

        // Get patient's RUG classification for rationale
        $rugClassification = $patient->rugClassifications()->latest()->first();

        $serviceEvaluations = [];
        $totalWeeklyCostCents = 0;

        foreach ($services as $templateService) {
            $evaluation = $this->evaluateTemplateService(
                $templateService,
                $organizationId,
                $today
            );

            $serviceEvaluations[] = $evaluation;
            $totalWeeklyCostCents += $evaluation['weekly_cost_cents'];
        }

        // Determine budget status
        $weeklyCap = $template->weekly_cap_cents ?? self::DEFAULT_WEEKLY_CAP_CENTS;
        $budgetStatus = $this->deriveBudgetStatus($totalWeeklyCostCents, $weeklyCap);

        // Build rationale
        $rationale = $this->buildRationale(
            $template,
            $rugClassification,
            $totalWeeklyCostCents,
            $weeklyCap
        );

        return new BundleEvaluation(
            templateId: $template->id,
            templateCode: $template->code,
            rugGroup: $template->rug_group,
            rugCategory: $template->rug_category,
            tier: $template->tier,
            tierLabel: $template->tier_label,
            weeklyCapCents: $weeklyCap,
            totalWeeklyCostCents: $totalWeeklyCostCents,
            isWithinCap: $totalWeeklyCostCents <= $weeklyCap,
            budgetStatus: $budgetStatus,
            services: $serviceEvaluations,
            rationale: $rationale
        );
    }

    /**
     * Evaluate a single template service.
     */
    protected function evaluateTemplateService(
        CareBundleTemplateService $templateService,
        ?int $organizationId,
        Carbon $at
    ): array {
        $serviceType = $templateService->serviceType;

        // Resolve rate from rate card
        $rate = $this->rateRepository->getEffectiveRateById(
            $serviceType->id,
            $organizationId,
            $at
        );

        // Determine rate to use
        $rateCents = $rate?->rate_cents
            ?? $templateService->cost_per_visit_cents
            ?? $this->getDefaultRate($serviceType);

        $unitType = $rate?->unit_type ?? 'visit';
        $frequencyPerWeek = $templateService->default_frequency_per_week;
        $durationMinutes = $templateService->default_duration_minutes;

        // Calculate weekly cost based on unit type
        $weeklyCostCents = $this->calculateWeeklyCost(
            $rateCents,
            $unitType,
            $frequencyPerWeek,
            $durationMinutes
        );

        return [
            'service_type_id' => $serviceType->id,
            'service_type_code' => $serviceType->code,
            'service_type_name' => $serviceType->name,
            'rate_cents' => $rateCents,
            'rate_dollars' => $rateCents / 100,
            'unit_type' => $unitType,
            'frequency_per_week' => $frequencyPerWeek,
            'duration_minutes' => $durationMinutes,
            'weekly_cost_cents' => $weeklyCostCents,
            'weekly_cost_dollars' => $weeklyCostCents / 100,
            'rate_source' => $rate ? ($rate->is_system_default ? 'system_default' : 'organization') : 'template_default',
            'is_required' => $templateService->is_required,
            'is_conditional' => $templateService->is_conditional,
        ];
    }

    /**
     * Calculate weekly cost based on unit type.
     */
    protected function calculateWeeklyCost(
        int $rateCents,
        string $unitType,
        int $frequencyPerWeek,
        ?int $durationMinutes
    ): int {
        return match ($unitType) {
            'hour' => (int) round($rateCents * ($durationMinutes / 60) * $frequencyPerWeek),
            'visit' => $rateCents * $frequencyPerWeek,
            'month' => (int) round($rateCents / 4), // Weekly portion of monthly
            'trip' => $rateCents * $frequencyPerWeek,
            'call' => $rateCents * $frequencyPerWeek,
            'service' => $rateCents * $frequencyPerWeek,
            'night' => $rateCents * $frequencyPerWeek,
            'block' => $rateCents * $frequencyPerWeek,
            default => $rateCents * $frequencyPerWeek,
        };
    }

    /**
     * Get default rate for a service type (fallback).
     */
    protected function getDefaultRate(ServiceType $serviceType): int
    {
        // Use service type's cost_per_visit if available
        if ($serviceType->cost_per_visit) {
            return (int) ($serviceType->cost_per_visit * 100);
        }

        // Fallback defaults by category
        return match ($serviceType->code) {
            'PSW', 'HMK', 'RES' => 3500, // $35/hour
            'NUR' => 11000, // $110/visit
            'PT', 'OT' => 12000, // $120/visit
            'SLP', 'RT' => 13000, // $130/visit
            'SW', 'RD' => 11000, // $110/visit
            'PERS' => 4500, // $45/month (weekly = ~$11.25)
            'RPM' => 13000, // $130/month (weekly = ~$32.50)
            'TRANS' => 7000, // $70/trip
            'MEAL' => 1200, // $12/meal
            default => 10000, // $100 default
        };
    }

    /**
     * Derive budget status from cost vs cap.
     */
    protected function deriveBudgetStatus(int $totalCents, int $capCents): string
    {
        if ($totalCents <= $capCents) {
            return 'OK';
        }

        if ($totalCents <= (int) round($capCents * self::WARNING_THRESHOLD)) {
            return 'WARNING';
        }

        return 'OVER_CAP';
    }

    /**
     * Build a rationale explanation for the cost calculation.
     */
    protected function buildRationale(
        CareBundleTemplate $template,
        mixed $rugClassification,
        int $totalCostCents,
        int $capCents
    ): array {
        $rationale = [
            'rug_group' => $template->rug_group,
            'rug_category' => $template->rug_category,
            'tier' => $template->tier,
            'tier_label' => $template->tier_label,
            'template_code' => $template->code,
            'weekly_cap_cents' => $capCents,
            'weekly_cap_dollars' => $capCents / 100,
            'expected_weekly_cost_cents' => $totalCostCents,
            'expected_weekly_cost_dollars' => $totalCostCents / 100,
            'budget_utilization_percent' => $capCents > 0
                ? round(($totalCostCents / $capCents) * 100, 1)
                : 0,
        ];

        if ($rugClassification) {
            $rationale['patient_classification'] = [
                'adl_sum' => $rugClassification->adl_sum,
                'iadl_sum' => $rugClassification->iadl_sum,
                'cps_score' => $rugClassification->cps_score,
                'flags' => $rugClassification->flags,
            ];
        }

        return $rationale;
    }

    /**
     * Snapshot rates for service assignments when creating a care plan.
     *
     * @param CarePlan $carePlan
     * @param CareBundleTemplate $template
     * @param int|null $organizationId
     * @return Collection<ServiceAssignment>
     */
    public function createServiceAssignmentsWithRates(
        CarePlan $carePlan,
        CareBundleTemplate $template,
        ?int $organizationId = null
    ): Collection {
        $today = Carbon::today();
        $services = $template->services()->with('serviceType')->get();
        $assignments = collect();

        foreach ($services as $templateService) {
            $serviceType = $templateService->serviceType;

            // Resolve rate from rate card
            $rate = $this->rateRepository->getEffectiveRateById(
                $serviceType->id,
                $organizationId,
                $today
            );

            $rateCents = $rate?->rate_cents
                ?? $templateService->cost_per_visit_cents
                ?? $this->getDefaultRate($serviceType);

            $unitType = $rate?->unit_type ?? 'visit';
            $frequencyPerWeek = $templateService->default_frequency_per_week;
            $durationMinutes = $templateService->default_duration_minutes;

            $weeklyCostCents = $this->calculateWeeklyCost(
                $rateCents,
                $unitType,
                $frequencyPerWeek,
                $durationMinutes
            );

            $assignment = ServiceAssignment::create([
                'care_plan_id' => $carePlan->id,
                'patient_id' => $carePlan->patient_id,
                'service_provider_organization_id' => $organizationId,
                'service_type_id' => $serviceType->id,
                'status' => ServiceAssignment::STATUS_PLANNED,
                'source' => 'manual',
                // Billing rate snapshot
                'billing_rate_cents' => $rateCents,
                'billing_unit_type' => $unitType,
                'frequency_per_week' => $frequencyPerWeek,
                'duration_minutes' => $durationMinutes,
                'calculated_weekly_cost_cents' => $weeklyCostCents,
            ]);

            $assignments->push($assignment);
        }

        return $assignments;
    }

    /**
     * Recalculate costs for a care plan using current rates.
     * Returns the new costs without modifying stored snapshots.
     */
    public function previewCarePlanWithCurrentRates(
        CarePlan $carePlan,
        ?int $organizationId = null
    ): array {
        $today = Carbon::today();
        $assignments = $carePlan->serviceAssignments()->with('serviceType')->get();

        $serviceCosts = [];
        $totalWeeklyCostCents = 0;

        foreach ($assignments as $assignment) {
            // Get current rate (not snapshot)
            $rate = $this->rateRepository->getEffectiveRateById(
                $assignment->service_type_id,
                $organizationId,
                $today
            );

            $currentRateCents = $rate?->rate_cents ?? $assignment->billing_rate_cents;
            $unitType = $rate?->unit_type ?? $assignment->billing_unit_type ?? 'visit';

            $currentWeeklyCost = $this->calculateWeeklyCost(
                $currentRateCents,
                $unitType,
                $assignment->frequency_per_week ?? 1,
                $assignment->duration_minutes ?? 60
            );

            $serviceCosts[] = [
                'service_assignment_id' => $assignment->id,
                'service_type_id' => $assignment->service_type_id,
                'service_type_code' => $assignment->serviceType?->code,
                'snapshot_rate_cents' => $assignment->billing_rate_cents,
                'current_rate_cents' => $currentRateCents,
                'rate_changed' => $assignment->billing_rate_cents !== $currentRateCents,
                'snapshot_weekly_cost_cents' => $assignment->calculated_weekly_cost_cents,
                'current_weekly_cost_cents' => $currentWeeklyCost,
            ];

            $totalWeeklyCostCents += $currentWeeklyCost;
        }

        $template = $carePlan->careBundleTemplate;
        $weeklyCap = $template?->weekly_cap_cents ?? self::DEFAULT_WEEKLY_CAP_CENTS;

        return [
            'care_plan_id' => $carePlan->id,
            'current_total_weekly_cost_cents' => $totalWeeklyCostCents,
            'current_total_weekly_cost_dollars' => $totalWeeklyCostCents / 100,
            'weekly_cap_cents' => $weeklyCap,
            'weekly_cap_dollars' => $weeklyCap / 100,
            'budget_status' => $this->deriveBudgetStatus($totalWeeklyCostCents, $weeklyCap),
            'is_within_cap' => $totalWeeklyCostCents <= $weeklyCap,
            'services' => $serviceCosts,
        ];
    }

    /**
     * Get total weekly cost for a care plan using snapshot rates.
     */
    public function getCarePlanWeeklyCost(CarePlan $carePlan): int
    {
        return $carePlan->serviceAssignments()
            ->sum('calculated_weekly_cost_cents') ?? 0;
    }

    /**
     * Validate a bundle configuration against the weekly cap.
     *
     * @param array $services Array of service configurations
     * @param int|null $organizationId
     * @param int|null $weeklyCap Override weekly cap
     * @return ValidationResult
     */
    public function validateBundleConfiguration(
        array $services,
        ?int $organizationId = null,
        ?int $weeklyCap = null
    ): ValidationResult {
        $today = Carbon::today();
        $weeklyCap = $weeklyCap ?? self::DEFAULT_WEEKLY_CAP_CENTS;
        $totalCostCents = 0;
        $serviceDetails = [];

        foreach ($services as $service) {
            $serviceTypeId = $service['service_type_id'] ?? null;
            if (!$serviceTypeId) {
                continue;
            }

            $rate = $this->rateRepository->getEffectiveRateById(
                $serviceTypeId,
                $organizationId,
                $today
            );

            $rateCents = $rate?->rate_cents ?? ($service['rate_cents'] ?? 10000);
            $unitType = $rate?->unit_type ?? ($service['unit_type'] ?? 'visit');
            $frequency = $service['frequency_per_week'] ?? 1;
            $duration = $service['duration_minutes'] ?? 60;

            $weeklyCost = $this->calculateWeeklyCost($rateCents, $unitType, $frequency, $duration);
            $totalCostCents += $weeklyCost;

            $serviceDetails[] = [
                'service_type_id' => $serviceTypeId,
                'rate_cents' => $rateCents,
                'weekly_cost_cents' => $weeklyCost,
            ];
        }

        $budgetStatus = $this->deriveBudgetStatus($totalCostCents, $weeklyCap);

        return new ValidationResult(
            isValid: $budgetStatus !== 'OVER_CAP',
            totalWeeklyCostCents: $totalCostCents,
            weeklyCap: $weeklyCap,
            budgetStatus: $budgetStatus,
            utilizationPercent: $weeklyCap > 0 ? round(($totalCostCents / $weeklyCap) * 100, 1) : 0,
            services: $serviceDetails
        );
    }
}

/**
 * Result of a bundle evaluation.
 */
class BundleEvaluation
{
    public function __construct(
        public int $templateId,
        public string $templateCode,
        public ?string $rugGroup,
        public ?string $rugCategory,
        public ?int $tier,
        public string $tierLabel,
        public int $weeklyCapCents,
        public int $totalWeeklyCostCents,
        public bool $isWithinCap,
        public string $budgetStatus,
        public array $services,
        public array $rationale,
    ) {}

    public function toArray(): array
    {
        return [
            'template_id' => $this->templateId,
            'template_code' => $this->templateCode,
            'rug_group' => $this->rugGroup,
            'rug_category' => $this->rugCategory,
            'tier' => $this->tier,
            'tier_label' => $this->tierLabel,
            'weekly_cap_cents' => $this->weeklyCapCents,
            'weekly_cap_dollars' => $this->weeklyCapCents / 100,
            'total_weekly_cost_cents' => $this->totalWeeklyCostCents,
            'total_weekly_cost_dollars' => $this->totalWeeklyCostCents / 100,
            'is_within_cap' => $this->isWithinCap,
            'budget_status' => $this->budgetStatus,
            'services' => $this->services,
            'rationale' => $this->rationale,
        ];
    }
}

/**
 * Result of bundle validation.
 */
class ValidationResult
{
    public function __construct(
        public bool $isValid,
        public int $totalWeeklyCostCents,
        public int $weeklyCap,
        public string $budgetStatus,
        public float $utilizationPercent,
        public array $services,
    ) {}

    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'total_weekly_cost_cents' => $this->totalWeeklyCostCents,
            'total_weekly_cost_dollars' => $this->totalWeeklyCostCents / 100,
            'weekly_cap_cents' => $this->weeklyCap,
            'weekly_cap_dollars' => $this->weeklyCap / 100,
            'budget_status' => $this->budgetStatus,
            'utilization_percent' => $this->utilizationPercent,
            'services' => $this->services,
        ];
    }
}
