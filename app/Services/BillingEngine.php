<?php

namespace App\Services;

use App\Models\CareBundleService;
use App\Models\OrganizationCostProfile;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Models\StaffWageRate;
use App\Models\User;
use App\Repositories\ServiceRateRepository;
use Carbon\Carbon;

/**
 * BillingEngine
 *
 * Provides billing and cost calculation services, distinguishing between:
 * - Billing Rate: What SPO/SSPO charges (from ServiceRate / rate card)
 * - True Cost: Actual underlying cost (wages + overhead + travel)
 *
 * This separation enables margin analysis and cost tracking while maintaining
 * accurate billing to Ontario Health atHome.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class BillingEngine
{
    public function __construct(
        protected ServiceRateRepository $rateRepository
    ) {}

    /**
     * Calculate the billing amount for a delivered service.
     *
     * Uses the billing_rate_cents from the associated CareBundleService
     * or resolves from the ServiceRate rate card.
     *
     * @param DeliveredServiceData $service Data about the delivered service
     * @return BillingResult
     */
    public function calculateBillingAmount(DeliveredServiceData $service): BillingResult
    {
        $rateCents = null;
        $rateSource = 'unknown';
        $unitType = 'visit';

        // 1. Try to get rate from associated care bundle service (snapshot)
        if ($service->careBundleServiceId) {
            $bundleService = CareBundleService::find($service->careBundleServiceId);
            if ($bundleService && $bundleService->billing_rate_cents) {
                $rateCents = $bundleService->billing_rate_cents;
                $unitType = $bundleService->unit_type ?? 'visit';
                $rateSource = 'care_bundle_snapshot';
            }
        }

        // 2. If no snapshot, resolve from rate card
        if ($rateCents === null) {
            $rate = $this->rateRepository->getEffectiveRateById(
                $service->serviceTypeId,
                $service->organizationId,
                $service->serviceDate
            );

            if ($rate) {
                $rateCents = $rate->rate_cents;
                $unitType = $rate->unit_type;
                $rateSource = $rate->is_system_default ? 'system_default' : 'organization_rate';
            }
        }

        if ($rateCents === null) {
            return new BillingResult(
                billingAmountCents: 0,
                unitType: $unitType,
                quantity: 0,
                rateSource: 'none',
                notes: 'No billing rate found for service'
            );
        }

        // Calculate quantity based on unit type
        $quantity = $this->calculateQuantity($service, $unitType);
        $billingAmount = (int) round($rateCents * $quantity);

        return new BillingResult(
            billingAmountCents: $billingAmount,
            unitType: $unitType,
            quantity: $quantity,
            rateCentsPerUnit: $rateCents,
            rateSource: $rateSource
        );
    }

    /**
     * Calculate the true underlying cost for a delivered service.
     *
     * Uses staff wage rates + organization cost profile (overhead + travel).
     *
     * @param DeliveredServiceData $service Data about the delivered service
     * @return TrueCostResult
     */
    public function calculateTrueCost(DeliveredServiceData $service): TrueCostResult
    {
        // Get organization cost profile
        $costProfile = $service->organizationId
            ? OrganizationCostProfile::where('organization_id', $service->organizationId)->first()
            : null;

        if (!$costProfile) {
            $costProfile = new OrganizationCostProfile(OrganizationCostProfile::DEFAULTS);
        }

        // Get wage rate for the staff member or role
        $wageRate = $this->getWageRate($service);

        if (!$wageRate) {
            return new TrueCostResult(
                trueCostCents: 0,
                laborCostCents: 0,
                overheadCostCents: 0,
                travelCostCents: 0,
                notes: 'No wage rate found for service'
            );
        }

        // Calculate labor cost (wage * hours worked)
        $hoursWorked = $service->durationMinutes / 60;
        $laborCostCents = $wageRate->calculateCost($hoursWorked, includesBenefits: true);

        // Calculate overhead
        $overheadMultiplier = $costProfile->overhead_multiplier - 1; // Remove the base 1.0
        $overheadCostCents = (int) round($laborCostCents * $overheadMultiplier);

        // Calculate travel cost
        $travelCostCents = $costProfile->calculateTravelCost($service->distanceKm);

        // Total true cost
        $trueCostCents = $laborCostCents + $overheadCostCents + $travelCostCents;

        return new TrueCostResult(
            trueCostCents: $trueCostCents,
            laborCostCents: $laborCostCents,
            overheadCostCents: $overheadCostCents,
            travelCostCents: $travelCostCents,
            wageCentsPerHour: $wageRate->wage_cents_per_hour,
            benefitsMultiplier: $wageRate->benefits_multiplier,
            hoursWorked: $hoursWorked
        );
    }

    /**
     * Calculate both billing amount and true cost for margin analysis.
     *
     * @param DeliveredServiceData $service
     * @return MarginAnalysis
     */
    public function calculateMargin(DeliveredServiceData $service): MarginAnalysis
    {
        $billing = $this->calculateBillingAmount($service);
        $trueCost = $this->calculateTrueCost($service);

        $marginCents = $billing->billingAmountCents - $trueCost->trueCostCents;
        $marginPercent = $billing->billingAmountCents > 0
            ? ($marginCents / $billing->billingAmountCents) * 100
            : 0;

        return new MarginAnalysis(
            billingAmountCents: $billing->billingAmountCents,
            trueCostCents: $trueCost->trueCostCents,
            marginCents: $marginCents,
            marginPercent: round($marginPercent, 2),
            billing: $billing,
            trueCost: $trueCost
        );
    }

    /**
     * Calculate weekly projected margin for a care bundle.
     */
    public function calculateWeeklyMargin(
        int $patientId,
        array $bundleServices,
        int $organizationId
    ): WeeklyMarginSummary {
        $totalBillingCents = 0;
        $totalTrueCostCents = 0;
        $serviceBreakdowns = [];

        foreach ($bundleServices as $service) {
            $serviceData = new DeliveredServiceData(
                serviceTypeId: $service['service_type_id'],
                organizationId: $organizationId,
                staffId: null,
                durationMinutes: $service['duration_minutes'] ?? 60,
                serviceDate: Carbon::today(),
                distanceKm: null,
                careBundleServiceId: $service['care_bundle_service_id'] ?? null
            );

            $margin = $this->calculateMargin($serviceData);
            $frequencyPerWeek = $service['frequency_per_week'] ?? 1;

            $weeklyBilling = $margin->billingAmountCents * $frequencyPerWeek;
            $weeklyTrueCost = $margin->trueCostCents * $frequencyPerWeek;

            $totalBillingCents += $weeklyBilling;
            $totalTrueCostCents += $weeklyTrueCost;

            $serviceBreakdowns[] = [
                'service_type_id' => $service['service_type_id'],
                'frequency_per_week' => $frequencyPerWeek,
                'per_visit_billing_cents' => $margin->billingAmountCents,
                'per_visit_true_cost_cents' => $margin->trueCostCents,
                'weekly_billing_cents' => $weeklyBilling,
                'weekly_true_cost_cents' => $weeklyTrueCost,
                'weekly_margin_cents' => $weeklyBilling - $weeklyTrueCost,
            ];
        }

        $marginCents = $totalBillingCents - $totalTrueCostCents;
        $marginPercent = $totalBillingCents > 0
            ? ($marginCents / $totalBillingCents) * 100
            : 0;

        return new WeeklyMarginSummary(
            totalBillingCents: $totalBillingCents,
            totalTrueCostCents: $totalTrueCostCents,
            marginCents: $marginCents,
            marginPercent: round($marginPercent, 2),
            serviceBreakdowns: $serviceBreakdowns
        );
    }

    /**
     * Get wage rate for a service.
     */
    protected function getWageRate(DeliveredServiceData $service): ?StaffWageRate
    {
        // 1. Try staff-specific rate
        if ($service->staffId && $service->organizationId) {
            $staffRate = StaffWageRate::query()
                ->forUser($service->staffId)
                ->forOrganization($service->organizationId)
                ->where(function ($q) use ($service) {
                    $q->whereNull('service_type_id')
                        ->orWhere('service_type_id', $service->serviceTypeId);
                })
                ->effectiveOn($service->serviceDate)
                ->orderByDesc('service_type_id') // Prefer service-specific
                ->first();

            if ($staffRate) {
                return $staffRate;
            }
        }

        // 2. Try role-based rate
        if ($service->organizationId) {
            $serviceType = ServiceType::find($service->serviceTypeId);
            $role = $this->mapServiceTypeToRole($serviceType);

            if ($role) {
                $roleRate = StaffWageRate::query()
                    ->forOrganization($service->organizationId)
                    ->roleBased()
                    ->forRole($role)
                    ->effectiveOn($service->serviceDate)
                    ->first();

                if ($roleRate) {
                    return $roleRate;
                }
            }
        }

        return null;
    }

    /**
     * Map service type to a staff role for wage lookup.
     */
    protected function mapServiceTypeToRole(?ServiceType $serviceType): ?string
    {
        if (!$serviceType) {
            return null;
        }

        return match ($serviceType->code) {
            'PSW', 'HMK', 'RES', 'DEL-ACTS' => StaffWageRate::ROLE_PSW,
            'NUR' => StaffWageRate::ROLE_RN,
            'PT' => StaffWageRate::ROLE_PT,
            'OT' => StaffWageRate::ROLE_OT,
            'SLP' => StaffWageRate::ROLE_SLP,
            'SW' => StaffWageRate::ROLE_SW,
            'RD' => StaffWageRate::ROLE_RD,
            'RT' => StaffWageRate::ROLE_RT,
            'NP' => StaffWageRate::ROLE_NP,
            default => null,
        };
    }

    /**
     * Calculate quantity based on unit type and service data.
     */
    protected function calculateQuantity(DeliveredServiceData $service, string $unitType): float
    {
        return match ($unitType) {
            'hour' => $service->durationMinutes / 60,
            'visit' => 1.0,
            'month' => 1.0 / 30, // Assuming daily proration
            'trip' => 1.0,
            'call' => 1.0,
            'service' => 1.0,
            'night' => 1.0,
            'block' => 1.0,
            default => 1.0,
        };
    }
}

/**
 * Data transfer object for a delivered service.
 */
class DeliveredServiceData
{
    public function __construct(
        public int $serviceTypeId,
        public ?int $organizationId,
        public ?int $staffId,
        public int $durationMinutes,
        public Carbon $serviceDate,
        public ?float $distanceKm = null,
        public ?int $careBundleServiceId = null,
    ) {}
}

/**
 * Result of billing calculation.
 */
class BillingResult
{
    public function __construct(
        public int $billingAmountCents,
        public string $unitType,
        public float $quantity,
        public ?int $rateCentsPerUnit = null,
        public string $rateSource = 'unknown',
        public ?string $notes = null,
    ) {}

    public function toArray(): array
    {
        return [
            'billing_amount_cents' => $this->billingAmountCents,
            'billing_amount_dollars' => $this->billingAmountCents / 100,
            'unit_type' => $this->unitType,
            'quantity' => $this->quantity,
            'rate_cents_per_unit' => $this->rateCentsPerUnit,
            'rate_dollars_per_unit' => $this->rateCentsPerUnit ? $this->rateCentsPerUnit / 100 : null,
            'rate_source' => $this->rateSource,
            'notes' => $this->notes,
        ];
    }
}

/**
 * Result of true cost calculation.
 */
class TrueCostResult
{
    public function __construct(
        public int $trueCostCents,
        public int $laborCostCents,
        public int $overheadCostCents,
        public int $travelCostCents,
        public ?int $wageCentsPerHour = null,
        public ?float $benefitsMultiplier = null,
        public ?float $hoursWorked = null,
        public ?string $notes = null,
    ) {}

    public function toArray(): array
    {
        return [
            'true_cost_cents' => $this->trueCostCents,
            'true_cost_dollars' => $this->trueCostCents / 100,
            'labor_cost_cents' => $this->laborCostCents,
            'labor_cost_dollars' => $this->laborCostCents / 100,
            'overhead_cost_cents' => $this->overheadCostCents,
            'overhead_cost_dollars' => $this->overheadCostCents / 100,
            'travel_cost_cents' => $this->travelCostCents,
            'travel_cost_dollars' => $this->travelCostCents / 100,
            'wage_cents_per_hour' => $this->wageCentsPerHour,
            'wage_dollars_per_hour' => $this->wageCentsPerHour ? $this->wageCentsPerHour / 100 : null,
            'benefits_multiplier' => $this->benefitsMultiplier,
            'hours_worked' => $this->hoursWorked,
            'notes' => $this->notes,
        ];
    }
}

/**
 * Result of margin analysis.
 */
class MarginAnalysis
{
    public function __construct(
        public int $billingAmountCents,
        public int $trueCostCents,
        public int $marginCents,
        public float $marginPercent,
        public BillingResult $billing,
        public TrueCostResult $trueCost,
    ) {}

    public function toArray(): array
    {
        return [
            'billing_amount_cents' => $this->billingAmountCents,
            'billing_amount_dollars' => $this->billingAmountCents / 100,
            'true_cost_cents' => $this->trueCostCents,
            'true_cost_dollars' => $this->trueCostCents / 100,
            'margin_cents' => $this->marginCents,
            'margin_dollars' => $this->marginCents / 100,
            'margin_percent' => $this->marginPercent,
            'billing_detail' => $this->billing->toArray(),
            'true_cost_detail' => $this->trueCost->toArray(),
        ];
    }
}

/**
 * Weekly margin summary.
 */
class WeeklyMarginSummary
{
    public function __construct(
        public int $totalBillingCents,
        public int $totalTrueCostCents,
        public int $marginCents,
        public float $marginPercent,
        public array $serviceBreakdowns,
    ) {}

    public function toArray(): array
    {
        return [
            'total_billing_cents' => $this->totalBillingCents,
            'total_billing_dollars' => $this->totalBillingCents / 100,
            'total_true_cost_cents' => $this->totalTrueCostCents,
            'total_true_cost_dollars' => $this->totalTrueCostCents / 100,
            'margin_cents' => $this->marginCents,
            'margin_dollars' => $this->marginCents / 100,
            'margin_percent' => $this->marginPercent,
            'service_breakdowns' => $this->serviceBreakdowns,
        ];
    }
}
