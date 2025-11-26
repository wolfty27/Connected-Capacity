<?php

namespace App\Http\Controllers\Api\V2\CareOps;

use App\Http\Controllers\Controller;
use App\Services\CareOps\AssignmentEstimationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentEstimationController extends Controller
{
    protected $service;

    public function __construct(AssignmentEstimationService $service)
    {
        $this->service = $service;
    }

    public function estimate(Request $request): JsonResponse
    {
        $estimate = $this->service->calculateEstimate($request->all());
        return response()->json($estimate);
    }
}