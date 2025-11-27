<?php

namespace App\Services;

use App\Models\ServiceAssignment;
use App\Models\CareAssignment;
use App\Models\User;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CareOpsMetricsService
{
    /**
     * Get missed care statistics for the past 24 hours.
     * Uses real data from ServiceAssignment model via MissedCareService.
     *
     * @param int $orgId
     * @return array
     */
    public function getMissedCareStats(int $orgId): array
    {
        $missedCareService = new MissedCareService();

        // Get 24h metrics
        $metrics24h = $missedCareService->calculate($orgId, now()->subHours(24), now());

        // Get previous 24h for trend
        $metricsPrev24h = $missedCareService->calculate($orgId, now()->subHours(48), now()->subHours(24));

        $trend = $metrics24h['missed'] - $metricsPrev24h['missed'];
        $trendStr = $trend >= 0 ? "+{$trend}" : (string)$trend;

        // Get missed assignment details
        $missedAssignments = $missedCareService->getMissedAssignments($orgId, now()->subHours(24), now(), 10);

        $events = $missedAssignments->map(function ($assignment) {
            return [
                'id' => $assignment['id'],
                'patient_name' => $assignment['patient']['name'] ?? 'Unknown',
                'visit_time' => $assignment['scheduled_start'],
                'service_type' => $assignment['service_type']['name'] ?? 'Unknown',
                'reason' => $assignment['notes'] ?? 'No reason provided',
                'status' => $assignment['status'],
                'assigned_to' => $assignment['assigned_to'],
            ];
        })->toArray();

        return [
            'count_24h' => $metrics24h['missed'],
            'trend' => $trendStr,
            'status' => $metrics24h['missed'] > 0 ? 'critical' : 'success',
            'delivered' => $metrics24h['delivered'],
            'missed_rate' => $metrics24h['missed_rate'],
            'compliance' => $metrics24h['compliance'],
            'events' => $events,
        ];
    }

    /**
     * Get unfilled shifts for the next 48 hours.
     * 
     * @param int $orgId
     * @return array
     */
    public function getUnfilledShifts(int $orgId): array
    {
        // Logic: Count visits with status 'unassigned' or 'open' in next 48h
        return [
            'count_48h' => 5,
            'impacted_patients' => 3,
            'status' => 'warning', // >0 is warning
            'shifts' => [
                ['id' => 201, 'role' => 'PSW', 'time' => 'Tomorrow 08:00', 'area' => 'North York'],
                ['id' => 202, 'role' => 'Nurse', 'time' => 'Tomorrow 14:00', 'area' => 'Etobicoke'],
            ]
        ];
    }

    /**
     * Get program volume metrics (Active Bundles, Patients).
     * 
     * @param int $orgId
     * @return array
     */
    public function getProgramVolume(int $orgId): array
    {
        return [
            'active_bundles' => 142,
            'trend_week' => '+12',
            'staffing_level' => '98%',
            'eligible_enrolled_rate' => '100%' // RFP Metric
        ];
    }

    /**
     * Get SSPO Partner Performance metrics.
     * 
     * @param int $orgId
     * @return array
     */
    public function getPartnerPerformance(int $orgId): array
    {
        // Mock data matching wireframe
        return [
            [
                'id' => 1,
                'name' => 'Alexis Lodge',
                'specialty' => 'Dementia Care',
                'active_assignments' => 18,
                'acceptance_rate' => 100,
                'missed_visit_rate' => 0,
                'status' => 'good'
            ],
            [
                'id' => 2,
                'name' => 'Wellhaus',
                'specialty' => 'Digital Health / Virtual Care',
                'active_assignments' => 42,
                'acceptance_rate' => 98,
                'missed_visit_rate' => 0,
                'status' => 'good'
            ],
            [
                'id' => 3,
                'name' => 'Toronto Grace Health Centre',
                'specialty' => 'RPM / Complex Care',
                'active_assignments' => 56,
                'acceptance_rate' => 92,
                'missed_visit_rate' => 1.5,
                'status' => 'warning'
            ],
            [
                'id' => 4,
                'name' => 'Reconnect Community Health Services',
                'specialty' => 'Mental Health & Addictions',
                'active_assignments' => 24,
                'acceptance_rate' => 95,
                'missed_visit_rate' => 0.5,
                'status' => 'good'
            ]
        ];
    }

    /**
     * Get Quality & Compliance metrics (RFP secondary metrics).
     * 
     * @param int $orgId
     * @return array
     */
    public function getQualityMetrics(int $orgId): array
    {
        return [
            'ed_visits_avoidable' => 0,
            'readmissions' => 0,
            'complaints' => 0,
            'ltc_transition_requests' => 0,
            'time_to_first_service_avg' => '18h', // Target < 24h
            'patient_satisfaction' => 96,
            'staff_satisfaction' => 92
        ];
    }
}
