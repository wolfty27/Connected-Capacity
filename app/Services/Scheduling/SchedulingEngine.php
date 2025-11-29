<?php

namespace App\Services\Scheduling;

use App\DTOs\SchedulingValidationResultDTO;
use App\DTOs\TravelValidationResultDTO;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceRoleMapping;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\User;
use App\Services\Travel\TravelTimeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * SchedulingEngine
 *
 * Domain service for staff scheduling operations:
 * - Finding eligible staff for a service type
 * - Validating assignment constraints (role, availability, capacity, conflicts)
 * - Detecting scheduling conflicts
 * - Travel-aware scheduling validation
 *
 * All business logic is metadata-driven via ServiceRoleMapping, StaffAvailability,
 * and RegionArea lookups. No hardcoded region or travel time logic.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class SchedulingEngine
{
    /**
     * Default capacity warning threshold (80%).
     */
    protected float $capacityWarningThreshold = 0.80;

    /**
     * Default buffer time in minutes between appointments.
     */
    protected int $defaultBufferMinutes = 5;

    /**
     * Buffer time per service category (configurable via metadata in future).
     */
    protected array $serviceBuffers = [
        'nursing' => 10,
        'personal_support' => 5,
        'homemaking' => 5,
        'therapy' => 10,
        'behaviour' => 10,
        'default' => 5,
    ];

    /**
     * TravelTimeService instance (lazily loaded).
     */
    protected ?TravelTimeService $travelTimeService = null;

    /**
     * Get staff eligible to provide a service at a given time.
     *
     * Filters by:
     * 1. Role eligibility (ServiceRoleMapping)
     * 2. Availability at the requested time (StaffAvailability)
     * 3. Capacity constraints (weekly hours)
     *
     * @param ServiceType $serviceType
     * @param Carbon $dateTime
     * @param int $durationMinutes
     * @param int|null $organizationId Filter by organization
     * @return Collection<User>
     */
    public function getEligibleStaff(
        ServiceType $serviceType,
        Carbon $dateTime,
        int $durationMinutes,
        ?int $organizationId = null
    ): Collection {
        // Get role IDs eligible for this service type
        $eligibleRoleIds = ServiceRoleMapping::active()
            ->where('service_type_id', $serviceType->id)
            ->pluck('staff_role_id')
            ->toArray();

        if (empty($eligibleRoleIds)) {
            return collect();
        }

        // Get week range for capacity calculation
        $weekStart = $dateTime->copy()->startOfWeek();
        $weekEnd = $dateTime->copy()->endOfWeek();

        // Query staff with matching roles - include field staff and coordinators
        $query = User::query()
            ->whereIn('role', [
                User::ROLE_FIELD_STAFF,
                User::ROLE_SPO_COORDINATOR,
                User::ROLE_SSPO_COORDINATOR,
            ])
            ->where('staff_status', User::STAFF_STATUS_ACTIVE)
            ->whereIn('staff_role_id', $eligibleRoleIds)
            ->with(['staffRole', 'employmentTypeModel', 'availabilities']);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $staff = $query->get();

        // Filter by availability and capacity
        return $staff->filter(function ($user) use ($dateTime, $durationMinutes, $weekStart, $weekEnd) {
            // Check availability for the requested day and time
            if (!$this->isStaffAvailable($user, $dateTime, $durationMinutes)) {
                return false;
            }

            // Check capacity (optional - could be warning instead)
            $scheduledHours = $this->getScheduledHoursForWeek($user->id, $weekStart, $weekEnd);
            $maxHours = $user->max_weekly_hours ?? 40;
            $remainingMinutes = ($maxHours * 60) - ($scheduledHours * 60);

            return $remainingMinutes >= $durationMinutes;
        })->values();
    }

    /**
     * Check if a staff member is available at a given time.
     */
    public function isStaffAvailable(User $user, Carbon $dateTime, int $durationMinutes): bool
    {
        $dayOfWeek = $dateTime->dayOfWeek;
        $startTime = $dateTime->format('H:i:s');
        $endTime = $dateTime->copy()->addMinutes($durationMinutes)->format('H:i:s');

        $availability = StaffAvailability::where('user_id', $user->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('effective_from', '<=', $dateTime->toDateString())
            ->where(function ($q) use ($dateTime) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', $dateTime->toDateString());
            })
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->first();

        return $availability !== null;
    }

    /**
     * Check for scheduling conflicts with existing assignments (staff).
     */
    public function hasConflicts(
        int $staffId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeAssignmentId = null
    ): bool {
        $query = ServiceAssignment::where('assigned_user_id', $staffId)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED, ServiceAssignment::STATUS_MISSED])
            ->where(function ($q) use ($startTime, $endTime) {
                // Check for overlapping time ranges
                $q->where(function ($inner) use ($startTime, $endTime) {
                    $inner->where('scheduled_start', '<', $endTime)
                          ->where('scheduled_end', '>', $startTime);
                });
            });

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        return $query->exists();
    }

    /**
     * Check for patient scheduling conflicts (patient non-concurrency).
     *
     * Ensures a patient cannot have multiple providers at the same time,
     * unless the assignment is explicitly marked as allowing concurrent care.
     *
     * @param int $patientId The patient's ID
     * @param Carbon $startTime Proposed start time
     * @param Carbon $endTime Proposed end time
     * @param int|null $excludeAssignmentId Assignment ID to exclude (for updates)
     * @return bool True if there are conflicts
     */
    public function hasPatientConflicts(
        int $patientId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeAssignmentId = null
    ): bool {
        $query = ServiceAssignment::where('patient_id', $patientId)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED, ServiceAssignment::STATUS_MISSED])
            ->where(function ($q) use ($startTime, $endTime) {
                // Check for overlapping time ranges
                $q->where('scheduled_start', '<', $endTime)
                  ->where('scheduled_end', '>', $startTime);
            });

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        return $query->exists();
    }

    /**
     * Get patient's conflicting assignments.
     *
     * @param int $patientId The patient's ID
     * @param Carbon $startTime Proposed start time
     * @param Carbon $endTime Proposed end time
     * @param int|null $excludeAssignmentId Assignment ID to exclude
     * @return Collection<ServiceAssignment>
     */
    public function getPatientConflicts(
        int $patientId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeAssignmentId = null
    ): Collection {
        $query = ServiceAssignment::where('patient_id', $patientId)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED, ServiceAssignment::STATUS_MISSED])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('scheduled_start', '<', $endTime)
                  ->where('scheduled_end', '>', $startTime);
            })
            ->with(['assignedUser', 'serviceType']);

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        return $query->get();
    }

    /**
     * Check if a patient has any overlapping assignments during a time range.
     *
     * This is a simpler version of hasPatientConflicts() that returns a boolean
     * for use in validation flows.
     *
     * @param int $patientId The patient's ID
     * @param Carbon $start Proposed start time
     * @param Carbon $end Proposed end time
     * @param int|null $ignoreAssignmentId Assignment ID to exclude (for updates)
     * @return bool True if the patient has overlapping assignments
     */
    public function patientHasOverlap(
        int $patientId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreAssignmentId = null
    ): bool {
        return $this->hasPatientConflicts($patientId, $start, $end, $ignoreAssignmentId);
    }

    /**
     * Check if scheduling a service violates the minimum gap rule.
     *
     * For services with min_gap_between_visits_minutes defined, this ensures
     * sufficient time has elapsed since the last visit of the same service type.
     *
     * Example: PSW visits require 120 min gap, so if last PSW ended at 09:00,
     * next PSW cannot start before 11:00 (09:00 + 120 min).
     *
     * @param int $patientId The patient's ID
     * @param int $serviceTypeId The service type ID
     * @param Carbon $start Proposed start time
     * @param int|null $ignoreAssignmentId Assignment ID to exclude (for updates)
     * @return string|null Error message if violated, null if valid
     */
    public function checkSpacingRule(
        int $patientId,
        int $serviceTypeId,
        Carbon $start,
        ?int $ignoreAssignmentId = null
    ): ?string {
        // Get the service type to check for min_gap requirement
        $serviceType = ServiceType::find($serviceTypeId);
        if (!$serviceType || !$serviceType->min_gap_between_visits_minutes) {
            return null; // No spacing rule for this service type
        }

        $minGapMinutes = $serviceType->min_gap_between_visits_minutes;

        // Find the most recent previous assignment of the same service type
        // that ended before the proposed start time
        $previousAssignment = ServiceAssignment::where('patient_id', $patientId)
            ->where('service_type_id', $serviceTypeId)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED, ServiceAssignment::STATUS_MISSED])
            ->where('scheduled_end', '<=', $start)
            ->orderBy('scheduled_end', 'desc');

        if ($ignoreAssignmentId) {
            $previousAssignment->where('id', '!=', $ignoreAssignmentId);
        }

        $previous = $previousAssignment->first();

        if ($previous) {
            $actualGapMinutes = $previous->scheduled_end->diffInMinutes($start);

            if ($actualGapMinutes < $minGapMinutes) {
                $earliestAllowed = $previous->scheduled_end->copy()->addMinutes($minGapMinutes);
                return sprintf(
                    'Spacing rule violated: %s visits require %d min gap. Last visit ended at %s, earliest next visit is %s (gap is %d min, need %d min)',
                    $serviceType->name,
                    $minGapMinutes,
                    $previous->scheduled_end->format('H:i'),
                    $earliestAllowed->format('H:i'),
                    $actualGapMinutes,
                    $minGapMinutes
                );
            }
        }

        return null; // Valid - no previous assignment or sufficient gap
    }

    /**
     * Get conflicting assignments.
     */
    public function getConflicts(
        int $staffId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeAssignmentId = null
    ): Collection {
        $query = ServiceAssignment::where('assigned_user_id', $staffId)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED, ServiceAssignment::STATUS_MISSED])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('scheduled_start', '<', $endTime)
                  ->where('scheduled_end', '>', $startTime);
            })
            ->with(['patient.user', 'serviceType']);

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        return $query->get();
    }

    /**
     * Validate a service assignment.
     *
     * Checks:
     * 1. Staff role is eligible for service type
     * 2. Staff is available at the scheduled time
     * 3. No conflicting assignments
     * 4. Capacity constraints (warning if over threshold)
     */
    public function validateAssignment(ServiceAssignment $assignment): SchedulingValidationResultDTO
    {
        $errors = [];
        $warnings = [];

        $staff = $assignment->assignedUser;
        $serviceType = $assignment->serviceType;
        $startTime = $assignment->scheduled_start;
        $endTime = $assignment->scheduled_end;

        if (!$staff) {
            $errors[] = 'Staff member not found';
            return SchedulingValidationResultDTO::invalid($errors);
        }

        if (!$serviceType) {
            $errors[] = 'Service type not found';
            return SchedulingValidationResultDTO::invalid($errors);
        }

        // 1. Check role eligibility
        if ($staff->staff_role_id) {
            $isEligible = ServiceRoleMapping::active()
                ->where('staff_role_id', $staff->staff_role_id)
                ->where('service_type_id', $serviceType->id)
                ->exists();

            if (!$isEligible) {
                $roleName = $staff->staffRole?->name ?? 'Unknown';
                $errors[] = "Staff role '{$roleName}' is not eligible to provide '{$serviceType->name}'";
            }
        }

        // 2. Check availability
        $durationMinutes = $startTime->diffInMinutes($endTime);
        if (!$this->isStaffAvailable($staff, $startTime, $durationMinutes)) {
            $warnings[] = "Staff is not available at the scheduled time (check their availability settings)";
        }

        // 3. Check for conflicts
        $conflicts = $this->getConflicts(
            $staff->id,
            $startTime,
            $endTime,
            $assignment->exists ? $assignment->id : null
        );

        if ($conflicts->isNotEmpty()) {
            foreach ($conflicts as $conflict) {
                $patientName = $conflict->patient?->user?->name ?? 'Unknown';
                $serviceName = $conflict->serviceType?->name ?? 'Unknown';
                $conflictTime = $conflict->scheduled_start->format('H:i');
                $errors[] = "Conflicts with existing assignment: {$serviceName} for {$patientName} at {$conflictTime}";
            }
        }

        // 4. Check capacity (warning only)
        $weekStart = $startTime->copy()->startOfWeek();
        $weekEnd = $startTime->copy()->endOfWeek();
        $scheduledHours = $this->getScheduledHoursForWeek(
            $staff->id,
            $weekStart,
            $weekEnd,
            $assignment->exists ? $assignment->id : null
        );
        $maxHours = $staff->max_weekly_hours ?? 40;
        $newHours = $durationMinutes / 60;
        $totalHours = $scheduledHours + $newHours;
        $utilization = $maxHours > 0 ? ($totalHours / $maxHours) : 0;

        if ($utilization > 1) {
            $warnings[] = "Staff will exceed weekly capacity ({$totalHours}h / {$maxHours}h)";
        } elseif ($utilization >= $this->capacityWarningThreshold) {
            $pct = round($utilization * 100);
            $warnings[] = "Staff is at {$pct}% of weekly capacity";
        }

        if (!empty($errors)) {
            return SchedulingValidationResultDTO::invalid($errors, $warnings);
        }

        return SchedulingValidationResultDTO::valid($warnings);
    }

    /**
     * Get scheduled hours for a staff member in a week.
     */
    public function getScheduledHoursForWeek(
        int $staffId,
        Carbon $weekStart,
        Carbon $weekEnd,
        ?int $excludeAssignmentId = null
    ): float {
        $query = ServiceAssignment::where('assigned_user_id', $staffId)
            ->whereBetween('scheduled_start', [$weekStart, $weekEnd])
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED, ServiceAssignment::STATUS_MISSED]);

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        $totalMinutes = $query->get()->sum(function ($a) {
            if ($a->duration_minutes) {
                return $a->duration_minutes;
            }
            if ($a->scheduled_start && $a->scheduled_end) {
                return $a->scheduled_start->diffInMinutes($a->scheduled_end);
            }
            return 60; // Default to 1 hour
        });

        return round($totalMinutes / 60, 2);
    }

    /**
     * Get staff utilization for a week.
     */
    public function getStaffUtilization(
        int $staffId,
        Carbon $weekStart,
        Carbon $weekEnd
    ): array {
        $user = User::with('employmentTypeModel')->find($staffId);
        if (!$user) {
            return ['scheduled' => 0, 'capacity' => 0, 'utilization' => 0];
        }

        $scheduled = $this->getScheduledHoursForWeek($staffId, $weekStart, $weekEnd);
        $capacity = $user->max_weekly_hours ?? $user->employmentTypeModel?->standard_hours_per_week ?? 40;
        $utilization = $capacity > 0 ? round(($scheduled / $capacity) * 100, 1) : 0;

        return [
            'scheduled' => $scheduled,
            'capacity' => $capacity,
            'utilization' => min(100, $utilization),
        ];
    }

    /**
     * Get grid data for scheduling dashboard.
     *
     * @param int|null $organizationId
     * @param Carbon $weekStart
     * @param Carbon $weekEnd
     * @param int|null $staffId Filter to specific staff
     * @param int|null $patientId Filter assignments to specific patient
     * @return array
     */
    public function getGridData(
        ?int $organizationId,
        Carbon $weekStart,
        Carbon $weekEnd,
        ?int $staffId = null,
        ?int $patientId = null
    ): array {
        // Get staff - include field staff and coordinators who may deliver care
        $staffQuery = User::query()
            ->whereIn('role', [
                User::ROLE_FIELD_STAFF,
                User::ROLE_SPO_COORDINATOR,
                User::ROLE_SSPO_COORDINATOR,
            ])
            ->where('staff_status', User::STAFF_STATUS_ACTIVE)
            ->with(['staffRole', 'employmentTypeModel', 'availabilities']);

        // When filtering by patient_id, find staff who have assignments for that patient in this week
        if ($patientId) {
            $staffIdsWithPatientAssignments = ServiceAssignment::query()
                ->where('patient_id', $patientId)
                ->whereBetween('scheduled_start', [$weekStart, $weekEnd])
                ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED])
                ->pluck('assigned_user_id')
                ->unique()
                ->toArray();

            // If there are staff with assignments for this patient, filter to them
            // Otherwise, show all staff so user can assign care
            if (!empty($staffIdsWithPatientAssignments)) {
                $staffQuery->whereIn('id', $staffIdsWithPatientAssignments);
            }
        }

        // When filtering by staff_id, prioritize that filter over org filter
        if ($staffId) {
            $staffQuery->where('id', $staffId);
        } elseif ($organizationId) {
            // Only apply org filter if not filtering by specific staff
            $staffQuery->where('organization_id', $organizationId);
        }

        $staff = $staffQuery->orderBy('name')->get();

        // Get assignments
        $assignmentQuery = ServiceAssignment::query()
            ->whereBetween('scheduled_start', [$weekStart, $weekEnd])
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED])
            ->with(['patient.user', 'serviceType', 'assignedUser']);

        // Filter assignments to match the staff we're showing
        if ($staffId) {
            $assignmentQuery->where('assigned_user_id', $staffId);
        } elseif ($organizationId && !$patientId) {
            // Only apply org filter if not filtering by patient
            $assignmentQuery->where('service_provider_organization_id', $organizationId);
        }

        if ($patientId) {
            $assignmentQuery->where('patient_id', $patientId);
        }

        $assignments = $assignmentQuery->orderBy('scheduled_start')->get();

        // Transform staff for response
        $staffData = $staff->map(function ($user) use ($weekStart, $weekEnd) {
            $utilization = $this->getStaffUtilization($user->id, $weekStart, $weekEnd);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->staffRole ? [
                    'id' => $user->staffRole->id,
                    'code' => $user->staffRole->code,
                    'name' => $user->staffRole->name,
                    'category' => $user->staffRole->category,
                ] : null,
                'employment_type' => $user->employmentTypeModel ? [
                    'id' => $user->employmentTypeModel->id,
                    'code' => $user->employmentTypeModel->code,
                    'name' => $user->employmentTypeModel->name,
                ] : null,
                'organization_id' => $user->organization_id,
                'weekly_capacity_hours' => $user->max_weekly_hours ?? 40,
                'status' => $user->staff_status,
                'utilization' => $utilization,
                'availability' => $user->availabilities->map(fn($a) => [
                    'day_of_week' => $a->day_of_week,
                    'start_time' => Carbon::parse($a->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($a->end_time)->format('H:i'),
                ]),
            ];
        });

        // Transform assignments for response
        $assignmentData = $assignments->map(function ($a) {
            return [
                'id' => $a->id,
                'staff_id' => $a->assigned_user_id,
                'patient_id' => $a->patient_id,
                'patient_name' => $a->patient?->user?->name ?? 'Unknown',
                'service_type_id' => $a->service_type_id,
                'service_type_name' => $a->serviceType?->name ?? 'Unknown',
                'category' => $a->serviceType?->category ?? 'other',
                'color' => $this->getCategoryColor($a->serviceType?->category ?? 'other'),
                'date' => $a->scheduled_start->toDateString(),
                'start_time' => $a->scheduled_start->format('H:i'),
                'end_time' => $a->scheduled_end?->format('H:i') ?? $a->scheduled_start->addHour()->format('H:i'),
                'duration_minutes' => $a->duration_minutes ?? ($a->scheduled_end ? $a->scheduled_start->diffInMinutes($a->scheduled_end) : 60),
                'status' => $a->status,
                'verification_status' => $a->verification_status,
            ];
        });

        return [
            'staff' => $staffData,
            'assignments' => $assignmentData,
            'week' => [
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd->toDateString(),
            ],
        ];
    }

    /**
     * Get color for a service category.
     */
    protected function getCategoryColor(string $category): string
    {
        return match (strtolower($category)) {
            'nursing' => '#DBEAFE',
            'psw', 'personal_support' => '#D1FAE5',
            'homemaking' => '#FEF3C7',
            'behaviour', 'behavioral' => '#FEE2E2',
            'rehab', 'therapy' => '#E9D5FF',
            default => '#F3F4F6',
        };
    }

    // =========================================================================
    // TRAVEL-AWARE SCHEDULING
    // =========================================================================

    /**
     * Get the TravelTimeService instance (lazily loaded).
     */
    protected function getTravelTimeService(): TravelTimeService
    {
        if ($this->travelTimeService === null) {
            $this->travelTimeService = app(TravelTimeService::class);
        }
        return $this->travelTimeService;
    }

    /**
     * Set a custom TravelTimeService (useful for testing).
     */
    public function setTravelTimeService(TravelTimeService $service): self
    {
        $this->travelTimeService = $service;
        return $this;
    }

    /**
     * Validate an assignment considering travel time constraints.
     *
     * Checks:
     * 1. No direct time overlap with other assignments
     * 2. Sufficient travel time from previous assignment
     * 3. Sufficient travel time to next assignment
     *
     * @param User $staff The staff member
     * @param Patient $patient The patient to visit
     * @param Carbon $start Proposed start time
     * @param Carbon $end Proposed end time
     * @param int|null $serviceTypeId Service type for buffer calculation
     * @param int|null $excludeAssignmentId Assignment ID to exclude (for updates)
     * @return TravelValidationResultDTO
     */
    public function canAssignWithTravel(
        User $staff,
        Patient $patient,
        Carbon $start,
        Carbon $end,
        ?int $serviceTypeId = null,
        ?int $excludeAssignmentId = null
    ): TravelValidationResultDTO {
        $errors = [];
        $warnings = [];
        $travelFromPrevious = null;
        $travelToNext = null;
        $earliestStart = null;
        $latestEnd = null;

        // 0. Check patient non-concurrency FIRST (patient cannot have multiple providers at same time)
        if ($this->patientHasOverlap($patient->id, $start, $end, $excludeAssignmentId)) {
            $errors[] = 'Patient already has another visit scheduled during that time.';
            return TravelValidationResultDTO::invalid($errors, $warnings);
        }

        // 0b. Check spacing rule for repeated services (e.g., PSW visits need 120 min gap)
        if ($serviceTypeId) {
            $spacingError = $this->checkSpacingRule($patient->id, $serviceTypeId, $start, $excludeAssignmentId);
            if ($spacingError) {
                $errors[] = $spacingError;
                return TravelValidationResultDTO::invalid($errors, $warnings);
            }
        }

        // Check if patient has coordinates
        if (!$patient->hasCoordinates()) {
            $warnings[] = 'Patient location unknown - travel time cannot be calculated';
            // Allow assignment but warn
        }

        // 1. Check for direct staff time overlap
        if ($this->hasConflicts($staff->id, $start, $end, $excludeAssignmentId)) {
            $conflicts = $this->getConflicts($staff->id, $start, $end, $excludeAssignmentId);
            foreach ($conflicts as $conflict) {
                $patientName = $conflict->patient?->user?->name ?? 'Unknown';
                $conflictTime = $conflict->scheduled_start->format('H:i');
                $errors[] = "Staff conflict: already assigned to {$patientName} at {$conflictTime}";
            }
            return TravelValidationResultDTO::invalid($errors, $warnings);
        }

        // Skip travel checks if patient has no coordinates
        if (!$patient->hasCoordinates()) {
            return TravelValidationResultDTO::valid($warnings);
        }

        // 2. Check travel from previous assignment
        $previous = $this->getPreviousAssignment($staff->id, $start, $excludeAssignmentId);
        if ($previous && $previous->patient && $previous->patient->hasCoordinates()) {
            $bufferPrev = $this->getBufferMinutes($previous->service_type_id);
            $departPrev = $previous->scheduled_end->copy()->addMinutes($bufferPrev);

            $travelFromPrevious = $this->getTravelTimeService()->getTravelMinutes(
                $previous->patient->lat,
                $previous->patient->lng,
                $patient->lat,
                $patient->lng,
                $departPrev
            );

            $earliestStart = $departPrev->copy()->addMinutes($travelFromPrevious);

            if ($start->lt($earliestStart)) {
                $errors[] = sprintf(
                    'Cannot start at %s - earliest arrival is %s (need %d min travel + %d min buffer from previous patient)',
                    $start->format('H:i'),
                    $earliestStart->format('H:i'),
                    $travelFromPrevious,
                    $bufferPrev
                );
            }
        }

        // 3. Check travel to next assignment
        $next = $this->getNextAssignment($staff->id, $end, $excludeAssignmentId);
        if ($next && $next->patient && $next->patient->hasCoordinates()) {
            $bufferCurrent = $this->getBufferMinutes($serviceTypeId);
            $departCurrent = $end->copy()->addMinutes($bufferCurrent);

            $travelToNext = $this->getTravelTimeService()->getTravelMinutes(
                $patient->lat,
                $patient->lng,
                $next->patient->lat,
                $next->patient->lng,
                $departCurrent
            );

            // Buffer for next appointment
            $bufferNext = $this->getBufferMinutes($next->service_type_id);

            // Latest end = next.start - travel - buffer_next - buffer_current
            $latestEnd = $next->scheduled_start
                ->copy()
                ->subMinutes($travelToNext)
                ->subMinutes($bufferNext);

            if ($end->gt($latestEnd)) {
                $errors[] = sprintf(
                    'Must end by %s to reach next patient by %s (need %d min travel + buffers)',
                    $latestEnd->format('H:i'),
                    $next->scheduled_start->format('H:i'),
                    $travelToNext
                );
            }
        }

        if (!empty($errors)) {
            return TravelValidationResultDTO::invalid(
                $errors,
                $warnings,
                $earliestStart,
                $latestEnd,
                $travelFromPrevious,
                $travelToNext
            );
        }

        return TravelValidationResultDTO::valid(
            $warnings,
            $earliestStart,
            $latestEnd,
            $travelFromPrevious,
            $travelToNext
        );
    }

    /**
     * Get the previous assignment for a staff member before a given time.
     *
     * @param int $staffId Staff member ID
     * @param Carbon $beforeTime Time to look before
     * @param int|null $excludeAssignmentId Assignment to exclude
     * @return ServiceAssignment|null
     */
    public function getPreviousAssignment(
        int $staffId,
        Carbon $beforeTime,
        ?int $excludeAssignmentId = null
    ): ?ServiceAssignment {
        $query = ServiceAssignment::where('assigned_user_id', $staffId)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED, ServiceAssignment::STATUS_MISSED])
            ->where('scheduled_end', '<=', $beforeTime)
            ->whereDate('scheduled_start', $beforeTime->toDateString())
            ->with(['patient', 'serviceType'])
            ->orderBy('scheduled_end', 'desc');

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        return $query->first();
    }

    /**
     * Get the next assignment for a staff member after a given time.
     *
     * @param int $staffId Staff member ID
     * @param Carbon $afterTime Time to look after
     * @param int|null $excludeAssignmentId Assignment to exclude
     * @return ServiceAssignment|null
     */
    public function getNextAssignment(
        int $staffId,
        Carbon $afterTime,
        ?int $excludeAssignmentId = null
    ): ?ServiceAssignment {
        $query = ServiceAssignment::where('assigned_user_id', $staffId)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED, ServiceAssignment::STATUS_MISSED])
            ->where('scheduled_start', '>=', $afterTime)
            ->whereDate('scheduled_start', $afterTime->toDateString())
            ->with(['patient', 'serviceType'])
            ->orderBy('scheduled_start', 'asc');

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        return $query->first();
    }

    /**
     * Get buffer time in minutes for a service type.
     *
     * @param int|null $serviceTypeId Service type ID
     * @return int Buffer minutes
     */
    public function getBufferMinutes(?int $serviceTypeId): int
    {
        if (!$serviceTypeId) {
            return $this->defaultBufferMinutes;
        }

        $serviceType = ServiceType::find($serviceTypeId);
        if (!$serviceType) {
            return $this->defaultBufferMinutes;
        }

        $category = strtolower($serviceType->category ?? 'default');

        return $this->serviceBuffers[$category] ?? $this->defaultBufferMinutes;
    }

    /**
     * Calculate travel time between two patients.
     *
     * @param Patient $from Origin patient
     * @param Patient $to Destination patient
     * @param Carbon $departureTime When to depart
     * @return int|null Travel minutes, or null if coordinates unavailable
     */
    public function getTravelTimeBetweenPatients(
        Patient $from,
        Patient $to,
        Carbon $departureTime
    ): ?int {
        if (!$from->hasCoordinates() || !$to->hasCoordinates()) {
            return null;
        }

        return $this->getTravelTimeService()->getTravelMinutes(
            $from->lat,
            $from->lng,
            $to->lat,
            $to->lng,
            $departureTime
        );
    }

    /**
     * Get all assignments for a staff member on a given day, ordered by start time.
     *
     * @param int $staffId Staff member ID
     * @param Carbon $date The date
     * @param int|null $excludeAssignmentId Assignment to exclude
     * @return Collection<ServiceAssignment>
     */
    public function getAssignmentsForDay(
        int $staffId,
        Carbon $date,
        ?int $excludeAssignmentId = null
    ): Collection {
        $query = ServiceAssignment::where('assigned_user_id', $staffId)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED, ServiceAssignment::STATUS_MISSED])
            ->whereDate('scheduled_start', $date->toDateString())
            ->with(['patient', 'serviceType'])
            ->orderBy('scheduled_start', 'asc');

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        return $query->get();
    }

    /**
     * Find available time slots for a staff member on a given day.
     *
     * Returns gaps between assignments that could accommodate a new visit,
     * considering travel time and buffers.
     *
     * @param User $staff Staff member
     * @param Carbon $date The date to check
     * @param Patient $patient Patient to visit
     * @param int $durationMinutes Required duration
     * @param int|null $serviceTypeId Service type (for buffer)
     * @return array Array of ['start' => Carbon, 'end' => Carbon] available slots
     */
    public function findAvailableSlots(
        User $staff,
        Carbon $date,
        Patient $patient,
        int $durationMinutes,
        ?int $serviceTypeId = null
    ): array {
        if (!$patient->hasCoordinates()) {
            return [];
        }

        // Get availability window for this day
        $availability = StaffAvailability::where('user_id', $staff->id)
            ->where('day_of_week', $date->dayOfWeek)
            ->where('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', $date->toDateString());
            })
            ->first();

        if (!$availability) {
            return [];
        }

        $dayStart = $date->copy()->setTimeFromTimeString($availability->start_time);
        $dayEnd = $date->copy()->setTimeFromTimeString($availability->end_time);

        // Get existing assignments
        $assignments = $this->getAssignmentsForDay($staff->id, $date);

        $slots = [];
        $currentTime = $dayStart->copy();
        $buffer = $this->getBufferMinutes($serviceTypeId);

        foreach ($assignments as $assignment) {
            if (!$assignment->patient || !$assignment->patient->hasCoordinates()) {
                continue;
            }

            // Calculate travel to this assignment's patient
            $travelTo = $this->getTravelTimeService()->getTravelMinutes(
                $patient->lat,
                $patient->lng,
                $assignment->patient->lat,
                $assignment->patient->lng,
                $currentTime
            );

            // Check if there's time for a visit before this assignment
            $latestEnd = $assignment->scheduled_start->copy()
                ->subMinutes($travelTo)
                ->subMinutes($buffer);

            if ($currentTime->copy()->addMinutes($durationMinutes)->lte($latestEnd)) {
                $slots[] = [
                    'start' => $currentTime->copy(),
                    'end' => $latestEnd->copy(),
                ];
            }

            // Move current time to after this assignment + buffer + travel from
            $assignmentBuffer = $this->getBufferMinutes($assignment->service_type_id);
            $travelFrom = $this->getTravelTimeService()->getTravelMinutes(
                $assignment->patient->lat,
                $assignment->patient->lng,
                $patient->lat,
                $patient->lng,
                $assignment->scheduled_end
            );
            $currentTime = $assignment->scheduled_end->copy()
                ->addMinutes($assignmentBuffer)
                ->addMinutes($travelFrom);
        }

        // Check for time after last assignment
        if ($currentTime->copy()->addMinutes($durationMinutes)->lte($dayEnd)) {
            $slots[] = [
                'start' => $currentTime->copy(),
                'end' => $dayEnd->copy(),
            ];
        }

        return $slots;
    }
}
