<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\CareOps\JeopardyBoardService;
use App\Services\CareOps\VisitVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Jeopardy Board Controller
 *
 * Provides API endpoints for the Jeopardy Board (Missed Care Risk) feature.
 *
 * The Jeopardy Board shows:
 * - CRITICAL alerts: Overdue unverified visits (past grace period)
 * - WARNING alerts: Upcoming visits at risk (within 2 hours)
 *
 * Per OHaH RFP: Target is 0% missed care.
 */
class JeopardyBoardController extends Controller
{
    public function __construct(
        protected JeopardyBoardService $jeopardyService,
        protected VisitVerificationService $verificationService
    ) {}

    /**
     * Get all active alerts for the Jeopardy Board.
     *
     * GET /api/v2/jeopardy/alerts
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        $data = $this->jeopardyService->getActiveAlerts($organizationId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get summary statistics for the Jeopardy Board.
     *
     * GET /api/v2/jeopardy/summary
     *
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        $data = $this->jeopardyService->getSummaryStats($organizationId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Resolve an alert by verifying the visit.
     *
     * POST /api/v2/jeopardy/alerts/{id}/resolve
     *
     * When a coordinator resolves an alert:
     * 1. The assignment's verification_status is set to VERIFIED
     * 2. The assignment is removed from the Jeopardy Board
     * 3. The Missed Care Rate is updated (resolved visit counts as delivered)
     *
     * @param int $id Assignment ID
     * @return JsonResponse
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $assignment = $this->jeopardyService->resolveAlert($id, $user);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved successfully',
            'data' => [
                'assignment_id' => $assignment->id,
                'verification_status' => $assignment->verification_status,
                'verified_at' => $assignment->verified_at?->toIso8601String(),
                'verified_by' => $user?->name ?? 'System',
            ],
        ]);
    }

    /**
     * Mark an alert as missed (confirm the visit was not delivered).
     *
     * POST /api/v2/jeopardy/alerts/{id}/mark-missed
     *
     * When a coordinator marks a visit as missed:
     * 1. The assignment's verification_status is set to MISSED
     * 2. The assignment's status is set to MISSED
     * 3. The Missed Care Rate is updated (missed visit counts in numerator)
     *
     * @param int $id Assignment ID
     * @return JsonResponse
     */
    public function markMissed(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $assignment = \App\Models\ServiceAssignment::find($id);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        $assignment = $this->verificationService->markMissed($assignment, $user);

        return response()->json([
            'success' => true,
            'message' => 'Visit marked as missed',
            'data' => [
                'assignment_id' => $assignment->id,
                'verification_status' => $assignment->verification_status,
                'status' => $assignment->status,
            ],
        ]);
    }

    /**
     * Bulk resolve multiple alerts.
     *
     * POST /api/v2/jeopardy/alerts/bulk-resolve
     *
     * @return JsonResponse
     */
    public function bulkResolve(Request $request): JsonResponse
    {
        $request->validate([
            'assignment_ids' => 'required|array',
            'assignment_ids.*' => 'integer',
        ]);

        $user = $request->user();
        $count = $this->verificationService->bulkVerify(
            $request->input('assignment_ids'),
            $user,
            \App\Models\ServiceAssignment::VERIFICATION_SOURCE_COORDINATOR
        );

        return response()->json([
            'success' => true,
            'message' => "{$count} alerts resolved successfully",
            'data' => [
                'resolved_count' => $count,
            ],
        ]);
    }
}
