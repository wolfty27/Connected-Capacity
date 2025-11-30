<?php

namespace App\Services\CareOps;

use App\Models\EmploymentType;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Scheduling\CareBundleAssignmentPlanner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Workforce Capacity Service
 *
 * Computes workforce capacity vs required care hours for capacity planning.
 *
 * Key metrics:
 * - Available hours: Total staff availability by role/service type
 * - Required hours: Care bundle requirements for active patients
 * - Net capacity: Available - Required - Travel Overhead
 * - Travel overhead: Default 30 min per visit or from actual data
 *
 * Supports filtering by:
 * - Time period (week or month)
 * - Provider type (SPO internal vs SSPO)
 * - Role
 * - Service type
 */
class WorkforceCapacityService
{
    // Default travel time per visit in minutes
    public const DEFAULT_TRAVEL_MINUTES_PER_VISIT = 30;

    // Standard work hours per week for capacity calculation
    public const STANDARD_HOURS_PER_WEEK = 40.0;

    protected CareBundleAssignmentPlanner $planner;
    protected FteComplianceService $fteService;

    public function __construct(
        CareBundleAssignmentPlanner $planner,
        FteComplianceService $fteService
    ) {
        $this->planner = $planner;
        $this->fteService = $fteService;
    }

    /**
     * Get comprehensive capacity snapshot for an organization.
     *
     * @param int|null $organizationId Organization ID
     * @param Carbon $startDate Period start date
     * @param Carbon $endDate Period end date
     * @param string|null $providerType Filter by 'spo' or 'sspo'
     * @return array
     */
    public function getCapacitySnapshot(
        ?int $organizationId,
        Carbon $startDate,
        Carbon $endDate,
        ?string $providerType = null
    ): array {
        $orgId = $organizationId ?? Auth::user()?->organization_id;

        if (!$orgId) {
            return $this->emptySnapshot();
        }

        // Get available capacity from staff
        $availableCapacity = $this->getAvailableCapacity($orgId, $startDate, $endDate, $providerType);

        // Get required care from care bundles
        $requiredCare = $this->getRequiredCare($orgId, $startDate, $endDate, $providerType);

        // Get scheduled hours (already assigned)
        $scheduledHours = $this->getScheduledHours($orgId, $startDate, $endDate, $providerType);

        // Calculate travel overhead
        $travelOverhead = $this->calculateTravelOverhead($orgId, $startDate, $endDate, $providerType);

        // Calculate net capacity
        $netCapacity = $availableCapacity['total_hours'] - $requiredCare['total_hours'] - $travelOverhead['total_hours'];
        $remainingCapacity = $availableCapacity['total_hours'] - $scheduledHours['total_hours'] - $travelOverhead['total_hours'];

        // Utilization rate
        $utilizationRate = $availableCapacity['total_hours'] > 0
            ? round(($scheduledHours['total_hours'] / $availableCapacity['total_hours']) * 100, 1)
            : 0;

        // Coverage rate (how much of required care can we cover)
        $coverageRate = $requiredCare['total_hours'] > 0
            ? round(min(100, ($availableCapacity['total_hours'] / $requiredCare['total_hours']) * 100), 1)
            : 100;

        return [
            'organization_id' => $orgId,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'weeks' => max(1, $startDate->diffInWeeks($endDate)),
            ],
            'provider_type' => $providerType,
            'calculated_at' => Carbon::now()->toIso8601String(),

            // Summary metrics
            'summary' => [
                'available_hours' => round($availableCapacity['total_hours'], 1),
                'required_hours' => round($requiredCare['total_hours'], 1),
                'scheduled_hours' => round($scheduledHours['total_hours'], 1),
                'travel_overhead' => round($travelOverhead['total_hours'], 1),
                'net_capacity' => round($netCapacity, 1),
                'remaining_capacity' => round($remainingCapacity, 1),
                'utilization_rate' => $utilizationRate,
                'coverage_rate' => $coverageRate,
                'status' => $this->getCapacityStatus($netCapacity),
            ],

            // Breakdowns
            'available_by_role' => $availableCapacity['by_role'],
            'required_by_service' => $requiredCare['by_service'],
            'scheduled_by_service' => $scheduledHours['by_service'],

            // Staff counts
            'staff_count' => $availableCapacity['staff_count'],

            // Patient counts
            'patient_count' => $requiredCare['patient_count'],
            'patients_with_unscheduled_needs' => $requiredCare['patients_with_needs'],
        ];
    }

    /**
     * Get available staff capacity.
     */
    public function getAvailableCapacity(
        int $organizationId,
        Carbon $startDate,
        Carbon $endDate,
        ?string $providerType = null
    ): array {
        // Get staff members with their availability
        $staffQuery = User::where('organization_id', $organizationId)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where(function ($q) {
                $q->where('staff_status', User::STAFF_STATUS_ACTIVE)
                  ->orWhereNull('staff_status');
            })
            ->with(['staffRole', 'employmentTypeModel']);

        // Filter by provider type
        if ($providerType === 'spo') {
            $staffQuery->whereHas('employmentTypeModel', function ($q) {
                $q->where('is_direct_staff', true);
            });
        } elseif ($providerType === 'sspo') {
            $staffQuery->whereHas('employmentTypeModel', function ($q) {
                $q->where('is_direct_staff', false);
            });
        }

        $staff = $staffQuery->get();

        $byRole = [];
        $totalHours = 0;
        $staffCount = [
            'total' => $staff->count(),
            'full_time' => 0,
            'part_time' => 0,
            'casual' => 0,
            'sspo' => 0,
        ];

        // Calculate weeks in period
        $weeks = max(1, $startDate->diffInWeeks($endDate));

        foreach ($staff as $member) {
            // Get weekly hours from employment type or fallback
            $weeklyHours = $member->max_weekly_hours
                ?? $member->employmentTypeModel?->standard_hours_per_week
                ?? self::STANDARD_HOURS_PER_WEEK;

            $memberHours = $weeklyHours * $weeks;
            $totalHours += $memberHours;

            // Group by role
            $roleCode = $member->staffRole?->code ?? 'OTHER';
            $roleName = $member->staffRole?->name ?? 'Other';

            if (!isset($byRole[$roleCode])) {
                $byRole[$roleCode] = [
                    'role_code' => $roleCode,
                    'role_name' => $roleName,
                    'staff_count' => 0,
                    'total_hours' => 0,
                    'weekly_hours' => 0,
                ];
            }

            $byRole[$roleCode]['staff_count']++;
            $byRole[$roleCode]['total_hours'] += $memberHours;
            $byRole[$roleCode]['weekly_hours'] += $weeklyHours;

            // Update staff counts
            $empTypeCode = $member->employmentTypeModel?->code ?? 'FT';
            if ($empTypeCode === EmploymentType::CODE_FULL_TIME) {
                $staffCount['full_time']++;
            } elseif ($empTypeCode === EmploymentType::CODE_PART_TIME) {
                $staffCount['part_time']++;
            } elseif ($empTypeCode === EmploymentType::CODE_CASUAL) {
                $staffCount['casual']++;
            } elseif ($empTypeCode === EmploymentType::CODE_SSPO) {
                $staffCount['sspo']++;
            }
        }

        return [
            'total_hours' => $totalHours,
            'by_role' => array_values($byRole),
            'staff_count' => $staffCount,
        ];
    }

    /**
     * Get required care hours from active care bundles.
     */
    public function getRequiredCare(
        int $organizationId,
        Carbon $startDate,
        Carbon $endDate,
        ?string $providerType = null
    ): array {
        // Get requirements using the planner
        $requirements = $this->planner->getUnscheduledRequirements(
            $organizationId,
            $startDate,
            $endDate
        );

        $byService = [];
        $totalHours = 0;
        $totalVisits = 0;

        // Get all active patients with care plans
        $patientCount = Patient::where('status', 'Active')
            ->whereHas('carePlans', fn($q) => $q->where('status', 'active'))
            ->count();

        foreach ($requirements as $dto) {
            foreach ($dto->services as $service) {
                $serviceTypeId = $service->serviceTypeId;
                $serviceTypeName = $service->serviceTypeName;

                // Get the service type for preferred_provider filtering
                $serviceType = ServiceType::find($serviceTypeId);

                // Filter by provider type if specified
                if ($providerType === 'spo' && $serviceType?->isSspoOwned()) {
                    continue; // Skip SSPO services when filtering for SPO
                }
                if ($providerType === 'sspo' && $serviceType?->isSpoOwned()) {
                    continue; // Skip SPO services when filtering for SSPO
                }

                if (!isset($byService[$serviceTypeId])) {
                    $byService[$serviceTypeId] = [
                        'service_type_id' => $serviceTypeId,
                        'service_type_name' => $serviceTypeName,
                        'category' => $service->category,
                        'total_hours' => 0,
                        'total_visits' => 0,
                        'patient_count' => 0,
                    ];
                }

                // Calculate hours and visits
                if ($service->unitType === 'hours') {
                    $byService[$serviceTypeId]['total_hours'] += $service->required;
                    $totalHours += $service->required;
                } else {
                    $byService[$serviceTypeId]['total_visits'] += $service->required;

                    // Estimate hours from visits (using service type default duration)
                    $durationMinutes = $serviceType?->default_duration_minutes ?? 60;
                    $estimatedHours = ($service->required * $durationMinutes) / 60;
                    $byService[$serviceTypeId]['total_hours'] += $estimatedHours;
                    $totalHours += $estimatedHours;
                }

                $byService[$serviceTypeId]['patient_count']++;
            }
        }

        return [
            'total_hours' => $totalHours,
            'total_visits' => $totalVisits,
            'by_service' => array_values($byService),
            'patient_count' => $patientCount,
            'patients_with_needs' => $requirements->count(),
        ];
    }

    /**
     * Get currently scheduled hours.
     */
    public function getScheduledHours(
        int $organizationId,
        Carbon $startDate,
        Carbon $endDate,
        ?string $providerType = null
    ): array {
        $query = ServiceAssignment::where('service_provider_organization_id', $organizationId)
            ->whereBetween('scheduled_start', [$startDate, $endDate])
            ->whereNotIn('status', [
                ServiceAssignment::STATUS_CANCELLED,
                ServiceAssignment::STATUS_MISSED,
            ])
            ->with('serviceType');

        // Filter by provider type
        if ($providerType === 'spo') {
            $query->where('source', ServiceAssignment::SOURCE_INTERNAL);
        } elseif ($providerType === 'sspo') {
            $query->where('source', ServiceAssignment::SOURCE_SSPO);
        }

        $assignments = $query->get();

        $byService = [];
        $totalHours = 0;

        foreach ($assignments as $assignment) {
            $serviceTypeId = $assignment->service_type_id;
            $serviceTypeName = $assignment->serviceType?->name ?? 'Unknown';

            if (!isset($byService[$serviceTypeId])) {
                $byService[$serviceTypeId] = [
                    'service_type_id' => $serviceTypeId,
                    'service_type_name' => $serviceTypeName,
                    'total_hours' => 0,
                    'assignment_count' => 0,
                ];
            }

            // Calculate hours
            $hours = 0;
            if ($assignment->scheduled_start && $assignment->scheduled_end) {
                $hours = $assignment->scheduled_start->diffInMinutes($assignment->scheduled_end) / 60;
            } elseif ($assignment->duration_minutes) {
                $hours = $assignment->duration_minutes / 60;
            } else {
                $hours = 1; // Default 1 hour
            }

            $byService[$serviceTypeId]['total_hours'] += $hours;
            $byService[$serviceTypeId]['assignment_count']++;
            $totalHours += $hours;
        }

        return [
            'total_hours' => $totalHours,
            'by_service' => array_values($byService),
            'assignment_count' => $assignments->count(),
        ];
    }

    /**
     * Calculate travel overhead.
     */
    public function calculateTravelOverhead(
        int $organizationId,
        Carbon $startDate,
        Carbon $endDate,
        ?string $providerType = null
    ): array {
        // Count scheduled visits
        $query = ServiceAssignment::where('service_provider_organization_id', $organizationId)
            ->whereBetween('scheduled_start', [$startDate, $endDate])
            ->whereNotIn('status', [
                ServiceAssignment::STATUS_CANCELLED,
                ServiceAssignment::STATUS_MISSED,
            ]);

        if ($providerType === 'spo') {
            $query->where('source', ServiceAssignment::SOURCE_INTERNAL);
        } elseif ($providerType === 'sspo') {
            $query->where('source', ServiceAssignment::SOURCE_SSPO);
        }

        $visitCount = $query->count();

        // Calculate total travel time using default or actual
        // In a full implementation, we would pull actual travel times from visits
        $travelMinutes = $visitCount * self::DEFAULT_TRAVEL_MINUTES_PER_VISIT;
        $travelHours = $travelMinutes / 60;

        return [
            'total_hours' => $travelHours,
            'total_minutes' => $travelMinutes,
            'visit_count' => $visitCount,
            'avg_travel_per_visit_minutes' => self::DEFAULT_TRAVEL_MINUTES_PER_VISIT,
        ];
    }

    /**
     * Get capacity status based on net capacity.
     */
    protected function getCapacityStatus(float $netCapacity): string
    {
        if ($netCapacity >= 40) {
            return 'GREEN'; // Healthy surplus
        } elseif ($netCapacity >= 0) {
            return 'YELLOW'; // Tight but manageable
        }
        return 'RED'; // Over capacity
    }

    /**
     * Get capacity forecast for upcoming weeks.
     *
     * Projects capacity based on:
     * - Current staff availability
     * - Expected patient discharge/intake
     * - Historical patterns
     *
     * @param int $organizationId
     * @param int $weeksAhead
     * @param string|null $providerType
     * @return array Flat array of weekly forecasts
     */
    public function getCapacityForecast(
        int $organizationId,
        int $weeksAhead = 4,
        ?string $providerType = null
    ): array {
        $forecast = [];
        $currentWeekStart = Carbon::now()->startOfWeek();

        for ($i = 0; $i < $weeksAhead; $i++) {
            $weekStart = $currentWeekStart->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();

            $snapshot = $this->getCapacitySnapshot(
                $organizationId,
                $weekStart,
                $weekEnd,
                $providerType
            );

            $forecast[] = [
                'week_start' => $weekStart->toDateString(),
                'week_label' => $weekStart->format('M d'),
                'summary' => $snapshot['summary'],
            ];
        }

        return $forecast;
    }

    /**
     * Get capacity by provider type (SPO vs SSPO comparison).
     */
    public function getCapacityByProviderType(
        int $organizationId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $spoCapacity = $this->getCapacitySnapshot(
            $organizationId,
            $startDate,
            $endDate,
            'spo'
        );

        $sspoCapacity = $this->getCapacitySnapshot(
            $organizationId,
            $startDate,
            $endDate,
            'sspo'
        );

        $totalCapacity = $this->getCapacitySnapshot(
            $organizationId,
            $startDate,
            $endDate,
            null
        );

        return [
            'organization_id' => $organizationId,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'spo' => [
                'summary' => $spoCapacity['summary'],
                'staff_count' => $spoCapacity['staff_count'],
            ],
            'sspo' => [
                'summary' => $sspoCapacity['summary'],
                'staff_count' => $sspoCapacity['staff_count'],
            ],
            'total' => [
                'summary' => $totalCapacity['summary'],
                'staff_count' => $totalCapacity['staff_count'],
            ],
            'spo_share' => $totalCapacity['summary']['available_hours'] > 0
                ? round(($spoCapacity['summary']['available_hours'] / $totalCapacity['summary']['available_hours']) * 100, 1)
                : 0,
            'sspo_share' => $totalCapacity['summary']['available_hours'] > 0
                ? round(($sspoCapacity['summary']['available_hours'] / $totalCapacity['summary']['available_hours']) * 100, 1)
                : 0,
        ];
    }

    /**
     * Return empty snapshot for missing data.
     */
    protected function emptySnapshot(): array
    {
        return [
            'organization_id' => null,
            'period' => null,
            'provider_type' => null,
            'calculated_at' => Carbon::now()->toIso8601String(),
            'summary' => [
                'available_hours' => 0,
                'required_hours' => 0,
                'scheduled_hours' => 0,
                'travel_overhead' => 0,
                'net_capacity' => 0,
                'remaining_capacity' => 0,
                'utilization_rate' => 0,
                'coverage_rate' => 0,
                'status' => 'GREY',
            ],
            'available_by_role' => [],
            'required_by_service' => [],
            'scheduled_by_service' => [],
            'staff_count' => ['total' => 0, 'full_time' => 0, 'part_time' => 0, 'casual' => 0, 'sspo' => 0],
            'patient_count' => 0,
            'patients_with_unscheduled_needs' => 0,
        ];
    }
}
