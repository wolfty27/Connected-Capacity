<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ServiceAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * MobileWorklistController - Mobile worklist API for field staff
 *
 * Per MOB-002: Provides optimized endpoints for mobile worklist display
 * - Today's assignments with patient info
 * - Upcoming assignments
 * - Assignment details for offline caching
 */
class MobileWorklistController extends Controller
{
    /**
     * Get all active assignments for the current user.
     *
     * GET /api/mobile/worklist
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $startDate = $request->date('start_date', Carbon::today());
        $endDate = $request->date('end_date', Carbon::today()->addDays(7));

        $assignments = ServiceAssignment::where('assigned_user_id', $user->id)
            ->whereIn('status', ['pending', 'active', 'in_progress', 'planned'])
            ->whereBetween('scheduled_start', [$startDate, $endDate])
            ->with(['patient:id,first_name,last_name,address,phone', 'serviceType:id,name,code'])
            ->orderBy('scheduled_start')
            ->get();

        return response()->json([
            'data' => $assignments->map(fn ($a) => $this->formatAssignment($a)),
            'meta' => [
                'total' => $assignments->count(),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * Get today's assignments for quick access.
     *
     * GET /api/mobile/worklist/today
     */
    public function today(): JsonResponse
    {
        $user = Auth::user();

        $assignments = ServiceAssignment::where('assigned_user_id', $user->id)
            ->whereIn('status', ['pending', 'active', 'in_progress', 'planned'])
            ->whereDate('scheduled_start', Carbon::today())
            ->with(['patient:id,first_name,last_name,address,phone,emergency_contact', 'serviceType:id,name,code,default_duration_minutes'])
            ->orderBy('scheduled_start')
            ->get();

        // Calculate summary stats
        $completed = ServiceAssignment::where('assigned_user_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('scheduled_start', Carbon::today())
            ->count();

        return response()->json([
            'data' => $assignments->map(fn ($a) => $this->formatAssignment($a, true)),
            'summary' => [
                'total_scheduled' => $assignments->count() + $completed,
                'completed' => $completed,
                'remaining' => $assignments->count(),
                'next_assignment' => $assignments->first() ? $this->formatAssignment($assignments->first(), true) : null,
            ],
        ]);
    }

    /**
     * Get upcoming assignments for the week.
     *
     * GET /api/mobile/worklist/upcoming
     */
    public function upcoming(): JsonResponse
    {
        $user = Auth::user();

        $assignments = ServiceAssignment::where('assigned_user_id', $user->id)
            ->whereIn('status', ['pending', 'active', 'planned'])
            ->where('scheduled_start', '>', Carbon::today()->endOfDay())
            ->where('scheduled_start', '<=', Carbon::today()->addDays(7))
            ->with(['patient:id,first_name,last_name,address', 'serviceType:id,name,code'])
            ->orderBy('scheduled_start')
            ->get();

        // Group by date
        $grouped = $assignments->groupBy(fn ($a) => $a->scheduled_start->toDateString());

        return response()->json([
            'data' => $grouped->map(fn ($items, $date) => [
                'date' => $date,
                'day_name' => Carbon::parse($date)->format('l'),
                'assignments' => $items->map(fn ($a) => $this->formatAssignment($a)),
            ])->values(),
        ]);
    }

    /**
     * Get single assignment details.
     *
     * GET /api/mobile/worklist/{assignment}
     */
    public function show(ServiceAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $assignment->load([
            'patient:id,first_name,last_name,address,phone,emergency_contact,date_of_birth',
            'serviceType:id,name,code,description,default_duration_minutes',
            'carePlan:id,goals,interventions',
        ]);

        return response()->json([
            'data' => $this->formatAssignment($assignment, true),
        ]);
    }

    /**
     * Get patient details for an assignment.
     *
     * GET /api/mobile/worklist/{assignment}/patient
     */
    public function patientDetails(ServiceAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $patient = $assignment->patient;
        $patient->load(['latestInterraiAssessment']);

        return response()->json([
            'data' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),
                'age' => $patient->date_of_birth?->age,
                'address' => $patient->address,
                'phone' => $patient->phone,
                'emergency_contact' => $patient->emergency_contact,
                'risk_flags' => $patient->risk_flags ?? [],
                'maple_score' => $patient->maple_score,
                'high_risk_flags' => $patient->latestInterraiAssessment?->high_risk_flags ?? [],
                'special_instructions' => $patient->special_instructions ?? null,
            ],
        ]);
    }

    /**
     * Get care plan summary for an assignment.
     *
     * GET /api/mobile/worklist/{assignment}/care-plan
     */
    public function carePlanSummary(ServiceAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $carePlan = $assignment->carePlan;

        if (!$carePlan) {
            return response()->json([
                'data' => null,
                'message' => 'No care plan associated with this assignment',
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $carePlan->id,
                'status' => $carePlan->status,
                'goals' => $carePlan->goals ?? [],
                'interventions' => $carePlan->interventions ?? [],
                'risks' => $carePlan->risks ?? [],
                'notes' => $carePlan->notes,
                'approved_at' => $carePlan->approved_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Format assignment for mobile response.
     */
    protected function formatAssignment(ServiceAssignment $assignment, bool $detailed = false): array
    {
        $data = [
            'id' => $assignment->id,
            'status' => $assignment->status,
            'scheduled_start' => $assignment->scheduled_start?->toIso8601String(),
            'scheduled_end' => $assignment->scheduled_end?->toIso8601String(),
            'scheduled_time' => $assignment->scheduled_start?->format('g:i A'),
            'duration_minutes' => $assignment->serviceType?->default_duration_minutes ?? 60,
            'service' => [
                'id' => $assignment->serviceType?->id,
                'name' => $assignment->serviceType?->name,
                'code' => $assignment->serviceType?->code,
            ],
            'patient' => [
                'id' => $assignment->patient?->id,
                'name' => $assignment->patient?->name,
                'address' => $assignment->patient?->address,
            ],
            'actual_start' => $assignment->actual_start?->toIso8601String(),
            'actual_end' => $assignment->actual_end?->toIso8601String(),
            'is_clocked_in' => $assignment->actual_start && !$assignment->actual_end,
        ];

        if ($detailed) {
            $data['patient']['phone'] = $assignment->patient?->phone;
            $data['patient']['emergency_contact'] = $assignment->patient?->emergency_contact;
            $data['notes'] = $assignment->notes;
            $data['frequency_rule'] = $assignment->frequency_rule;
            $data['care_plan_id'] = $assignment->care_plan_id;
            $data['service']['description'] = $assignment->serviceType?->description;
        }

        return $data;
    }
}
