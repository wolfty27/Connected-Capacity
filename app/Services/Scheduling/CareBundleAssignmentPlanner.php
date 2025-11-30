<?php

namespace App\Services\Scheduling;

use App\DTOs\RequiredAssignmentDTO;
use App\DTOs\UnscheduledServiceDTO;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * CareBundleAssignmentPlanner
 *
 * Domain service that calculates unscheduled care requirements by comparing
 * CareBundleTemplate services against existing ServiceAssignments.
 *
 * For each patient with an active care plan, this service:
 * 1. Gets required services from their CareBundleTemplate
 * 2. Sums scheduled hours/visits from ServiceAssignments
 * 3. Computes remaining = required - scheduled
 *
 * Returns RequiredAssignmentDTO[] filtered to patients with remaining > 0.
 */
class CareBundleAssignmentPlanner
{
    /**
     * Get unscheduled care requirements for an organization.
     *
     * @param int|null $organizationId SPO organization ID (null = all)
     * @param Carbon $startDate Week start date
     * @param Carbon $endDate Week end date
     * @param int|null $patientId Optional filter by specific patient
     * @return Collection<RequiredAssignmentDTO>
     */
    public function getUnscheduledRequirements(
        ?int $organizationId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $patientId = null
    ): Collection {
        // Get active patients with care plans
        $query = Patient::query()
            ->where('status', 'Active')
            ->whereHas('carePlans', fn($q) => $q->where('status', 'active'))
            ->with([
                'user:id,name',
                'carePlans' => fn($q) => $q
                    ->where('status', 'active')
                    ->with([
                        'careBundleTemplate.services.serviceType',
                        'careBundle.serviceTypes',
                    ]),
                'riskFlags',
            ]);

        if ($patientId) {
            $query->where('id', $patientId);
        }

        $patients = $query->get();

        if ($patients->isEmpty()) {
            return collect();
        }

        // Get scheduled assignments for the date range
        $assignmentQuery = ServiceAssignment::query()
            ->whereIn('patient_id', $patients->pluck('id'))
            ->whereBetween('scheduled_start', [$startDate, $endDate])
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED, ServiceAssignment::STATUS_MISSED])
            ->select('patient_id', 'service_type_id')
            ->selectRaw('SUM(COALESCE(duration_minutes, EXTRACT(EPOCH FROM (scheduled_end - scheduled_start)) / 60)) as total_minutes')
            ->selectRaw('COUNT(*) as total_visits')
            ->groupBy('patient_id', 'service_type_id');

        if ($organizationId) {
            $assignmentQuery->where('service_provider_organization_id', $organizationId);
        }

        $assignments = $assignmentQuery->get();

        // Index assignments by patient_id and service_type_id
        $assignmentIndex = [];
        foreach ($assignments as $a) {
            $key = "{$a->patient_id}_{$a->service_type_id}";
            $assignmentIndex[$key] = [
                'hours' => round($a->total_minutes / 60, 2),
                'visits' => $a->total_visits,
            ];
        }

        // Build RequiredAssignmentDTO for each patient
        $results = collect();

        foreach ($patients as $patient) {
            $carePlan = $patient->carePlans->first();
            if (!$carePlan) {
                continue;
            }

            $dto = $this->buildPatientRequirements(
                $patient,
                $carePlan,
                $assignmentIndex
            );

            // Only include if there are unscheduled needs
            if ($dto && $dto->hasUnscheduledNeeds()) {
                $results->push($dto);
            }
        }

        // Sort by priority level (highest first)
        return $results->sortByDesc(fn($dto) => $dto->getPriorityLevel())->values();
    }

    /**
     * Build requirements DTO for a single patient.
     *
     * Priority for determining required services:
     * 1. CarePlan.service_requirements (customized in wizard)
     * 2. CareBundleTemplate.services (template defaults)
     * 3. CareBundle.serviceTypes (legacy system)
     */
    protected function buildPatientRequirements(
        Patient $patient,
        CarePlan $carePlan,
        array $assignmentIndex
    ): ?RequiredAssignmentDTO {
        $services = [];

        // Priority 1: Use plan-level service_requirements if available
        // These are customized requirements from the bundle wizard
        if ($carePlan->hasServiceRequirements()) {
            foreach ($carePlan->service_requirements as $requirement) {
                $serviceTypeId = $requirement['service_type_id'] ?? null;
                if (!$serviceTypeId) {
                    continue;
                }

                $serviceType = ServiceType::find($serviceTypeId);
                if (!$serviceType) {
                    continue;
                }

                $key = "{$patient->id}_{$serviceTypeId}";
                $scheduled = $assignmentIndex[$key] ?? ['hours' => 0, 'visits' => 0];

                $unitType = $this->getUnitType($serviceType);
                $frequency = $requirement['frequency_per_week'] ?? 1;
                $duration = $requirement['duration_minutes'] ?? 60;

                // For fixed-visit services, use the fixed count
                if ($serviceType->isFixedVisits()) {
                    $required = (float) ($serviceType->fixed_visits_per_plan ?? 2);
                    $scheduledUnits = $this->getScheduledVisitsForCarePlan($carePlan->id, $serviceTypeId);
                } else {
                    $required = $unitType === 'hours'
                        ? round(($frequency * $duration) / 60, 2)
                        : $frequency;
                    $scheduledUnits = $unitType === 'hours' ? $scheduled['hours'] : $scheduled['visits'];
                }

                $services[] = new UnscheduledServiceDTO(
                    serviceTypeId: $serviceTypeId,
                    serviceTypeName: $serviceType->name,
                    category: $serviceType->category ?? 'other',
                    color: $this->getCategoryColor($serviceType->category ?? 'other'),
                    required: $required,
                    scheduled: $scheduledUnits,
                    unitType: $unitType
                );
            }
        }
        // Priority 2: Use CareBundleTemplate system
        elseif ($carePlan->careBundleTemplate && $carePlan->careBundleTemplate->services->isNotEmpty()) {
            foreach ($carePlan->careBundleTemplate->services as $templateService) {
                $serviceType = $templateService->serviceType;
                if (!$serviceType) {
                    continue;
                }

                $key = "{$patient->id}_{$serviceType->id}";
                $scheduled = $assignmentIndex[$key] ?? ['hours' => 0, 'visits' => 0];

                // Determine unit type based on service type (fixed_visits services use visits)
                $unitType = $this->getUnitType($serviceType);

                // Get required units - pass serviceType for fixed_visits handling
                $required = $this->getRequiredUnits($templateService, $unitType, $serviceType);
                $scheduledUnits = $unitType === 'hours' ? $scheduled['hours'] : $scheduled['visits'];

                // For fixed-visit services, calculate scheduled across entire care plan, not just this week
                if ($serviceType->isFixedVisits()) {
                    $scheduledUnits = $this->getScheduledVisitsForCarePlan($carePlan->id, $serviceType->id);
                }

                $services[] = new UnscheduledServiceDTO(
                    serviceTypeId: $serviceType->id,
                    serviceTypeName: $serviceType->name,
                    category: $serviceType->category ?? 'other',
                    color: $this->getCategoryColor($serviceType->category ?? 'other'),
                    required: $required,
                    scheduled: $scheduledUnits,
                    unitType: $unitType,
                    careBundleServiceId: $templateService->id
                );
            }
        }
        // Priority 3: Fall back to legacy CareBundle
        elseif ($carePlan->careBundle && $carePlan->careBundle->serviceTypes->isNotEmpty()) {
            foreach ($carePlan->careBundle->serviceTypes as $serviceType) {
                $key = "{$patient->id}_{$serviceType->id}";
                $scheduled = $assignmentIndex[$key] ?? ['hours' => 0, 'visits' => 0];

                $unitType = $this->getUnitType($serviceType);

                // Handle fixed-visit services (like RPM) differently
                if ($serviceType->isFixedVisits()) {
                    $required = (float) ($serviceType->fixed_visits_per_plan ?? 2);
                    $scheduledUnits = $this->getScheduledVisitsForCarePlan($carePlan->id, $serviceType->id);
                } else {
                    $frequency = $serviceType->pivot->default_frequency_per_week ?? 1;
                    $duration = $serviceType->default_duration_minutes ?? 60;

                    $required = $unitType === 'hours'
                        ? round(($frequency * $duration) / 60, 2)
                        : $frequency;
                    $scheduledUnits = $unitType === 'hours' ? $scheduled['hours'] : $scheduled['visits'];
                }

                $services[] = new UnscheduledServiceDTO(
                    serviceTypeId: $serviceType->id,
                    serviceTypeName: $serviceType->name,
                    category: $serviceType->category ?? 'other',
                    color: $this->getCategoryColor($serviceType->category ?? 'other'),
                    required: $required,
                    scheduled: $scheduledUnits,
                    unitType: $unitType
                );
            }
        }

        if (empty($services)) {
            return null;
        }

        // Get RUG category from template or patient
        $rugCategory = $carePlan->careBundleTemplate?->rug_category
            ?? $patient->rug_category
            ?? 'Unknown';

        // Get risk flags
        $riskFlags = $this->extractRiskFlags($patient);

        return new RequiredAssignmentDTO(
            patientId: $patient->id,
            patientName: $patient->user?->name ?? 'Unknown',
            rugCategory: $rugCategory,
            riskFlags: $riskFlags,
            services: $services,
            carePlanId: $carePlan->id,
            careBundleTemplateId: $carePlan->care_bundle_template_id
        );
    }

    /**
     * Get unit type for a service type (hours vs visits).
     *
     * Fixed-visit services (like RPM) are always measured in visits.
     */
    protected function getUnitType($serviceType): string
    {
        // Fixed-visit services are always measured in visits
        if ($serviceType->scheduling_mode === ServiceType::SCHEDULING_MODE_FIXED_VISITS) {
            return 'visits';
        }

        // Allied health services typically measured in visits
        $visitCategories = ['rehab', 'therapy', 'assessment'];
        $visitCodes = ['OT', 'PT', 'SLP', 'SW', 'RD', 'RT'];

        $category = strtolower($serviceType->category ?? '');
        $code = strtoupper($serviceType->code ?? '');

        if (in_array($category, $visitCategories) || in_array($code, $visitCodes)) {
            return 'visits';
        }

        return 'hours';
    }

    /**
     * Get required units from a template service.
     *
     * For fixed-visit services (like RPM), the required units is the
     * fixed_visits_per_plan from the ServiceType, not weekly frequency.
     * These are tracked across the entire care plan, not per week.
     *
     * @param mixed $templateService The template service record
     * @param string $unitType 'hours' or 'visits'
     * @param ServiceType|null $serviceType The service type model (for fixed_visits check)
     * @return float Required units
     */
    protected function getRequiredUnits($templateService, string $unitType, ?ServiceType $serviceType = null): float
    {
        // For fixed-visit services (like RPM), use the fixed_visits_per_plan value
        // These are total visits for the care plan, not per week
        if ($serviceType && $serviceType->scheduling_mode === ServiceType::SCHEDULING_MODE_FIXED_VISITS) {
            return (float) ($serviceType->fixed_visits_per_plan ?? 2);
        }

        $frequency = $templateService->default_frequency_per_week ?? 1;
        $duration = $templateService->default_duration_minutes ?? 60;

        if ($unitType === 'hours') {
            return round(($frequency * $duration) / 60, 2);
        }

        return $frequency;
    }

    /**
     * Extract risk flags from patient.
     */
    protected function extractRiskFlags(Patient $patient): array
    {
        $flags = [];

        // Check for high-risk conditions from patient model or related data
        if ($patient->riskFlags && $patient->riskFlags->isNotEmpty()) {
            foreach ($patient->riskFlags as $flag) {
                if ($flag->severity === 'high' || $flag->severity === 'critical') {
                    $flags[] = 'dangerous';
                } elseif ($flag->severity === 'medium') {
                    $flags[] = 'warning';
                }
            }
        }

        // Check patient attributes for risk indicators
        if (method_exists($patient, 'isHighRisk') && $patient->isHighRisk()) {
            if (!in_array('dangerous', $flags)) {
                $flags[] = 'dangerous';
            }
        }

        return array_unique($flags);
    }

    /**
     * Get color for a service category.
     */
    protected function getCategoryColor(string $category): string
    {
        return match (strtolower($category)) {
            'nursing' => '#DBEAFE', // Blue
            'psw', 'personal_support' => '#D1FAE5', // Green
            'homemaking' => '#FEF3C7', // Yellow
            'behaviour', 'behavioral' => '#FEE2E2', // Red
            'rehab', 'therapy' => '#E9D5FF', // Purple
            default => '#F3F4F6', // Gray
        };
    }

    /**
     * Get the total number of scheduled visits for a fixed-visit service across the entire care plan.
     *
     * For services like RPM that have a fixed number of visits per care plan (not per week),
     * we need to count all visits regardless of date range.
     *
     * @param int $carePlanId The care plan ID
     * @param int $serviceTypeId The service type ID
     * @return int Total number of scheduled visits
     */
    protected function getScheduledVisitsForCarePlan(int $carePlanId, int $serviceTypeId): int
    {
        return ServiceAssignment::where('care_plan_id', $carePlanId)
            ->where('service_type_id', $serviceTypeId)
            ->whereNotIn('status', [
                ServiceAssignment::STATUS_CANCELLED,
                ServiceAssignment::STATUS_MISSED,
            ])
            ->count();
    }

    /**
     * Get requirements for a single patient.
     */
    public function getPatientRequirements(
        int $patientId,
        Carbon $startDate,
        Carbon $endDate
    ): ?RequiredAssignmentDTO {
        $results = $this->getUnscheduledRequirements(
            organizationId: null,
            startDate: $startDate,
            endDate: $endDate,
            patientId: $patientId
        );

        return $results->first();
    }

    /**
     * Get summary statistics for unscheduled care.
     */
    public function getSummaryStats(
        ?int $organizationId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $requirements = $this->getUnscheduledRequirements(
            $organizationId,
            $startDate,
            $endDate
        );

        return [
            'patients_with_needs' => $requirements->count(),
            'total_remaining_hours' => $requirements->sum(fn($dto) => $dto->getTotalRemainingHours()),
            'total_remaining_visits' => $requirements->sum(fn($dto) => $dto->getTotalRemainingVisits()),
            'high_priority_count' => $requirements->filter(fn($dto) => $dto->getPriorityLevel() >= 10)->count(),
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ];
    }
}
