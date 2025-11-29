<?php

namespace App\Http\Controllers\Api\V2;

use App\DTOs\RequiredAssignmentDTO;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Services\Scheduling\CareBundleAssignmentPlanner;
use App\Services\Scheduling\SchedulingEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class SchedulingController extends Controller
{
    public function __construct(
        private SchedulingEngine $engine,
        private CareBundleAssignmentPlanner $planner
    ) {}

    /**
     * GET /v2/scheduling/requirements
     *
     * Returns unscheduled care requirements for all patients.
     * This powers the Unscheduled Care panel.
     */
    public function requirements(Request $request): JsonResponse
    {
        $startDate = $request->has('start_date')
            ? Carbon::parse($request->input('start_date'))
            : Carbon::now()->startOfWeek();

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->input('end_date'))
            : Carbon::now()->endOfWeek();

        $patientId = $request->input('patient_id');
        $organizationId = $request->input('organization_id');

        $requirements = $this->planner->getUnscheduledRequirements(
            $organizationId,
            $startDate,
            $endDate,
            $patientId
        );

        $summary = $this->planner->getSummaryStats(
            $organizationId,
            $startDate,
            $endDate
        );

        return response()->json([
            'data' => $requirements->map(fn($dto) => $dto->toArray())->values(),
            'summary' => $summary,
        ]);
    }

    /**
     * GET /v2/scheduling/patient-timeline
     *
     * Returns assignments for a specific patient, sorted by time.
     * This powers the Patient Timeline view.
     */
    public function patientTimeline(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
        ]);

        $patientId = $request->input('patient_id');

        $startDate = $request->has('start_date')
            ? Carbon::parse($request->input('start_date'))
            : Carbon::now()->startOfWeek();

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->input('end_date'))
            : Carbon::now()->endOfWeek();

        $patient = Patient::find($patientId);

        $assignments = ServiceAssignment::query()
            ->forPatient($patientId)
            ->scheduled()
            ->inDateRange($startDate, $endDate)
            ->with(['serviceType'])
            ->orderBy('scheduled_start')
            ->get();

        // Group by date for easier frontend rendering
        $groupedByDate = $assignments->groupBy(function ($assignment) {
            return $assignment->scheduled_start->format('Y-m-d');
        });

        $days = [];
        foreach ($groupedByDate as $date => $dayAssignments) {
            $totalMinutes = $dayAssignments->sum('duration_minutes');
            $days[] = [
                'date' => $date,
                'day_name' => Carbon::parse($date)->format('l'),
                'total_hours' => round($totalMinutes / 60, 1),
                'assignments' => $dayAssignments->map(fn($a) => $this->formatAssignment($a))->values(),
            ];
        }

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->full_name,
                'rug_category' => $patient->rug_category,
            ],
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'days' => $days,
        ]);
    }

    /**
     * POST /v2/scheduling/assignments
     *
     * Create a new service assignment.
     * Validates patient non-concurrency and spacing rules.
     */
    public function storeAssignment(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'service_type_id' => 'required|integer|exists:service_types,id',
            'care_plan_id' => 'sometimes|integer|exists:care_plans,id',
            'assigned_user_id' => 'sometimes|integer',
            'scheduled_start' => 'required|date',
            'scheduled_end' => 'required|date|after:scheduled_start',
            'visit_label' => 'sometimes|string|max:100',
            'notes' => 'sometimes|string|max:1000',
        ]);

        $patientId = $request->input('patient_id');
        $serviceTypeId = $request->input('service_type_id');
        $start = Carbon::parse($request->input('scheduled_start'));
        $end = Carbon::parse($request->input('scheduled_end'));

        // Validate using SchedulingEngine
        $validation = $this->engine->validateAssignment(
            $patientId,
            $serviceTypeId,
            $start,
            $end
        );

        if (!$validation['valid']) {
            return response()->json([
                'message' => 'Assignment validation failed.',
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ], 422);
        }

        // Create the assignment
        $assignment = ServiceAssignment::create([
            'patient_id' => $patientId,
            'service_type_id' => $serviceTypeId,
            'care_plan_id' => $request->input('care_plan_id'),
            'assigned_user_id' => $request->input('assigned_user_id'),
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'duration_minutes' => $start->diffInMinutes($end),
            'status' => ServiceAssignment::STATUS_PLANNED,
            'visit_label' => $request->input('visit_label'),
            'notes' => $request->input('notes'),
        ]);

        $assignment->load('serviceType', 'patient');

        return response()->json([
            'message' => 'Assignment created successfully.',
            'data' => $this->formatAssignment($assignment),
            'warnings' => $validation['warnings'],
        ], 201);
    }

    /**
     * PATCH /v2/scheduling/assignments/{id}
     *
     * Update an existing service assignment.
     * Validates patient non-concurrency and spacing rules.
     */
    public function updateAssignment(Request $request, int $id): JsonResponse
    {
        $assignment = ServiceAssignment::findOrFail($id);

        $request->validate([
            'scheduled_start' => 'sometimes|date',
            'scheduled_end' => 'sometimes|date|after:scheduled_start',
            'assigned_user_id' => 'sometimes|integer',
            'status' => 'sometimes|string|in:pending,planned,active,completed,cancelled,missed',
            'visit_label' => 'sometimes|string|max:100',
            'notes' => 'sometimes|string|max:1000',
        ]);

        $start = $request->has('scheduled_start')
            ? Carbon::parse($request->input('scheduled_start'))
            : $assignment->scheduled_start;

        $end = $request->has('scheduled_end')
            ? Carbon::parse($request->input('scheduled_end'))
            : $assignment->scheduled_end;

        // If times are changing, validate
        if ($request->has('scheduled_start') || $request->has('scheduled_end')) {
            $validation = $this->engine->validateAssignment(
                $assignment->patient_id,
                $assignment->service_type_id,
                $start,
                $end,
                $assignment->id // Exclude self from overlap check
            );

            if (!$validation['valid']) {
                return response()->json([
                    'message' => 'Assignment validation failed.',
                    'errors' => $validation['errors'],
                    'warnings' => $validation['warnings'],
                ], 422);
            }
        }

        // Update the assignment
        $assignment->update([
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'duration_minutes' => $start->diffInMinutes($end),
            'assigned_user_id' => $request->input('assigned_user_id', $assignment->assigned_user_id),
            'status' => $request->input('status', $assignment->status),
            'visit_label' => $request->input('visit_label', $assignment->visit_label),
            'notes' => $request->input('notes', $assignment->notes),
        ]);

        $assignment->load('serviceType', 'patient');

        return response()->json([
            'message' => 'Assignment updated successfully.',
            'data' => $this->formatAssignment($assignment),
        ]);
    }

    /**
     * DELETE /v2/scheduling/assignments/{id}
     *
     * Delete (soft-delete) a service assignment.
     */
    public function deleteAssignment(int $id): JsonResponse
    {
        $assignment = ServiceAssignment::findOrFail($id);
        $assignment->delete();

        return response()->json([
            'message' => 'Assignment deleted successfully.',
        ]);
    }

    /**
     * GET /v2/scheduling/validate
     *
     * Validate a proposed assignment without creating it.
     * Useful for real-time UI feedback.
     */
    public function validateAssignment(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'service_type_id' => 'required|integer|exists:service_types,id',
            'scheduled_start' => 'required|date',
            'scheduled_end' => 'required|date|after:scheduled_start',
            'ignore_assignment_id' => 'sometimes|integer',
        ]);

        $validation = $this->engine->validateAssignment(
            $request->input('patient_id'),
            $request->input('service_type_id'),
            Carbon::parse($request->input('scheduled_start')),
            Carbon::parse($request->input('scheduled_end')),
            $request->input('ignore_assignment_id')
        );

        return response()->json($validation);
    }

    /**
     * GET /v2/scheduling/suggested-slots
     *
     * Get suggested time slots for a service that respect spacing rules.
     */
    public function suggestedSlots(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'service_type_id' => 'required|integer|exists:service_types,id',
            'date' => 'required|date',
            'duration_minutes' => 'sometimes|integer|min:15|max:480',
        ]);

        $suggestions = $this->engine->getSuggestedTimeSlots(
            $request->input('patient_id'),
            $request->input('service_type_id'),
            Carbon::parse($request->input('date')),
            $request->input('duration_minutes', 60)
        );

        return response()->json([
            'slots' => $suggestions,
        ]);
    }

    /**
     * Format a ServiceAssignment for API response.
     */
    private function formatAssignment(ServiceAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'patient_id' => $assignment->patient_id,
            'patient_name' => $assignment->patient?->full_name,
            'service_type_id' => $assignment->service_type_id,
            'service_type_name' => $assignment->serviceType?->name,
            'service_type_code' => $assignment->serviceType?->code,
            'category' => $assignment->serviceType?->category,
            'color' => $assignment->serviceType?->color,
            'care_plan_id' => $assignment->care_plan_id,
            'assigned_user_id' => $assignment->assigned_user_id,
            'scheduled_start' => $assignment->scheduled_start->toIso8601String(),
            'scheduled_end' => $assignment->scheduled_end->toIso8601String(),
            'scheduled_date' => $assignment->scheduled_date,
            'start_time' => $assignment->scheduled_start->format('H:i'),
            'end_time' => $assignment->scheduled_end->format('H:i'),
            'time_range' => $assignment->time_range,
            'duration_minutes' => $assignment->duration_minutes,
            'duration_hours' => $assignment->duration_hours,
            'status' => $assignment->status,
            'visit_label' => $assignment->visit_label,
            'verification_status' => $assignment->verification_status,
            'notes' => $assignment->notes,
        ];
    }
}
