<?php

namespace App\Services;

use App\Models\EmploymentType;
use App\Models\Skill;
use App\Models\StaffAvailability;
use App\Models\StaffRole;
use App\Models\StaffUnavailability;
use App\Models\User;
use App\Services\CareOps\FteComplianceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Password;

/**
 * StaffProfileService
 * 
 * Aggregates staff profile data from multiple sources into a unified response.
 * This is the primary service for the Staff Profile page.
 * 
 * All labels, colors, and display values are computed here (not in React).
 */
class StaffProfileService
{
    public function __construct(
        protected StaffScheduleService $scheduleService,
        protected StaffSatisfactionService $satisfactionService,
        protected StaffTravelMetricsService $travelService,
        protected FteComplianceService $fteService
    ) {}

    /**
     * Get complete staff profile with all related data.
     */
    public function getProfile(int $staffUserId): ?array
    {
        $staff = User::with([
            'staffRole',
            'employmentTypeModel',
            'organization',
            'skills',
            'availabilities',
        ])->find($staffUserId);
        
        if (!$staff) {
            return null;
        }
        
        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone_number,
            'avatar_initials' => $this->getInitials($staff->name),
            
            // Staff status (from User model constants)
            'staff_status' => $staff->staff_status,
            'staff_status_label' => $this->getStatusLabel($staff->staff_status),
            'staff_status_color' => $this->getStatusColor($staff->staff_status),
            
            // Scheduling lock status
            'is_scheduling_locked' => $staff->is_scheduling_locked ?? false,
            'scheduling_locked_at' => $staff->scheduling_locked_at?->toIso8601String(),
            'scheduling_locked_reason' => $staff->scheduling_locked_reason,
            'can_be_scheduled' => $staff->canBeScheduled(),
            
            // Role (from StaffRole metadata)
            'staff_role' => $staff->staffRole ? [
                'id' => $staff->staffRole->id,
                'code' => $staff->staffRole->code,
                'name' => $staff->staffRole->name,
                'category' => $staff->staffRole->category,
                'badge_color' => $staff->staffRole->badge_color ?? 'blue',
                'is_regulated' => $staff->staffRole->is_regulated,
            ] : null,
            
            // Employment type (from EmploymentType metadata)
            'employment_type' => $staff->employmentTypeModel ? [
                'id' => $staff->employmentTypeModel->id,
                'code' => $staff->employmentTypeModel->code,
                'name' => $staff->employmentTypeModel->name,
                'standard_hours_per_week' => $staff->employmentTypeModel->standard_hours_per_week,
                'is_full_time' => $staff->employmentTypeModel->is_full_time,
                'is_direct_staff' => $staff->employmentTypeModel->is_direct_staff,
                'badge_color' => $staff->employmentTypeModel->badge_color ?? 'green',
            ] : null,
            
            // Organization
            'organization' => $staff->organization ? [
                'id' => $staff->organization->id,
                'name' => $staff->organization->name,
                'slug' => $staff->organization->slug,
            ] : null,
            
            // Employment dates
            'hire_date' => $staff->hire_date?->toDateString(),
            'termination_date' => $staff->termination_date?->toDateString(),
            'tenure' => $this->calculateTenure($staff->hire_date),
            
            // Capacity
            'max_weekly_hours' => (float) ($staff->max_weekly_hours ?? 40),
            'fte_value' => (float) ($staff->fte_value ?? 1.0),
            'fte_eligible' => $staff->employmentTypeModel?->is_direct_staff ?? false,
            
            // Account status
            'account_status' => [
                'is_active' => $staff->isActiveStaff(),
                'is_on_leave' => $staff->isOnLeave(),
                'last_login_at' => null, // Would need to track this separately
            ],
            
            // Computed stats
            'stats' => $this->getStaffStats($staff),
            
            // Skills summary
            'skills_count' => $staff->skills->count(),
            'expiring_skills_count' => $staff->getExpiringSkills(30)->count(),
        ];
    }

    /**
     * Get staff statistics (scheduled hours, utilization, satisfaction).
     */
    protected function getStaffStats(User $staff): array
    {
        $weeklyScheduledHours = $this->scheduleService->getWeeklyScheduledHours($staff->id);
        $maxWeeklyHours = (float) ($staff->max_weekly_hours ?? 40);
        $utilization = $maxWeeklyHours > 0 
            ? round(($weeklyScheduledHours / $maxWeeklyHours) * 100, 1)
            : 0;
        
        $satisfaction = $this->satisfactionService->getStaffSatisfaction($staff->id);
        $scheduleSummary = $this->scheduleService->getScheduleSummary($staff->id);
        
        return [
            'weekly_scheduled_hours' => $weeklyScheduledHours,
            'weekly_capacity_hours' => $maxWeeklyHours,
            'utilization_percent' => $utilization,
            'satisfaction_score' => $satisfaction['score'],
            'satisfaction_label' => $satisfaction['label'],
            'satisfaction_count' => $satisfaction['count'],
            'upcoming_appointments_count' => $scheduleSummary['upcoming_count'],
            'today_appointments' => $scheduleSummary['today'],
            'this_week' => $scheduleSummary['this_week'],
        ];
    }

    /**
     * Update staff status.
     */
    public function updateStatus(int $staffUserId, string $newStatus, ?array $options = []): bool
    {
        $staff = User::find($staffUserId);
        if (!$staff) {
            return false;
        }
        
        // Validate status
        $validStatuses = [
            User::STAFF_STATUS_ACTIVE,
            User::STAFF_STATUS_INACTIVE,
            User::STAFF_STATUS_ON_LEAVE,
            User::STAFF_STATUS_TERMINATED,
        ];
        
        if (!in_array($newStatus, $validStatuses)) {
            return false;
        }
        
        $staff->staff_status = $newStatus;
        
        // If setting to terminated, also record termination date
        if ($newStatus === User::STAFF_STATUS_TERMINATED) {
            $staff->termination_date = Carbon::now();
        }
        
        // If setting to on_leave and duration provided, create unavailability
        if ($newStatus === User::STAFF_STATUS_ON_LEAVE && !empty($options['leave_end_date'])) {
            StaffUnavailability::create([
                'user_id' => $staffUserId,
                'unavailability_type' => $options['leave_type'] ?? StaffUnavailability::TYPE_OTHER,
                'start_datetime' => Carbon::now(),
                'end_datetime' => Carbon::parse($options['leave_end_date']),
                'is_all_day' => true,
                'reason' => $options['reason'] ?? 'Status change to On Leave',
                'approval_status' => StaffUnavailability::STATUS_APPROVED,
                'approved_at' => Carbon::now(),
            ]);
        }
        
        return $staff->save();
    }

    /**
     * Lock staff from scheduling.
     */
    public function lockScheduling(int $staffUserId, ?string $reason = null): bool
    {
        $staff = User::find($staffUserId);
        if (!$staff) {
            return false;
        }
        
        $staff->lockScheduling($reason);
        return true;
    }

    /**
     * Unlock staff for scheduling.
     */
    public function unlockScheduling(int $staffUserId): bool
    {
        $staff = User::find($staffUserId);
        if (!$staff) {
            return false;
        }
        
        $staff->unlockScheduling();
        return true;
    }

    /**
     * Send password reset email.
     */
    public function sendPasswordReset(int $staffUserId): bool
    {
        $staff = User::find($staffUserId);
        if (!$staff) {
            return false;
        }
        
        $status = Password::sendResetLink(['email' => $staff->email]);
        
        return $status === Password::RESET_LINK_SENT;
    }

    /**
     * Soft delete staff (set status to terminated).
     */
    public function softDelete(int $staffUserId): bool
    {
        return $this->updateStatus($staffUserId, User::STAFF_STATUS_TERMINATED);
    }

    /**
     * Get staff skills with metadata.
     */
    public function getStaffSkills(int $staffUserId): array
    {
        $staff = User::with('skills')->find($staffUserId);
        if (!$staff) {
            return [];
        }
        
        return $staff->skills->map(function ($skill) {
            $pivot = $skill->pivot;
            $isExpired = $pivot->expires_at && Carbon::parse($pivot->expires_at)->isPast();
            $isExpiringSoon = $pivot->expires_at && Carbon::parse($pivot->expires_at)->isBefore(Carbon::now()->addDays(30));
            
            return [
                'id' => $skill->id,
                'code' => $skill->code,
                'name' => $skill->name,
                'category' => $skill->category,
                'requires_certification' => $skill->requires_certification,
                'proficiency_level' => $pivot->proficiency_level,
                'certified_at' => $pivot->certified_at,
                'expires_at' => $pivot->expires_at,
                'is_expired' => $isExpired,
                'is_expiring_soon' => $isExpiringSoon && !$isExpired,
                'status' => $isExpired ? 'expired' : ($isExpiringSoon ? 'expiring_soon' : 'valid'),
                'status_color' => $isExpired ? 'red' : ($isExpiringSoon ? 'amber' : 'green'),
            ];
        })->toArray();
    }

    /**
     * Get all available skills (for adding to staff).
     */
    public function getAvailableSkills(): array
    {
        return Skill::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn($skill) => [
                'id' => $skill->id,
                'code' => $skill->code,
                'name' => $skill->name,
                'category' => $skill->category,
                'requires_certification' => $skill->requires_certification,
                'renewal_period_months' => $skill->renewal_period_months,
            ])
            ->toArray();
    }

    /**
     * Get staff availability blocks.
     */
    public function getStaffAvailability(int $staffUserId): array
    {
        $availability = StaffAvailability::where('user_id', $staffUserId)
            ->currentlyEffective()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
        
        return $availability->map(fn($block) => [
            'id' => $block->id,
            'day_of_week' => $block->day_of_week,
            'day_name' => $this->getDayName($block->day_of_week),
            'start_time' => $block->start_time?->format('H:i'),
            'end_time' => $block->end_time?->format('H:i'),
            'duration_hours' => $block->duration_hours ?? 0,
            'is_recurring' => $block->is_recurring,
            'service_areas' => $block->service_areas,
        ])->toArray();
    }

    /**
     * Get staff unavailabilities (time off).
     */
    public function getStaffUnavailabilities(int $staffUserId, bool $includeHistory = false): array
    {
        $query = StaffUnavailability::where('user_id', $staffUserId);
        
        if (!$includeHistory) {
            $query->where('end_datetime', '>=', Carbon::now()->subDays(30));
        }
        
        $unavailabilities = $query->orderBy('start_datetime', 'desc')->get();
        
        return $unavailabilities->map(fn($u) => [
            'id' => $u->id,
            'type' => $u->unavailability_type,
            'type_label' => $this->getUnavailabilityTypeLabel($u->unavailability_type),
            'start_datetime' => $u->start_datetime?->toIso8601String(),
            'end_datetime' => $u->end_datetime?->toIso8601String(),
            'start_date' => $u->start_datetime?->toDateString(),
            'end_date' => $u->end_datetime?->toDateString(),
            'is_all_day' => $u->is_all_day,
            'reason' => $u->reason,
            'approval_status' => $u->approval_status,
            'approval_status_label' => ucfirst($u->approval_status),
            'is_current' => $u->start_datetime?->isPast() && $u->end_datetime?->isFuture(),
            'is_upcoming' => $u->start_datetime?->isFuture(),
        ])->toArray();
    }

    /**
     * Get status label from constant.
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            User::STAFF_STATUS_ACTIVE => 'Active',
            User::STAFF_STATUS_INACTIVE => 'Inactive',
            User::STAFF_STATUS_ON_LEAVE => 'On Leave',
            User::STAFF_STATUS_TERMINATED => 'Terminated',
            default => ucfirst($status),
        };
    }

    /**
     * Get status color for UI.
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            User::STAFF_STATUS_ACTIVE => 'green',
            User::STAFF_STATUS_INACTIVE => 'gray',
            User::STAFF_STATUS_ON_LEAVE => 'amber',
            User::STAFF_STATUS_TERMINATED => 'red',
            default => 'gray',
        };
    }

    /**
     * Get initials from name.
     */
    protected function getInitials(string $name): string
    {
        $words = explode(' ', trim($name));
        $initials = '';
        
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }
        
        return substr($initials, 0, 2);
    }

    /**
     * Calculate tenure from hire date.
     */
    protected function calculateTenure(?Carbon $hireDate): ?array
    {
        if (!$hireDate) {
            return null;
        }
        
        $diff = $hireDate->diff(Carbon::now());
        
        return [
            'years' => $diff->y,
            'months' => $diff->m,
            'total_months' => ($diff->y * 12) + $diff->m,
            'display' => $this->formatTenure($diff->y, $diff->m),
        ];
    }

    /**
     * Format tenure for display.
     */
    protected function formatTenure(int $years, int $months): string
    {
        $parts = [];
        
        if ($years > 0) {
            $parts[] = $years . ' year' . ($years > 1 ? 's' : '');
        }
        
        if ($months > 0 || empty($parts)) {
            $parts[] = $months . ' month' . ($months !== 1 ? 's' : '');
        }
        
        return implode(', ', $parts);
    }

    /**
     * Get day name from day of week number.
     */
    protected function getDayName(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            default => 'Unknown',
        };
    }

    /**
     * Get unavailability type label.
     */
    protected function getUnavailabilityTypeLabel(string $type): string
    {
        return match ($type) {
            StaffUnavailability::TYPE_VACATION => 'Vacation',
            StaffUnavailability::TYPE_SICK => 'Sick Leave',
            StaffUnavailability::TYPE_PERSONAL => 'Personal',
            StaffUnavailability::TYPE_TRAINING => 'Training',
            StaffUnavailability::TYPE_JURY_DUTY => 'Jury Duty',
            StaffUnavailability::TYPE_BEREAVEMENT => 'Bereavement',
            StaffUnavailability::TYPE_MATERNITY => 'Maternity Leave',
            StaffUnavailability::TYPE_PATERNITY => 'Paternity Leave',
            StaffUnavailability::TYPE_MEDICAL => 'Medical Leave',
            StaffUnavailability::TYPE_OTHER => 'Other',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
