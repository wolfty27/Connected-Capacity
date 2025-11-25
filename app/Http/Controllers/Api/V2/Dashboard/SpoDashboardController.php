<?php

namespace App\Http\Controllers\Api\V2\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\CareOpsMetricsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SpoDashboardController extends Controller
{
    protected $metricsService;

    public function __construct(CareOpsMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    /**
     * Get the main SPO Dashboard data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // In a real app, we'd get the Org ID from the authenticated user
        // $orgId = $request->user()->organization_id;
        $orgId = 1; // Default for now

        $data = [
            'kpi' => [
                'missed_care' => $this->metricsService->getMissedCareStats($orgId),
                'unfilled_shifts' => $this->metricsService->getUnfilledShifts($orgId),
                'program_volume' => $this->metricsService->getProgramVolume($orgId),
            ],
            'partners' => $this->metricsService->getPartnerPerformance($orgId),
            'quality' => $this->metricsService->getQualityMetrics($orgId),
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'org_id' => $orgId
            ]
        ];

        return response()->json($data);
    }
}
