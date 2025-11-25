<?php

namespace App\Http\Controllers\Api\V2\CareOps;

use App\Http\Controllers\Controller;
use App\Services\CareOps\FteComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FteComplianceController extends Controller
{
    protected $service;

    public function __construct(FteComplianceService $service)
    {
        $this->service = $service;
    }

    public function current(Request $request): JsonResponse
    {
        // Authorize? Usually handled by middleware
        $snapshot = $this->service->calculateSnapshot();
        return response()->json($snapshot);
    }

    public function project(Request $request): JsonResponse
    {
        $type = $request->input('type', 'part_time');
        $projection = $this->service->calculateProjection($type);
        return response()->json($projection);
    }
}