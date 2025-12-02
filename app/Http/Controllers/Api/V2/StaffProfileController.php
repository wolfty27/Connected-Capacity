<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Skill;
use App\Models\StaffAvailability;
use App\Models\StaffUnavailability;
use App\Services\StaffProfileService;
use App\Services\StaffScheduleService;
use App\Services\StaffSatisfactionService;
use App\Services\StaffTravelMetricsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * StaffProfileController
 * 
 * API endpoints for staff profile management.
 * All business logic is in the domain services; this controller only handles HTTP.
 */
class StaffProfileController extends Controller
{
    public function __construct(
        protected StaffProfileService $profileService,
        protected StaffScheduleService $scheduleService,
        protected StaffSatisfactionService $satisfactionService,
        protected StaffTravelMetricsService $travelService
    ) {}

    /**
     * GET /api/v2/staff/{id}/profile
     * Get complete staff profile.
     */
    public function show(int $id): JsonResponse
    {
        $profile = $this->profileService->getProfile($id);
        
        if (!$profile) {
            return response()->json([
                'error' => 'Staff member not found',
            ], 404);
        }
        
        return response()->json([
            'data' => $profile,
        ]);
    }

    /**
     * PATCH /api/v2/staff/{id}/status
     * Update staff status (active, inactive, on_leave, terminated).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:active,inactive,on_leave,terminated',
            'leave_end_date' => 'nullable|date|after:today',
            'leave_type' => 'nullable|string',
            'reason' => 'nullable|string|max:500',
        ]);
        
        $success = $this->profileService->updateStatus(
            $id, 
            $validated['status'],
            $validated
        );
        
        if (!$success) {
            return response()->json([
                'error' => 'Failed to update status',
            ], 422);
        }
        
        return response()->json([
            'message' => 'Status updated successfully',
            'data' => $this->profileService->getProfile($id),
        ]);
    }

    /**
     * POST /api/v2/staff/{id}/scheduling-lock
     * Lock staff from scheduling.
     */
    public function lockScheduling(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);
        
        $success = $this->profileService->lockScheduling($id, $validated['reason'] ?? null);
        
        if (!$success) {
            return response()->json([
                'error' => 'Failed to lock scheduling',
            ], 422);
        }
        
        return response()->json([
            'message' => 'Scheduling locked successfully',
        ]);
    }

    /**
     * DELETE /api/v2/staff/{id}/scheduling-lock
     * Unlock staff for scheduling.
     */
    public function unlockScheduling(int $id): JsonResponse
    {
        $success = $this->profileService->unlockScheduling($id);
        
        if (!$success) {
            return response()->json([
                'error' => 'Failed to unlock scheduling',
            ], 422);
        }
        
        return response()->json([
            'message' => 'Scheduling unlocked successfully',
        ]);
    }

    /**
     * GET /api/v2/staff/{id}/schedule
     * Get staff schedule (upcoming and recent appointments).
     */
    public function schedule(Request $request, int $id): JsonResponse
    {
        $weekStart = $request->input('week_start')
            ? Carbon::parse($request->input('week_start'))
            : Carbon::now()->startOfWeek();
        
        return response()->json([
            'data' => [
                'summary' => $this->scheduleService->getScheduleSummary($id),
                'weekly_schedule' => $this->scheduleService->getWeeklyScheduleByDay($id, $weekStart),
                'upcoming' => $this->scheduleService->getUpcomingAppointments($id),
                'recent' => $this->scheduleService->getRecentAppointments($id),
            ],
        ]);
    }

    /**
     * GET /api/v2/staff/{id}/availability
     * Get staff availability blocks.
     */
    public function availability(int $id): JsonResponse
    {
        return response()->json([
            'data' => $this->profileService->getStaffAvailability($id),
        ]);
    }

    /**
     * POST /api/v2/staff/{id}/availability
     * Create or update availability block.
     */
    public function storeAvailability(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_recurring' => 'boolean',
            'service_areas' => 'nullable|array',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);
        
        $availability = StaffAvailability::create([
            'user_id' => $id,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'is_recurring' => $validated['is_recurring'] ?? true,
            'service_areas' => $validated['service_areas'] ?? null,
            'effective_from' => $validated['effective_from'] ?? Carbon::now(),
            'effective_to' => $validated['effective_to'] ?? null,
        ]);
        
        return response()->json([
            'message' => 'Availability created successfully',
            'data' => $availability,
        ], 201);
    }

    /**
     * DELETE /api/v2/staff/{id}/availability/{availabilityId}
     * Delete availability block.
     */
    public function destroyAvailability(int $id, int $availabilityId): JsonResponse
    {
        $availability = StaffAvailability::where('user_id', $id)
            ->where('id', $availabilityId)
            ->first();
        
        if (!$availability) {
            return response()->json([
                'error' => 'Availability block not found',
            ], 404);
        }
        
        $availability->delete();
        
        return response()->json([
            'message' => 'Availability deleted successfully',
        ]);
    }

    /**
     * GET /api/v2/staff/{id}/unavailabilities
     * Get staff unavailabilities (time off).
     */
    public function unavailabilities(Request $request, int $id): JsonResponse
    {
        $includeHistory = $request->boolean('include_history', false);
        
        return response()->json([
            'data' => $this->profileService->getStaffUnavailabilities($id, $includeHistory),
        ]);
    }

    /**
     * POST /api/v2/staff/{id}/unavailabilities
     * Create time off request.
     */
    public function storeUnavailability(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'unavailability_type' => 'required|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'is_all_day' => 'boolean',
            'reason' => 'nullable|string|max:500',
        ]);
        
        $unavailability = StaffUnavailability::create([
            'user_id' => $id,
            'unavailability_type' => $validated['unavailability_type'],
            'start_datetime' => $validated['start_datetime'],
            'end_datetime' => $validated['end_datetime'],
            'is_all_day' => $validated['is_all_day'] ?? false,
            'reason' => $validated['reason'] ?? null,
            'approval_status' => StaffUnavailability::STATUS_PENDING,
        ]);
        
        return response()->json([
            'message' => 'Time off request created successfully',
            'data' => $unavailability,
        ], 201);
    }

    /**
     * PATCH /api/v2/staff/{id}/unavailabilities/{unavailabilityId}
     * Update time off request (e.g., approve/deny).
     */
    public function updateUnavailability(Request $request, int $id, int $unavailabilityId): JsonResponse
    {
        $unavailability = StaffUnavailability::where('user_id', $id)
            ->where('id', $unavailabilityId)
            ->first();
        
        if (!$unavailability) {
            return response()->json([
                'error' => 'Time off request not found',
            ], 404);
        }
        
        $validated = $request->validate([
            'approval_status' => 'nullable|string|in:pending,approved,denied',
            'start_datetime' => 'nullable|date',
            'end_datetime' => 'nullable|date|after:start_datetime',
            'reason' => 'nullable|string|max:500',
        ]);
        
        if (isset($validated['approval_status'])) {
            $unavailability->approval_status = $validated['approval_status'];
            if ($validated['approval_status'] === StaffUnavailability::STATUS_APPROVED) {
                $unavailability->approved_at = Carbon::now();
                $unavailability->approved_by = auth()->id();
            }
        }
        
        $unavailability->fill(array_filter($validated, fn($v, $k) => $k !== 'approval_status', ARRAY_FILTER_USE_BOTH));
        $unavailability->save();
        
        return response()->json([
            'message' => 'Time off request updated successfully',
            'data' => $unavailability,
        ]);
    }

    /**
     * DELETE /api/v2/staff/{id}/unavailabilities/{unavailabilityId}
     * Cancel time off request.
     */
    public function destroyUnavailability(int $id, int $unavailabilityId): JsonResponse
    {
        $unavailability = StaffUnavailability::where('user_id', $id)
            ->where('id', $unavailabilityId)
            ->first();
        
        if (!$unavailability) {
            return response()->json([
                'error' => 'Time off request not found',
            ], 404);
        }
        
        $unavailability->delete();
        
        return response()->json([
            'message' => 'Time off request cancelled successfully',
        ]);
    }

    /**
     * GET /api/v2/staff/{id}/skills
     * Get staff skills.
     */
    public function skills(int $id): JsonResponse
    {
        return response()->json([
            'data' => $this->profileService->getStaffSkills($id),
        ]);
    }

    /**
     * POST /api/v2/staff/{id}/skills
     * Add skill to staff.
     */
    public function storeSkill(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'proficiency_level' => 'nullable|string|in:basic,competent,proficient,expert',
            'certified_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:certified_at',
        ]);
        
        $staff = User::find($id);
        if (!$staff) {
            return response()->json(['error' => 'Staff member not found'], 404);
        }
        
        // Check if skill already exists
        if ($staff->skills()->where('skill_id', $validated['skill_id'])->exists()) {
            return response()->json([
                'error' => 'Skill already assigned to staff member',
            ], 422);
        }
        
        $skill = Skill::find($validated['skill_id']);
        
        // Calculate expiration if skill requires certification
        $expiresAt = $validated['expires_at'] ?? null;
        if (!$expiresAt && $skill->requires_certification && $skill->renewal_period_months) {
            $certifiedAt = $validated['certified_at'] ?? Carbon::now();
            $expiresAt = Carbon::parse($certifiedAt)->addMonths($skill->renewal_period_months);
        }
        
        $staff->skills()->attach($validated['skill_id'], [
            'proficiency_level' => $validated['proficiency_level'] ?? 'competent',
            'certified_at' => $validated['certified_at'] ?? Carbon::now(),
            'expires_at' => $expiresAt,
        ]);
        
        return response()->json([
            'message' => 'Skill added successfully',
            'data' => $this->profileService->getStaffSkills($id),
        ], 201);
    }

    /**
     * DELETE /api/v2/staff/{id}/skills/{skillId}
     * Remove skill from staff.
     */
    public function destroySkill(int $id, int $skillId): JsonResponse
    {
        $staff = User::find($id);
        if (!$staff) {
            return response()->json(['error' => 'Staff member not found'], 404);
        }
        
        $staff->skills()->detach($skillId);
        
        return response()->json([
            'message' => 'Skill removed successfully',
        ]);
    }

    /**
     * GET /api/v2/staff/{id}/satisfaction
     * Get staff satisfaction metrics.
     */
    public function satisfaction(Request $request, int $id): JsonResponse
    {
        $days = $request->input('days', 90);
        
        return response()->json([
            'data' => [
                'summary' => $this->satisfactionService->getStaffSatisfaction($id, $days),
                'breakdown' => $this->satisfactionService->getSatisfactionBreakdown($id, $days),
                'trend' => $this->satisfactionService->getSatisfactionTrend($id),
                'recent_reports' => $this->satisfactionService->getRecentReports($id),
            ],
        ]);
    }

    /**
     * GET /api/v2/staff/{id}/travel
     * Get staff travel metrics.
     */
    public function travel(Request $request, int $id): JsonResponse
    {
        $weekStart = $request->input('week_start')
            ? Carbon::parse($request->input('week_start'))
            : Carbon::now()->startOfWeek();
        
        return response()->json([
            'data' => [
                'weekly_metrics' => $this->travelService->getWeeklyTravelMetrics($id, $weekStart),
                'assignment_details' => $this->travelService->getAssignmentTravelDetails($id),
                'estimated_weekly_overhead' => $this->travelService->estimateWeeklyTravelOverhead($id),
            ],
        ]);
    }

    /**
     * GET /api/v2/skills
     * Get all available skills (for skill assignment).
     */
    public function availableSkills(): JsonResponse
    {
        return response()->json([
            'data' => $this->profileService->getAvailableSkills(),
        ]);
    }

    /**
     * POST /api/v2/staff/{id}/send-password-reset
     * Send password reset email to staff member.
     */
    public function sendPasswordReset(int $id): JsonResponse
    {
        $success = $this->profileService->sendPasswordReset($id);
        
        if (!$success) {
            return response()->json([
                'error' => 'Failed to send password reset email',
            ], 422);
        }
        
        return response()->json([
            'message' => 'Password reset email sent successfully',
        ]);
    }

    /**
     * DELETE /api/v2/staff/{id}
     * Soft delete staff member (set status to terminated).
     */
    public function destroy(int $id): JsonResponse
    {
        $success = $this->profileService->softDelete($id);
        
        if (!$success) {
            return response()->json([
                'error' => 'Failed to delete staff member',
            ], 422);
        }
        
        return response()->json([
            'message' => 'Staff member deleted successfully',
        ]);
    }
}
