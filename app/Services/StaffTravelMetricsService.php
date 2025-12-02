<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\User;
use App\Services\Travel\TravelTimeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * StaffTravelMetricsService
 * 
 * Calculates travel metrics for staff members based on their service assignments.
 * Uses the unified TravelTimeService infrastructure for all travel calculations.
 * 
 * Travel time is calculated between consecutive assignments in chronological order.
 */
class StaffTravelMetricsService
{
    public function __construct(
        protected TravelTimeService $travelTimeService
    ) {}

    /**
     * Get comprehensive travel metrics for a staff member.
     * 
     * @param int $staffUserId Staff user ID
     * @param Carbon|null $weekStart Start of week (defaults to current week)
     * @return array Travel metrics summary
     */
    public function getWeeklyTravelMetrics(int $staffUserId, ?Carbon $weekStart = null): array
    {
        $weekStart = $weekStart ?? Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        
        $assignments = $this->getAssignmentsWithTravel($staffUserId, $weekStart, $weekEnd);
        
        if ($assignments->isEmpty()) {
            return $this->emptyMetrics($weekStart);
        }
        
        $totalTravelMinutes = $assignments->sum('travel_minutes');
        $assignmentsWithTravel = $assignments->filter(fn($a) => $a['travel_minutes'] > 0);
        
        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'total_travel_minutes' => $totalTravelMinutes,
            'total_travel_hours' => round($totalTravelMinutes / 60, 2),
            'total_assignments' => $assignments->count(),
            'assignments_with_travel' => $assignmentsWithTravel->count(),
            'average_travel_per_assignment' => $assignmentsWithTravel->count() > 0
                ? round($totalTravelMinutes / $assignmentsWithTravel->count(), 1)
                : 0,
            'average_travel_per_day' => round($totalTravelMinutes / 5, 1), // Assume 5-day work week
            'by_day' => $this->groupByDay($assignments),
            'by_region' => $this->groupByRegion($assignments),
        ];
    }

    /**
     * Get per-assignment travel details for a staff member.
     * 
     * @return Collection Collection of assignments with travel_minutes
     */
    public function getAssignmentTravelDetails(int $staffUserId, ?Carbon $start = null, ?Carbon $end = null, int $limit = 20): Collection
    {
        $start = $start ?? Carbon::now()->subDays(14);
        $end = $end ?? Carbon::now()->addDays(7);
        
        $assignments = ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->whereBetween('scheduled_start', [$start, $end])
            ->whereNotNull('scheduled_start')
            ->with(['patient', 'serviceType'])
            ->orderBy('scheduled_start')
            ->limit($limit * 2) // Get more to calculate travel between consecutive
            ->get();
        
        return $this->calculateTravelForAssignments($assignments);
    }

    /**
     * Get travel summary by region for a staff member.
     */
    public function getTravelByRegion(int $staffUserId, int $days = 30): array
    {
        $start = Carbon::now()->subDays($days);
        $end = Carbon::now();
        
        $assignments = $this->getAssignmentsWithTravel($staffUserId, $start, $end);
        
        return $this->groupByRegion($assignments);
    }

    /**
     * Calculate travel overhead for capacity planning.
     * Returns estimated travel hours per week for a staff member.
     */
    public function estimateWeeklyTravelOverhead(int $staffUserId): float
    {
        // Use last 4 weeks average if available
        $fourWeeksAgo = Carbon::now()->subWeeks(4)->startOfWeek();
        $lastWeek = Carbon::now()->subWeek()->endOfWeek();
        
        $assignments = ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->whereBetween('scheduled_start', [$fourWeeksAgo, $lastWeek])
            ->where('status', ServiceAssignment::STATUS_COMPLETED)
            ->with('patient')
            ->orderBy('scheduled_start')
            ->get();
        
        if ($assignments->count() < 5) {
            // Not enough data, return default estimate
            return 3.0; // Default 3 hours travel per week
        }
        
        $assignmentsWithTravel = $this->calculateTravelForAssignments($assignments);
        $totalTravelMinutes = $assignmentsWithTravel->sum('travel_minutes');
        $weeks = 4;
        
        return round(($totalTravelMinutes / 60) / $weeks, 2);
    }

    /**
     * Get assignments with calculated travel times.
     */
    protected function getAssignmentsWithTravel(int $staffUserId, Carbon $start, Carbon $end): Collection
    {
        $assignments = ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->whereBetween('scheduled_start', [$start, $end])
            ->whereNotNull('scheduled_start')
            ->with(['patient', 'serviceType'])
            ->orderBy('scheduled_start')
            ->get();
        
        return $this->calculateTravelForAssignments($assignments);
    }

    /**
     * Calculate travel time between consecutive assignments.
     */
    protected function calculateTravelForAssignments(Collection $assignments): Collection
    {
        if ($assignments->isEmpty()) {
            return collect();
        }
        
        $result = collect();
        $previousAssignment = null;
        
        foreach ($assignments as $assignment) {
            $travelMinutes = 0;
            $travelFrom = null;
            
            if ($previousAssignment && $this->isSameDay($previousAssignment, $assignment)) {
                // Calculate travel from previous assignment
                $travelMinutes = $this->calculateTravelBetween($previousAssignment, $assignment);
                $travelFrom = $previousAssignment->patient?->full_name ?? 'Previous Visit';
            }
            
            $result->push([
                'assignment_id' => $assignment->id,
                'patient_id' => $assignment->patient_id,
                'patient_name' => $assignment->patient?->full_name ?? 'Unknown',
                'service_type' => $assignment->serviceType?->code ?? 'Unknown',
                'service_name' => $assignment->serviceType?->name ?? 'Unknown',
                'scheduled_at' => $assignment->scheduled_start?->toIso8601String(),
                'scheduled_date' => $assignment->scheduled_start?->toDateString(),
                'status' => $assignment->status,
                'travel_minutes' => $travelMinutes,
                'travel_from' => $travelFrom,
                'region' => $assignment->patient?->region ?? 'Unknown',
                'latitude' => $assignment->patient?->latitude,
                'longitude' => $assignment->patient?->longitude,
            ]);
            
            $previousAssignment = $assignment;
        }
        
        return $result;
    }

    /**
     * Calculate travel time between two assignments.
     */
    protected function calculateTravelBetween(ServiceAssignment $from, ServiceAssignment $to): int
    {
        $fromPatient = $from->patient;
        $toPatient = $to->patient;
        
        if (!$fromPatient || !$toPatient) {
            return 0;
        }
        
        // Check for valid coordinates
        if (!$this->hasValidCoordinates($fromPatient) || !$this->hasValidCoordinates($toPatient)) {
            return 15; // Default 15 minutes if no coordinates
        }
        
        return $this->travelTimeService->getTravelMinutes(
            $fromPatient->latitude,
            $fromPatient->longitude,
            $toPatient->latitude,
            $toPatient->longitude,
            $to->scheduled_start ?? Carbon::now()
        );
    }

    /**
     * Check if patient has valid coordinates.
     */
    protected function hasValidCoordinates(?Patient $patient): bool
    {
        return $patient 
            && $patient->latitude !== null 
            && $patient->longitude !== null
            && $patient->latitude != 0
            && $patient->longitude != 0;
    }

    /**
     * Check if two assignments are on the same day.
     */
    protected function isSameDay(ServiceAssignment $a, ServiceAssignment $b): bool
    {
        if (!$a->scheduled_start || !$b->scheduled_start) {
            return false;
        }
        
        return $a->scheduled_start->isSameDay($b->scheduled_start);
    }

    /**
     * Group assignments by day with travel totals.
     */
    protected function groupByDay(Collection $assignments): array
    {
        $grouped = $assignments->groupBy('scheduled_date');
        
        return $grouped->map(function ($dayAssignments, $date) {
            return [
                'date' => $date,
                'day_name' => Carbon::parse($date)->format('l'),
                'total_travel_minutes' => $dayAssignments->sum('travel_minutes'),
                'assignment_count' => $dayAssignments->count(),
            ];
        })->values()->toArray();
    }

    /**
     * Group assignments by region with travel totals.
     */
    protected function groupByRegion(Collection $assignments): array
    {
        $grouped = $assignments->groupBy('region');
        
        return $grouped->map(function ($regionAssignments, $region) {
            return [
                'region' => $region ?: 'Unknown',
                'total_travel_minutes' => $regionAssignments->sum('travel_minutes'),
                'assignment_count' => $regionAssignments->count(),
                'average_travel' => $regionAssignments->count() > 0
                    ? round($regionAssignments->sum('travel_minutes') / $regionAssignments->count(), 1)
                    : 0,
            ];
        })->values()->toArray();
    }

    /**
     * Return empty metrics structure.
     */
    protected function emptyMetrics(Carbon $weekStart): array
    {
        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->copy()->endOfWeek()->toDateString(),
            'total_travel_minutes' => 0,
            'total_travel_hours' => 0,
            'total_assignments' => 0,
            'assignments_with_travel' => 0,
            'average_travel_per_assignment' => 0,
            'average_travel_per_day' => 0,
            'by_day' => [],
            'by_region' => [],
        ];
    }
}
