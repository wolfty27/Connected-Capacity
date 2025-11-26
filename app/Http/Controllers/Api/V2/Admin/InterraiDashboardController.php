<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Models\ReassessmentTrigger;
use App\Services\InterraiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * InterraiDashboardController - Admin InterRAI Dashboard API
 *
 * IR-006: Provides endpoints for the admin assessments dashboard including:
 * - KPI statistics
 * - Stale/missing assessment lists
 * - Failed upload management
 * - Bulk operations
 */
class InterraiDashboardController extends Controller
{
    public function __construct(
        protected InterraiService $interraiService
    ) {}

    /**
     * Get dashboard statistics.
     *
     * GET /api/v2/admin/interrai/dashboard-stats
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => $this->interraiService->getDashboardStats(),
        ]);
    }

    /**
     * Get stale assessments list.
     *
     * GET /api/v2/admin/interrai/stale-assessments
     */
    public function staleAssessments(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 50), 200);

        $patients = Patient::where('is_in_queue', true)
            ->where('interrai_status', Patient::INTERRAI_STATUS_STALE)
            ->with(['latestInterraiAssessment', 'primaryCoordinator'])
            ->orderBy('interrai_status_updated_at', 'asc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $patients->map(function ($patient) {
                $assessment = $patient->latestInterraiAssessment;
                return [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->user?->name ?? 'Unknown',
                    'coordinator' => $patient->primaryCoordinator?->name,
                    'assessment_date' => $assessment?->assessment_date?->format('Y-m-d'),
                    'days_stale' => $assessment?->assessment_date?->diffInDays(now()),
                    'maple_score' => $assessment?->maple_score,
                    'iar_status' => $assessment?->iar_upload_status,
                    'has_pending_trigger' => $patient->hasPendingReassessment(),
                ];
            }),
            'meta' => [
                'total' => $patients->count(),
            ],
        ]);
    }

    /**
     * Get patients missing assessments.
     *
     * GET /api/v2/admin/interrai/missing-assessments
     */
    public function missingAssessments(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 50), 200);

        $patients = Patient::where('is_in_queue', true)
            ->where('interrai_status', Patient::INTERRAI_STATUS_MISSING)
            ->with(['primaryCoordinator', 'queueEntry'])
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $patients->map(function ($patient) {
                return [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->user?->name ?? 'Unknown',
                    'coordinator' => $patient->primaryCoordinator?->name,
                    'queue_status' => $patient->queueEntry?->queue_status,
                    'days_in_queue' => $patient->created_at->diffInDays(now()),
                    'has_pending_trigger' => $patient->hasPendingReassessment(),
                ];
            }),
            'meta' => [
                'total' => $patients->count(),
            ],
        ]);
    }

    /**
     * Get failed IAR uploads.
     *
     * GET /api/v2/admin/interrai/failed-uploads
     */
    public function failedUploads(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 50), 200);

        $assessments = $this->interraiService->getFailedIarUploads($limit);

        return response()->json([
            'data' => $assessments->map(function ($assessment) {
                return [
                    'assessment_id' => $assessment->id,
                    'patient_id' => $assessment->patient_id,
                    'patient_name' => $assessment->patient?->user?->name ?? 'Unknown',
                    'assessment_date' => $assessment->assessment_date->format('Y-m-d'),
                    'maple_score' => $assessment->maple_score,
                    'source' => $assessment->source,
                    'failed_at' => $assessment->updated_at->toIso8601String(),
                    'created_at' => $assessment->created_at->toIso8601String(),
                ];
            }),
            'meta' => [
                'total' => $assessments->count(),
            ],
        ]);
    }

    /**
     * Bulk retry failed IAR uploads.
     *
     * POST /api/v2/admin/interrai/bulk-retry-iar
     */
    public function bulkRetryIar(): JsonResponse
    {
        $result = $this->interraiService->bulkRetryFailedUploads();

        Log::info('Admin bulk IAR retry initiated', [
            'user_id' => Auth::id(),
            'queued' => $result['queued'],
        ]);

        return response()->json([
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Sync all patient InterRAI statuses.
     *
     * POST /api/v2/admin/interrai/sync-statuses
     */
    public function syncStatuses(): JsonResponse
    {
        $result = $this->interraiService->syncAllPatientStatuses();

        Log::info('Admin InterRAI status sync initiated', [
            'user_id' => Auth::id(),
            'updated' => $result['updated'],
        ]);

        return response()->json([
            'message' => "Updated {$result['updated']} patient statuses",
            'data' => $result,
        ]);
    }

    /**
     * Get pending reassessment triggers.
     *
     * GET /api/v2/admin/interrai/pending-triggers
     */
    public function pendingTriggers(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 50), 200);
        $priority = $request->input('priority');

        $triggers = $this->interraiService->getReassessmentTriggers($limit, $priority);

        return response()->json([
            'data' => $triggers->map(fn ($t) => $t->toApiArray()),
            'meta' => [
                'total' => $triggers->count(),
                'urgent' => $triggers->where('priority', ReassessmentTrigger::PRIORITY_URGENT)->count(),
                'high' => $triggers->where('priority', ReassessmentTrigger::PRIORITY_HIGH)->count(),
            ],
        ]);
    }

    /**
     * Get compliance report data.
     *
     * GET /api/v2/admin/interrai/compliance-report
     */
    public function complianceReport(): JsonResponse
    {
        $totalInQueue = Patient::where('is_in_queue', true)->count();
        $withCurrent = Patient::where('is_in_queue', true)
            ->where('interrai_status', Patient::INTERRAI_STATUS_CURRENT)
            ->count();
        $withStale = Patient::where('is_in_queue', true)
            ->where('interrai_status', Patient::INTERRAI_STATUS_STALE)
            ->count();
        $withMissing = Patient::where('is_in_queue', true)
            ->where('interrai_status', Patient::INTERRAI_STATUS_MISSING)
            ->count();

        $totalAssessments = InterraiAssessment::count();
        $uploadedToIar = InterraiAssessment::where('iar_upload_status', InterraiAssessment::IAR_UPLOADED)->count();
        $pendingIar = InterraiAssessment::pendingIarUpload()->count();
        $failedIar = InterraiAssessment::where('iar_upload_status', InterraiAssessment::IAR_FAILED)->count();

        $completionRate = $totalInQueue > 0
            ? round(($withCurrent / $totalInQueue) * 100, 1)
            : 0;

        $iarSuccessRate = $totalAssessments > 0
            ? round(($uploadedToIar / $totalAssessments) * 100, 1)
            : 0;

        return response()->json([
            'data' => [
                'assessment_coverage' => [
                    'total_patients_in_queue' => $totalInQueue,
                    'with_current_assessment' => $withCurrent,
                    'with_stale_assessment' => $withStale,
                    'without_assessment' => $withMissing,
                    'completion_rate' => $completionRate,
                ],
                'iar_integration' => [
                    'total_assessments' => $totalAssessments,
                    'uploaded_to_iar' => $uploadedToIar,
                    'pending_upload' => $pendingIar,
                    'failed_upload' => $failedIar,
                    'success_rate' => $iarSuccessRate,
                ],
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
