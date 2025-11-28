<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\CareOps\JeopardyBoardService;
use App\Services\MissedCareService;
use App\Services\CareOpsMetricsService;
use Illuminate\Http\Request;

/**
 * SPO Dashboard Controller
 *
 * Provides data for the SPO Command Center including:
 * - Missed Care Rate (from MissedCareService)
 * - Jeopardy Board alerts (from JeopardyBoardService)
 * - Intake Queue
 * - Partner performance
 *
 * Per OHaH RFP: Target is 0% missed care.
 */
class SpoDashboardController extends Controller
{
    public function __construct(
        protected JeopardyBoardService $jeopardyService,
        protected MissedCareService $missedCareService,
        protected CareOpsMetricsService $metricsService
    ) {}

    public function index(Request $request)
    {
        $organizationId = $request->user()?->organization_id;

        // Get Jeopardy Board data from the service
        $jeopardyData = $this->jeopardyService->getActiveAlerts($organizationId);

        // Get Missed Care metrics from the service (uses 28-day window by default)
        $missedCareMetrics = $this->missedCareService->calculateForOrg($organizationId);

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
