<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\EmploymentType;
use App\Models\ServiceAssignment;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\CareOps\FteComplianceService;
use App\Services\CareOps\WorkforceCapacityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Workforce Controller
 *
 * Provides API endpoints for SPO Workforce Management dashboard.
 * Implements OHaH's 80% FTE requirement tracking per RFP Q&A:
 * FTE ratio = [Full-time direct staff ÷ Total direct staff] × 100%
 *
 * Key features:
 * - FTE compliance tracking (headcount-based per Q&A)
 * - HHR complement breakdown by role and employment type
 * - Staff satisfaction metrics (>95% target)
 * - Utilization and capacity metrics
 * - SSPO staff tracking (excluded from FTE ratio)
 */
class WorkforceController extends Controller
{
    protected FteComplianceService $fteService;
    protected WorkforceCapacityService $capacityService;

    public function __construct(FteComplianceService $fteService, WorkforceCapacityService $capacityService)
    {
        $this->fteService = $fteService;
        $this->capacityService = $capacityService;
    }

    /**
     * GET /api/v2/workforce/summary
     *
     * Returns comprehensive workforce summary combining:
     * - FTE compliance metrics
     * - HHR complement by role
     * - Staff satisfaction
     * - Capacity utilization
     */
    public function summary(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if (!$this->authorizeOrganization($organizationId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $summary = $this->fteService->getWorkforceSummary($organizationId);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * GET /api/v2/workforce/capacity
     *
     * Returns workforce capacity vs required care hours.
     * Supports filtering by provider type (spo/sspo) and date range.
     *
     * Query params:
     * - period_type: 'week' | 'month' (default: 'week')
     * - start_date: Date string (default: start of current week/month)
     * - provider_type: 'spo' | 'sspo' | null (all)
     * - forecast_weeks: Number of weeks to forecast (default: 0)
     */
    public function capacity(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if (!$this->authorizeOrganization($organizationId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $periodType = $request->input('period_type', 'week');
        $providerType = $request->input('provider_type');
        $forecastWeeks = min((int) $request->input('forecast_weeks', 0), 12);

        // Calculate date range based on period type
        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->input('start_date'));
        } else {
            $startDate = $periodType === 'month'
                ? Carbon::now()->startOfMonth()
                : Carbon::now()->startOfWeek();
        }

        $endDate = $periodType === 'month'
            ? $startDate->copy()->endOfMonth()
            : $startDate->copy()->endOfWeek();

        // Get capacity snapshot
        $snapshot = $this->capacityService->getCapacitySnapshot(
            $organizationId,
            $startDate,
            $endDate,
            $providerType
        );

        // Get forecast if requested
        $forecast = [];
        if ($forecastWeeks > 0) {
            $forecast = $this->capacityService->getCapacityForecast(
                $organizationId,
                $forecastWeeks,
                $providerType
            );
        }

        // Get provider type comparison if no filter applied
        $providerComparison = null;
        if (!$providerType) {
            $providerComparison = $this->capacityService->getCapacityByProviderType(
                $organizationId,
                $startDate,
                $endDate
            );
        }

        return response()->json([
            'data' => [
                'snapshot' => $snapshot,
                'forecast' => $forecast,
                'provider_comparison' => $providerComparison,
            ],
            'meta' => [
                'period_type' => $periodType,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'provider_type' => $providerType,
                'forecast_weeks' => $forecastWeeks,
                'default_travel_minutes' => WorkforceCapacityService::DEFAULT_TRAVEL_MINUTES_PER_VISIT,
            ],
        ]);
    }

    /**
     * GET /api/v2/workforce/fte-trend
     *
     * Returns weekly FTE compliance trend for the specified number of weeks.
     * Default is 8 weeks of historical data.
     */
    public function fteTrend(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if (!$this->authorizeOrganization($organizationId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $weeks = min($request->input('weeks', 8), 52);
        $trend = $this->fteService->getComplianceTrend($weeks, $organizationId);

        return response()->json([
            'data' => $trend,
            'meta' => [
                'weeks' => $weeks,
                'target' => FteComplianceService::FTE_COMPLIANCE_TARGET,
                'warning_threshold' => FteComplianceService::FTE_WARNING_THRESHOLD,
            ],
        ]);
    }

    /**
     * GET /api/v2/workforce/hhr-complement
     *
     * Returns HHR (Human Health Resources) complement breakdown
     * by role (RN, RPN, PSW, etc.) and employment type (FT, PT, Casual, SSPO).
     */
    public function hhrComplement(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if (!$this->authorizeOrganization($organizationId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $complement = $this->fteService->getHhrComplement($organizationId);

        return response()->json([
            'data' => $complement,
        ]);
    }

    /**
     * GET /api/v2/workforce/satisfaction
     *
     * Returns staff satisfaction metrics.
     * Target per RFP: >95% satisfaction rate.
     */
    public function satisfaction(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if (!$this->authorizeOrganization($organizationId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $satisfaction = $this->fteService->getStaffSatisfactionMetrics($organizationId);

        return response()->json([
            'data' => $satisfaction,
        ]);
    }

    /**
     * GET /api/v2/workforce/staff
     *
     * Returns paginated list of staff members with role and employment type info.
     * Supports filtering by role, employment type, and status.
     */
    public function staff(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if (!$this->authorizeOrganization($organizationId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = User::where('organization_id', $organizationId)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->with(['staffRole', 'employmentTypeModel', 'organization']);

        // Filters
        if ($request->filled('staff_role_id')) {
            $query->where('staff_role_id', $request->input('staff_role_id'));
        }

        if ($request->filled('staff_role_code')) {
            $query->whereHas('staffRole', function ($q) use ($request) {
                $q->where('code', $request->input('staff_role_code'));
            });
        }

        if ($request->filled('employment_type_id')) {
            $query->where('employment_type_id', $request->input('employment_type_id'));
        }

        if ($request->filled('employment_type_code')) {
            $query->whereHas('employmentTypeModel', function ($q) use ($request) {
                $q->where('code', $request->input('employment_type_code'));
            });
        }

        if ($request->filled('status')) {
            $query->where('staff_status', $request->input('status'));
        } else {
            // Default to active staff
            $query->where(function ($q) {
                $q->where('staff_status', User::STAFF_STATUS_ACTIVE)
                  ->orWhereNull('staff_status');
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->input('per_page', 25), 100);
        $staff = $query->paginate($perPage);

        // Transform
        $staff->getCollection()->transform(function ($member) {
            return $this->transformStaffMember($member);
        });

        return response()->json($staff);
    }

    /**
     * GET /api/v2/workforce/utilization
     *
     * Returns staff utilization metrics for the organization.
     */
    public function utilization(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if (!$this->authorizeOrganization($organizationId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $utilization = $this->fteService->getStaffUtilization($organizationId);

        return response()->json([
            'data' => $utilization,
        ]);
    }

    /**
     * GET /api/v2/workforce/compliance-gap
     *
     * Returns analysis of what's needed to achieve FTE compliance.
     */
    public function complianceGap(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if (!$this->authorizeOrganization($organizationId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $gap = $this->fteService->calculateComplianceGap($organizationId);

        return response()->json([
            'data' => $gap,
        ]);
    }

    /**
     * GET /api/v2/workforce/hire-projection
     *
     * Project FTE ratio change if a new hire of specified type is made.
     */
    public function hireProjection(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if (!$this->authorizeOrganization($organizationId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $employmentType = $request->input('employment_type', 'full_time');
        $projection = $this->fteService->calculateProjection($employmentType, $organizationId);

        return response()->json([
            'data' => $projection,
        ]);
    }

    /**
     * GET /api/v2/workforce/metadata/roles
     *
     * Returns list of staff roles for filtering and forms.
     */
    public function metadataRoles(): JsonResponse
    {
        $roles = StaffRole::active()
            ->ordered()
            ->get()
            ->map(fn($role) => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
                'category' => $role->category,
                'category_label' => $role->category_label,
                'is_regulated' => $role->is_regulated,
                'counts_for_fte' => $role->counts_for_fte,
                'badge_color' => $role->badge_color,
            ]);

        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * GET /api/v2/workforce/metadata/employment-types
     *
     * Returns list of employment types for filtering and forms.
     */
    public function metadataEmploymentTypes(): JsonResponse
    {
        $types = EmploymentType::active()
            ->ordered()
            ->get()
            ->map(fn($type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'is_direct_staff' => $type->is_direct_staff,
                'is_full_time' => $type->is_full_time,
                'standard_hours_per_week' => $type->standard_hours_per_week,
                'fte_equivalent' => $type->fte_equivalent,
                'badge_color' => $type->badge_color,
            ]);

        return response()->json([
            'data' => $types,
        ]);
    }

    /**
     * GET /api/v2/workforce/assignment-summary
     *
     * Returns summary of service assignments for the week, split by source (internal/SSPO).
     */
    public function assignmentSummary(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if (!$this->authorizeOrganization($organizationId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $weekStart = $request->filled('week_start')
            ? Carbon::parse($request->input('week_start'))->startOfWeek()
            : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        // Get assignments for the week
        $assignments = ServiceAssignment::where('service_provider_organization_id', $organizationId)
            ->whereBetween('scheduled_start', [$weekStart, $weekEnd])
            ->whereIn('status', ['planned', 'active', 'in_progress', 'completed'])
            ->get();

        $internalAssignments = $assignments->where('source', ServiceAssignment::SOURCE_INTERNAL);
        $sspoAssignments = $assignments->where('source', ServiceAssignment::SOURCE_SSPO);

        $calculateHours = function ($collection) {
            return $collection->sum(function ($assignment) {
                if ($assignment->scheduled_start && $assignment->scheduled_end) {
                    return $assignment->scheduled_start->diffInMinutes($assignment->scheduled_end) / 60;
                }
                return $assignment->estimated_hours_per_week ?? 1;
            });
        };

        return response()->json([
            'data' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'internal' => [
                    'count' => $internalAssignments->count(),
                    'hours' => round($calculateHours($internalAssignments), 1),
                ],
                'sspo' => [
                    'count' => $sspoAssignments->count(),
                    'hours' => round($calculateHours($sspoAssignments), 1),
                ],
                'total' => [
                    'count' => $assignments->count(),
                    'hours' => round($calculateHours($assignments), 1),
                ],
            ],
        ]);
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * Get organization ID from request or current user.
     */
    protected function getOrganizationId(Request $request): ?int
    {
        $orgId = $request->input('organization_id');

        if (empty($orgId)) {
            return Auth::user()?->organization_id;
        }

        return (int) $orgId;
    }

    /**
     * Check if current user can access the specified organization.
     */
    protected function authorizeOrganization(?int $organizationId): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Master/admin can access any org
        if ($user->isMaster() || $user->role === User::ROLE_ADMIN) {
            return true;
        }

        // SPO/SSPO admins/coordinators can access their own org
        return $user->organization_id === $organizationId;
    }

    /**
     * Transform staff member for API response.
     */
    protected function transformStaffMember(User $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->role,
            'staff_status' => $member->staff_status ?? 'active',

            // Staff role (from metadata)
            'staff_role_id' => $member->staff_role_id,
            'staff_role_code' => $member->staffRole?->code,
            'staff_role_name' => $member->staffRole?->name,
            'staff_role_category' => $member->staffRole?->category,

            // Employment type (from metadata)
            'employment_type_id' => $member->employment_type_id,
            'employment_type_code' => $member->employmentTypeModel?->code,
            'employment_type_name' => $member->employmentTypeModel?->name,
            'is_direct_staff' => $member->employmentTypeModel?->is_direct_staff ?? true,
            'is_full_time' => $member->employmentTypeModel?->is_full_time ?? false,

            // Hours & capacity
            'max_weekly_hours' => $member->max_weekly_hours ?? FteComplianceService::FULL_TIME_HOURS_PER_WEEK,
            'fte_value' => $member->fte_value,
            'current_weekly_hours' => round($member->current_weekly_hours ?? 0, 1),
            'available_hours' => round($member->available_hours ?? 0, 1),
            'utilization_rate' => round($member->fte_utilization ?? 0, 1),

            // Satisfaction (if available)
            'job_satisfaction' => $member->job_satisfaction,
            'job_satisfaction_recorded_at' => $member->job_satisfaction_recorded_at?->toDateString(),

            // Dates
            'hire_date' => $member->hire_date,
            'organization_id' => $member->organization_id,
            'organization_name' => $member->organization?->name,

            // External reference
            'external_staff_id' => $member->external_staff_id,
        ];
    }
}
