<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateWeeklyHuddleReportJob;
use App\Services\HpgResponseService;
use App\Services\MissedCareService;
use App\Services\ReferralIntakeService;
use App\Services\SlaComplianceNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SlaComplianceController - OHaH SLA compliance dashboard endpoints
 *
 * Provides API endpoints for:
 * - Overall SLA compliance status
 * - HPG 15-minute response metrics
 * - First service 24-hour metrics
 * - Missed care 0% target metrics
 * - Weekly huddle report generation
 */
class SlaComplianceController extends Controller
{
    public function __construct(
        protected HpgResponseService $hpgService,
        protected MissedCareService $missedCareService,
        protected SlaComplianceNotificationService $notificationService,
        protected ReferralIntakeService $intakeService
    ) {}

    /**
     * Get overall SLA compliance status.
     *
     * GET /api/v2/sla/status
     */
    public function status(Request $request): JsonResponse
    {
        $organizationId = $request->input('organization_id');

        $status = $this->notificationService->getComplianceStatus($organizationId);

        return response()->json([
            'data' => $status,
        ]);
    }

    /**
     * Get detailed HPG response metrics.
     *
     * GET /api/v2/sla/hpg-response
     */
    public function hpgResponse(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(7);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();
        $organizationId = $request->input('organization_id');

        $metrics = $this->hpgService->getComplianceMetrics($startDate, $endDate, $organizationId);
        $dailyStats = $this->hpgService->getDailyStats($startDate, $endDate, $organizationId);

        return response()->json([
            'data' => [
                'summary' => [
                    'sla_target' => '15 minutes',
                    'total_referrals' => $metrics['total'],
                    'responded_in_time' => $metrics['compliant'],
                    'breaches' => $metrics['breached'],
                    'compliance_rate' => $metrics['compliance_rate'],
                    'average_response_minutes' => $metrics['average_response_minutes'],
                ],
                'daily_breakdown' => $dailyStats,
                'period' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Get missed care metrics.
     *
     * GET /api/v2/sla/missed-care
     */
    public function missedCare(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(7);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();
        $organizationId = $request->input('organization_id');

        $metrics = $this->missedCareService->calculate($organizationId, $startDate, $endDate);
        $byServiceType = $this->missedCareService->calculateByServiceType($organizationId, $startDate, $endDate);
        $trend = $this->missedCareService->getDailyTrend($organizationId, 14);
        $risk = $this->missedCareService->checkComplianceRisk($organizationId);

        return response()->json([
            'data' => [
                'summary' => [
                    'sla_target' => '0%',
                    'total_scheduled' => $metrics['total'],
                    'delivered' => $metrics['delivered'],
                    'missed' => $metrics['missed'],
                    'missed_rate' => $metrics['missed_rate'],
                    'compliant' => $metrics['compliance'],
                ],
                'by_service_type' => $byServiceType,
                'daily_trend' => $trend,
                'risk_assessment' => $risk,
                'period' => $metrics['period'],
            ],
        ]);
    }

    /**
     * Get list of missed assignments for review.
     *
     * GET /api/v2/sla/missed-assignments
     */
    public function missedAssignments(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(7);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();
        $organizationId = $request->input('organization_id');
        $limit = min($request->input('limit', 50), 100);

        $assignments = $this->missedCareService->getMissedAssignments(
            $organizationId,
            $startDate,
            $endDate,
            $limit
        );

        return response()->json([
            'data' => $assignments,
            'meta' => [
                'total' => $assignments->count(),
                'period' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Get SSPO performance by missed care.
     *
     * GET /api/v2/sla/sspo-performance
     */
    public function sspoPerformance(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(7);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();

        $bySspo = $this->missedCareService->calculateBySspo($startDate, $endDate);

        return response()->json([
            'data' => $bySspo,
            'meta' => [
                'total_organizations' => $bySspo->count(),
                'period' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Get referral intake metrics including InterRAI status.
     *
     * GET /api/v2/sla/intake-metrics
     */
    public function intakeMetrics(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(7);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();

        $metrics = $this->intakeService->getIntakeMetrics($startDate, $endDate);

        return response()->json([
            'data' => $metrics,
        ]);
    }

    /**
     * Get referrals needing InterRAI completion.
     *
     * GET /api/v2/sla/pending-interrai
     */
    public function pendingInterrai(Request $request): JsonResponse
    {
        $limit = min($request->input('limit', 50), 100);

        $referrals = $this->intakeService->getReferralsNeedingInterrai($limit);

        return response()->json([
            'data' => $referrals,
            'meta' => [
                'total' => $referrals->count(),
            ],
        ]);
    }

    /**
     * Run compliance check and dispatch alerts.
     *
     * POST /api/v2/sla/check
     */
    public function runCheck(): JsonResponse
    {
        $results = $this->notificationService->runAllChecks();

        return response()->json([
            'data' => $results,
            'message' => 'Compliance check completed',
        ]);
    }

    /**
     * Generate weekly huddle report.
     *
     * POST /api/v2/sla/huddle-report
     */
    public function generateHuddleReport(Request $request): JsonResponse
    {
        $organizationId = $request->input('organization_id');
        $weekStart = $request->input('week_start')
            ? Carbon::parse($request->input('week_start'))->startOfWeek()
            : now()->startOfWeek();

        // Dispatch job asynchronously
        GenerateWeeklyHuddleReportJob::dispatch($organizationId, $weekStart);

        return response()->json([
            'message' => 'Weekly huddle report generation started',
            'data' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekStart->copy()->endOfWeek()->toDateString(),
                'organization_id' => $organizationId,
                'status' => 'queued',
            ],
        ]);
    }

    /**
     * Get dashboard summary with all key metrics.
     *
     * GET /api/v2/sla/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $organizationId = $request->input('organization_id');
        $period = $request->input('period', '7d');

        $startDate = match ($period) {
            '24h' => now()->subHours(24),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            'mtd' => now()->startOfMonth(),
            default => now()->subDays(7),
        };
        $endDate = now();

        // Gather all metrics
        $hpgMetrics = $this->hpgService->getComplianceMetrics($startDate, $endDate, $organizationId);
        $missedMetrics = $this->missedCareService->calculate($organizationId, $startDate, $endDate);
        $missedRisk = $this->missedCareService->checkComplianceRisk($organizationId);
        $intakeMetrics = $this->intakeService->getIntakeMetrics($startDate, $endDate);

        // Calculate overall compliance score
        $overallScore = $this->calculateOverallScore($hpgMetrics, $missedMetrics);

        return response()->json([
            'data' => [
                'overall' => [
                    'score' => $overallScore,
                    'status' => $this->getStatusLabel($overallScore),
                    'compliant' => $overallScore >= 95,
                ],
                'hpg_response' => [
                    'compliance_rate' => $hpgMetrics['compliance_rate'],
                    'breaches' => $hpgMetrics['breached'],
                    'average_minutes' => $hpgMetrics['average_response_minutes'],
                    'target' => '15 min',
                    'status' => $hpgMetrics['breached'] === 0 ? 'compliant' : 'breached',
                ],
                'missed_care' => [
                    'rate' => $missedMetrics['missed_rate'],
                    'count' => $missedMetrics['missed'],
                    'total' => $missedMetrics['total'],
                    'target' => '0%',
                    'status' => $missedMetrics['compliance'] ? 'compliant' : 'at_risk',
                    'risk' => $missedRisk,
                ],
                'interrai' => [
                    'completion_rate' => $intakeMetrics['interrai_completion_rate'],
                    'pending' => $intakeMetrics['needing_interrai_completion'],
                    'total_referrals' => $intakeMetrics['total_referrals'],
                ],
                'period' => [
                    'label' => $period,
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Calculate overall compliance score (0-100).
     */
    protected function calculateOverallScore(array $hpgMetrics, array $missedMetrics): float
    {
        $score = 100.0;

        // HPG response weight: 40%
        $hpgScore = $hpgMetrics['compliance_rate'];
        $score -= (100 - $hpgScore) * 0.4;

        // Missed care weight: 60% (more important per OHaH)
        $missedScore = 100 - ($missedMetrics['missed_rate'] * 10); // 10x penalty
        $missedScore = max(0, $missedScore);
        $score -= (100 - $missedScore) * 0.6;

        return max(0, round($score, 1));
    }

    /**
     * Get status label from score.
     */
    protected function getStatusLabel(float $score): string
    {
        return match (true) {
            $score >= 98 => 'excellent',
            $score >= 95 => 'good',
            $score >= 85 => 'needs_attention',
            $score >= 70 => 'at_risk',
            default => 'critical',
        };
    }
}
