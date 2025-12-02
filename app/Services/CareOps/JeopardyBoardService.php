<?php

namespace App\Services\CareOps;

use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Jeopardy Board Service
 *
 * Provides data for the SPO Command Center Jeopardy Board.
 *
 * The Jeopardy Board shows:
 * 1. CRITICAL alerts: Overdue unverified appointments (verification_status = PENDING past grace period)
 * 2. WARNING alerts: Upcoming appointments at risk (scheduled within 2 hours)
 *
 * Per OHaH contract: Target is 0% missed care.
 */
class JeopardyBoardService
{
    protected VisitVerificationService $verificationService;

    /**
     * Hours threshold for "at risk" warning alerts.
     */
    protected int $warningThresholdHours = 2;

    public function __construct(VisitVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Get all active alerts for the Jeopardy Board.
     *
     * @param int|null $organizationId Filter by organization
     * @return array
     */
    public function getActiveAlerts(?int $organizationId = null): array
    {
        $criticalAlerts = $this->getCriticalAlerts($organizationId);
        $warningAlerts = $this->getWarningAlerts($organizationId);

        $allAlerts = $criticalAlerts->merge($warningAlerts)
            ->sortBy('scheduled_start')
            ->values();

        return [
            'total_active' => $allAlerts->count(),
            'critical_count' => $criticalAlerts->count(),
            'warning_count' => $warningAlerts->count(),
            'alerts' => $allAlerts->toArray(),
        ];
    }

    /**
     * Get CRITICAL alerts - overdue unverified appointments.
     *
     * These are appointments where:
     * - verification_status = PENDING
     * - scheduled_start is past the grace period (default 24 hours)
     *
     * @param int|null $organizationId
     * @return Collection
     */
    public function getCriticalAlerts(?int $organizationId = null): Collection
    {
        $overdueAssignments = $this->verificationService->getOverdueAssignments($organizationId);

        return $overdueAssignments->map(function (ServiceAssignment $assignment) {
            $breachDuration = $this->calculateBreachDuration($assignment);

            return [
                'id' => $assignment->id,
                'type' => 'visit_verification_overdue',
                'risk_level' => 'CRITICAL',
                'patient' => [
                    'id' => $assignment->patient?->id,
                    'user' => ['name' => $assignment->patient?->user?->name ?? 'Unknown'],
                ],
                'care_assignment' => [
                    'id' => $assignment->id,
                    'assigned_user' => ['name' => $assignment->assignedUser?->name ?? 'Unassigned'],
                ],
                'service_type' => $assignment->serviceType?->name ?? 'Unknown',
                'reason' => 'Visit Verification Overdue',
                'breach_duration' => $breachDuration['display'],
                'breached_days_ago' => $breachDuration['days'],
                'scheduled_start' => $assignment->scheduled_start?->toIso8601String(),
                'assignment_id' => $assignment->id,
            ];
        });
    }

    /**
     * Get WARNING alerts - appointments at risk of being missed.
     *
     * These are appointments where:
     * - verification_status = PENDING
     * - status = planned or pending
     * - scheduled_start is within the next N hours (default 2)
     *
     * @param int|null $organizationId
     * @return Collection
     */
    public function getWarningAlerts(?int $organizationId = null): Collection
    {
        $now = Carbon::now();
        $upcoming = $now->copy()->addHours($this->warningThresholdHours);

        $query = ServiceAssignment::with(['patient.user', 'assignedUser', 'serviceType'])
            ->where('verification_status', ServiceAssignment::VERIFICATION_PENDING)
            ->whereIn('status', [ServiceAssignment::STATUS_PLANNED, ServiceAssignment::STATUS_PENDING])
            ->whereBetween('scheduled_start', [$now, $upcoming]);

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        return $query->orderBy('scheduled_start', 'asc')
            ->get()
            ->map(function (ServiceAssignment $assignment) use ($now) {
                $minutesRemaining = $now->diffInMinutes($assignment->scheduled_start, false);

                return [
                    'id' => $assignment->id,
                    'type' => 'late_start_risk',
                    'risk_level' => 'WARNING',
                    'patient' => [
                        'id' => $assignment->patient?->id,
                        'user' => ['name' => $assignment->patient?->user?->name ?? 'Unknown'],
                    ],
                    'care_assignment' => [
                        'id' => $assignment->id,
                        'assigned_user' => ['name' => $assignment->assignedUser?->name ?? 'Unassigned'],
                    ],
                    'service_type' => $assignment->serviceType?->name ?? 'Unknown',
                    'reason' => 'Late Start Risk',
                    'time_remaining' => $minutesRemaining . 'm',
                    'scheduled_start' => $assignment->scheduled_start?->toIso8601String(),
                    'assignment_id' => $assignment->id,
                ];
            });
    }

    /**
     * Resolve an alert by marking the assignment as verified.
     *
     * @param int $assignmentId
     * @param \App\Models\User|null $user
     * @return ServiceAssignment|null
     */
    public function resolveAlert(int $assignmentId, ?\App\Models\User $user = null): ?ServiceAssignment
    {
        $assignment = ServiceAssignment::find($assignmentId);

        if (!$assignment) {
            return null;
        }

        return $this->verificationService->markVerified(
            $assignment,
            $user,
            null,
            ServiceAssignment::VERIFICATION_SOURCE_COORDINATOR
        );
    }

    /**
     * Calculate how long ago the breach occurred.
     *
     * @param ServiceAssignment $assignment
     * @return array
     */
    protected function calculateBreachDuration(ServiceAssignment $assignment): array
    {
        if (!$assignment->scheduled_start) {
            return ['display' => 'Unknown', 'days' => 0];
        }

        $now = Carbon::now();
        $scheduledStart = $assignment->scheduled_start;

        $days = (int) $scheduledStart->diffInDays($now);
        $hours = (int) $scheduledStart->diffInHours($now) % 24;

        if ($days > 0) {
            $display = "{$days}d ago";
        } elseif ($hours > 0) {
            $display = "{$hours}h ago";
        } else {
            $minutes = $scheduledStart->diffInMinutes($now);
            $display = "{$minutes}m ago";
        }

        return ['display' => $display, 'days' => $days];
    }

    /**
     * Get summary statistics for the Jeopardy Board.
     *
     * @param int|null $organizationId
     * @return array
     */
    public function getSummaryStats(?int $organizationId = null): array
    {
        $alerts = $this->getActiveAlerts($organizationId);
        $verificationStats = $this->verificationService->getVerificationStats(
            $organizationId ?? 0,
            Carbon::now()->startOfWeek(),
            Carbon::now()
        );

        return [
            'active_alerts' => $alerts['total_active'],
            'critical_count' => $alerts['critical_count'],
            'warning_count' => $alerts['warning_count'],
            'weekly_missed_rate' => $verificationStats['missed_rate'],
            'weekly_verification_rate' => $verificationStats['verification_rate'],
            'is_compliant' => $verificationStats['is_compliant'],
        ];
    }
}
