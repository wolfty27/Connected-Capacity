<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\CareOps\MissedCareMonitor;
use Illuminate\Http\Request;

class SpoDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Use Service to get real/simulated risk data
        $monitor = new MissedCareMonitor();
        $risks = $monitor->getRiskSnapshot();

        // Fetch Intake Queue
        $intakeQueue = Patient::with('user', 'hospital')
            ->where('status', 'referral_received')
            ->take(5)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->user->name ?? 'Unknown',
                    'source' => $p->hospital->user->name ?? 'Hospital', // Assuming hospital has user
                    'received_at' => $p->created_at->toIso8601String(),
                    'ohip' => $p->ohip
                ];
            });

        // If no risks found (likely due to empty DB), mock one for demo purposes
        if ($risks['missed_count'] === 0 && $risks['jeopardy_count'] === 0) {
             $risks['risks'] = [
                [
                    'risk_level' => 'CRITICAL',
                    'breach_duration' => '15m ago',
                    'patient' => ['user' => ['name' => 'Jane Doe']],
                    'care_assignment' => ['assigned_user' => ['name' => 'Nurse Joy']],
                    'reason' => 'Visit Verification Overdue'
                ],
                [
                    'risk_level' => 'WARNING',
                    'time_remaining' => '45m',
                    'patient' => ['user' => ['name' => 'John Smith']],
                    'care_assignment' => ['assigned_user' => ['name' => 'PSW Team A']],
                    'reason' => 'Late Start Risk'
                ]
             ];
             $risks['missed_count'] = 1;
        }

        $data = [
            'kpi' => [
                'missed_care' => [
                    'count_24h' => $risks['missed_count'],
                    'status' => $risks['missed_count'] > 0 ? 'critical' : 'success'
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
            'jeopardy_board' => $risks['risks'],
            'intake_queue' => $intakeQueue,
            'partners' => [
                [
                    'id' => 1,
                    'name' => 'Mindfulness Care Partners',
                    'referrals_active' => 12,
                    'referrals_pending' => 4,
                    'visit_completion_rate' => 98,
                    'capacity_status' => 'High'
                ],
                [
                    'id' => 2,
                    'name' => 'Active Rehab Solutions',
                    'referrals_active' => 8,
                    'referrals_pending' => 1,
                    'visit_completion_rate' => 92,
                    'capacity_status' => 'Moderate'
                ]
            ],
            'quality' => [
                'patient_satisfaction' => 4.8,
                'incident_rate' => 0.5
            ]
        ];

        return response()->json($data);
    }
}
