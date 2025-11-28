<?php

namespace App\Services\Scheduling;

use App\DTOs\SchedulingValidationResultDTO;
use App\Models\ServiceAssignment;
use App\Models\ServiceRoleMapping;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * SchedulingEngine
 *
 * Domain service for staff scheduling operations:
 * - Finding eligible staff for a service type
 * - Validating assignment constraints (role, availability, capacity, conflicts)
 * - Detecting scheduling conflicts
 *
 * All business logic is metadata-driven via ServiceRoleMapping and StaffAvailability.
 */
class SchedulingEngine
{
    /**
     * Default capacity warning threshold (80%).
     */
    protected float $capacityWarningThreshold = 0.80;

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
            ->with(['staffRole', 'employmentTypeModel', 'availability']);

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
     * Check for scheduling conflicts with existing assignments.
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
            ->with(['staffRole', 'employmentTypeModel', 'availability']);

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
                'availability' => $user->availability->map(fn($a) => [
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
}
