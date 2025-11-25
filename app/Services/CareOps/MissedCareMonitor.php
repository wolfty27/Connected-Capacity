<?php

namespace App\Services\CareOps;

use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MissedCareMonitor
{
    /**
     * Identify visits that have exceeded their clinical window and are not completed.
     * Contract Rule: 0% Missed Care Target.
     * 
     * @return Collection
     */
    public function detectMissedCare(): Collection
    {
        $now = Carbon::now();
        
        // In a real scenario, we would use 'window_end_at'.
        // For prototype, we assume a visit is missed if it hasn't started 
        // 2 hours after its scheduled time.
        $threshold = $now->copy()->subHours(2);

        return Visit::with(['patient.user', 'careAssignment.assignedUser'])
            ->where('status', 'pending')
            ->where('scheduled_at', '<', $threshold)
            ->get()
            ->map(function ($visit) {
                $visit->risk_level = 'CRITICAL'; // Missed
                $visit->breach_duration = $visit->scheduled_at->diffForHumans();
                return $visit;
            });
    }

    /**
     * Identify visits approaching their deadline (Jeopardy Board).
     * Contract Rule: "At Risk" if < 2 hours remaining in window.
     * 
     * @return Collection
     */
    public function detectJeopardy(): Collection
    {
        $now = Carbon::now();
        $upcoming = $now->copy()->addHours(2);

        return Visit::with(['patient.user', 'careAssignment.assignedUser'])
            ->where('status', 'pending')
            ->whereBetween('scheduled_at', [$now, $upcoming])
            ->get()
            ->map(function ($visit) {
                $visit->risk_level = 'WARNING'; // Jeopardy
                $visit->time_remaining = $visit->scheduled_at->diffInMinutes($now, true) . ' mins';
                return $visit;
            });
    }

    /**
     * Aggregate all risks for the Command Center.
     */
    public function getRiskSnapshot(): array
    {
        $missed = $this->detectMissedCare();
        $jeopardy = $this->detectJeopardy();

        return [
            'missed_count' => $missed->count(),
            'jeopardy_count' => $jeopardy->count(),
            'risks' => $missed->merge($jeopardy)->sortBy('scheduled_at')->values()
        ];
    }
}