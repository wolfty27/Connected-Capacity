<?php

namespace App\Services;

use App\Models\CareBundleTemplate;
use App\Models\CareBundleTemplateService;
use App\Models\RUGClassification;
use App\Models\RugServiceRecommendation;
use App\Models\ServiceType;
use Illuminate\Support\Collection;

/**
 * RUG Service Planner
 *
 * Domain service that builds service lists for care bundles based on RUG/interRAI criteria.
 * Combines base bundle template services with clinically indicated services from
 * RugServiceRecommendation metadata.
 *
 * Uses metadata-driven approach - no business rules embedded in this service.
 * All service recommendations come from the rug_service_recommendations table.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 * @see docs/CC21_RUG_Bundle_Templates.md
 */
class RugServicePlanner
{
    /**
     * Build complete service list for a RUG classification.
     *
     * Combines:
     * 1. Base services from CareBundleTemplate
     * 2. Clinically indicated services from RugServiceRecommendation metadata
     *
     * @param RUGClassification $rug The patient's RUG classification
     * @param CareBundleTemplate $template The selected bundle template
     * @return Collection Collection of service configurations
     */
    public function buildServicesFor(RUGClassification $rug, CareBundleTemplate $template): Collection
    {
        // Start with base template services
        $services = $this->getBaseTemplateServices($template, $rug);

        // Add clinically indicated services based on RUG/interRAI criteria
        $recommendations = $this->getApplicableRecommendations($rug);
        $services = $this->mergeRecommendations($services, $recommendations);

        // Sort by priority
        return $services->sortByDesc('priority')->values();
    }

    /**
     * Get base services from the bundle template.
     */
    protected function getBaseTemplateServices(CareBundleTemplate $template, RUGClassification $rug): Collection
    {
        $templateServices = $template->services()->with('serviceType')->get();

        return $templateServices->map(function (CareBundleTemplateService $service) use ($rug) {
            // Check if conditional service should be included
            if (!$this->shouldIncludeService($service, $rug)) {
                return null;
            }

            return [
                'service_type_id' => $service->service_type_id,
                'service_type_code' => $service->serviceType?->code,
                'service_type_name' => $service->serviceType?->name,
                'frequency_per_week' => $service->default_frequency_per_week,
                'duration_minutes' => $service->default_duration_minutes,
                'is_required' => $service->is_required,
                'source' => 'template',
                'priority' => $service->is_required ? 100 : 50,
                'cost_per_visit' => $service->serviceType?->cost_per_visit ?? 0,
                'estimated_weekly_cost' => $service->calculateWeeklyCost(),
            ];
        })->filter();
    }

    /**
     * Check if a conditional service should be included based on RUG flags.
     */
    protected function shouldIncludeService(CareBundleTemplateService $service, RUGClassification $rug): bool
    {
        // Required services are always included
        if ($service->is_required) {
            return true;
        }

        // Non-conditional services are included by default
        if (!$service->is_conditional || empty($service->condition_flags)) {
            return true;
        }

        // Check if any required flags are present in the RUG classification
        $rugFlags = $rug->flags ?? [];
        foreach ($service->condition_flags as $flag) {
            if ($rugFlags[$flag] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get applicable service recommendations based on RUG classification.
     */
    protected function getApplicableRecommendations(RUGClassification $rug): Collection
    {
        return RugServiceRecommendation::getForClassification($rug);
    }

    /**
     * Merge recommendations into the service list.
     *
     * If a service already exists, update frequency to the higher value.
     * If a service doesn't exist, add it from recommendations.
     */
    protected function mergeRecommendations(Collection $services, Collection $recommendations): Collection
    {
        foreach ($recommendations as $rec) {
            $existingIndex = $services->search(function ($service) use ($rec) {
                return $service['service_type_id'] === $rec->service_type_id;
            });

            if ($existingIndex !== false) {
                // Update existing service if recommendation has higher frequency
                $existing = $services[$existingIndex];
                if ($rec->min_frequency_per_week > $existing['frequency_per_week']) {
                    $services[$existingIndex] = array_merge($existing, [
                        'frequency_per_week' => $rec->min_frequency_per_week,
                        'source' => 'template+recommendation',
                        'justification' => $rec->justification,
                    ]);
                }
            } else {
                // Add new service from recommendation
                $services->push([
                    'service_type_id' => $rec->service_type_id,
                    'service_type_code' => $rec->serviceType?->code,
                    'service_type_name' => $rec->serviceType?->name,
                    'frequency_per_week' => $rec->min_frequency_per_week,
                    'duration_minutes' => $rec->default_duration_minutes ?? $rec->serviceType?->default_duration_minutes ?? 60,
                    'is_required' => $rec->is_required,
                    'source' => 'recommendation',
                    'priority' => $rec->priority_weight,
                    'cost_per_visit' => $rec->serviceType?->cost_per_visit ?? 0,
                    'estimated_weekly_cost' => $this->calculateWeeklyCost($rec),
                    'justification' => $rec->justification,
                ]);
            }
        }

        return $services;
    }

    /**
     * Calculate weekly cost for a recommendation.
     */
    protected function calculateWeeklyCost(RugServiceRecommendation $rec): float
    {
        $costPerVisit = $rec->serviceType?->cost_per_visit ?? 0;
        $frequency = $rec->min_frequency_per_week ?? 0;

        return $costPerVisit * $frequency;
    }

    /**
     * Get only the clinically indicated services (excluding base template services).
     *
     * Useful for displaying additional services added based on RUG/interRAI criteria.
     */
    public function getAdditionalServicesFor(RUGClassification $rug, CareBundleTemplate $template): Collection
    {
        $templateServiceIds = $template->services()
            ->pluck('service_type_id')
            ->toArray();

        $recommendations = $this->getApplicableRecommendations($rug);

        return $recommendations->filter(function ($rec) use ($templateServiceIds) {
            return !in_array($rec->service_type_id, $templateServiceIds, true);
        })->map(function ($rec) {
            return [
                'service_type_id' => $rec->service_type_id,
                'service_type_code' => $rec->serviceType?->code,
                'service_type_name' => $rec->serviceType?->name,
                'frequency_per_week' => $rec->min_frequency_per_week,
                'duration_minutes' => $rec->default_duration_minutes ?? $rec->serviceType?->default_duration_minutes ?? 60,
                'is_required' => $rec->is_required,
                'justification' => $rec->justification,
                'priority' => $rec->priority_weight,
            ];
        })->values();
    }

    /**
     * Calculate total estimated weekly cost for a service plan.
     */
    public function calculateTotalWeeklyCost(Collection $services): float
    {
        return $services->sum('estimated_weekly_cost');
    }

    /**
     * Check if the total cost is within the bundle cap.
     */
    public function isWithinBudget(Collection $services, CareBundleTemplate $template): bool
    {
        $totalCost = $this->calculateTotalWeeklyCost($services);
        $cap = $template->weekly_cap_cents / 100; // Convert cents to dollars

        return $totalCost <= $cap;
    }

    /**
     * Get services that exceed the budget, in priority order (lowest first).
     *
     * This can be used to suggest which services to reduce or remove
     * to fit within the bundle budget.
     */
    public function getServicesExceedingBudget(Collection $services, CareBundleTemplate $template): Collection
    {
        $cap = $template->weekly_cap_cents / 100;
        $totalCost = $this->calculateTotalWeeklyCost($services);

        if ($totalCost <= $cap) {
            return collect();
        }

        $excess = $totalCost - $cap;
        $nonRequired = $services->where('is_required', false)
            ->sortBy('priority')
            ->values();

        $toRemove = collect();
        $removedCost = 0;

        foreach ($nonRequired as $service) {
            if ($removedCost >= $excess) {
                break;
            }
            $toRemove->push($service);
            $removedCost += $service['estimated_weekly_cost'];
        }

        return $toRemove;
    }

    /**
     * Get a summary of services by category.
     */
    public function getServiceSummaryByCategory(Collection $services): Collection
    {
        $serviceTypes = ServiceType::whereIn('id', $services->pluck('service_type_id'))
            ->get()
            ->keyBy('id');

        return $services->groupBy(function ($service) use ($serviceTypes) {
            $type = $serviceTypes[$service['service_type_id']] ?? null;
            return $type?->category ?? 'Other';
        })->map(function ($categoryServices, $category) {
            return [
                'category' => $category,
                'services_count' => $categoryServices->count(),
                'total_visits_per_week' => $categoryServices->sum('frequency_per_week'),
                'total_hours_per_week' => $categoryServices->sum(function ($s) {
                    return ($s['frequency_per_week'] * $s['duration_minutes']) / 60;
                }),
                'total_weekly_cost' => $categoryServices->sum('estimated_weekly_cost'),
                'services' => $categoryServices->values(),
            ];
        })->values();
    }

    /**
     * Validate that all required services are included.
     */
    public function validateRequiredServices(Collection $services, CareBundleTemplate $template): array
    {
        $requiredTemplateServices = $template->services()
            ->where('is_required', true)
            ->pluck('service_type_id')
            ->toArray();

        $includedServiceIds = $services->pluck('service_type_id')->toArray();

        $missing = array_diff($requiredTemplateServices, $includedServiceIds);

        if (empty($missing)) {
            return ['valid' => true, 'missing' => []];
        }

        $missingServices = ServiceType::whereIn('id', $missing)->pluck('name', 'id');

        return [
            'valid' => false,
            'missing' => $missingServices->toArray(),
        ];
    }
}
