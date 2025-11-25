<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AiForecastController extends Controller
{
    public function forecast(Request $request)
    {
        // Mock AI response
        return response()->json([
            'insights' => [
                [
                    'title' => 'Capacity Warning',
                    'type' => 'warning',
                    'description' => 'Projected 15% surge in nursing visits over the weekend.',
                    'metric' => '+15% Volume'
                ],
                [
                    'title' => 'Optimization Opportunity',
                    'type' => 'success',
                    'description' => 'Reassigning 3 shifts in North York could reduce travel time.',
                    'metric' => '-45 mins Travel'
                ]
            ]
        ]);
    }
}
