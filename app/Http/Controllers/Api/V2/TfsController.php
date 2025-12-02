<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\TfsMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TFS (Time-to-First-Service) Controller
 *
 * Provides API endpoints for Time-to-First-Service metrics and details.
 */
class TfsController extends Controller
{
    public function __construct(
        protected TfsMetricsService $tfsService
    ) {}

    /**
     * Get TFS summary metrics.
     */
    public function summary(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        
        $metrics = $this->tfsService->calculate($organizationId);

        return response()->json([
            'data' => $metrics->toArray(),
        ]);
    }

    /**
     * Get detailed patient data for TFS calculation.
     */
    public function details(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        
        $metrics = $this->tfsService->calculate($organizationId);
        $patients = $this->tfsService->getPatientDetails($organizationId);

        // Group patients by status for summary
        $byStatus = collect($patients)->groupBy('status');

        return response()->json([
            'data' => [
                'summary' => $metrics->toArray(),
                'patients' => $patients,
                'counts' => [
                    'total' => count($patients),
                    'with_first_service' => collect($patients)->where('has_first_service', true)->count(),
                    'awaiting_first_service' => $byStatus->get('awaiting_first_service', collect())->count(),
                    'within_target' => $byStatus->get('within_target', collect())->count(),
                    'below_standard' => $byStatus->get('below_standard', collect())->count(),
                    'exceeded_target' => $byStatus->get('exceeded_target', collect())->count(),
                ],
            ],
        ]);
    }
}
