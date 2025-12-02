<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\CareOps\JeopardyBoardService;
use App\Services\MissedCareService;
use App\Services\CareOpsMetricsService;
use App\Services\QinService;
use App\Services\ReferralMetricsService;
use App\Services\TfsMetricsService;
use Illuminate\Http\Request;

/**
 * SPO Dashboard Controller
 *
 * Provides data for the SPO Command Center including:
 * - Referral Acceptance Rate (from ReferralMetricsService)
 * - Time-to-First-Service (from TfsMetricsService)
 * - Missed Care Rate (from MissedCareService)
 * - Jeopardy Board alerts (from JeopardyBoardService)
 * - Active QINs and Potential QINs (from QinService)
 * - Intake Queue
 * - Partner performance
 *
 * Per OHaH RFP: Target is 100% referral acceptance, <24h TFS, 0% missed care, 0 active QINs.
 */
class SpoDashboardController extends Controller
{
    public function __construct(
        protected JeopardyBoardService $jeopardyService,
        protected MissedCareService $missedCareService,
        protected CareOpsMetricsService $metricsService,
        protected QinService $qinService,
        protected ReferralMetricsService $referralMetricsService,
        protected TfsMetricsService $tfsMetricsService
    ) {}

    public function index(Request $request)
    {
        $organizationId = $request->user()?->organization_id;

        // Get Jeopardy Board data from the service
        $jeopardyData = $this->jeopardyService->getActiveAlerts($organizationId);

        // Get Missed Care metrics from the service (uses 28-day window by default)
        $missedCareMetrics = $this->missedCareService->calculateForOrg($organizationId);

        // Get Referral Acceptance metrics (uses 28-day window by default)
        $referralMetrics = $this->referralMetricsService->calculate($organizationId);

        // Get Time-to-First-Service metrics (uses 28-day window by default)
        $tfsMetrics = $this->tfsMetricsService->calculate($organizationId);

        // Get QIN metrics from QinService (hybrid model)
        $qinMetrics = $organizationId 
            ? $this->qinService->getMetrics($organizationId)
            : ['active_count' => 0, 'potential_count' => 0, 'potential_breaches' => []];

        // Fetch Intake Queue - patients flagged as in queue
        $intakeQueue = Patient::with('user', 'hospital')
            ->where('is_in_queue', true)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->user->name ?? 'Unknown',
                    'source' => $p->hospital->name ?? ($p->hospital->user->name ?? 'Hospital'),
                    'received_at' => $p->created_at->toIso8601String(),
                    'ohip' => $p->ohip
                ];
            });

        $data = [
            'kpi' => [
                // Referral Acceptance - from ReferralMetricsService
                'referral_acceptance' => [
                    'rate_percent' => $referralMetrics->ratePercent,
                    'accepted' => $referralMetrics->accepted,
                    'total' => $referralMetrics->total,
                    'pending' => $referralMetrics->pending,
                    'band' => $referralMetrics->band,
                    'is_compliant' => $referralMetrics->isCompliant(),
                ],
                // Time-to-First-Service - from TfsMetricsService
                'time_to_first_service' => [
                    'average_hours' => $tfsMetrics->averageHours,
                    'median_hours' => $tfsMetrics->medianHours,
                    'patients_with_first_service' => $tfsMetrics->patientsWithFirstService,
                    'patients_total' => $tfsMetrics->patientsTotal,
                    'band' => $tfsMetrics->band,
                    'is_compliant' => $tfsMetrics->isCompliant(),
                    'formatted_average' => $tfsMetrics->getFormattedAverage(),
                ],
                'missed_care' => [
                    'count_24h' => $jeopardyData['critical_count'],
                    'status' => $jeopardyData['critical_count'] > 0 ? 'critical' : 'success',
                    // Add the actual missed care rate metrics
                    'rate_percent' => $missedCareMetrics->ratePercent,
                    'missed_events' => $missedCareMetrics->missedEvents,
                    'delivered_events' => $missedCareMetrics->deliveredEvents,
                    'is_compliant' => $missedCareMetrics->isCompliant,
                    'band' => $missedCareMetrics->getComplianceBand(),
                ],
                // Active QINs - officially issued by OHaH
                'active_qins' => [
                    'count' => $qinMetrics['active_count'],
                    'open_count' => $qinMetrics['open_count'] ?? 0,
                    'pending_review_count' => $qinMetrics['pending_review_count'] ?? 0,
                    'closed_ytd' => $qinMetrics['closed_ytd'] ?? 0,
                    'records' => $qinMetrics['active_records'] ?? [],
                ],
                // Potential QINs - calculated from metric breaches (informational)
                'potential_qins' => [
                    'count' => $qinMetrics['potential_count'],
                    'breaches' => $qinMetrics['potential_breaches'] ?? [],
                ],
                'unfilled_shifts' => [
                    'count_48h' => 5,
                    'status' => 'warning',
                    'impacted_patients' => 4
                ],
                'program_volume' => [
                    'active_bundles' => 124,
                    'trend_week' => '+5%'
                ]
            ],
            // Jeopardy Board data with full structure
            'jeopardy_board' => $jeopardyData['alerts'],
            'jeopardy_summary' => [
                'total_active' => $jeopardyData['total_active'],
                'critical_count' => $jeopardyData['critical_count'],
                'warning_count' => $jeopardyData['warning_count'],
            ],
            // Full missed care metrics for detailed display
            'missed_care' => $missedCareMetrics->toArray(),
            // Full referral metrics for detailed display
            'referral_metrics' => $referralMetrics->toArray(),
            // Full TFS metrics for detailed display
            'tfs_metrics' => $tfsMetrics->toArray(),
            'intake_queue' => $intakeQueue,
            'partners' => $this->metricsService->getPartnerPerformance($organizationId ?? 1),
            'quality' => [
                'patient_satisfaction' => 4.8,
                'incident_rate' => 0.5
            ]
        ];

        return response()->json($data);
    }
}
