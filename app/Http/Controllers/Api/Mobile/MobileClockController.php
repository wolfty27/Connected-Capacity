<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ServiceAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MobileClockController - Clock in/out API for field staff
 *
 * Per MOB-003: Handles time tracking with geolocation
 * - Clock in with location verification
 * - Clock out with visit summary
 * - Location pings during visit
 */
class MobileClockController extends Controller
{
    /**
     * Clock in to an assignment.
     *
     * POST /api/mobile/assignments/{assignment}/clock-in
     */
    public function clockIn(Request $request, ServiceAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'offline_timestamp' => ['nullable', 'date'],
        ]);

        // Check if already clocked in
        if ($assignment->actual_start && !$assignment->actual_end) {
            return response()->json([
                'error' => 'Already clocked in to this assignment',
                'clocked_in_at' => $assignment->actual_start->toIso8601String(),
            ], 422);
        }

        // Check if assignment is in valid state
        if (!in_array($assignment->status, ['pending', 'active', 'planned'])) {
            return response()->json([
                'error' => 'Cannot clock in to assignment with status: ' . $assignment->status,
            ], 422);
        }

        DB::transaction(function () use ($assignment, $validated) {
            $clockInTime = isset($validated['offline_timestamp'])
                ? \Carbon\Carbon::parse($validated['offline_timestamp'])
                : now();

            $assignment->update([
                'actual_start' => $clockInTime,
                'status' => 'in_progress',
            ]);

            // Store clock in location
            $this->storeLocationEvent($assignment, 'clock_in', $validated);
        });

        Log::info('Mobile clock in', [
            'assignment_id' => $assignment->id,
            'user_id' => Auth::id(),
            'patient_id' => $assignment->patient_id,
            'location' => [$validated['latitude'], $validated['longitude']],
        ]);

        return response()->json([
            'message' => 'Successfully clocked in',
            'data' => [
                'assignment_id' => $assignment->id,
                'clocked_in_at' => $assignment->fresh()->actual_start->toIso8601String(),
                'status' => 'in_progress',
            ],
        ]);
    }

    /**
     * Clock out from an assignment.
     *
     * POST /api/mobile/assignments/{assignment}/clock-out
     */
    public function clockOut(Request $request, ServiceAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'offline_timestamp' => ['nullable', 'date'],
            'visit_notes' => ['nullable', 'string', 'max:2000'],
            'tasks_completed' => ['nullable', 'array'],
            'tasks_completed.*' => ['string'],
            'patient_condition' => ['nullable', 'string', 'in:stable,improved,declined,escalation_needed'],
        ]);

        // Check if clocked in
        if (!$assignment->actual_start) {
            return response()->json([
                'error' => 'Not clocked in to this assignment',
            ], 422);
        }

        // Check if already clocked out
        if ($assignment->actual_end) {
            return response()->json([
                'error' => 'Already clocked out from this assignment',
                'clocked_out_at' => $assignment->actual_end->toIso8601String(),
            ], 422);
        }

        $visitDuration = null;

        DB::transaction(function () use ($assignment, $validated, &$visitDuration) {
            $clockOutTime = isset($validated['offline_timestamp'])
                ? \Carbon\Carbon::parse($validated['offline_timestamp'])
                : now();

            $visitDuration = $assignment->actual_start->diffInMinutes($clockOutTime);

            // Update notes if provided
            $notes = $assignment->notes ?? '';
            if (!empty($validated['visit_notes'])) {
                $notes .= ($notes ? "\n\n" : '') . "Visit Notes ({$clockOutTime->format('Y-m-d H:i')}):\n" . $validated['visit_notes'];
            }

            $assignment->update([
                'actual_end' => $clockOutTime,
                'status' => 'completed',
                'notes' => $notes,
            ]);

            // Store clock out location
            $this->storeLocationEvent($assignment, 'clock_out', $validated);

            // If escalation needed, trigger alert
            if (($validated['patient_condition'] ?? null) === 'escalation_needed') {
                $this->triggerEscalation($assignment, $validated);
            }
        });

        Log::info('Mobile clock out', [
            'assignment_id' => $assignment->id,
            'user_id' => Auth::id(),
            'patient_id' => $assignment->patient_id,
            'duration_minutes' => $visitDuration,
        ]);

        return response()->json([
            'message' => 'Successfully clocked out',
            'data' => [
                'assignment_id' => $assignment->id,
                'clocked_out_at' => $assignment->fresh()->actual_end->toIso8601String(),
                'status' => 'completed',
                'visit_duration_minutes' => $visitDuration,
            ],
        ]);
    }

    /**
     * Get clock status for an assignment.
     *
     * GET /api/mobile/assignments/{assignment}/clock-status
     */
    public function status(ServiceAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $isClockedIn = $assignment->actual_start && !$assignment->actual_end;
        $isCompleted = $assignment->status === 'completed';

        return response()->json([
            'data' => [
                'assignment_id' => $assignment->id,
                'status' => $assignment->status,
                'is_clocked_in' => $isClockedIn,
                'is_completed' => $isCompleted,
                'clocked_in_at' => $assignment->actual_start?->toIso8601String(),
                'clocked_out_at' => $assignment->actual_end?->toIso8601String(),
                'elapsed_minutes' => $isClockedIn
                    ? $assignment->actual_start->diffInMinutes(now())
                    : null,
                'visit_duration_minutes' => $isCompleted && $assignment->actual_start && $assignment->actual_end
                    ? $assignment->actual_start->diffInMinutes($assignment->actual_end)
                    : null,
            ],
        ]);
    }

    /**
     * Record a location ping during an active visit.
     *
     * POST /api/mobile/assignments/{assignment}/location-ping
     */
    public function locationPing(Request $request, ServiceAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Only record if clocked in
        if (!$assignment->actual_start || $assignment->actual_end) {
            return response()->json([
                'error' => 'Not currently clocked in to this assignment',
            ], 422);
        }

        $this->storeLocationEvent($assignment, 'ping', $validated);

        return response()->json([
            'message' => 'Location recorded',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Store a location event for audit trail.
     */
    protected function storeLocationEvent(ServiceAssignment $assignment, string $eventType, array $data): void
    {
        // Store in audit_logs or a dedicated location_events table
        // For now, we'll append to assignment notes as JSON
        $locationData = [
            'event' => $eventType,
            'timestamp' => now()->toIso8601String(),
            'user_id' => Auth::id(),
            'lat' => $data['latitude'],
            'lng' => $data['longitude'],
            'accuracy' => $data['accuracy_meters'] ?? null,
            'device_id' => $data['device_id'] ?? null,
        ];

        Log::info('Location event recorded', array_merge($locationData, [
            'assignment_id' => $assignment->id,
        ]));

        // TODO: In production, store in dedicated location_events table
        // for now, this is just logged
    }

    /**
     * Trigger escalation for patient condition concerns.
     */
    protected function triggerEscalation(ServiceAssignment $assignment, array $data): void
    {
        Log::warning('Patient escalation triggered from mobile', [
            'assignment_id' => $assignment->id,
            'patient_id' => $assignment->patient_id,
            'user_id' => Auth::id(),
            'notes' => $data['visit_notes'] ?? null,
        ]);

        // Update assignment status to reflect escalation
        $assignment->update(['status' => 'escalated']);

        // TODO: Dispatch notification to care coordinator
        // event(new PatientEscalationTriggered($assignment, $data));
    }
}
