<?php

namespace App\Services\CareOps;

use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * FTE Compliance Service
 *
 * Implements OHaH's 80% FTE requirement tracking:
 * - SPO must deliver at least 80% of care hours via full-time staff
 * - SSPO hours count as external/subcontracted
 * - Tracks compliance at organization and system level
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

        // Use hours-based ratio as primary (more accurate per OHaH)
        $primaryRatio = $hoursMetrics['total_hours'] > 0 ? $hoursRatio : $headcountRatio;
        $band = $this->determineBand($primaryRatio);

        return [
            'organization_id' => $orgId,
            'organization_name' => $organization->name,
            'organization_type' => $organization->type,
            'calculated_at' => Carbon::now()->toIso8601String(),

            // Headcount-based metrics
            'total_staff' => $staffMetrics['total_staff'],
            'full_time_staff' => $staffMetrics['full_time_staff'],
            'part_time_staff' => $staffMetrics['part_time_staff'],
            'casual_staff' => $staffMetrics['casual_staff'],
            'headcount_fte_ratio' => round($headcountRatio, 1),

            // Hours-based metrics (primary for OHaH compliance)
            'total_hours' => round($hoursMetrics['total_hours'], 1),
            'internal_hours' => round($hoursMetrics['internal_hours'], 1),
            'sspo_hours' => round($hoursMetrics['sspo_hours'], 1),
            'hours_fte_ratio' => round($hoursRatio, 1),

            // Capacity metrics
            'total_capacity_hours' => round($staffMetrics['total_capacity_hours'], 1),
            'utilized_hours' => round($hoursMetrics['internal_hours'], 1),
            'utilization_rate' => $staffMetrics['total_capacity_hours'] > 0
                ? round(($hoursMetrics['internal_hours'] / $staffMetrics['total_capacity_hours']) * 100, 1)
                : 0,

            // Compliance status
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
     */
    public function getStaffMetrics(int $organizationId): array
    {
        $staffQuery = User::where('organization_id', $organizationId)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where(function ($q) {
                $q->where('staff_status', User::STAFF_STATUS_ACTIVE)
                  ->orWhereNull('staff_status'); // Legacy records
            });

        $allStaff = $staffQuery->get();

        $fullTime = $allStaff->filter(fn($s) =>
            $s->employment_type === 'full_time' || ($s->fte_value ?? 0) >= 0.8
        );

        $partTime = $allStaff->filter(fn($s) =>
            $s->employment_type === 'part_time' && ($s->fte_value ?? 0) < 0.8
        );

        $casual = $allStaff->filter(fn($s) =>
            $s->employment_type === 'casual'
        );

        // Calculate total capacity (sum of max_weekly_hours)
        $totalCapacity = $allStaff->sum(fn($s) => $s->max_weekly_hours ?? self::FULL_TIME_HOURS_PER_WEEK);

        // Count by status
        $byStatus = [
            'active' => $allStaff->where('staff_status', User::STAFF_STATUS_ACTIVE)->count(),
            'inactive' => $allStaff->where('staff_status', User::STAFF_STATUS_INACTIVE)->count(),
            'on_leave' => $allStaff->where('staff_status', User::STAFF_STATUS_ON_LEAVE)->count(),
        ];

        return [
            'total_staff' => $allStaff->count(),
            'full_time_staff' => $fullTime->count(),
            'part_time_staff' => $partTime->count(),
            'casual_staff' => $casual->count(),
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

        // SSPO hours (assigned to SSPO or external org)
        $sspoHours = ServiceAssignment::where('service_provider_organization_id', $organizationId)
            ->where(function ($q) use ($organizationId) {
                // Assigned to different org or unassigned to user but has SSPO
                $q->whereHas('assignedUser', function ($subQ) use ($organizationId) {
                    $subQ->where('organization_id', '!=', $organizationId);
                })->orWhere(function ($subQ) {
                    $subQ->whereNull('assigned_user_id')
                         ->whereNotNull('sspo_organization_id');
                });
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
     * Calculate projection if adding new staff or changing employment type
     */
    public function calculateProjection(string $newStaffType, ?int $organizationId = null): array
    {
        $current = $this->calculateSnapshot($organizationId);

        $newTotal = $current['total_staff'] + 1;
        $newFullTime = $current['full_time_staff'] + ($newStaffType === 'full_time' ? 1 : 0);

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

            $ratio = $hoursMetrics['total_hours'] > 0
                ? ($hoursMetrics['internal_hours'] / $hoursMetrics['total_hours']) * 100
                : null;

            $trend[] = [
                'week_start' => $weekStart->toDateString(),
                'week_label' => $weekStart->format('M d'),
                'internal_hours' => round($hoursMetrics['internal_hours'], 1),
                'sspo_hours' => round($hoursMetrics['sspo_hours'], 1),
                'total_hours' => round($hoursMetrics['total_hours'], 1),
                'fte_ratio' => $ratio !== null ? round($ratio, 1) : null,
                'band' => $ratio !== null ? $this->determineBand($ratio) : self::BAND_GREY,
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
}
