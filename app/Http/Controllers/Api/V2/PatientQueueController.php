<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Services\CareBundleBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * PatientQueueController - API for managing patient queue workflows
 *
 * Implements the Workday-style queue management where patients progress
 * through defined stages from intake to active care profiles.
 */
class PatientQueueController extends Controller
{
    protected CareBundleBuilderService $bundleBuilder;

    public function __construct(CareBundleBuilderService $bundleBuilder)
    {
        $this->bundleBuilder = $bundleBuilder;
    }

    /**
     * Get all patients in the queue.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = PatientQueue::with(['patient.user', 'assignedCoordinator'])
            ->inQueue();

        // Filter by status
        if ($request->has('status')) {
            $query->withStatus($request->status);
        }

        // Filter by coordinator
        if ($request->has('coordinator_id')) {
            $query->assignedTo($request->coordinator_id);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', '<=', $request->priority);
        }

        // Order by priority and entry time
        $queue = $query->byPriority()->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $queue->items(),
            'meta' => [
                'current_page' => $queue->currentPage(),
                'last_page' => $queue->lastPage(),
                'per_page' => $queue->perPage(),
                'total' => $queue->total(),
            ],
            'summary' => $this->getQueueSummary(),
        ]);
    }

    /**
     * Get queue summary statistics.
     */
    protected function getQueueSummary(): array
    {
        $summary = [];
        foreach (PatientQueue::STATUSES as $status) {
            if ($status !== 'transitioned') {
                $summary[$status] = PatientQueue::withStatus($status)->count();
            }
        }

        $summary['total_in_queue'] = PatientQueue::inQueue()->count();
        $summary['ready_for_bundle'] = PatientQueue::readyForBundle()->count();

        return $summary;
    }

    /**
     * Get a specific queue entry.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $queue = PatientQueue::with([
            'patient.user',
            'patient.transitionNeedsProfile',
            'assignedCoordinator',
            'transitions.transitionedBy'
        ])->findOrFail($id);

        return response()->json([
            'data' => $queue,
            'valid_transitions' => PatientQueue::VALID_TRANSITIONS[$queue->queue_status] ?? [],
            'status_label' => $queue->status_label,
            'time_in_queue_minutes' => $queue->time_in_queue,
        ]);
    }

    /**
     * Add a patient to the queue.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'priority' => 'nullable|integer|min:1|max:10',
            'assigned_coordinator_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if patient is already in queue
        $existing = PatientQueue::where('patient_id', $request->patient_id)
            ->whereNotIn('queue_status', ['transitioned'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Patient is already in the queue',
                'data' => $existing,
            ], 409);
        }

        $queue = PatientQueue::create([
            'patient_id' => $request->patient_id,
            'queue_status' => 'pending_intake',
            'priority' => $request->priority ?? 5,
            'assigned_coordinator_id' => $request->assigned_coordinator_id,
            'entered_queue_at' => now(),
            'notes' => $request->notes,
        ]);

        // Update patient status
        Patient::where('id', $request->patient_id)->update([
            'is_in_queue' => true,
        ]);

        return response()->json([
            'message' => 'Patient added to queue',
            'data' => $queue->load(['patient.user', 'assignedCoordinator']),
        ], 201);
    }

    /**
     * Transition a queue entry to a new status.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function transition(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to_status' => 'required|string|in:' . implode(',', PatientQueue::STATUSES),
            'reason' => 'nullable|string|max:500',
            'context' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $queue = PatientQueue::findOrFail($id);

        if (!$queue->canTransitionTo($request->to_status)) {
            return response()->json([
                'message' => "Invalid transition from '{$queue->queue_status}' to '{$request->to_status}'",
                'valid_transitions' => PatientQueue::VALID_TRANSITIONS[$queue->queue_status] ?? [],
            ], 422);
        }

        $queue->transitionTo(
            $request->to_status,
            Auth::id(),
            $request->reason,
            $request->context
        );

        // If transitioning to 'transitioned', update patient
        if ($request->to_status === 'transitioned') {
            $this->bundleBuilder->transitionPatientToActive($queue->patient_id, Auth::id());
        }

        return response()->json([
            'message' => 'Queue status updated',
            'data' => $queue->fresh(['patient.user', 'transitions']),
        ]);
    }

    /**
     * Update queue entry details.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'priority' => 'nullable|integer|min:1|max:10',
            'assigned_coordinator_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000',
            'queue_metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $queue = PatientQueue::findOrFail($id);

        $queue->update($request->only([
            'priority',
            'assigned_coordinator_id',
            'notes',
            'queue_metadata',
        ]));

        return response()->json([
            'message' => 'Queue entry updated',
            'data' => $queue->fresh(['patient.user', 'assignedCoordinator']),
        ]);
    }

    /**
     * Get patients ready for bundle building.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function readyForBundle(Request $request): JsonResponse
    {
        $patients = PatientQueue::with([
            'patient.user',
            'patient.transitionNeedsProfile',
            'assignedCoordinator'
        ])
            ->readyForBundle()
            ->byPriority()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $patients->items(),
            'meta' => [
                'current_page' => $patients->currentPage(),
                'last_page' => $patients->lastPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total(),
            ],
        ]);
    }

    /**
     * Get transition history for a queue entry.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function transitions(int $id): JsonResponse
    {
        $queue = PatientQueue::findOrFail($id);
        $transitions = $queue->transitions()->with('transitionedBy')->get();

        return response()->json([
            'data' => $transitions,
            'current_status' => $queue->queue_status,
            'status_label' => $queue->status_label,
        ]);
    }

    /**
     * Start bundle building for a patient in queue.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function startBundleBuilding(Request $request, int $id): JsonResponse
    {
        $queue = PatientQueue::findOrFail($id);

        if (!$queue->isReadyForBundle()) {
            return response()->json([
                'message' => 'Patient is not ready for bundle building. Current status: ' . $queue->status_label,
                'required_status' => 'assessment_complete',
                'has_assessment' => $queue->hasCompletedAssessment(),
            ], 422);
        }

        $queue->transitionTo(
            'bundle_building',
            Auth::id(),
            'Started care bundle building process'
        );

        return response()->json([
            'message' => 'Bundle building started',
            'data' => $queue->fresh(['patient.user']),
            'redirect_to' => "/care-bundles/create/{$queue->patient_id}",
        ]);
    }
}
