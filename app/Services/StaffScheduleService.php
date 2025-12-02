<?php

namespace App\Services;

use App\Models\ServiceAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * StaffScheduleService
 * 
 * Manages staff schedule data including upcoming and past appointments.
 * Provides schedule summaries for the Staff Profile page.
 */
class StaffScheduleService
{
    /**
     * Get upcoming appointments for a staff member.
     * 
     * @param int $staffUserId Staff user ID
     * @param int $days Number of days to look ahead (default 14)
     * @param int $limit Maximum appointments to return
     * @return Collection
     */
    public function getUpcomingAppointments(int $staffUserId, int $days = 14, int $limit = 50): Collection
    {
        return ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->where('scheduled_start', '>=', Carbon::now())
            ->where('scheduled_start', '<=', Carbon::now()->addDays($days))
            ->whereIn('status', [
                ServiceAssignment::STATUS_PLANNED,
                ServiceAssignment::STATUS_ACTIVE,
                ServiceAssignment::STATUS_IN_PROGRESS,
            ])
            ->with(['patient', 'serviceType', 'carePlan'])
            ->orderBy('scheduled_start')
            ->limit($limit)
            ->get()
            ->map(fn($a) => $this->formatAppointment($a));
    }

    /**
     * Get recent past appointments for a staff member.
     * 
     * @param int $staffUserId Staff user ID
     * @param int $days Number of days to look back (default 14)
     * @param int $limit Maximum appointments to return
     * @return Collection
     */
    public function getRecentAppointments(int $staffUserId, int $days = 14, int $limit = 50): Collection
    {
        return ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->where('scheduled_start', '<', Carbon::now())
            ->where('scheduled_start', '>=', Carbon::now()->subDays($days))
            ->with(['patient', 'serviceType', 'carePlan'])
            ->orderBy('scheduled_start', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($a) => $this->formatAppointment($a));
    }

    /**
     * Get schedule summary for a staff member.
     */
    public function getScheduleSummary(int $staffUserId): array
    {
        $now = Carbon::now();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        
        // This week's assignments
        $thisWeekAssignments = ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->whereBetween('scheduled_start', [$weekStart, $weekEnd])
            ->get();
        
        // Upcoming count (next 7 days from now)
        $upcomingCount = ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->where('scheduled_start', '>=', $now)
            ->where('scheduled_start', '<=', $now->copy()->addDays(7))
            ->whereIn('status', [
                ServiceAssignment::STATUS_PLANNED,
                ServiceAssignment::STATUS_ACTIVE,
            ])
            ->count();
        
        // Today's assignments
        $todayAssignments = ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->whereDate('scheduled_start', $now->toDateString())
            ->get();
        
        // Calculate scheduled hours this week
        $scheduledMinutes = $thisWeekAssignments->sum('duration_minutes');
        
        return [
            'this_week' => [
                'total_assignments' => $thisWeekAssignments->count(),
                'completed' => $thisWeekAssignments->where('status', ServiceAssignment::STATUS_COMPLETED)->count(),
                'scheduled' => $thisWeekAssignments->whereIn('status', [
                    ServiceAssignment::STATUS_PLANNED,
                    ServiceAssignment::STATUS_ACTIVE,
                ])->count(),
                'missed' => $thisWeekAssignments->where('status', ServiceAssignment::STATUS_MISSED)->count(),
                'scheduled_hours' => round($scheduledMinutes / 60, 1),
            ],
            'today' => [
                'total' => $todayAssignments->count(),
                'completed' => $todayAssignments->where('status', ServiceAssignment::STATUS_COMPLETED)->count(),
                'remaining' => $todayAssignments->whereIn('status', [
                    ServiceAssignment::STATUS_PLANNED,
                    ServiceAssignment::STATUS_ACTIVE,
                ])->count(),
            ],
            'upcoming_count' => $upcomingCount,
            'next_appointment' => $this->getNextAppointment($staffUserId),
        ];
    }

    /**
     * Get the next scheduled appointment for a staff member.
     */
    public function getNextAppointment(int $staffUserId): ?array
    {
        $next = ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->where('scheduled_start', '>=', Carbon::now())
            ->whereIn('status', [
                ServiceAssignment::STATUS_PLANNED,
                ServiceAssignment::STATUS_ACTIVE,
            ])
            ->with(['patient', 'serviceType'])
            ->orderBy('scheduled_start')
            ->first();
        
        return $next ? $this->formatAppointment($next) : null;
    }

    /**
     * Get scheduled hours for a specific week.
     */
    public function getWeeklyScheduledHours(int $staffUserId, ?Carbon $weekStart = null): float
    {
        $weekStart = $weekStart ?? Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        
        $totalMinutes = ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->whereBetween('scheduled_start', [$weekStart, $weekEnd])
            ->sum('duration_minutes');
        
        return round($totalMinutes / 60, 2);
    }

    /**
     * Get schedule by day for a week.
     */
    public function getWeeklyScheduleByDay(int $staffUserId, ?Carbon $weekStart = null): array
    {
        $weekStart = $weekStart ?? Carbon::now()->startOfWeek();
        
        $assignments = ServiceAssignment::where('assigned_user_id', $staffUserId)
            ->whereBetween('scheduled_start', [$weekStart, $weekStart->copy()->endOfWeek()])
            ->with(['patient', 'serviceType'])
            ->orderBy('scheduled_start')
            ->get();
        
        $schedule = [];
        
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dayAssignments = $assignments->filter(function ($a) use ($date) {
                return $a->scheduled_start?->isSameDay($date);
            });
            
            $schedule[] = [
                'date' => $date->toDateString(),
                'day_name' => $date->format('l'),
                'day_short' => $date->format('D'),
                'assignments' => $dayAssignments->map(fn($a) => $this->formatAppointment($a))->values(),
                'total_hours' => round($dayAssignments->sum('duration_minutes') / 60, 1),
                'count' => $dayAssignments->count(),
            ];
        }
        
        return $schedule;
    }

    /**
     * Format appointment for API response.
     */
    protected function formatAppointment(ServiceAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'patient_id' => $assignment->patient_id,
            'patient_name' => $assignment->patient?->full_name ?? 'Unknown',
            'service_type_code' => $assignment->serviceType?->code,
            'service_type_name' => $assignment->serviceType?->name ?? 'Unknown',
            'scheduled_start' => $assignment->scheduled_start?->toIso8601String(),
            'scheduled_date' => $assignment->scheduled_start?->toDateString(),
            'scheduled_time' => $assignment->scheduled_start?->format('g:i A'),
            'duration_minutes' => $assignment->duration_minutes,
            'status' => $assignment->status,
            'status_label' => $this->getStatusLabel($assignment->status),
            'status_color' => $this->getStatusColor($assignment->status),
            'address' => $assignment->patient?->address,
            'region' => $assignment->patient?->region,
        ];
    }

    /**
     * Get status label.
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            ServiceAssignment::STATUS_PLANNED => 'Scheduled',
            ServiceAssignment::STATUS_ACTIVE => 'Confirmed',
            ServiceAssignment::STATUS_IN_PROGRESS => 'In Progress',
            ServiceAssignment::STATUS_COMPLETED => 'Completed',
            ServiceAssignment::STATUS_MISSED => 'Missed',
            ServiceAssignment::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($status),
        };
    }

    /**
     * Get status color for UI.
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            ServiceAssignment::STATUS_PLANNED => 'blue',
            ServiceAssignment::STATUS_ACTIVE => 'teal',
            ServiceAssignment::STATUS_IN_PROGRESS => 'amber',
            ServiceAssignment::STATUS_COMPLETED => 'green',
            ServiceAssignment::STATUS_MISSED => 'red',
            ServiceAssignment::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }
}
