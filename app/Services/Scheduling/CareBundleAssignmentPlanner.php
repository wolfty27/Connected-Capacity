<?php

namespace App\Services\Scheduling;

use App\DTOs\RequiredAssignmentDTO;
use App\DTOs\UnscheduledServiceDTO;
use App\Models\CareBundleService;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CareBundleAssignmentPlanner
{
    /**
     * Get unscheduled care requirements for all patients (or a specific patient).
     *
     * This implements the bundles.unscheduled_care_correctness feature:
     * - Computes required_units from CareBundleService
     * - Computes scheduled_units from ServiceAssignments
     * - Returns patients where remaining_units > 0
     *
     * @param int|null $organizationId Filter by organization (optional)
     * @param Carbon $startDate Start of the period to check
     * @param Carbon $endDate End of the period to check
     * @param int|null $patientId Filter to specific patient (optional)
     * @return Collection<RequiredAssignmentDTO>
     */
    public function getUnscheduledRequirements(
        ?int $organizationId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $patientId = null
    ): Collection {
        // Get patients with active care plans
        $query = Patient::query()
            ->active()
            ->withActiveCarePlan()
            ->with([
                'activeCarePlan.careBundleTemplate.services.serviceType',
                'activeCarePlan',
            ]);

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        if ($patientId !== null) {
            $query->where('id', $patientId);
        }

        $patients = $query->get();

        $results = collect();

        foreach ($patients as $patient) {
            $dto = $this->getPatientRequirements($patient, $startDate, $endDate);
            if ($dto !== null && $dto->hasUnscheduledNeeds()) {
                $results->push($dto);
            }
        }

        // Sort by priority (1=highest priority first)
        return $results->sortBy(fn($dto) => $dto->getPriorityLevel())->values();
    }

    /**
     * Get unscheduled care requirements for a specific patient.
     *
     * @param Patient|int $patient Patient model or ID
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return RequiredAssignmentDTO|null
     */
    public function getPatientRequirements(
        Patient|int $patient,
        Carbon $startDate,
        Carbon $endDate
    ): ?RequiredAssignmentDTO {
        if (is_int($patient)) {
            $patient = Patient::with([
                'activeCarePlan.careBundleTemplate.services.serviceType',
            ])->find($patient);
        }

        if (!$patient) {
            return null;
        }

        $carePlan = $patient->activeCarePlan;
        if (!$carePlan) {
            return null;
        }

        $bundleTemplate = $carePlan->careBundleTemplate;
        if (!$bundleTemplate) {
            return null;
        }

        $services = [];

        foreach ($bundleTemplate->services as $bundleService) {
            $serviceType = $bundleService->serviceType;
            if (!$serviceType || !$serviceType->is_active) {
                continue;
            }

            $serviceDto = $this->computeServiceRequirements(
                $patient->id,
                $carePlan,
                $bundleService,
                $startDate,
                $endDate
            );

            $services[] = $serviceDto;
        }

        return new RequiredAssignmentDTO(
            patientId: $patient->id,
            patientName: $patient->full_name,
            rugCategory: $patient->rug_category,
            riskFlags: $patient->risk_flags ?? [],
            services: $services,
            carePlanId: $carePlan->id,
            careBundleTemplateId: $bundleTemplate->id
        );
    }

    /**
     * Compute requirements for a specific service within a care plan.
     *
     * @param int $patientId
     * @param CarePlan $carePlan
     * @param CareBundleService $bundleService
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return UnscheduledServiceDTO
     */
    private function computeServiceRequirements(
        int $patientId,
        CarePlan $carePlan,
        CareBundleService $bundleService,
        Carbon $startDate,
        Carbon $endDate
    ): UnscheduledServiceDTO {
        $serviceType = $bundleService->serviceType;

        if ($serviceType->isFixedVisits()) {
            // Fixed visits mode (e.g., RPM with 2 visits per care plan)
            return $this->computeFixedVisitsRequirements(
                $patientId,
                $carePlan,
                $bundleService,
                $serviceType
            );
        }

        // Weekly scheduling mode (hours per week)
        return $this->computeWeeklyRequirements(
            $patientId,
            $carePlan,
            $bundleService,
            $serviceType,
            $startDate,
            $endDate
        );
    }

    /**
     * Compute requirements for fixed-visits services (like RPM).
     *
     * For RPM: required_visits = 2 (Setup + Discharge)
     * Count ALL visits for this care plan (regardless of date range)
     */
    private function computeFixedVisitsRequirements(
        int $patientId,
        CarePlan $carePlan,
        CareBundleService $bundleService,
        ServiceType $serviceType
    ): UnscheduledServiceDTO {
        // Required visits from bundle or service type default
        $requiredVisits = $bundleService->visits_per_plan
            ?? $serviceType->fixed_visits_per_plan
            ?? 2;

        // Count ALL scheduled visits for this care plan (not date-filtered)
        $scheduledVisits = ServiceAssignment::query()
            ->where('care_plan_id', $carePlan->id)
            ->forServiceType($serviceType->id)
            ->scheduled() // Exclude cancelled/missed
            ->count();

        return new UnscheduledServiceDTO(
            serviceTypeId: $serviceType->id,
            serviceTypeName: $serviceType->name,
            category: $serviceType->category,
            color: $serviceType->color,
            required: $requiredVisits,
            scheduled: $scheduledVisits,
            unitType: 'visits'
        );
    }

    /**
     * Compute requirements for weekly-scheduled services (hours per week).
     *
     * Count hours within the specified date range.
     */
    private function computeWeeklyRequirements(
        int $patientId,
        CarePlan $carePlan,
        CareBundleService $bundleService,
        ServiceType $serviceType,
        Carbon $startDate,
        Carbon $endDate
    ): UnscheduledServiceDTO {
        // Required hours per week from bundle
        $requiredHours = $bundleService->hours_per_week ?? 0;

        // Get scheduled hours within the date range
        $assignments = ServiceAssignment::query()
            ->forPatient($patientId)
            ->forServiceType($serviceType->id)
            ->scheduled()
            ->inDateRange($startDate, $endDate)
            ->get();

        $scheduledMinutes = $assignments->sum('duration_minutes');
        $scheduledHours = $scheduledMinutes / 60;

        return new UnscheduledServiceDTO(
            serviceTypeId: $serviceType->id,
            serviceTypeName: $serviceType->name,
            category: $serviceType->category,
            color: $serviceType->color,
            required: $requiredHours,
            scheduled: round($scheduledHours, 2),
            unitType: 'hours'
        );
    }

    /**
     * Get summary statistics for unscheduled care.
     *
     * @param int|null $organizationId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
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

        $patientsWithNeeds = $requirements->filter(fn($dto) => $dto->hasUnscheduledNeeds())->count();
        $totalRemainingHours = $requirements->sum(fn($dto) => $dto->getTotalRemainingHours());
        $totalRemainingVisits = $requirements->sum(fn($dto) => $dto->getTotalRemainingVisits());
        $highPriorityCount = $requirements->filter(fn($dto) => $dto->getPriorityLevel() === 1)->count();

        return [
            'patients_with_needs' => $patientsWithNeeds,
            'total_remaining_hours' => round($totalRemainingHours, 1),
            'total_remaining_visits' => (int) $totalRemainingVisits,
            'high_priority_count' => $highPriorityCount,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ];
    }

    /**
     * Check if all required care has been scheduled for a patient.
     *
     * @param int $patientId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return bool
     */
    public function isPatientFullyScheduled(
        int $patientId,
        Carbon $startDate,
        Carbon $endDate
    ): bool {
        $requirements = $this->getPatientRequirements($patientId, $startDate, $endDate);

        if ($requirements === null) {
            return true; // No active care plan = nothing to schedule
        }

        return !$requirements->hasUnscheduledNeeds();
    }

    /**
     * Get services that still need scheduling for a patient.
     *
     * @param int $patientId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getUnscheduledServicesForPatient(
        int $patientId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $requirements = $this->getPatientRequirements($patientId, $startDate, $endDate);

        if ($requirements === null) {
            return [];
        }

        return $requirements->getServicesWithNeeds();
    }
}
