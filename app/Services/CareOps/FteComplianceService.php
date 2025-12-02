<?php

namespace App\Services\CareOps;

use App\Models\EmploymentType;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\StaffRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * FTE Compliance Service
 *
 * Implements OHaH's 80% FTE requirement tracking per RFP Q&A:
 * FTE ratio = [Number of active full-time direct staff in the program in a week
 *              ÷ Number of active direct staff in the program in the same week] × 100%
 *
 * This is a HEADCOUNT ratio, not hours-based.
 * Full-time is defined per Ontario's Employment Standards Act.
 */
class FteComplianceService
{
    // Compliance thresholds (OHaH requirement)
    public const FTE_COMPLIANCE_TARGET = 80.0;
    public const FTE_WARNING_THRESHOLD = 75.0;
    public const FTE_CRITICAL_THRESHOLD = 70.0;

    // Standard full-time hours per week
    public const FULL_TIME_HOURS_PER_WEEK = 40.0;

    // Compliance bands
    public const BAND_GREEN = 'GREEN';   // >= 80% - Compliant
    public const BAND_YELLOW = 'YELLOW'; // 75-79% - At Risk
    public const BAND_RED = 'RED';       // < 75% - Non-Compliant
    public const BAND_GREY = 'GREY';     // No data

    // Staff satisfaction target (per RFP)
    public const SATISFACTION_TARGET = 95.0;

    /**
     * Calculate FTE compliance snapshot for an organization
     */
    public function calculateSnapshot(?int $organizationId = null): array
    {
        $orgId = $organizationId ?? Auth::user()?->organization_id;

        if (!$orgId) {
            return $this->emptySnapshot();
        }

        $organization = ServiceProviderOrganization::find($orgId);
        if (!$organization) {
            return $this->emptySnapshot();
        }

        // Get staff metrics
        $staffMetrics = $this->getStaffMetrics($orgId);

        // Get hours metrics (current week)
        $hoursMetrics = $this->getHoursMetrics($orgId);

        // Calculate compliance ratios
        $headcountRatio = $staffMetrics['total_staff'] > 0
            ? ($staffMetrics['full_time_staff'] / $staffMetrics['total_staff']) * 100
            : 0;

        $hoursRatio = $hoursMetrics['total_hours'] > 0
            ? ($hoursMetrics['internal_hours'] / $hoursMetrics['total_hours']) * 100
            : 0;

        // Use headcount-based ratio as primary per RFP Q&A:
        // FTE ratio = [Number of active full-time direct staff ÷ Number of active direct staff] × 100%
        $primaryRatio = $headcountRatio;
        $band = $this->determineBand($primaryRatio);

        return [
            'organization_id' => $orgId,
            'organization_name' => $organization->name,
            'organization_type' => $organization->type,
            'calculated_at' => Carbon::now()->toIso8601String(),

            // Headcount-based metrics (primary per RFP Q&A)
            // Note: total_staff = direct staff only (FT + PT + Casual)
            'total_staff' => $staffMetrics['total_staff'],
            'full_time_staff' => $staffMetrics['full_time_staff'],
            'part_time_staff' => $staffMetrics['part_time_staff'],
            'casual_staff' => $staffMetrics['casual_staff'],
            'sspo_staff' => $staffMetrics['sspo_staff'] ?? 0,
            'headcount_fte_ratio' => round($headcountRatio, 1),

            // Hours-based metrics (supplementary tracking)
            'total_hours' => round($hoursMetrics['total_hours'], 1),
            'internal_hours' => round($hoursMetrics['internal_hours'], 1),
            'sspo_hours' => round($hoursMetrics['sspo_hours'], 1),
            'hours_fte_ratio' => round($hoursRatio, 1),

            // Capacity metrics (based on direct staff only)
            'total_capacity_hours' => round($staffMetrics['total_capacity_hours'], 1),
            'utilized_hours' => round($hoursMetrics['internal_hours'], 1),
            'utilization_rate' => $staffMetrics['total_capacity_hours'] > 0
                ? round(($hoursMetrics['internal_hours'] / $staffMetrics['total_capacity_hours']) * 100, 1)
                : 0,

            // Compliance status (per RFP Q&A: FT / Direct Staff × 100%)
            'fte_ratio' => round($primaryRatio, 1),
            'band' => $band,
            'is_compliant' => $primaryRatio >= self::FTE_COMPLIANCE_TARGET,
            'gap_to_compliance' => max(0, self::FTE_COMPLIANCE_TARGET - $primaryRatio),

            // Staff by status
            'staff_by_status' => $staffMetrics['by_status'],
        ];
    }

    /**
     * Get detailed staff metrics for an organization
     *
     * Uses EmploymentType metadata to correctly classify staff:
     * - is_direct_staff: true for FT, PT, Casual (counts in FTE denominator)
     * - is_full_time: true for FT only (counts in FTE numerator)
     * - SSPO staff (is_direct_staff=false) are excluded from FTE ratio
     *
     * Per RFP Q&A: FTE ratio = FT direct staff / Total direct staff × 100%
     */
    public function getStaffMetrics(int $organizationId): array
    {
        // Get all staff with their employment type relationship
        $allStaff = User::where('organization_id', $organizationId)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where(function ($q) {
                $q->where('staff_status', User::STAFF_STATUS_ACTIVE)
                  ->orWhereNull('staff_status'); // Legacy records
            })
            ->with('employmentTypeModel')
            ->get();

        // Count staff by employment type using metadata
        $fullTime = $allStaff->filter(function ($staff) {
            return $staff->employmentTypeModel?->is_full_time === true
                && $staff->employmentTypeModel?->is_direct_staff === true;
        });

        $partTime = $allStaff->filter(function ($staff) {
            return $staff->employmentTypeModel?->code === EmploymentType::CODE_PART_TIME
                && $staff->employmentTypeModel?->is_direct_staff === true;
        });

        $casual = $allStaff->filter(function ($staff) {
            return $staff->employmentTypeModel?->code === EmploymentType::CODE_CASUAL
                && $staff->employmentTypeModel?->is_direct_staff === true;
        });

        $sspo = $allStaff->filter(function ($staff) {
            return $staff->employmentTypeModel?->is_direct_staff === false;
        });

        // Direct staff = FT + PT + Casual (excludes SSPO)
        $directStaff = $allStaff->filter(function ($staff) {
            return $staff->employmentTypeModel?->is_direct_staff === true;
        });

        // Calculate total capacity from employment type metadata
        // Use standard_hours_per_week from EmploymentType, fallback to max_weekly_hours
        $totalCapacity = $directStaff->sum(function ($staff) {
            return $staff->employmentTypeModel?->standard_hours_per_week
                ?? $staff->max_weekly_hours
                ?? self::FULL_TIME_HOURS_PER_WEEK;
        });

        // Count by status
        $byStatus = [
            'active' => $allStaff->where('staff_status', User::STAFF_STATUS_ACTIVE)->count(),
            'inactive' => $allStaff->where('staff_status', User::STAFF_STATUS_INACTIVE)->count(),
            'on_leave' => $allStaff->where('staff_status', User::STAFF_STATUS_ON_LEAVE)->count(),
        ];

        return [
            // Total direct staff only (for FTE ratio denominator)
            'total_staff' => $directStaff->count(),
            'full_time_staff' => $fullTime->count(),
            'part_time_staff' => $partTime->count(),
            'casual_staff' => $casual->count(),
            // SSPO tracked separately, not in FTE ratio
            'sspo_staff' => $sspo->count(),
            // Capacity based on direct staff only
            'total_capacity_hours' => $totalCapacity,
            'total_fte' => round($totalCapacity / self::FULL_TIME_HOURS_PER_WEEK, 2),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Get hours metrics for the current week
     */
    public function getHoursMetrics(int $organizationId, ?Carbon $weekStart = null): array
    {
        $weekStart = $weekStart ?? Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        // Internal SPO hours (assigned to SPO staff)
        $internalHours = ServiceAssignment::where('service_provider_organization_id', $organizationId)
            ->whereNotNull('assigned_user_id')
            ->whereHas('assignedUser', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            })
            ->whereBetween('scheduled_start', [$weekStart, $weekEnd])
            ->whereIn('status', ['planned', 'in_progress', 'completed'])
            ->get()
            ->sum(function ($assignment) {
                return $this->calculateAssignmentHours($assignment);
            });

        // SSPO hours (assigned to external org staff or has SSPO source)
        $sspoHours = ServiceAssignment::where('service_provider_organization_id', $organizationId)
            ->where(function ($q) use ($organizationId) {
                // Assigned to staff from different org OR marked as SSPO source
                $q->whereHas('assignedUser', function ($subQ) use ($organizationId) {
                    $subQ->where('organization_id', '!=', $organizationId);
                })->orWhere('source', 'SSPO');
            })
            ->whereBetween('scheduled_start', [$weekStart, $weekEnd])
            ->whereIn('status', ['planned', 'in_progress', 'completed'])
            ->get()
            ->sum(function ($assignment) {
                return $this->calculateAssignmentHours($assignment);
            });

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'internal_hours' => $internalHours,
            'sspo_hours' => $sspoHours,
            'total_hours' => $internalHours + $sspoHours,
        ];
    }

    /**
     * Calculate hours for a service assignment
     */
    protected function calculateAssignmentHours(ServiceAssignment $assignment): float
    {
        if ($assignment->scheduled_start && $assignment->scheduled_end) {
            return $assignment->scheduled_start->diffInMinutes($assignment->scheduled_end) / 60;
        }

        return $assignment->estimated_duration_hours ?? 1.0;
    }

    /**
     * Calculate projection if adding new staff or changing employment type.
     *
     * Accepts employment type codes: FT, PT, CASUAL, SSPO
     * or legacy strings: full_time, part_time, casual
     *
     * Per RFP Q&A:
     * - SSPO staff do NOT affect FTE ratio (excluded from both numerator and denominator)
     * - Only direct staff (FT/PT/Casual) are counted
     */
    public function calculateProjection(string $newStaffType, ?int $organizationId = null): array
    {
        $current = $this->calculateSnapshot($organizationId);

        // Normalize employment type code
        $typeCode = strtoupper($newStaffType);
        $isDirectStaff = !in_array($typeCode, [EmploymentType::CODE_SSPO, 'SSPO']);
        $isFullTime = in_array($typeCode, [EmploymentType::CODE_FULL_TIME, 'FULL_TIME']);

        // SSPO hires don't affect the FTE ratio since they're excluded
        if (!$isDirectStaff) {
            return [
                'current' => [
                    'ratio' => $current['fte_ratio'],
                    'band' => $current['band'],
                    'is_compliant' => $current['is_compliant'],
                ],
                'projected' => [
                    'ratio' => $current['fte_ratio'],
                    'band' => $current['band'],
                    'is_compliant' => $current['is_compliant'],
                ],
                'impact' => 0,
                'recommendation' => 'SSPO staff do not affect FTE compliance ratio.',
            ];
        }

        $newTotal = $current['total_staff'] + 1;
        $newFullTime = $current['full_time_staff'] + ($isFullTime ? 1 : 0);

        $projectedRatio = $newTotal > 0 ? ($newFullTime / $newTotal) * 100 : 0;
        $projectedBand = $this->determineBand($projectedRatio);

        return [
            'current' => [
                'ratio' => $current['fte_ratio'],
                'band' => $current['band'],
                'is_compliant' => $current['is_compliant'],
            ],
            'projected' => [
                'ratio' => round($projectedRatio, 1),
                'band' => $projectedBand,
                'is_compliant' => $projectedRatio >= self::FTE_COMPLIANCE_TARGET,
            ],
            'impact' => round($projectedRatio - $current['fte_ratio'], 1),
            'recommendation' => $this->getHiringRecommendation($current['fte_ratio'], $projectedRatio),
        ];
    }

    /**
     * Calculate how many full-time staff needed to reach compliance
     */
    public function calculateComplianceGap(?int $organizationId = null): array
    {
        $current = $this->calculateSnapshot($organizationId);

        if ($current['is_compliant']) {
            return [
                'is_compliant' => true,
                'current_ratio' => $current['fte_ratio'],
                'full_time_needed' => 0,
                'message' => 'Organization is compliant with 80% FTE requirement.',
            ];
        }

        $totalStaff = $current['total_staff'];
        $fullTimeStaff = $current['full_time_staff'];

        // Calculate how many full-time staff needed
        // Target: (FT + X) / (Total + X) >= 0.8
        // Solving for X: X >= (0.8 * Total - FT) / 0.2
        $needed = ceil((self::FTE_COMPLIANCE_TARGET / 100 * $totalStaff - $fullTimeStaff) / 0.2);
        $needed = max(0, $needed);

        return [
            'is_compliant' => false,
            'current_ratio' => $current['fte_ratio'],
            'target_ratio' => self::FTE_COMPLIANCE_TARGET,
            'gap' => $current['gap_to_compliance'],
            'full_time_needed' => $needed,
            'message' => $needed > 0
                ? "Need to hire {$needed} full-time staff to reach 80% compliance."
                : "Reduce part-time/casual reliance to improve ratio.",
        ];
    }

    /**
     * Get weekly compliance trend (last N weeks)
     */
    public function getComplianceTrend(int $weeks = 8, ?int $organizationId = null): array
    {
        $orgId = $organizationId ?? Auth::user()?->organization_id;

        if (!$orgId) {
            return [];
        }

        $trend = [];
        $currentWeekStart = Carbon::now()->startOfWeek();

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = $currentWeekStart->copy()->subWeeks($i);
            $hoursMetrics = $this->getHoursMetrics($orgId, $weekStart);

            // Get staff metrics for headcount-based ratio (per RFP requirement)
            $staffMetrics = $this->getStaffMetrics($orgId);
            $headcountRatio = $staffMetrics['total_staff'] > 0
                ? ($staffMetrics['full_time_staff'] / $staffMetrics['total_staff']) * 100
                : null;

            $trend[] = [
                'week_start' => $weekStart->toDateString(),
                'week_label' => $weekStart->format('M d'),
                'internal_hours' => round($hoursMetrics['internal_hours'], 1),
                'sspo_hours' => round($hoursMetrics['sspo_hours'], 1),
                'total_hours' => round($hoursMetrics['total_hours'], 1),
                'full_time_staff' => $staffMetrics['full_time_staff'],
                'total_staff' => $staffMetrics['total_staff'],
                'fte_ratio' => $headcountRatio !== null ? round($headcountRatio, 1) : null,
                'band' => $headcountRatio !== null ? $this->determineBand($headcountRatio) : self::BAND_GREY,
            ];
        }

        return $trend;
    }

    /**
     * Get staff utilization report
     */
    public function getStaffUtilization(?int $organizationId = null): Collection
    {
        $orgId = $organizationId ?? Auth::user()?->organization_id;

        if (!$orgId) {
            return collect();
        }

        return User::where('organization_id', $orgId)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->activeStaff()
            ->get()
            ->map(function ($staff) {
                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'employment_type' => $staff->employment_type,
                    'fte_value' => $staff->fte_value,
                    'max_weekly_hours' => $staff->max_weekly_hours ?? self::FULL_TIME_HOURS_PER_WEEK,
                    'current_weekly_hours' => $staff->current_weekly_hours,
                    'available_hours' => $staff->available_hours,
                    'utilization_rate' => $staff->fte_utilization,
                    'status' => $staff->staff_status,
                    'skills_count' => $staff->skills()->count(),
                ];
            })
            ->sortByDesc('utilization_rate')
            ->values();
    }

    /**
     * Get HHR (Human Health Resources) complement breakdown by role and employment type.
     *
     * Per RFP Q&A: Provides breakdown of staff by worker type (RN, RPN, PSW, etc.)
     * and employment category (FT, PT, Casual, SSPO).
     *
     * SSPO staff are tracked separately and do NOT count in FTE ratio calculations.
     */
    public function getHhrComplement(?int $organizationId = null): array
    {
        $orgId = $organizationId ?? Auth::user()?->organization_id;

        if (!$orgId) {
            return $this->emptyHhrComplement();
        }

        // Get all staff roles and employment types for structured output
        $staffRoles = StaffRole::active()->ordered()->get();
        $employmentTypes = EmploymentType::active()->ordered()->get();

        // Get staff with role and employment type relationships
        $staff = User::where('organization_id', $orgId)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where(function ($q) {
                $q->where('staff_status', User::STAFF_STATUS_ACTIVE)
                  ->orWhereNull('staff_status');
            })
            ->with(['staffRole', 'employmentTypeModel'])
            ->get();

        // Build complement matrix: role -> employment_type -> count
        $complement = [];
        $totals = [
            'by_role' => [],
            'by_employment_type' => [],
            'grand_total' => 0,
            'direct_staff_total' => 0,
            'sspo_staff_total' => 0,
            'full_time_total' => 0,
        ];

        // Initialize structure
        foreach ($staffRoles as $role) {
            $complement[$role->code] = [
                'role_id' => $role->id,
                'role_code' => $role->code,
                'role_name' => $role->name,
                'category' => $role->category,
                'is_regulated' => $role->is_regulated,
                'counts_for_fte' => $role->counts_for_fte,
                'by_employment_type' => [],
                'total' => 0,
                'fte_eligible' => 0,
            ];

            foreach ($employmentTypes as $empType) {
                $complement[$role->code]['by_employment_type'][$empType->code] = [
                    'employment_type_id' => $empType->id,
                    'employment_type_code' => $empType->code,
                    'employment_type_name' => $empType->name,
                    'is_direct_staff' => $empType->is_direct_staff,
                    'is_full_time' => $empType->is_full_time,
                    'count' => 0,
                    'capacity_hours' => 0,
                ];
            }
        }

        // Count staff by role and employment type
        foreach ($staff as $member) {
            $roleCode = $member->staffRole?->code ?? 'OTHER';
            $empTypeCode = $member->employmentTypeModel?->code ?? 'FT';

            // Handle case where role doesn't exist in our structure
            if (!isset($complement[$roleCode])) {
                continue;
            }

            // Handle case where employment type doesn't exist
            if (!isset($complement[$roleCode]['by_employment_type'][$empTypeCode])) {
                continue;
            }

            $complement[$roleCode]['by_employment_type'][$empTypeCode]['count']++;
            $complement[$roleCode]['by_employment_type'][$empTypeCode]['capacity_hours'] +=
                $member->max_weekly_hours ?? self::FULL_TIME_HOURS_PER_WEEK;

            $complement[$roleCode]['total']++;

            // Count FTE eligible (direct staff with this role)
            if ($member->employmentTypeModel?->is_direct_staff ?? true) {
                $complement[$roleCode]['fte_eligible']++;
            }

            // Update totals
            $totals['grand_total']++;
            $totals['by_role'][$roleCode] = ($totals['by_role'][$roleCode] ?? 0) + 1;
            $totals['by_employment_type'][$empTypeCode] = ($totals['by_employment_type'][$empTypeCode] ?? 0) + 1;

            if ($member->employmentTypeModel?->is_direct_staff ?? true) {
                $totals['direct_staff_total']++;
            } else {
                $totals['sspo_staff_total']++;
            }

            if ($member->employmentTypeModel?->is_full_time ?? false) {
                $totals['full_time_total']++;
            }
        }

        // Calculate FTE ratio (direct staff only, per Q&A)
        $fteRatio = $totals['direct_staff_total'] > 0
            ? ($totals['full_time_total'] / $totals['direct_staff_total']) * 100
            : 0;

        return [
            'organization_id' => $orgId,
            'calculated_at' => Carbon::now()->toIso8601String(),
            'complement' => array_values($complement),
            'totals' => $totals,
            'fte_ratio' => round($fteRatio, 1),
            'band' => $this->determineBand($fteRatio),
            'is_compliant' => $fteRatio >= self::FTE_COMPLIANCE_TARGET,
            'employment_types' => $employmentTypes->map(fn($et) => [
                'id' => $et->id,
                'code' => $et->code,
                'name' => $et->name,
                'is_direct_staff' => $et->is_direct_staff,
                'is_full_time' => $et->is_full_time,
                'badge_color' => $et->badge_color,
            ])->toArray(),
            'staff_roles' => $staffRoles->map(fn($sr) => [
                'id' => $sr->id,
                'code' => $sr->code,
                'name' => $sr->name,
                'category' => $sr->category,
                'badge_color' => $sr->badge_color,
            ])->toArray(),
        ];
    }

    /**
     * Get staff satisfaction metrics for an organization.
     *
     * Per RFP: Target is >95% staff satisfaction.
     * Tracks job_satisfaction field on User model.
     * Supports both enum values ('excellent', 'good', 'neutral', 'poor')
     * and numeric values (0-100).
     */
    public function getStaffSatisfactionMetrics(?int $organizationId = null): array
    {
        $orgId = $organizationId ?? Auth::user()?->organization_id;

        if (!$orgId) {
            return $this->emptySatisfactionMetrics();
        }

        $staff = User::where('organization_id', $orgId)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where(function ($q) {
                $q->where('staff_status', User::STAFF_STATUS_ACTIVE)
                  ->orWhereNull('staff_status');
            })
            ->whereNotNull('job_satisfaction')
            ->get();

        $totalResponses = $staff->count();

        if ($totalResponses === 0) {
            return $this->emptySatisfactionMetrics($orgId);
        }

        // Convert satisfaction values to numeric (supports enum or numeric)
        $satisfactionMap = [
            'excellent' => 95,
            'good' => 85,
            'neutral' => 60,
            'poor' => 35,
        ];

        $numericSatisfaction = $staff->map(function ($s) use ($satisfactionMap) {
            $value = $s->job_satisfaction;
            // If it's a string enum, convert to numeric
            if (is_string($value) && isset($satisfactionMap[strtolower($value)])) {
                return $satisfactionMap[strtolower($value)];
            }
            // If it's already numeric, use it directly
            return is_numeric($value) ? (float) $value : 60;
        });

        // Calculate satisfaction metrics
        $avgSatisfaction = $numericSatisfaction->avg();

        // Count satisfied (>= 80% or 'excellent'/'good' equivalent)
        $satisfiedCount = $numericSatisfaction->filter(fn($v) => $v >= 80)->count();
        $satisfiedRate = ($satisfiedCount / $totalResponses) * 100;

        // Distribution breakdown
        $distribution = [
            'very_satisfied' => $numericSatisfaction->filter(fn($v) => $v >= 90)->count(),
            'satisfied' => $numericSatisfaction->filter(fn($v) => $v >= 70 && $v < 90)->count(),
            'neutral' => $numericSatisfaction->filter(fn($v) => $v >= 50 && $v < 70)->count(),
            'dissatisfied' => $numericSatisfaction->filter(fn($v) => $v >= 30 && $v < 50)->count(),
            'very_dissatisfied' => $numericSatisfaction->filter(fn($v) => $v < 30)->count(),
        ];

        // Most recent survey date
        $latestSurvey = $staff->max('job_satisfaction_recorded_at');

        return [
            'organization_id' => $orgId,
            'calculated_at' => Carbon::now()->toIso8601String(),
            'total_responses' => $totalResponses,
            'average_satisfaction' => round($avgSatisfaction, 1),
            'satisfied_count' => $satisfiedCount,
            'satisfaction_rate' => round($satisfiedRate, 1),
            'target_rate' => self::SATISFACTION_TARGET,
            'meets_target' => $satisfiedRate >= self::SATISFACTION_TARGET,
            'gap_to_target' => max(0, self::SATISFACTION_TARGET - $satisfiedRate),
            'distribution' => $distribution,
            'latest_survey_date' => $latestSurvey?->toDateString(),
        ];
    }

    /**
     * Get comprehensive workforce summary combining FTE, HHR, and satisfaction.
     */
    public function getWorkforceSummary(?int $organizationId = null): array
    {
        $orgId = $organizationId ?? Auth::user()?->organization_id;

        $fteSnapshot = $this->calculateSnapshot($orgId);
        $hhrComplement = $this->getHhrComplement($orgId);
        $satisfaction = $this->getStaffSatisfactionMetrics($orgId);

        // Get SSPO hours for capacity tracking
        $hoursMetrics = $this->getHoursMetrics($orgId);

        return [
            'organization_id' => $orgId,
            'calculated_at' => Carbon::now()->toIso8601String(),

            // FTE Compliance (primary metric per RFP Q&A)
            'fte_compliance' => [
                'ratio' => $fteSnapshot['fte_ratio'],
                'band' => $fteSnapshot['band'],
                'is_compliant' => $fteSnapshot['is_compliant'],
                'target' => self::FTE_COMPLIANCE_TARGET,
                'gap' => $fteSnapshot['gap_to_compliance'],
                'full_time_count' => $fteSnapshot['full_time_staff'],
                'direct_staff_count' => $fteSnapshot['total_staff'],
            ],

            // Staff Headcount Summary
            // Note: 'total' is direct staff only (FT + PT + Casual) for FTE ratio compliance
            'headcount' => [
                'total' => $fteSnapshot['total_staff'],
                'full_time' => $fteSnapshot['full_time_staff'],
                'part_time' => $fteSnapshot['part_time_staff'],
                'casual' => $fteSnapshot['casual_staff'],
                'sspo' => $fteSnapshot['sspo_staff'] ?? 0,
            ],

            // Hours & Capacity
            'capacity' => [
                'total_capacity_hours' => $fteSnapshot['total_capacity_hours'],
                'utilized_hours' => $fteSnapshot['utilized_hours'],
                'utilization_rate' => $fteSnapshot['utilization_rate'],
                'internal_hours' => $hoursMetrics['internal_hours'],
                'sspo_hours' => $hoursMetrics['sspo_hours'],
            ],

            // HHR Complement (by role)
            'hhr_complement' => $hhrComplement['complement'],
            'hhr_totals' => $hhrComplement['totals'],

            // Staff Satisfaction
            'satisfaction' => [
                'average' => $satisfaction['average_satisfaction'],
                'rate' => $satisfaction['satisfaction_rate'],
                'meets_target' => $satisfaction['meets_target'],
                'target' => self::SATISFACTION_TARGET,
                'responses' => $satisfaction['total_responses'],
            ],

            // Reference data
            'employment_types' => $hhrComplement['employment_types'],
            'staff_roles' => $hhrComplement['staff_roles'],
        ];
    }

    /**
     * Get compliance summary across all SPOs (for platform admin)
     */
    public function getPlatformComplianceSummary(): array
    {
        $spos = ServiceProviderOrganization::where('type', 'se_health')->get();

        $summaries = $spos->map(function ($spo) {
            $snapshot = $this->calculateSnapshot($spo->id);
            return [
                'organization_id' => $spo->id,
                'organization_name' => $spo->name,
                'fte_ratio' => $snapshot['fte_ratio'],
                'band' => $snapshot['band'],
                'is_compliant' => $snapshot['is_compliant'],
                'total_staff' => $snapshot['total_staff'],
                'total_hours' => $snapshot['total_hours'],
            ];
        });

        $compliantCount = $summaries->where('is_compliant', true)->count();
        $totalCount = $summaries->count();

        return [
            'total_organizations' => $totalCount,
            'compliant_count' => $compliantCount,
            'non_compliant_count' => $totalCount - $compliantCount,
            'platform_compliance_rate' => $totalCount > 0
                ? round(($compliantCount / $totalCount) * 100, 1)
                : 0,
            'organizations' => $summaries->sortBy('fte_ratio')->values(),
        ];
    }

    /**
     * Determine compliance band based on ratio
     */
    protected function determineBand(float $ratio): string
    {
        if ($ratio >= self::FTE_COMPLIANCE_TARGET) {
            return self::BAND_GREEN;
        } elseif ($ratio >= self::FTE_WARNING_THRESHOLD) {
            return self::BAND_YELLOW;
        }
        return self::BAND_RED;
    }

    /**
     * Get hiring recommendation based on compliance change
     */
    protected function getHiringRecommendation(float $currentRatio, float $projectedRatio): string
    {
        $improvement = $projectedRatio - $currentRatio;

        if ($projectedRatio >= self::FTE_COMPLIANCE_TARGET) {
            return 'This hire would maintain/achieve compliance.';
        } elseif ($improvement > 0) {
            return 'This hire improves ratio but does not achieve compliance.';
        } elseif ($improvement < 0) {
            return 'Warning: This hire would reduce compliance ratio.';
        }
        return 'No impact on compliance ratio.';
    }

    /**
     * Return empty snapshot for missing data
     */
    protected function emptySnapshot(): array
    {
        return [
            'organization_id' => null,
            'organization_name' => null,
            'organization_type' => null,
            'calculated_at' => Carbon::now()->toIso8601String(),
            'total_staff' => 0,
            'full_time_staff' => 0,
            'part_time_staff' => 0,
            'casual_staff' => 0,
            'headcount_fte_ratio' => 0,
            'total_hours' => 0,
            'internal_hours' => 0,
            'sspo_hours' => 0,
            'hours_fte_ratio' => 0,
            'total_capacity_hours' => 0,
            'utilized_hours' => 0,
            'utilization_rate' => 0,
            'fte_ratio' => 0,
            'band' => self::BAND_GREY,
            'is_compliant' => false,
            'gap_to_compliance' => self::FTE_COMPLIANCE_TARGET,
            'staff_by_status' => ['active' => 0, 'inactive' => 0, 'on_leave' => 0],
        ];
    }

    /**
     * Return empty HHR complement for missing data
     */
    protected function emptyHhrComplement(): array
    {
        return [
            'organization_id' => null,
            'calculated_at' => Carbon::now()->toIso8601String(),
            'complement' => [],
            'totals' => [
                'by_role' => [],
                'by_employment_type' => [],
                'grand_total' => 0,
                'direct_staff_total' => 0,
                'sspo_staff_total' => 0,
                'full_time_total' => 0,
            ],
            'fte_ratio' => 0,
            'band' => self::BAND_GREY,
            'is_compliant' => false,
            'employment_types' => [],
            'staff_roles' => [],
        ];
    }

    /**
     * Return empty satisfaction metrics for missing data
     */
    protected function emptySatisfactionMetrics(?int $organizationId = null): array
    {
        return [
            'organization_id' => $organizationId,
            'calculated_at' => Carbon::now()->toIso8601String(),
            'total_responses' => 0,
            'average_satisfaction' => null,
            'satisfied_count' => 0,
            'satisfaction_rate' => null,
            'target_rate' => self::SATISFACTION_TARGET,
            'meets_target' => false,
            'gap_to_target' => null,
            'distribution' => [
                'very_satisfied' => 0,
                'satisfied' => 0,
                'neutral' => 0,
                'dissatisfied' => 0,
                'very_dissatisfied' => 0,
            ],
            'latest_survey_date' => null,
        ];
    }
}
