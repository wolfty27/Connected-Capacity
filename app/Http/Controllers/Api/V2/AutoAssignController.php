<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Llm\LlmExplanationService;
use App\Services\Scheduling\AutoAssignEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AutoAssignController
 *
 * API endpoints for AI-assisted scheduling.
 *
 * Endpoints:
 * - GET  /suggestions           - Generate assignment suggestions
 * - GET  /suggestions/{patient}/{service}/explain - Get explanation for a suggestion
 * - POST /suggestions/accept    - Accept a single suggestion
 * - POST /suggestions/accept-batch - Accept multiple suggestions
 */
class AutoAssignController extends Controller
{
    public function __construct(
        private AutoAssignEngine $autoAssignEngine,
        private LlmExplanationService $explanationService
    ) {}

    /**
     * Generate assignment suggestions for unscheduled care.
     *
     * GET /api/v2/scheduling/suggestions
     *
     * Query params:
     * - week_start: Start of week (default: current week)
     * - organization_id: SPO org ID (default: user's org)
     *
     * @return JsonResponse
     */
    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => 'sometimes|date',
            'organization_id' => 'sometimes|integer|exists:service_provider_organizations,id',
        ]);

        $weekStart = isset($validated['week_start'])
            ? Carbon::parse($validated['week_start'])->startOfWeek()
            : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $organizationId = $validated['organization_id']
            ?? auth()->user()->organization_id;

        if (!$organizationId) {
            return response()->json([
                'error' => 'Organization ID is required',
            ], 400);
        }

        $suggestions = $this->autoAssignEngine->generateSuggestions(
            $organizationId,
            $weekStart,
            $weekEnd
        );

        // Enrich suggestions with staff names for display
        $enrichedSuggestions = $suggestions->map(function ($suggestion) {
            $data = $suggestion->toArray();

            // Add staff name for display (safe to include in response)
            if ($suggestion->suggestedStaffId) {
                $staff = User::find($suggestion->suggestedStaffId);
                $data['suggested_staff_name'] = $staff?->name;
            }

            return $data;
        });

        return response()->json([
            'data' => $enrichedSuggestions->values(),
            'meta' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'organization_id' => $organizationId,
                'total_suggestions' => $suggestions->count(),
                'strong_matches' => $suggestions->where('matchStatus', 'strong')->count(),
                'moderate_matches' => $suggestions->where('matchStatus', 'moderate')->count(),
                'weak_matches' => $suggestions->where('matchStatus', 'weak')->count(),
                'no_matches' => $suggestions->where('matchStatus', 'none')->count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get explanation for a specific suggestion.
     *
     * GET /api/v2/scheduling/suggestions/{patient_id}/{service_type_id}/explain
     *
     * Query params:
     * - staff_id: The suggested staff ID (required)
     * - week_start: Week context (default: current week)
     *
     * @return JsonResponse
     */
    public function explain(Request $request, int $patientId, int $serviceTypeId): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => 'required|integer|exists:users,id',
            'week_start' => 'sometimes|date',
        ]);

        $weekStart = isset($validated['week_start'])
            ? Carbon::parse($validated['week_start'])->startOfWeek()
            : Carbon::now()->startOfWeek();

        $organizationId = auth()->user()->organization_id;

        if (!$organizationId) {
            return response()->json([
                'error' => 'Organization ID is required',
            ], 400);
        }

        // Reconstruct the suggestion DTO
        $suggestion = $this->autoAssignEngine->getSuggestionForService(
            patientId: $patientId,
            serviceTypeId: $serviceTypeId,
            staffId: $validated['staff_id'],
            weekStart: $weekStart,
            organizationId: $organizationId
        );

        if (!$suggestion) {
            return response()->json([
                'error' => 'Suggestion not found. Ensure patient, service type, and staff IDs are valid.',
            ], 404);
        }

        // Generate explanation via LLM service
        $explanation = $this->explanationService->getExplanation(
            $suggestion,
            auth()->id()
        );

        return response()->json([
            'data' => [
                'short_explanation' => $explanation->shortExplanation,
                'detailed_points' => $explanation->detailedPoints,
                'confidence_label' => $explanation->confidenceLabel,
                'source' => $explanation->source,
                'generated_at' => $explanation->generatedAt->toIso8601String(),
                'response_time_ms' => $explanation->responseTimeMs,
            ],
            'context' => [
                'patient_id' => $patientId,
                'service_type_id' => $serviceTypeId,
                'staff_id' => $validated['staff_id'],
                'confidence_score' => $suggestion->confidenceScore,
                'match_status' => $suggestion->matchStatus,
            ],
        ]);
    }

    /**
     * Accept a suggestion and create an assignment.
     *
     * POST /api/v2/scheduling/suggestions/accept
     *
     * Body:
     * - patient_id: Patient ID
     * - service_type_id: Service type ID
     * - staff_id: Staff user ID
     * - scheduled_start: Start datetime
     * - scheduled_end: End datetime
     *
     * @return JsonResponse
     */
    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'service_type_id' => 'required|integer|exists:service_types,id',
            'staff_id' => 'required|integer|exists:users,id',
            'scheduled_start' => 'required|date',
            'scheduled_end' => 'required|date|after:scheduled_start',
        ]);

        $organizationId = auth()->user()->organization_id;

        if (!$organizationId) {
            return response()->json([
                'error' => 'Organization ID is required',
            ], 400);
        }

        $result = $this->autoAssignEngine->acceptSuggestion(
            patientId: $validated['patient_id'],
            serviceTypeId: $validated['service_type_id'],
            staffId: $validated['staff_id'],
            scheduledStart: Carbon::parse($validated['scheduled_start']),
            scheduledEnd: Carbon::parse($validated['scheduled_end']),
            acceptedBy: auth()->id(),
            organizationId: $organizationId
        );

        if (!$result['success']) {
            return response()->json([
                'error' => 'Failed to create assignment',
                'validation_errors' => $result['errors'] ?? [],
            ], 422);
        }

        return response()->json([
            'data' => [
                'assignment_id' => $result['assignment_id'],
                'message' => 'Assignment created successfully',
            ],
        ], 201);
    }

    /**
     * Accept multiple suggestions in batch.
     *
     * POST /api/v2/scheduling/suggestions/accept-batch
     *
     * Body:
     * - suggestions: Array of suggestion objects
     *   - patient_id
     *   - service_type_id
     *   - staff_id
     *   - scheduled_start
     *   - scheduled_end
     *
     * @return JsonResponse
     */
    public function acceptBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'suggestions' => 'required|array|min:1|max:50',
            'suggestions.*.patient_id' => 'required|integer|exists:patients,id',
            'suggestions.*.service_type_id' => 'required|integer|exists:service_types,id',
            'suggestions.*.staff_id' => 'required|integer|exists:users,id',
            'suggestions.*.scheduled_start' => 'required|date',
            'suggestions.*.scheduled_end' => 'required|date',
        ]);

        $organizationId = auth()->user()->organization_id;

        if (!$organizationId) {
            return response()->json([
                'error' => 'Organization ID is required',
            ], 400);
        }

        $results = $this->autoAssignEngine->acceptBatch(
            $validated['suggestions'],
            auth()->id(),
            $organizationId
        );

        $statusCode = count($results['failed']) === 0 ? 201 : 207; // 207 = Multi-Status

        return response()->json([
            'data' => [
                'successful' => $results['successful'],
                'failed' => $results['failed'],
            ],
            'meta' => [
                'total_submitted' => count($validated['suggestions']),
                'total_created' => count($results['successful']),
                'total_failed' => count($results['failed']),
            ],
        ], $statusCode);
    }

    /**
     * Get summary statistics for auto-assign suggestions.
     *
     * GET /api/v2/scheduling/suggestions/summary
     *
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => 'sometimes|date',
            'organization_id' => 'sometimes|integer|exists:service_provider_organizations,id',
        ]);

        $weekStart = isset($validated['week_start'])
            ? Carbon::parse($validated['week_start'])->startOfWeek()
            : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $organizationId = $validated['organization_id']
            ?? auth()->user()->organization_id;

        if (!$organizationId) {
            return response()->json([
                'error' => 'Organization ID is required',
            ], 400);
        }

        $suggestions = $this->autoAssignEngine->generateSuggestions(
            $organizationId,
            $weekStart,
            $weekEnd
        );

        // Group by match status
        $byStatus = $suggestions->groupBy('matchStatus');

        // Get unique patients and services
        $uniquePatients = $suggestions->pluck('patientId')->unique()->count();
        $uniqueServices = $suggestions->pluck('serviceTypeId')->unique()->count();

        return response()->json([
            'data' => [
                'total_suggestions' => $suggestions->count(),
                'by_match_status' => [
                    'strong' => $byStatus->get('strong', collect())->count(),
                    'moderate' => $byStatus->get('moderate', collect())->count(),
                    'weak' => $byStatus->get('weak', collect())->count(),
                    'none' => $byStatus->get('none', collect())->count(),
                ],
                'unique_patients' => $uniquePatients,
                'unique_services' => $uniqueServices,
                'acceptance_ready' => $suggestions->filter(fn($s) => $s->matchStatus !== 'none')->count(),
            ],
            'meta' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'organization_id' => $organizationId,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Generate AI-powered weekly scheduling summary.
     *
     * GET /api/v2/scheduling/suggestions/weekly-summary
     *
     * Returns a natural language summary of the week's scheduling situation,
     * including highlights, priorities, and AI insights.
     *
     * @return JsonResponse
     */
    public function weeklySummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => 'sometimes|date',
            'organization_id' => 'sometimes|integer|exists:service_provider_organizations,id',
        ]);

        $weekStart = isset($validated['week_start'])
            ? Carbon::parse($validated['week_start'])->startOfWeek()
            : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $organizationId = $validated['organization_id']
            ?? auth()->user()->organization_id;

        if (!$organizationId) {
            return response()->json([
                'error' => 'Organization ID is required',
            ], 400);
        }

        // Generate suggestions to get AI metrics
        $suggestions = $this->autoAssignEngine->generateSuggestions(
            $organizationId,
            $weekStart,
            $weekEnd
        );

        $byStatus = $suggestions->groupBy('matchStatus');

        // Gather metrics for summary generation
        // TODO: In production, these would come from other services
        $metrics = [
            'total_staff' => \App\Models\User::whereHas('staffProfile', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)->where('status', 'active');
            })->count() ?: 25,
            'available_hours' => 800, // Would come from WorkforceCapacityService
            'scheduled_hours' => 650, // Would come from SchedulingController
            'net_capacity' => 150,
            'unscheduled_hours' => 120.5,
            'unscheduled_visits' => $suggestions->count(),
            'patients_needing_care' => $suggestions->pluck('patientId')->unique()->count(),
            'total_suggestions' => $suggestions->count(),
            'strong_matches' => $byStatus->get('strong', collect())->count(),
            'moderate_matches' => $byStatus->get('moderate', collect())->count(),
            'weak_matches' => $byStatus->get('weak', collect())->count() + $byStatus->get('none', collect())->count(),
            'tfs_hours' => 18.5, // Would come from TfsController
            'missed_care_rate' => 2.1, // Would come from SpoDashboardController
        ];

        // Generate AI summary
        $summary = $this->explanationService->generateWeeklySummary(
            $metrics,
            auth()->id()
        );

        return response()->json([
            'data' => [
                'summary' => $summary['summary'],
                'highlights' => $summary['highlights'],
                'priorities' => $summary['priorities'],
                'source' => $summary['source'],
            ],
            'metrics' => $metrics,
            'meta' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'organization_id' => $organizationId,
                'generated_at' => now()->toIso8601String(),
                'response_time_ms' => $summary['response_time_ms'] ?? 0,
            ],
        ]);
    }
}
