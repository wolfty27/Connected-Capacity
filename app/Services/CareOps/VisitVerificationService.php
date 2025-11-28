<?php

namespace App\Services\CareOps;

use App\Models\ServiceAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Visit Verification Service
 *
 * Domain service for managing visit verification workflow.
 *
 * Per OHaH contract requirements:
 * - All scheduled visits must be verified to track service delivery
 * - Unverified visits past grace period are flagged as overdue
 * - Target: 0% missed care (all visits verified within grace period)
 *
 * Grace period is configurable via:
 * - config('careops.verification_grace_minutes') or
 * - ServiceAssignment::DEFAULT_VERIFICATION_GRACE_MINUTES (24 hours default)
 */
class VisitVerificationService
{
    /**
     * Default grace period in minutes before marking as overdue.
     */
    protected int $graceMinutes;

    public function __construct()
    {
        $this->graceMinutes = config(
            'careops.verification_grace_minutes',
            ServiceAssignment::DEFAULT_VERIFICATION_GRACE_MINUTES
        );
    }

    /**
     * Mark a service assignment as verified.
     *
     * @param ServiceAssignment $assignment The assignment to verify
     * @param User|null $user The user performing verification (null for system)
     * @param Carbon|null $verifiedAt When verified (defaults to now)
     * @param string|null $source Verification source (defaults to staff_manual)
     * @return ServiceAssignment
     */
    public function markVerified(
        ServiceAssignment $assignment,
        ?User $user = null,
        ?Carbon $verifiedAt = null,
        ?string $source = null
    ): ServiceAssignment {
        $assignment->update([
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'verified_at' => $verifiedAt ?? now(),
            'verification_source' => $source ?? ServiceAssignment::VERIFICATION_SOURCE_STAFF_MANUAL,
            'verified_by_user_id' => $user?->id,
        ]);

        return $assignment->fresh();
    }

    /**
     * Mark a service assignment as missed.
     *
     * @param ServiceAssignment $assignment The assignment to mark as missed
     * @param User|null $user The user marking as missed (null for system)
     * @param string|null $source Verification source
     * @return ServiceAssignment
     */
    public function markMissed(
        ServiceAssignment $assignment,
        ?User $user = null,
        ?string $source = null
    ): ServiceAssignment {
        $assignment->update([
            'verification_status' => ServiceAssignment::VERIFICATION_MISSED,
            'verified_at' => now(),
            'verification_source' => $source ?? ServiceAssignment::VERIFICATION_SOURCE_COORDINATOR,
            'verified_by_user_id' => $user?->id,
        ]);

        // Also update the assignment status to 'missed' if not already
        if (!in_array($assignment->status, [ServiceAssignment::STATUS_MISSED, ServiceAssignment::STATUS_CANCELLED])) {
            $assignment->update(['status' => ServiceAssignment::STATUS_MISSED]);
        }

        return $assignment->fresh();
    }

    /**
     * Check if a service assignment is overdue for verification.
     *
     * @param ServiceAssignment $assignment
     * @param int|null $graceMinutes Custom grace period (defaults to configured)
     * @return bool
     */
    public function isOverdue(ServiceAssignment $assignment, ?int $graceMinutes = null): bool
    {
        return $assignment->isOverdueForVerification($graceMinutes ?? $this->graceMinutes);
    }

    /**
     * Process all overdue unverified assignments and mark them as missed.
     *
     * This is typically called by a scheduled job.
     *
     * @param int|null $organizationId Filter by organization
     * @param int|null $graceMinutes Custom grace period
     * @return int Number of assignments marked as missed
     */
    public function processOverdueAssignments(?int $organizationId = null, ?int $graceMinutes = null): int
    {
        $grace = $graceMinutes ?? $this->graceMinutes;

        $query = ServiceAssignment::overdueUnverified($grace);

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        $overdueAssignments = $query->get();

        $count = 0;
        foreach ($overdueAssignments as $assignment) {
            $this->markMissed($assignment, null, ServiceAssignment::VERIFICATION_SOURCE_SSPO_SYSTEM);
            $count++;
        }

        return $count;
    }

    /**
     * Get all overdue unverified assignments for an organization.
     *
     * @param int|null $organizationId
     * @param int|null $graceMinutes
     * @return Collection
     */
    public function getOverdueAssignments(?int $organizationId = null, ?int $graceMinutes = null): Collection
    {
        $grace = $graceMinutes ?? $this->graceMinutes;

        $query = ServiceAssignment::with(['patient.user', 'assignedUser', 'serviceType'])
            ->overdueUnverified($grace);

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        return $query->orderBy('scheduled_start', 'asc')->get();
    }

    /**
     * Get verification statistics for an organization.
     *
     * @param int $organizationId
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     */
    public function getVerificationStats(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? Carbon::now()->subWeek();
        $endDate = $endDate ?? Carbon::now();

        $baseQuery = ServiceAssignment::where('service_provider_organization_id', $organizationId)
            ->whereBetween('scheduled_start', [$startDate, $endDate]);

        $total = (clone $baseQuery)->count();
        $verified = (clone $baseQuery)->verified()->count();
        $missed = (clone $baseQuery)->verificationMissed()->count();
        $pending = (clone $baseQuery)->verificationPending()->count();
        $overdueCount = (clone $baseQuery)->overdueUnverified($this->graceMinutes)->count();

        $verificationRate = $total > 0 ? round(($verified / $total) * 100, 1) : 100.0;
        $missedRate = $total > 0 ? round(($missed / $total) * 100, 2) : 0.0;

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'total_appointments' => $total,
            'verified' => $verified,
            'missed' => $missed,
            'pending' => $pending,
            'overdue_pending' => $overdueCount,
            'verification_rate' => $verificationRate,
            'missed_rate' => $missedRate,
            'is_compliant' => $missedRate === 0.0,
        ];
    }

    /**
     * Bulk verify multiple assignments.
     *
     * @param array $assignmentIds
     * @param User|null $user
     * @param string|null $source
     * @return int Number of assignments verified
     */
    public function bulkVerify(array $assignmentIds, ?User $user = null, ?string $source = null): int
    {
        return DB::transaction(function () use ($assignmentIds, $user, $source) {
            return ServiceAssignment::whereIn('id', $assignmentIds)
                ->where('verification_status', ServiceAssignment::VERIFICATION_PENDING)
                ->update([
                    'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
                    'verified_at' => now(),
                    'verification_source' => $source ?? ServiceAssignment::VERIFICATION_SOURCE_STAFF_MANUAL,
                    'verified_by_user_id' => $user?->id,
                ]);
        });
    }

    /**
     * Get the configured grace period in minutes.
     */
    public function getGraceMinutes(): int
    {
        return $this->graceMinutes;
    }
}
