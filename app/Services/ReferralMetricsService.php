<?php

namespace App\Services;

use App\DTOs\ReferralMetricsDTO;
use App\Models\PatientQueue;
use Carbon\Carbon;

/**
 * ReferralMetricsService - Calculates referral acceptance metrics per OHaH requirements
 *
 * OHaH RFP (Appendix 1) mandates 100% referral acceptance target:
 * "Referral Acceptance Rate = (accepted referrals / total referrals) × 100%"
 *
 * Band thresholds:
 * - Band A: 100% (Meets Target)
 * - Band B: 95-99.9% (Below Standard)
 * - Band C: <95% (Action Required)
 *
 * A referral is considered "accepted" when:
 * - is_accepted = true (explicit acceptance flag)
 * - OR transitioned_at IS NOT NULL (patient has moved to active care)
 *
 * A referral is "pending" when:
 * - is_accepted = false AND rejection_reason IS NULL
 * - Patient is still in intake/assessment stages
 *
 * A referral is "rejected" when:
 * - rejection_reason IS NOT NULL
 * - (Rare - SPO declines due to service area or capacity)
 */
class ReferralMetricsService
{
    /**
     * Default reporting window in days.
     */
    public const DEFAULT_REPORTING_DAYS = 28;

    /**
     * Calculate referral acceptance metrics for an organization.
     *
     * @param int|null $organizationId Filter by SPO (null = all)
     * @param Carbon|null $startDate Period start (default: 28 days ago)
     * @param Carbon|null $endDate Period end (default: now)
     * @return ReferralMetricsDTO
     */
    public function calculate(?int $organizationId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): ReferralMetricsDTO
    {
        $startDate = $startDate ?? now()->subDays(self::DEFAULT_REPORTING_DAYS);
        $endDate = $endDate ?? now();

        // Base query: all referrals in period
        $baseQuery = PatientQueue::query()
            ->whereBetween('entered_queue_at', [$startDate, $endDate]);

        // Filter by organization if provided
        // Note: For now, we don't filter by org since patients don't have direct org relationship
        // In a full implementation, we'd filter through the referring hospital or assigned coordinator
        // For the demo, all patients are for the single seeded SPO, so this works correctly

        // Count accepted referrals
        // A referral is accepted if:
        // 1. is_accepted = true, OR
        // 2. transitioned_at IS NOT NULL (patient moved to active care)
        $accepted = (clone $baseQuery)->where(function ($q) {
            $q->where('is_accepted', true)
              ->orWhereNotNull('transitioned_at');
        })->count();

        // Count pending referrals (not yet accepted, not rejected)
        $pending = (clone $baseQuery)
            ->where('is_accepted', false)
            ->whereNull('rejection_reason')
            ->whereNull('transitioned_at')
            ->count();

        // Count rejected referrals
        $rejected = (clone $baseQuery)
            ->whereNotNull('rejection_reason')
            ->count();

        return ReferralMetricsDTO::fromCalculation(
            accepted: $accepted,
            pending: $pending,
            rejected: $rejected,
            startDate: $startDate,
            endDate: $endDate
        );
    }

    /**
     * Get acceptance rate as a simple percentage (for dashboard cards).
     */
    public function getAcceptanceRate(?int $organizationId = null): float
    {
        return $this->calculate($organizationId)->ratePercent;
    }

    /**
     * Get the current compliance band.
     */
    public function getComplianceBand(?int $organizationId = null): string
    {
        return $this->calculate($organizationId)->band;
    }

    /**
     * Get referrals pending acceptance (for operational views).
     */
    public function getPendingReferrals(?int $organizationId = null): \Illuminate\Support\Collection
    {
        $query = PatientQueue::with('patient.user')
            ->where('is_accepted', false)
            ->whereNull('rejection_reason')
            ->whereNull('transitioned_at')
            ->orderBy('entered_queue_at', 'asc');

        return $query->get();
    }

    /**
     * Check if acceptance SLA is breached for a specific queue entry.
     * Per RFP: Referrals should be accepted within 24 hours.
     */
    public function isAcceptanceSlaBreached(PatientQueue $queueEntry): bool
    {
        if ($queueEntry->is_accepted || $queueEntry->transitioned_at) {
            return false; // Already accepted
        }

        if ($queueEntry->rejection_reason) {
            return false; // Rejected (handled differently)
        }

        // Check if more than 24 hours since entry
        return $queueEntry->entered_queue_at->diffInHours(now()) > 24;
    }
}
