<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\SspoPerformanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SspoPerformanceController - SSPO partner performance metrics API
 *
 * Provides endpoints for:
 * - Individual SSPO performance metrics
 * - SSPO rankings and comparisons
 * - Service type and decline reason breakdowns
 * - Performance trends over time
 */
class SspoPerformanceController extends Controller
{
    public function __construct(
        protected SspoPerformanceService $performanceService
    ) {}

    /**
     * Get comprehensive performance metrics for an SSPO.
     *
     * GET /api/v2/sspo/{id}/performance
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(30);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();

        $metrics = $this->performanceService->getPerformanceMetrics($id, $startDate, $endDate);

        if (isset($metrics['error'])) {
            return response()->json(['message' => $metrics['error']], 404);
        }

        return response()->json([
            'data' => $metrics,
        ]);
    }

    /**
     * Get all SSPO rankings.
     *
     * GET /api/v2/sspo/rankings
     */
    public function rankings(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(30);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();

        $rankings = $this->performanceService->getAllSspoRankings($startDate, $endDate);

        return response()->json([
            'data' => $rankings,
            'meta' => [
                'total' => $rankings->count(),
                'period' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Get acceptance metrics for an SSPO.
     *
     * GET /api/v2/sspo/{id}/acceptance
     */
    public function acceptance(Request $request, int $id): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(30);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();

        $metrics = $this->performanceService->getAcceptanceMetrics($id, $startDate, $endDate);

        return response()->json([
            'data' => $metrics,
            'meta' => [
                'period' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Get response time metrics for an SSPO.
     *
     * GET /api/v2/sspo/{id}/response-time
     */
    public function responseTime(Request $request, int $id): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(30);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();

        $metrics = $this->performanceService->getResponseTimeMetrics($id, $startDate, $endDate);

        return response()->json([
            'data' => $metrics,
            'meta' => [
                'period' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Get daily performance trend for an SSPO.
     *
     * GET /api/v2/sspo/{id}/trend
     */
    public function trend(Request $request, int $id): JsonResponse
    {
        $days = min($request->input('days', 14), 90);

        $trend = $this->performanceService->getDailyTrend($id, $days);

        return response()->json([
            'data' => $trend,
            'meta' => [
                'days' => $days,
            ],
        ]);
    }

    /**
     * Get service type breakdown for an SSPO.
     *
     * GET /api/v2/sspo/{id}/service-types
     */
    public function serviceTypes(Request $request, int $id): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(30);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();

        $breakdown = $this->performanceService->getServiceTypeBreakdown($id, $startDate, $endDate);

        return response()->json([
            'data' => $breakdown,
            'meta' => [
                'total_service_types' => $breakdown->count(),
                'period' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Get decline reasons for an SSPO.
     *
     * GET /api/v2/sspo/{id}/decline-reasons
     */
    public function declineReasons(Request $request, int $id): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->subDays(30);
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now();

        $reasons = $this->performanceService->getDeclineReasons($id, $startDate, $endDate);

        return response()->json([
            'data' => $reasons,
            'meta' => [
                'total_declines' => $reasons->sum('count'),
                'period' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Get SSPO dashboard summary.
     *
     * GET /api/v2/sspo/{id}/dashboard
     */
    public function dashboard(Request $request, int $id): JsonResponse
    {
        $period = $request->input('period', '30d');

        $startDate = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            'mtd' => now()->startOfMonth(),
            'ytd' => now()->startOfYear(),
            default => now()->subDays(30),
        };
        $endDate = now();

        $metrics = $this->performanceService->getPerformanceMetrics($id, $startDate, $endDate);

        if (isset($metrics['error'])) {
            return response()->json(['message' => $metrics['error']], 404);
        }

        $trend = $this->performanceService->getDailyTrend($id, min(14, $startDate->diffInDays(now())));

        return response()->json([
            'data' => [
                'organization' => $metrics['organization'],
                'summary' => [
                    'acceptance_rate' => $metrics['acceptance']['acceptance_rate'],
                    'avg_response_minutes' => $metrics['response_time']['average_minutes'],
                    'completion_rate' => $metrics['service_delivery']['completion_rate'],
                    'missed_care_rate' => $metrics['missed_care']['missed_rate'],
                    'performance_score' => $metrics['ranking']['performance_score'],
                    'rank' => $metrics['ranking']['rank'],
                    'total_sspos' => $metrics['ranking']['total_sspos'],
                ],
                'acceptance' => $metrics['acceptance'],
                'service_delivery' => $metrics['service_delivery'],
                'trend' => $trend,
                'period' => [
                    'label' => $period,
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        ]);
    }
}
