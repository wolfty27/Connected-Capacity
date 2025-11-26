<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\StaffAvailability;
use App\Models\StaffUnavailability;
use App\Models\User;
use App\Services\CareOps\FteComplianceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StaffController extends Controller
{
    protected FteComplianceService $fteService;

    public function __construct(FteComplianceService $fteService)
    {
        $this->fteService = $fteService;
    }

    /**
     * List staff members for the current organization
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $request->input('organization_id', $user->organization_id);

        // Only allow access to own org unless master
        if (!$user->isMaster() && $organizationId != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = User::where('organization_id', $organizationId)
            ->whereIn('role', [User::ROLE_FIELD_STAFF, User::ROLE_SPO_COORDINATOR, User::ROLE_SSPO_COORDINATOR]);

        // Filters
        if ($request->filled('status')) {
            $query->where('staff_status', $request->input('status'));
        }

        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->input('employment_type'));
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('skill')) {
            $query->withSkill($request->input('skill'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Include relationships
        $query->with(['skills', 'organization']);

        // Sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $staff = $query->paginate($perPage);

        // Add computed properties
        $staff->getCollection()->transform(function ($member) {
            return $this->transformStaffMember($member);
        });

        return response()->json($staff);
    }

    /**
     * Get a single staff member
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        $staff = User::with(['skills', 'organization', 'availabilities', 'unavailabilities'])
            ->findOrFail($id);

        // Authorization check
        if (!$user->isMaster() && $staff->organization_id != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $this->transformStaffMember($staff, true),
        ]);
    }

    /**
     * Create a new staff member
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:FIELD_STAFF,SPO_COORDINATOR,SSPO_COORDINATOR',
            'organization_id' => 'required|exists:service_provider_organizations,id',
            'organization_role' => 'nullable|string|max:100',
            'employment_type' => 'required|in:full_time,part_time,casual',
            'fte_value' => 'nullable|numeric|min:0|max:1',
            'max_weekly_hours' => 'nullable|numeric|min:0|max:168',
            'hire_date' => 'nullable|date',
            'external_id' => 'nullable|string|max:100',
            'phone_number' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $organizationId = $request->input('organization_id');

        // Authorization check
        if (!$user->isMaster() && $organizationId != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $staff = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => bcrypt($request->input('password')),
            'role' => $request->input('role'),
            'organization_id' => $organizationId,
            'organization_role' => $request->input('organization_role'),
            'employment_type' => $request->input('employment_type'),
            'fte_value' => $request->input('fte_value', $request->input('employment_type') === 'full_time' ? 1.0 : 0.5),
            'max_weekly_hours' => $request->input('max_weekly_hours', 40),
            'staff_status' => User::STAFF_STATUS_ACTIVE,
            'hire_date' => $request->input('hire_date'),
            'external_id' => $request->input('external_id'),
            'phone_number' => $request->input('phone_number'),
        ]);

        return response()->json([
            'message' => 'Staff member created successfully',
            'data' => $this->transformStaffMember($staff->fresh(['skills', 'organization'])),
        ], 201);
    }

    /**
     * Update a staff member
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $staff = User::findOrFail($id);
        $user = Auth::user();

        // Authorization check
        if (!$user->isMaster() && $staff->organization_id != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'role' => 'sometimes|in:FIELD_STAFF,SPO_COORDINATOR,SSPO_COORDINATOR',
            'organization_role' => 'nullable|string|max:100',
            'employment_type' => 'sometimes|in:full_time,part_time,casual',
            'fte_value' => 'nullable|numeric|min:0|max:1',
            'max_weekly_hours' => 'nullable|numeric|min:0|max:168',
            'staff_status' => 'sometimes|in:active,inactive,on_leave,terminated',
            'hire_date' => 'nullable|date',
            'termination_date' => 'nullable|date',
            'external_id' => 'nullable|string|max:100',
            'phone_number' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $staff->fill($request->only([
            'name', 'email', 'role', 'organization_role',
            'employment_type', 'fte_value', 'max_weekly_hours',
            'staff_status', 'hire_date', 'termination_date',
            'external_id', 'phone_number'
        ]));
        $staff->save();

        return response()->json([
            'message' => 'Staff member updated successfully',
            'data' => $this->transformStaffMember($staff->fresh(['skills', 'organization'])),
        ]);
    }

    /**
     * Delete (soft) a staff member
     */
    public function destroy(int $id): JsonResponse
    {
        $staff = User::findOrFail($id);
        $user = Auth::user();

        // Authorization check
        if (!$user->isMaster() && $staff->organization_id != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Soft delete and set status
        $staff->staff_status = User::STAFF_STATUS_TERMINATED;
        $staff->termination_date = Carbon::today();
        $staff->save();
        $staff->delete();

        return response()->json([
            'message' => 'Staff member removed successfully',
        ]);
    }

    // ==========================================
    // Skills Management
    // ==========================================

    /**
     * List all available skills
     */
    public function listSkills(Request $request): JsonResponse
    {
        $query = Skill::active();

        if ($request->filled('category')) {
            $query->category($request->input('category'));
        }

        $skills = $query->orderBy('category')->orderBy('name')->get();

        return response()->json(['data' => $skills]);
    }

    /**
     * Get staff member's skills
     */
    public function getStaffSkills(int $staffId): JsonResponse
    {
        $staff = User::findOrFail($staffId);
        $user = Auth::user();

        if (!$user->isMaster() && $staff->organization_id != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $skills = $staff->skills()->get()->map(function ($skill) {
            return [
                'id' => $skill->id,
                'name' => $skill->name,
                'code' => $skill->code,
                'category' => $skill->category,
                'category_label' => $skill->category_label,
                'proficiency_level' => $skill->pivot->proficiency_level,
                'certified_at' => $skill->pivot->certified_at,
                'expires_at' => $skill->pivot->expires_at,
                'is_expired' => $skill->pivot->expires_at && Carbon::parse($skill->pivot->expires_at)->isPast(),
                'is_expiring_soon' => $skill->pivot->expires_at &&
                    Carbon::parse($skill->pivot->expires_at)->isBetween(Carbon::today(), Carbon::today()->addDays(30)),
                'verified_at' => $skill->pivot->verified_at,
                'certification_number' => $skill->pivot->certification_number,
            ];
        });

        return response()->json(['data' => $skills]);
    }

    /**
     * Assign a skill to a staff member
     */
    public function assignSkill(Request $request, int $staffId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'skill_id' => 'required|exists:skills,id',
            'proficiency_level' => 'required|in:basic,competent,proficient,expert',
            'certified_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:certified_at',
            'certification_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $staff = User::findOrFail($staffId);
        $user = Auth::user();

        if (!$user->isMaster() && $staff->organization_id != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $staff->skills()->syncWithoutDetaching([
            $request->input('skill_id') => [
                'proficiency_level' => $request->input('proficiency_level'),
                'certified_at' => $request->input('certified_at'),
                'expires_at' => $request->input('expires_at'),
                'certification_number' => $request->input('certification_number'),
                'verified_by' => $user->id,
                'verified_at' => Carbon::now(),
            ]
        ]);

        return response()->json([
            'message' => 'Skill assigned successfully',
            'data' => $this->getStaffSkills($staffId)->getData(),
        ]);
    }

    /**
     * Remove a skill from a staff member
     */
    public function removeSkill(int $staffId, int $skillId): JsonResponse
    {
        $staff = User::findOrFail($staffId);
        $user = Auth::user();

        if (!$user->isMaster() && $staff->organization_id != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $staff->skills()->detach($skillId);

        return response()->json([
            'message' => 'Skill removed successfully',
        ]);
    }

    // ==========================================
    // Availability Management
    // ==========================================

    /**
     * Get staff member's availability
     */
    public function getAvailability(int $staffId): JsonResponse
    {
        $staff = User::findOrFail($staffId);
        $user = Auth::user();

        if (!$user->isMaster() && $staff->organization_id != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $availabilities = $staff->availabilities()
            ->currentlyEffective()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(function ($avail) {
                return [
                    'id' => $avail->id,
                    'day_of_week' => $avail->day_of_week,
                    'day_name' => $avail->day_name,
                    'start_time' => $avail->start_time,
                    'end_time' => $avail->end_time,
                    'time_range' => $avail->time_range,
                    'duration_hours' => $avail->duration_hours,
                    'effective_from' => $avail->effective_from,
                    'effective_until' => $avail->effective_until,
                    'is_recurring' => $avail->is_recurring,
                    'service_areas' => $avail->service_areas,
                ];
            });

        return response()->json(['data' => $availabilities]);
    }

    /**
     * Set staff availability (replace all)
     */
    public function setAvailability(Request $request, int $staffId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'availabilities' => 'required|array',
            'availabilities.*.day_of_week' => 'required|integer|min:0|max:6',
            'availabilities.*.start_time' => 'required|date_format:H:i',
            'availabilities.*.end_time' => 'required|date_format:H:i|after:availabilities.*.start_time',
            'availabilities.*.effective_from' => 'nullable|date',
            'availabilities.*.effective_until' => 'nullable|date|after:availabilities.*.effective_from',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $staff = User::findOrFail($staffId);
        $user = Auth::user();

        if (!$user->isMaster() && $staff->organization_id != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::transaction(function () use ($staff, $request) {
            // End current availabilities
            $staff->availabilities()
                ->whereNull('effective_until')
                ->update(['effective_until' => Carbon::yesterday()]);

            // Create new availabilities
            foreach ($request->input('availabilities') as $avail) {
                $staff->availabilities()->create([
                    'day_of_week' => $avail['day_of_week'],
                    'start_time' => $avail['start_time'],
                    'end_time' => $avail['end_time'],
                    'effective_from' => $avail['effective_from'] ?? Carbon::today(),
                    'effective_until' => $avail['effective_until'] ?? null,
                    'is_recurring' => true,
                    'service_areas' => $avail['service_areas'] ?? null,
                ]);
            }
        });

        return response()->json([
            'message' => 'Availability updated successfully',
            'data' => $this->getAvailability($staffId)->getData(),
        ]);
    }

    // ==========================================
    // Unavailability (Time-Off) Management
    // ==========================================

    /**
     * Get staff unavailabilities
     */
    public function getUnavailabilities(Request $request, int $staffId): JsonResponse
    {
        $staff = User::findOrFail($staffId);
        $user = Auth::user();

        if (!$user->isMaster() && $staff->organization_id != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = $staff->unavailabilities();

        if ($request->filled('status')) {
            $query->where('approval_status', $request->input('status'));
        }

        if ($request->boolean('future_only')) {
            $query->future();
        }

        $unavailabilities = $query->orderBy('start_datetime')->get()->map(function ($unavail) {
            return [
                'id' => $unavail->id,
                'type' => $unavail->unavailability_type,
                'type_label' => $unavail->type_label,
                'start_datetime' => $unavail->start_datetime,
                'end_datetime' => $unavail->end_datetime,
                'is_all_day' => $unavail->is_all_day,
                'duration_hours' => $unavail->duration_hours,
                'reason' => $unavail->reason,
                'approval_status' => $unavail->approval_status,
                'status_label' => $unavail->status_label,
                'approved_by' => $unavail->approver?->name,
                'approved_at' => $unavail->approved_at,
            ];
        });

        return response()->json(['data' => $unavailabilities]);
    }

    /**
     * Request time off
     */
    public function requestTimeOff(Request $request, int $staffId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'unavailability_type' => 'required|in:vacation,sick,personal,training,jury_duty,bereavement,maternity,paternity,medical,other',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'is_all_day' => 'boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $staff = User::findOrFail($staffId);
        $user = Auth::user();

        if (!$user->isMaster() && $staff->organization_id != $user->organization_id && $staff->id != $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $unavailability = StaffUnavailability::create([
            'user_id' => $staffId,
            'unavailability_type' => $request->input('unavailability_type'),
            'start_datetime' => $request->input('start_datetime'),
            'end_datetime' => $request->input('end_datetime'),
            'is_all_day' => $request->boolean('is_all_day'),
            'reason' => $request->input('reason'),
            'approval_status' => StaffUnavailability::STATUS_PENDING,
            'requested_by' => $user->id,
            'requested_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Time-off request submitted',
            'data' => $unavailability,
        ], 201);
    }

    /**
     * Approve/deny time off request
     */
    public function processTimeOffRequest(Request $request, int $unavailabilityId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,deny',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $unavailability = StaffUnavailability::findOrFail($unavailabilityId);
        $user = Auth::user();
        $staff = $unavailability->user;

        // Must be coordinator or admin in same org
        if (!$user->isMaster() && $staff->organization_id != $user->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($request->input('action') === 'approve') {
            $unavailability->approve($user, $request->input('notes'));
            $message = 'Time-off request approved';
        } else {
            $unavailability->deny($user, $request->input('notes'));
            $message = 'Time-off request denied';
        }

        return response()->json([
            'message' => $message,
            'data' => $unavailability->fresh(),
        ]);
    }

    // ==========================================
    // FTE Compliance & Analytics
    // ==========================================

    /**
     * Get FTE compliance snapshot
     */
    public function getFteCompliance(Request $request): JsonResponse
    {
        $organizationId = $request->input('organization_id', Auth::user()->organization_id);

        return response()->json([
            'data' => $this->fteService->calculateSnapshot($organizationId),
        ]);
    }

    /**
     * Get FTE compliance trend
     */
    public function getFteComplianceTrend(Request $request): JsonResponse
    {
        $weeks = $request->input('weeks', 8);
        $organizationId = $request->input('organization_id', Auth::user()->organization_id);

        return response()->json([
            'data' => $this->fteService->getComplianceTrend($weeks, $organizationId),
        ]);
    }

    /**
     * Get staff utilization report
     */
    public function getStaffUtilization(Request $request): JsonResponse
    {
        $organizationId = $request->input('organization_id', Auth::user()->organization_id);

        return response()->json([
            'data' => $this->fteService->getStaffUtilization($organizationId),
        ]);
    }

    /**
     * Calculate hire projection
     */
    public function getHireProjection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employment_type' => 'required|in:full_time,part_time,casual',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return response()->json([
            'data' => $this->fteService->calculateProjection($request->input('employment_type')),
        ]);
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * Transform staff member for API response
     */
    protected function transformStaffMember(User $staff, bool $detailed = false): array
    {
        $data = [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role,
            'organization_id' => $staff->organization_id,
            'organization_name' => $staff->organization?->name,
            'organization_role' => $staff->organization_role,
            'employment_type' => $staff->employment_type,
            'fte_value' => $staff->fte_value,
            'max_weekly_hours' => $staff->max_weekly_hours ?? 40,
            'staff_status' => $staff->staff_status ?? 'active',
            'hire_date' => $staff->hire_date,
            'external_id' => $staff->external_id,
            'phone_number' => $staff->phone_number,

            // Computed properties
            'current_weekly_hours' => round($staff->current_weekly_hours, 1),
            'available_hours' => round($staff->available_hours, 1),
            'utilization_rate' => round($staff->fte_utilization, 1),
            'is_on_leave' => $staff->isOnLeave(),

            // Skills summary
            'skills_count' => $staff->skills->count(),
            'expiring_skills_count' => $staff->getExpiringSkills(30)->count(),
        ];

        if ($detailed) {
            $data['skills'] = $staff->skills->map(function ($skill) {
                return [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'code' => $skill->code,
                    'category' => $skill->category,
                    'proficiency_level' => $skill->pivot->proficiency_level,
                    'expires_at' => $skill->pivot->expires_at,
                ];
            });

            $data['availabilities'] = $staff->availabilities()
                ->currentlyEffective()
                ->get()
                ->map(fn($a) => [
                    'day_of_week' => $a->day_of_week,
                    'day_name' => $a->day_name,
                    'time_range' => $a->time_range,
                ]);

            $data['upcoming_unavailabilities'] = $staff->unavailabilities()
                ->approved()
                ->future()
                ->take(5)
                ->get()
                ->map(fn($u) => [
                    'type' => $u->type_label,
                    'start' => $u->start_datetime,
                    'end' => $u->end_datetime,
                ]);
        }

        return $data;
    }
}
