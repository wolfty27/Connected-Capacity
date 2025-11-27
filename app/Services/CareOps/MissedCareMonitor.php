<?php

namespace App\Services\CareOps;

use App\Models\ServiceAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MissedCareMonitor
{
    /**
     * Identify service assignments that have exceeded their clinical window and are not completed.
     * Contract Rule: 0% Missed Care Target.
     *
     * @param int|null $organizationId Filter by organization
     * @return Collection
     */
    public function detectMissedCare(?int $organizationId = null): Collection
    {
        $now = Carbon::now();

        // A service assignment is missed if:
        // - Status is 'missed' OR
        // - Status is 'planned'/'pending' and scheduled_start was >2 hours ago
        $threshold = $now->copy()->subHours(2);

        $query = ServiceAssignment::with(['patient.user', 'assignedUser', 'serviceType'])
            ->where(function ($q) use ($threshold) {
                $q->where('status', ServiceAssignment::STATUS_MISSED)
                  ->orWhere(function ($sub) use ($threshold) {
                      $sub->whereIn('status', [ServiceAssignment::STATUS_PLANNED, ServiceAssignment::STATUS_PENDING])
                          ->where('scheduled_start', '<', $threshold);
                  });
            });

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        return $query->get()->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'risk_level' => 'CRITICAL',
                'breach_duration' => $assignment->scheduled_start?->diffForHumans() ?? 'Unknown',
                'patient' => [
                    'id' => $assignment->patient?->id,
                    'user' => ['name' => $assignment->patient?->user?->name ?? 'Unknown'],
                ],
                'care_assignment' => [
                    'assigned_user' => ['name' => $assignment->assignedUser?->name ?? 'Unassigned'],
                ],
                'service_type' => $assignment->serviceType?->name ?? 'Unknown',
                'reason' => 'Visit Verification Overdue',
                'scheduled_start' => $assignment->scheduled_start?->toIso8601String(),
            ];
        });
    }

    /**
     * Identify service assignments approaching their deadline (Jeopardy Board).
     * Contract Rule: "At Risk" if < 2 hours remaining in window.
     *
     * @param int|null $organizationId Filter by organization
     * @return Collection
     */
    public function detectJeopardy(?int $organizationId = null): Collection
    {
        $now = Carbon::now();
        $upcoming = $now->copy()->addHours(2);

        $query = ServiceAssignment::with(['patient.user', 'assignedUser', 'serviceType'])
            ->whereIn('status', [ServiceAssignment::STATUS_PLANNED, ServiceAssignment::STATUS_PENDING])
            ->whereBetween('scheduled_start', [$now, $upcoming]);

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        return $query->get()->map(function ($assignment) use ($now) {
            $minutesRemaining = $now->diffInMinutes($assignment->scheduled_start, false);

            return [
                'id' => $assignment->id,
                'risk_level' => 'WARNING',
                'time_remaining' => $minutesRemaining . 'm',
                'patient' => [
                    'id' => $assignment->patient?->id,
                    'user' => ['name' => $assignment->patient?->user?->name ?? 'Unknown'],
                ],
                'care_assignment' => [
                    'assigned_user' => ['name' => $assignment->assignedUser?->name ?? 'Unassigned'],
                ],
                'service_type' => $assignment->serviceType?->name ?? 'Unknown',
                'reason' => 'Late Start Risk',
                'scheduled_start' => $assignment->scheduled_start?->toIso8601String(),
            ];
        });
    }

    /**
     * Aggregate all risks for the Command Center.
     *
     * @param int|null $organizationId Filter by organization
     * @return array
     */
    public function getRiskSnapshot(?int $organizationId = null): array
    {
        $missed = $this->detectMissedCare($organizationId);
        $jeopardy = $this->detectJeopardy($organizationId);

        return [
            'missed_count' => $missed->count(),
            'jeopardy_count' => $jeopardy->count(),
            'risks' => $missed->merge($jeopardy)
                ->sortBy('scheduled_start')
                ->values()
                ->toArray(),
        ];
    }
}