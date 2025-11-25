<?php

namespace App\Services\CareOps;

class AssignmentEstimationService
{
    public function calculateEstimate(array $data)
    {
        $freqPerWeek = $this->parseFrequency($data['frequency_rule'] ?? 'Weekly');
        $durationHours = ($data['duration_minutes'] ?? 60) / 60;
        $weeks = $data['expected_weeks'] ?? 12;

        $hoursPerWeek = $freqPerWeek * $durationHours;
        $totalHours = $hoursPerWeek * $weeks;
        
        // Mock travel: 5km per visit
        $travelKm = $freqPerWeek * 5;

        return [
            'visits_per_week' => $freqPerWeek,
            'hours_per_week' => $hoursPerWeek,
            'total_hours' => $totalHours,
            'estimated_travel_km' => $travelKm,
            'is_high_volume' => $hoursPerWeek > 20
        ];
    }

    private function parseFrequency($rule)
    {
        // Simple parser for prototype
        if (stripos($rule, 'Daily') !== false) return 7;
        if (stripos($rule, '2x') !== false) return 2;
        if (stripos($rule, '3x') !== false) return 3;
        if (stripos($rule, 'Weekly') !== false) return 1;
        if (stripos($rule, 'Bi-weekly') !== false) return 0.5;
        if (stripos($rule, 'Continuous') !== false) return 0; // Digital
        return 1;
    }
}