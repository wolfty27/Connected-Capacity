<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\QinService;
use App\Models\QinRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * QinController - API endpoints for Quality Improvement Notice management.
 *
 * Provides endpoints for:
 * - GET /v2/qin/active - Get active (issued) QINs
 * - GET /v2/qin/potential - Get potential QINs based on metric breaches
 * - GET /v2/qin/metrics - Get comprehensive QIN metrics for dashboard
 * - GET /v2/qin/all - Get all QIN records (for manager page)
 * - POST /v2/ohah/qin-webhook - Stub for OHaH webhook ingestion
 */
class QinController extends Controller
{
    public function __construct(
        protected QinService $qinService
    ) {}

    /**
     * GET /v2/qin/active
     *
     * Returns officially issued Active QINs (non-closed) for the organization.
     */
    public function active(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['error' => 'Organization not found'], 403);
        }

        $activeRecords = $this->qinService->getActiveQinRecords($organizationId);

        return response()->json([
            'data' => [
                'count' => $activeRecords->count(),
                'records' => $activeRecords->map(fn ($qin) => [
                    'id' => $qin->id,
                    'qin_number' => $qin->qin_number,
                    'indicator' => $qin->indicator,
                    'band_breach' => $qin->band_breach,
                    'issued_date' => $qin->issued_date->toDateString(),
                    'qip_due_date' => $qin->qip_due_date?->toDateString(),
                    'status' => $qin->status,
                    'status_label' => $qin->status_label,
                    'is_overdue' => $qin->isOverdue(),
                    'days_until_due' => $qin->daysUntilDue(),
                    'ohah_contact' => $qin->ohah_contact,
                ])->toArray(),
            ],
        ]);
    }

    /**
     * GET /v2/qin/potential
     *
     * Returns potential QINs based on current metric breaches.
     * These are NOT officially issued - just indicators of compliance risk.
     */
    public function potential(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['error' => 'Organization not found'], 403);
        }

        $potential = $this->qinService->calculatePotentialBreaches($organizationId);

        return response()->json([
            'data' => $potential,
            'meta' => [
                'description' => 'Potential QINs based on current metric breaches. These are informational and do not represent officially issued QINs.',
            ],
        ]);
    }

    /**
     * GET /v2/qin/metrics
     *
     * Returns comprehensive QIN metrics for dashboard display.
     * Includes both active (issued) and potential (calculated) QINs.
     */
    public function metrics(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['error' => 'Organization not found'], 403);
        }

        $metrics = $this->qinService->getMetrics($organizationId);

        return response()->json([
            'data' => $metrics,
        ]);
    }

    /**
     * GET /v2/qin/all
     *
     * Returns all QIN records for the QIN Manager page.
     */
    public function all(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['error' => 'Organization not found'], 403);
        }

        $records = $this->qinService->getAllQinRecords($organizationId);

        // Summary counts
        $summary = [
            'active_count' => $records->whereIn('status', [
                QinRecord::STATUS_OPEN,
                QinRecord::STATUS_SUBMITTED,
                QinRecord::STATUS_UNDER_REVIEW,
            ])->count(),
            'open_count' => $records->where('status', QinRecord::STATUS_OPEN)->count(),
            'pending_review_count' => $records->whereIn('status', [
                QinRecord::STATUS_SUBMITTED,
                QinRecord::STATUS_UNDER_REVIEW,
            ])->count(),
            'closed_ytd' => $records
                ->where('status', QinRecord::STATUS_CLOSED)
                ->filter(fn ($qin) => $qin->closed_at && $qin->closed_at->year === now()->year)
                ->count(),
        ];

        return response()->json([
            'data' => $records->map(fn ($qin) => [
                'id' => $qin->id,
                'qin_number' => $qin->qin_number,
                'indicator' => $qin->indicator,
                'band_breach' => $qin->band_breach,
                'issued_date' => $qin->issued_date->toDateString(),
                'qip_due_date' => $qin->qip_due_date?->toDateString(),
                'status' => $qin->status,
                'status_label' => $qin->status_label,
                'is_overdue' => $qin->isOverdue(),
                'days_until_due' => $qin->daysUntilDue(),
                'ohah_contact' => $qin->ohah_contact,
                'closed_at' => $qin->closed_at?->toDateString(),
            ])->toArray(),
            'summary' => $summary,
        ]);
    }

    /**
     * POST /v2/ohah/qin-webhook
     *
     * Stub endpoint for OHaH webhook ingestion.
     * In production, this would receive QIN notifications from Ontario Health.
     *
     * Expected payload:
     * {
     *   "qin_number": "QIN-2025-001",
     *   "indicator": "Referral Acceptance Rate",
     *   "band_breach": "Band C (<95%)",
     *   "issued_date": "2025-12-02",
     *   "qip_due_date": "2025-12-09",
     *   "ohah_contact": "Sarah Smith"
     * }
     */
    public function webhook(Request $request): JsonResponse
    {
        // Validate webhook signature (stub - would verify OHaH signature in production)
        // $this->validateWebhookSignature($request);

        $validated = $request->validate([
            'qin_number' => 'required|string|unique:qin_records,qin_number',
            'indicator' => 'required|string',
            'band_breach' => 'required|string',
            'issued_date' => 'required|date',
            'qip_due_date' => 'nullable|date',
            'ohah_contact' => 'nullable|string',
            'organization_id' => 'required|exists:service_provider_organizations,id',
        ]);

        $qin = $this->qinService->createQinRecord([
            ...$validated,
            'source' => QinRecord::SOURCE_OHAH_WEBHOOK,
            'status' => QinRecord::STATUS_OPEN,
        ]);

        \Log::info('QIN received via webhook', [
            'qin_number' => $qin->qin_number,
            'organization_id' => $qin->organization_id,
        ]);

        return response()->json([
            'message' => 'QIN received successfully',
            'qin_id' => $qin->id,
            'qin_number' => $qin->qin_number,
        ], 201);
    }

    /**
     * POST /v2/qin/{id}/submit-qip
     *
     * Mark a QIN as having a QIP submitted.
     */
    public function submitQip(Request $request, int $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        $qin = QinRecord::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if ($qin->status !== QinRecord::STATUS_OPEN) {
            return response()->json(['error' => 'QIN is not in open status'], 422);
        }

        $notes = $request->input('notes', 'QIP submitted by ' . $request->user()?->name);
        $qin = $this->qinService->updateStatus($id, QinRecord::STATUS_SUBMITTED, $notes);

        return response()->json([
            'message' => 'QIP submitted successfully',
            'qin' => [
                'id' => $qin->id,
                'qin_number' => $qin->qin_number,
                'status' => $qin->status,
                'status_label' => $qin->status_label,
            ],
        ]);
    }
}
