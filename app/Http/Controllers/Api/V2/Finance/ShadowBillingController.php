<?php

namespace App\Http\Controllers\Api\V2\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShadowBillingController extends Controller
{
    protected $service;

    public function __construct(BillingService $service)
    {
        $this->service = $service;
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $report = $this->service->generateShadowBill($request->start_date, $request->end_date);
        return response()->json($report);
    }
}