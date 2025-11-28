<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Scheduling\CareBundleAssignmentPlanner;
use App\Services\Scheduling\SchedulingEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SchedulingController
 *
 * API endpoints for the Staff Scheduling Dashboard.
 *
 * Routes:
 * - GET  /v2/scheduling/requirements - Unscheduled care needs
 * - GET  /v2/scheduling/grid         - Staff + assignments for week
 * - GET  /v2/scheduling/eligible-staff - Staff eligible for a service
 * - POST /v2/scheduling/assignments  - Create assignment
 * - PATCH /v2/scheduling/assignments/{id} - Update assignment
 * - DELETE /v2/scheduling/assignments/{id} - Cancel assignment
 */
class SchedulingController extends Controller
{
    public function __construct(
        protected CareBundleAssignmentPlanner $planner,
        protected SchedulingEngine $engine
    ) {}

    /**
     * Get unscheduled care requirements for a date range.
     *
     * Returns patients with care bundle services that have remaining
     * hours/visits to be scheduled.
     */
    public function requirements(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'patient_id' => 'nullable|integer|exists:patients,id',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $organizationId = $request->user()?->organization_id;

        $requirements = $this->planner->getUnscheduledRequirements(
            $organizationId,
            $startDate,
            $endDate,
            $request->patient_id
        );

        return response()->json([
            'data' => $requirements->map(fn($dto) => $dto->toArray())->values(),
            'summary' => $this->planner->getSummaryStats($organizationId, $startDate, $endDate),
        ]);
    }

    /**
     * Get scheduling grid data (staff + assignments).
     *
     * Returns staff list with availability and assignments for the week.
     */
    public function grid(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'staff_id' => 'nullable|integer|exists:users,id',
            'patient_id' => 'nullable|integer|exists:patients,id',
            'role_codes' => 'nullable|array',
            'role_codes.*' => 'string',
            'employment_type_codes' => 'nullable|array',
            'employment_type_codes.*' => 'string',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $organizationId = $request->user()?->organization_id;

        $gridData = $this->engine->getGridData(
            $organizationId,
            $startDate,
            $endDate,
            $request->staff_id,
            $request->patient_id
        );

        // Apply additional filters if provided
        if ($request->role_codes) {
            $gridData['staff'] = collect($gridData['staff'])
                ->filter(fn($s) => in_array($s['role']['code'] ?? '', $request->role_codes))
                ->values();
        }

        if ($request->employment_type_codes) {
            $gridData['staff'] = collect($gridData['staff'])
                ->filter(fn($s) => in_array($s['employment_type']['code'] ?? '', $request->employment_type_codes))
                ->values();
        }

        return response()->json([
            'data' => $gridData,
        ]);
    }

    /**
     * Get staff eligible to provide a service at a specific time.
     */
    public function eligibleStaff(Request $request): JsonResponse
    {
        $request->validate([
            'service_type_id' => 'required|integer|exists:service_types,id',
            'date_time' => 'required|date',
            'duration_minutes' => 'required|integer|min:15|max:480',
        ]);

        $serviceType = ServiceType::findOrFail($request->service_type_id);
        $dateTime = Carbon::parse($request->date_time);
        $organizationId = $request->user()?->organization_id;

        $eligibleStaff = $this->engine->getEligibleStaff(
            $serviceType,
            $dateTime,
            $request->duration_minutes,
            $organizationId
        );

        return response()->json([
            'data' => $eligibleStaff->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->staffRole ? [
                    'id' => $user->staffRole->id,
                    'code' => $user->staffRole->code,
                    'name' => $user->staffRole->name,
                ] : null,
                'employment_type' => $user->employmentTypeModel ? [
                    'id' => $user->employmentTypeModel->id,
                    'code' => $user->employmentTypeModel->code,
                    'name' => $user->employmentTypeModel->name,
                ] : null,
            ]),
            'service_type' => [
                'id' => $serviceType->id,
                'name' => $serviceType->name,
                'code' => $serviceType->code,
            ],
        ]);
    }

    /**
     * Create a new service assignment.
     */
    public function createAssignment(Request $request): JsonResponse
    {
        $request->validate([
            'staff_id' => 'required|integer|exists:users,id',
            'patient_id' => 'required|integer|exists:patients,id',
            'service_type_id' => 'required|integer|exists:service_types,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'notes' => 'nullable|string|max:1000',
        ]);

        $scheduledStart = Carbon::parse("{$request->date} {$request->start_time}");
        $scheduledEnd = $scheduledStart->copy()->addMinutes($request->duration_minutes);

        // Get care plan for patient
        $patient = Patient::with(['carePlans' => fn($q) => $q->where('status', 'active')])
            ->findOrFail($request->patient_id);
        $carePlan = $patient->carePlans->first();

        $assignment = new ServiceAssignment([
            'care_plan_id' => $carePlan?->id,
            'patient_id' => $request->patient_id,
            'service_type_id' => $request->service_type_id,
            'assigned_user_id' => $request->staff_id,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'duration_minutes' => $request->duration_minutes,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'source' => ServiceAssignment::SOURCE_INTERNAL,
            'service_provider_organization_id' => $request->user()?->organization_id,
            'notes' => $request->notes,
        ]);

        // Validate assignment
        $validation = $this->engine->validateAssignment($assignment);

        if (!$validation->isValid) {
            return response()->json([
                'message' => 'Assignment validation failed',
                'errors' => $validation->errors,
                'warnings' => $validation->warnings,
            ], 422);
        }

        $assignment->save();

        // Reload with relationships
        $assignment->load(['patient.user', 'serviceType', 'assignedUser']);

        return response()->json([
            'data' => $this->formatAssignment($assignment),
            'warnings' => $validation->warnings,
            'message' => 'Assignment created successfully',
        ], 201);
    }

    /**
     * Update an existing assignment.
     */
    public function updateAssignment(Request $request, int $id): JsonResponse
    {
        $assignment = ServiceAssignment::findOrFail($id);

        $request->validate([
            'staff_id' => 'nullable|integer|exists:users,id',
            'date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Apply updates
        if ($request->has('staff_id')) {
            $assignment->assigned_user_id = $request->staff_id;
        }

        if ($request->has('date') || $request->has('start_time')) {
            $date = $request->date ?? $assignment->scheduled_start->toDateString();
            $time = $request->start_time ?? $assignment->scheduled_start->format('H:i');
            $assignment->scheduled_start = Carbon::parse("{$date} {$time}");
        }

        if ($request->has('duration_minutes')) {
            $assignment->duration_minutes = $request->duration_minutes;
            $assignment->scheduled_end = $assignment->scheduled_start
                ->copy()
                ->addMinutes($request->duration_minutes);
        }

        if ($request->has('notes')) {
            $assignment->notes = $request->notes;
        }

        // Re-validate
        $validation = $this->engine->validateAssignment($assignment);

        if (!$validation->isValid) {
            return response()->json([
                'message' => 'Assignment validation failed',
                'errors' => $validation->errors,
                'warnings' => $validation->warnings,
            ], 422);
        }

        $assignment->save();
        $assignment->load(['patient.user', 'serviceType', 'assignedUser']);

        return response()->json([
            'data' => $this->formatAssignment($assignment),
            'warnings' => $validation->warnings,
            'message' => 'Assignment updated successfully',
        ]);
    }

    /**
     * Cancel an assignment (soft delete).
     */
    public function deleteAssignment(int $id): JsonResponse
    {
        $assignment = ServiceAssignment::findOrFail($id);
        $assignment->status = ServiceAssignment::STATUS_CANCELLED;
        $assignment->save();

        return response()->json([
            'message' => 'Assignment cancelled successfully',
        ]);
    }

    /**
     * Get navigation examples (staff and patient for deep links).
     */
    public function navigationExamples(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        // Get a sample staff member
        $sampleStaff = User::where('role', User::ROLE_FIELD_STAFF)
            ->where('staff_status', User::STAFF_STATUS_ACTIVE)
            ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
            ->with('staffRole')
            ->first();

        // Get a sample patient with active care plan
        $samplePatient = Patient::where('status', 'Active')
            ->whereHas('carePlans', fn($q) => $q->where('status', 'active'))
            ->with('user')
            ->first();

        return response()->json([
            'data' => [
                'staff' => $sampleStaff ? [
                    'id' => $sampleStaff->id,
                    'name' => $sampleStaff->name,
                    'role' => $sampleStaff->staffRole?->name,
                ] : null,
                'patient' => $samplePatient ? [
                    'id' => $samplePatient->id,
                    'name' => $samplePatient->user?->name,
                ] : null,
            ],
        ]);
    }

    /**
     * Format assignment for API response.
     */
    protected function formatAssignment(ServiceAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'staff_id' => $assignment->assigned_user_id,
            'staff_name' => $assignment->assignedUser?->name,
            'patient_id' => $assignment->patient_id,
            'patient_name' => $assignment->patient?->user?->name,
            'service_type_id' => $assignment->service_type_id,
            'service_type_name' => $assignment->serviceType?->name,
            'category' => $assignment->serviceType?->category,
            'date' => $assignment->scheduled_start->toDateString(),
            'start_time' => $assignment->scheduled_start->format('H:i'),
            'end_time' => $assignment->scheduled_end?->format('H:i'),
            'duration_minutes' => $assignment->duration_minutes,
            'status' => $assignment->status,
            'verification_status' => $assignment->verification_status,
            'notes' => $assignment->notes,
        ];
    }
}
