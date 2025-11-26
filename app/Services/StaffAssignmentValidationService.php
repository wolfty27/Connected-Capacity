<?php

namespace App\Services;

use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * STAFF-016: Staff Assignment Validation Service
 *
 * Validates staff assignments against:
 * - Skill requirements
 * - Availability windows
 * - Unavailability periods
 * - Capacity limits
 * - Certification expiry
 */
class StaffAssignmentValidationService
{
    /**
     * Validate a staff member for assignment
     *
     * @return array{valid: bool, errors: array, warnings: array}
     */
    public function validateAssignment(
        User $staff,
        ServiceType $serviceType,
        ?Carbon $scheduledStart = null,
        ?Carbon $scheduledEnd = null,
        ?float $estimatedHours = null
    ): array {
        $errors = [];
        $warnings = [];

        // 1. Check staff status
        $statusCheck = $this->checkStaffStatus($staff);
        if (!$statusCheck['valid']) {
            $errors = array_merge($errors, $statusCheck['errors']);
        }

        // 2. Check skill requirements
        $skillCheck = $this->checkSkillRequirements($staff, $serviceType);
        if (!$skillCheck['valid']) {
            $errors = array_merge($errors, $skillCheck['errors']);
        }
        $warnings = array_merge($warnings, $skillCheck['warnings'] ?? []);

        // 3. Check availability (if scheduled time provided)
        if ($scheduledStart && $scheduledEnd) {
            $availabilityCheck = $this->checkAvailability($staff, $scheduledStart, $scheduledEnd);
            if (!$availabilityCheck['valid']) {
                $errors = array_merge($errors, $availabilityCheck['errors']);
            }
            $warnings = array_merge($warnings, $availabilityCheck['warnings'] ?? []);
        }

        // 4. Check capacity
        $capacityCheck = $this->checkCapacity($staff, $estimatedHours ?? 1.0);
        if (!$capacityCheck['valid']) {
            $warnings = array_merge($warnings, $capacityCheck['warnings'] ?? []);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check staff status is active
     */
    public function checkStaffStatus(User $staff): array
    {
        $errors = [];

        if ($staff->staff_status === User::STAFF_STATUS_TERMINATED) {
            $errors[] = [
                'code' => 'STAFF_TERMINATED',
                'message' => 'Staff member has been terminated and cannot be assigned',
            ];
        } elseif ($staff->staff_status === User::STAFF_STATUS_INACTIVE) {
            $errors[] = [
                'code' => 'STAFF_INACTIVE',
                'message' => 'Staff member is inactive and cannot be assigned',
            ];
        } elseif ($staff->staff_status === User::STAFF_STATUS_ON_LEAVE) {
            $errors[] = [
                'code' => 'STAFF_ON_LEAVE',
                'message' => 'Staff member is currently on leave',
            ];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if staff has required skills for service type
     */
    public function checkSkillRequirements(User $staff, ServiceType $serviceType): array
    {
        $errors = [];
        $warnings = [];

        $requiredSkills = $serviceType->requiredSkills()->get();

        foreach ($requiredSkills as $skill) {
            $staffSkill = $staff->skills()
                ->where('skills.id', $skill->id)
                ->first();

            if (!$staffSkill) {
                $errors[] = [
                    'code' => 'MISSING_SKILL',
                    'message' => "Staff lacks required skill: {$skill->name}",
                    'skill_id' => $skill->id,
                    'skill_code' => $skill->code,
                ];
                continue;
            }

            // Check expiry
            if ($staffSkill->pivot->expires_at) {
                $expiresAt = Carbon::parse($staffSkill->pivot->expires_at);

                if ($expiresAt->isPast()) {
                    $errors[] = [
                        'code' => 'SKILL_EXPIRED',
                        'message' => "Certification for {$skill->name} has expired",
                        'skill_id' => $skill->id,
                        'expired_at' => $expiresAt->toDateString(),
                    ];
                } elseif ($expiresAt->lte(Carbon::today()->addDays(7))) {
                    $warnings[] = [
                        'code' => 'SKILL_EXPIRING_SOON',
                        'message' => "Certification for {$skill->name} expires on {$expiresAt->toDateString()}",
                        'skill_id' => $skill->id,
                        'expires_at' => $expiresAt->toDateString(),
                    ];
                }
            }

            // Check proficiency level
            $minProficiency = $staffSkill->pivot->minimum_proficiency ?? 'competent';
            $staffProficiency = $staffSkill->pivot->proficiency_level;

            if (!$this->meetsMinimumProficiency($staffProficiency, $minProficiency)) {
                $warnings[] = [
                    'code' => 'LOW_PROFICIENCY',
                    'message' => "Staff proficiency ({$staffProficiency}) is below recommended ({$minProficiency}) for {$skill->name}",
                    'skill_id' => $skill->id,
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if staff is available during scheduled time
     */
    public function checkAvailability(User $staff, Carbon $scheduledStart, Carbon $scheduledEnd): array
    {
        $errors = [];
        $warnings = [];

        // Check for unavailability periods (time-off)
        $hasUnavailability = $staff->unavailabilities()
            ->approved()
            ->overlapping($scheduledStart, $scheduledEnd)
            ->exists();

        if ($hasUnavailability) {
            $unavailability = $staff->unavailabilities()
                ->approved()
                ->overlapping($scheduledStart, $scheduledEnd)
                ->first();

            $errors[] = [
                'code' => 'STAFF_UNAVAILABLE',
                'message' => "Staff has approved time-off during this period ({$unavailability->type_label})",
                'unavailability_id' => $unavailability->id,
                'type' => $unavailability->unavailability_type,
            ];
        }

        // Check availability windows
        $hasAvailabilityWindow = $staff->availabilities()
            ->activeOn($scheduledStart)
            ->where('start_time', '<=', $scheduledStart->format('H:i:s'))
            ->where('end_time', '>=', $scheduledEnd->format('H:i:s'))
            ->exists();

        if (!$hasAvailabilityWindow) {
            // Check if there's any availability on this day
            $hasDayAvailability = $staff->availabilities()
                ->activeOn($scheduledStart)
                ->exists();

            if (!$hasDayAvailability) {
                $warnings[] = [
                    'code' => 'NO_AVAILABILITY_WINDOW',
                    'message' => "Staff has no registered availability for {$scheduledStart->format('l')}",
                    'day_of_week' => $scheduledStart->dayOfWeek,
                ];
            } else {
                $warnings[] = [
                    'code' => 'OUTSIDE_AVAILABILITY_WINDOW',
                    'message' => "Scheduled time is outside staff's normal availability hours",
                    'scheduled_start' => $scheduledStart->format('H:i'),
                    'scheduled_end' => $scheduledEnd->format('H:i'),
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if staff has capacity for additional hours
     */
    public function checkCapacity(User $staff, float $additionalHours): array
    {
        $warnings = [];

        $currentHours = $staff->current_weekly_hours;
        $maxHours = $staff->max_weekly_hours ?? 40;
        $projectedHours = $currentHours + $additionalHours;
        $utilizationRate = ($projectedHours / $maxHours) * 100;

        if ($projectedHours > $maxHours) {
            $warnings[] = [
                'code' => 'CAPACITY_EXCEEDED',
                'message' => "Assignment would exceed weekly capacity ({$projectedHours}h / {$maxHours}h)",
                'current_hours' => round($currentHours, 1),
                'max_hours' => $maxHours,
                'projected_hours' => round($projectedHours, 1),
                'utilization_rate' => round($utilizationRate, 1),
            ];
        } elseif ($utilizationRate > 90) {
            $warnings[] = [
                'code' => 'HIGH_UTILIZATION',
                'message' => "Staff utilization would be {$utilizationRate}% after this assignment",
                'utilization_rate' => round($utilizationRate, 1),
            ];
        }

        return [
            'valid' => true, // Capacity is a warning, not a hard block
            'warnings' => $warnings,
        ];
    }

    /**
     * Find eligible staff for a service type
     */
    public function findEligibleStaff(
        ServiceType $serviceType,
        ?int $organizationId = null,
        ?Carbon $scheduledStart = null,
        ?Carbon $scheduledEnd = null
    ): Collection {
        $query = User::where('role', User::ROLE_FIELD_STAFF)
            ->activeStaff();

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        // Filter by required skills
        $requiredSkillIds = $serviceType->requiredSkills()->pluck('skills.id');
        if ($requiredSkillIds->isNotEmpty()) {
            foreach ($requiredSkillIds as $skillId) {
                $query->whereHas('skills', function ($q) use ($skillId) {
                    $q->where('skills.id', $skillId)
                      ->where(function ($subQ) {
                          $subQ->whereNull('staff_skills.expires_at')
                               ->orWhere('staff_skills.expires_at', '>', Carbon::today());
                      });
                });
            }
        }

        // Filter by availability if time provided
        if ($scheduledStart && $scheduledEnd) {
            $query->availableOn($scheduledStart, $scheduledStart->format('H:i'), $scheduledEnd->format('H:i'));
        }

        return $query->with(['skills', 'organization'])
            ->get()
            ->map(function ($staff) use ($serviceType, $scheduledStart, $scheduledEnd) {
                $validation = $this->validateAssignment(
                    $staff,
                    $serviceType,
                    $scheduledStart,
                    $scheduledEnd
                );

                return [
                    'staff' => $staff,
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'organization' => $staff->organization?->name,
                    'employment_type' => $staff->employment_type,
                    'current_weekly_hours' => round($staff->current_weekly_hours, 1),
                    'available_hours' => round($staff->available_hours, 1),
                    'utilization_rate' => round($staff->fte_utilization, 1),
                    'is_valid' => $validation['valid'],
                    'errors' => $validation['errors'],
                    'warnings' => $validation['warnings'],
                    'skills_match' => $this->calculateSkillsMatch($staff, $serviceType),
                ];
            })
            ->sortByDesc('skills_match')
            ->sortBy(fn($s) => count($s['warnings']))
            ->values();
    }

    /**
     * Check if staff proficiency meets minimum requirement
     */
    protected function meetsMinimumProficiency(string $staffLevel, string $minLevel): bool
    {
        $levels = ['basic' => 1, 'competent' => 2, 'proficient' => 3, 'expert' => 4];

        return ($levels[$staffLevel] ?? 0) >= ($levels[$minLevel] ?? 0);
    }

    /**
     * Calculate how well staff skills match service requirements
     */
    protected function calculateSkillsMatch(User $staff, ServiceType $serviceType): float
    {
        $requiredSkills = $serviceType->requiredSkills()->pluck('skills.id');

        if ($requiredSkills->isEmpty()) {
            return 100.0;
        }

        $staffSkillIds = $staff->skills()
            ->whereIn('skills.id', $requiredSkills)
            ->where(function ($q) {
                $q->whereNull('staff_skills.expires_at')
                  ->orWhere('staff_skills.expires_at', '>', Carbon::today());
            })
            ->pluck('skills.id');

        return ($staffSkillIds->count() / $requiredSkills->count()) * 100;
    }
}
