<?php

namespace App\Repositories;

use App\Models\ServiceProviderOrganization;
use App\Models\ServiceRate;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * ServiceRateRepository
 *
 * Provides rate resolution logic for the rate card system.
 * Respects the metadata-driven architecture by resolving rates through:
 * 1. Organization-specific active rate (if exists)
 * 2. System-wide default rate (organization_id = null)
 *
 * All cost/billing logic should use this repository to resolve rates,
 * ensuring consistent rate lookups across the application.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class ServiceRateRepository
{
    /**
     * Get the effective rate for a service type at a given date.
     *
     * Resolution order:
     * 1. Organization-specific active rate
     * 2. System default rate (organization_id = null)
     *
     * @param ServiceType $type The service type
     * @param ServiceProviderOrganization|null $org The organization (null = system default only)
     * @param Carbon $at The date to check for active rates
     * @return ServiceRate|null The effective rate, or null if none found
     */
    public function getEffectiveRate(
        ServiceType $type,
        ?ServiceProviderOrganization $org,
        Carbon $at
    ): ?ServiceRate {
        // 1. Try organization-specific active rate
        if ($org !== null) {
            $orgRate = ServiceRate::query()
                ->forServiceType($type->id)
                ->forOrganization($org->id)
                ->effectiveOn($at)
                ->orderBy('effective_from', 'desc')
                ->first();

            if ($orgRate) {
                return $orgRate;
            }
        }

        // 2. Fallback to system default
        return ServiceRate::query()
            ->forServiceType($type->id)
            ->systemDefault()
            ->effectiveOn($at)
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Get the effective rate by service type ID.
     */
    public function getEffectiveRateById(
        int $serviceTypeId,
        ?int $organizationId,
        Carbon $at
    ): ?ServiceRate {
        // 1. Try organization-specific active rate
        if ($organizationId !== null) {
            $orgRate = ServiceRate::query()
                ->forServiceType($serviceTypeId)
                ->forOrganization($organizationId)
                ->effectiveOn($at)
                ->orderBy('effective_from', 'desc')
                ->first();

            if ($orgRate) {
                return $orgRate;
            }
        }

        // 2. Fallback to system default
        return ServiceRate::query()
            ->forServiceType($serviceTypeId)
            ->systemDefault()
            ->effectiveOn($at)
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Get the current effective rate (using today's date).
     */
    public function getCurrentRate(
        ServiceType $type,
        ?ServiceProviderOrganization $org = null
    ): ?ServiceRate {
        return $this->getEffectiveRate($type, $org, Carbon::today());
    }

    /**
     * Get all system default rates (currently active).
     */
    public function getSystemDefaultRates(): Collection
    {
        return ServiceRate::query()
            ->systemDefault()
            ->currentlyActive()
            ->with('serviceType')
            ->orderBy('service_type_id')
            ->get();
    }

    /**
     * Get all organization-specific rates (currently active).
     */
    public function getOrganizationRates(int $organizationId): Collection
    {
        return ServiceRate::query()
            ->forOrganization($organizationId)
            ->currentlyActive()
            ->with('serviceType')
            ->orderBy('service_type_id')
            ->get();
    }

    /**
     * Get a combined view of system defaults and org-specific rates.
     * Returns effective rates for all service types for an organization.
     */
    public function getEffectiveRatesForOrganization(?int $organizationId = null): Collection
    {
        $serviceTypes = ServiceType::where('active', true)
            ->orderBy('name')
            ->get();

        $today = Carbon::today();

        return $serviceTypes->map(function ($serviceType) use ($organizationId, $today) {
            $effectiveRate = $this->getEffectiveRateById(
                $serviceType->id,
                $organizationId,
                $today
            );

            $systemDefault = ServiceRate::query()
                ->forServiceType($serviceType->id)
                ->systemDefault()
                ->effectiveOn($today)
                ->orderBy('effective_from', 'desc')
                ->first();

            $orgRate = $organizationId
                ? ServiceRate::query()
                    ->forServiceType($serviceType->id)
                    ->forOrganization($organizationId)
                    ->effectiveOn($today)
                    ->orderBy('effective_from', 'desc')
                    ->first()
                : null;

            return [
                'service_type_id' => $serviceType->id,
                'service_type_code' => $serviceType->code,
                'service_type_name' => $serviceType->name,
                'service_type_category' => $serviceType->category,
                'effective_rate' => $effectiveRate?->toApiArray(),
                'system_default_rate' => $systemDefault?->toApiArray(),
                'organization_rate' => $orgRate?->toApiArray(),
                'has_org_override' => $orgRate !== null,
                'default_duration_minutes' => $serviceType->default_duration_minutes,
            ];
        });
    }

    /**
     * Create or update an organization-specific rate.
     * If a rate exists for the same service/org combination, it will be closed
     * and a new rate created.
     *
     * @param int $serviceTypeId
     * @param int $organizationId
     * @param string $unitType
     * @param int $rateCents
     * @param Carbon|null $effectiveFrom Defaults to today
     * @param Carbon|null $effectiveTo
     * @param string|null $notes
     * @param int|null $createdBy
     * @return ServiceRate The newly created rate
     */
    public function createOrganizationRate(
        int $serviceTypeId,
        int $organizationId,
        string $unitType,
        int $rateCents,
        ?Carbon $effectiveFrom = null,
        ?Carbon $effectiveTo = null,
        ?string $notes = null,
        ?int $createdBy = null
    ): ServiceRate {
        $effectiveFrom = $effectiveFrom ?? Carbon::today();

        // Close any existing active rate for this service/org combination
        $existingRate = ServiceRate::query()
            ->forServiceType($serviceTypeId)
            ->forOrganization($organizationId)
            ->currentlyActive()
            ->first();

        if ($existingRate && $existingRate->effective_from < $effectiveFrom) {
            // Close the existing rate the day before the new rate starts
            $existingRate->closeRate($effectiveFrom->copy()->subDay());
        } elseif ($existingRate) {
            // Delete if starting on same day or later
            $existingRate->delete();
        }

        // Create the new rate
        return ServiceRate::create([
            'service_type_id' => $serviceTypeId,
            'organization_id' => $organizationId,
            'unit_type' => $unitType,
            'rate_cents' => $rateCents,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Get rate history for a service type and organization.
     */
    public function getRateHistory(
        int $serviceTypeId,
        ?int $organizationId = null
    ): Collection {
        $query = ServiceRate::query()
            ->forServiceType($serviceTypeId)
            ->with(['serviceType', 'organization', 'creator'])
            ->orderBy('effective_from', 'desc');

        if ($organizationId === null) {
            $query->systemDefault();
        } else {
            $query->forOrganization($organizationId);
        }

        return $query->get();
    }

    /**
     * Calculate cost for a service using the effective rate.
     *
     * @param ServiceType $type
     * @param float $quantity The number of units (hours, visits, etc.)
     * @param ServiceProviderOrganization|null $org
     * @param Carbon|null $at Defaults to today
     * @return int Cost in cents, or 0 if no rate found
     */
    public function calculateCost(
        ServiceType $type,
        float $quantity,
        ?ServiceProviderOrganization $org = null,
        ?Carbon $at = null
    ): int {
        $at = $at ?? Carbon::today();
        $rate = $this->getEffectiveRate($type, $org, $at);

        if (!$rate) {
            return 0;
        }

        return $rate->calculateCost($quantity);
    }

    /**
     * Delete an organization-specific rate.
     * Does NOT delete system default rates.
     */
    public function deleteOrganizationRate(ServiceRate $rate): bool
    {
        if ($rate->is_system_default) {
            return false;
        }

        return $rate->delete();
    }

    /**
     * Check if an organization has any custom rates.
     */
    public function hasCustomRates(int $organizationId): bool
    {
        return ServiceRate::query()
            ->forOrganization($organizationId)
            ->currentlyActive()
            ->exists();
    }

    /**
     * Get all service types with their current effective rates for display.
     */
    public function getRateCardForDisplay(?int $organizationId = null): array
    {
        $rates = $this->getEffectiveRatesForOrganization($organizationId);

        return [
            'rates' => $rates->toArray(),
            'organization_id' => $organizationId,
            'has_custom_rates' => $organizationId ? $this->hasCustomRates($organizationId) : false,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }
}
