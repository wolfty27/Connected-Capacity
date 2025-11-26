<?php

namespace App\Services;

use App\Events\HpgDeadlineApproaching;
use App\Events\HpgDeadlineBreached;
use App\Models\TriageResult;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * HpgResponseService - Manages HPG referral response SLA tracking
 *
 * Per OHaH RFS requirements:
 * - SPO must respond to HPG referrals within 15 minutes
 * - Alert when deadline is approaching (at 10 minutes)
 * - Track SLA compliance metrics for reporting
 */
class HpgResponseService
{
    // Warning threshold (fire event when this many minutes remain)
    public const WARNING_THRESHOLD_MINUTES = 5;

    // Alert threshold (fire event when this many minutes remain)
    public const ALERT_THRESHOLD_MINUTES = 10;

    /**
     * Record receipt of an HPG referral.
     */
    public function recordHpgReceived(TriageResult $triageResult): TriageResult
    {
        $triageResult->update([
            'hpg_received_at' => now(),
        ]);

        Log::info('HPG referral received', [
            'triage_result_id' => $triageResult->id,
            'patient_id' => $triageResult->patient_id,
            'deadline' => now()->addMinutes(TriageResult::HPG_RESPONSE_SLA_MINUTES)->toIso8601String(),
        ]);

        return $triageResult;
    }

    /**
     * Record response to an HPG referral.
     */
    public function recordHpgResponse(TriageResult $triageResult, ?User $respondedBy = null): TriageResult
    {
        $triageResult->markHpgResponded($respondedBy?->id);

        $responseTime = $triageResult->hpg_response_time_minutes;
        $breached = $triageResult->isHpgSlaBreached();

        Log::info('HPG referral response recorded', [
            'triage_result_id' => $triageResult->id,
            'patient_id' => $triageResult->patient_id,
            'response_time_minutes' => $responseTime,
            'sla_breached' => $breached,
            'responded_by' => $respondedBy?->id,
        ]);

        return $triageResult;
    }

    /**
     * Check response deadline and fire events if at risk.
     *
     * This method should be called periodically (e.g., every minute via scheduler)
     * to check for pending referrals approaching their deadline.
     */
    public function checkResponseDeadlines(): array
    {
        $results = [
            'checked' => 0,
            'approaching' => 0,
            'breached' => 0,
        ];

        // Get all pending HPG referrals
        $pendingReferrals = TriageResult::pendingHpgResponse()
            ->with('patient.user')
            ->get();

        foreach ($pendingReferrals as $triage) {
            $results['checked']++;
            $status = $this->checkSingleDeadline($triage);

            if ($status === 'approaching') {
                $results['approaching']++;
            } elseif ($status === 'breached') {
                $results['breached']++;
            }
        }

        Log::info('HPG deadline check completed', $results);

        return $results;
    }

    /**
     * Check deadline for a single triage result.
     */
    public function checkSingleDeadline(TriageResult $triageResult): string
    {
        if (!$triageResult->hpg_received_at) {
            return 'no_hpg';
        }

        if ($triageResult->hpg_responded_at) {
            return 'responded';
        }

        $minutesRemaining = $triageResult->hpg_sla_minutes_remaining;

        // Already breached
        if ($minutesRemaining <= 0) {
            $minutesOverdue = abs($minutesRemaining);

            event(new HpgDeadlineBreached($triageResult, $minutesOverdue));

            Log::warning('HPG deadline breached', [
                'triage_result_id' => $triageResult->id,
                'patient_id' => $triageResult->patient_id,
                'minutes_overdue' => $minutesOverdue,
            ]);

            return 'breached';
        }

        // Approaching deadline
        if ($minutesRemaining <= self::ALERT_THRESHOLD_MINUTES) {
            event(new HpgDeadlineApproaching($triageResult, $minutesRemaining));

            Log::warning('HPG deadline approaching', [
                'triage_result_id' => $triageResult->id,
                'patient_id' => $triageResult->patient_id,
                'minutes_remaining' => $minutesRemaining,
            ]);

            return 'approaching';
        }

        return 'ok';
    }

    /**
     * Get all pending HPG referrals with their SLA status.
     */
    public function getPendingReferrals(): \Illuminate\Database\Eloquent\Collection
    {
        return TriageResult::pendingHpgResponse()
            ->with('patient.user')
            ->orderBy('hpg_received_at', 'asc')
            ->get()
            ->map(function ($triage) {
                return [
                    'id' => $triage->id,
                    'patient_id' => $triage->patient_id,
                    'patient_name' => $triage->patient?->user?->name,
                    'received_at' => $triage->hpg_received_at?->toIso8601String(),
                    'minutes_remaining' => $triage->hpg_sla_minutes_remaining,
                    'status' => $triage->hpg_sla_status,
                    'acuity_level' => $triage->acuity_level,
                    'crisis_designation' => $triage->crisis_designation,
                ];
            });
    }

    /**
     * Get SLA compliance metrics for a date range.
     */
    public function getComplianceMetrics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now();

        // Total referrals with HPG tracking
        $totalReferrals = TriageResult::whereNotNull('hpg_received_at')
            ->whereBetween('hpg_received_at', [$startDate, $endDate])
            ->count();

        // Responded referrals
        $respondedReferrals = TriageResult::whereNotNull('hpg_received_at')
            ->whereNotNull('hpg_responded_at')
            ->whereBetween('hpg_received_at', [$startDate, $endDate])
            ->count();

        // Referrals that breached SLA
        $breachedReferrals = TriageResult::hpgSlaBreached()
            ->whereBetween('hpg_received_at', [$startDate, $endDate])
            ->count();

        // Average response time (in minutes)
        $avgResponseTime = TriageResult::whereNotNull('hpg_received_at')
            ->whereNotNull('hpg_responded_at')
            ->whereBetween('hpg_received_at', [$startDate, $endDate])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, hpg_received_at, hpg_responded_at)) as avg_response')
            ->value('avg_response');

        // Currently pending
        $currentlyPending = TriageResult::pendingHpgResponse()->count();

        // Calculate compliance rate
        $compliantCount = $respondedReferrals - $breachedReferrals;
        $complianceRate = $respondedReferrals > 0
            ? round(($compliantCount / $respondedReferrals) * 100, 2)
            : 100;

        return [
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
            ],
            'total_referrals' => $totalReferrals,
            'responded' => $respondedReferrals,
            'pending' => $currentlyPending,
            'compliant' => $compliantCount,
            'breached' => $breachedReferrals,
            'compliance_rate' => $complianceRate,
            'average_response_time_minutes' => round($avgResponseTime ?? 0, 1),
            'sla_target_minutes' => TriageResult::HPG_RESPONSE_SLA_MINUTES,
        ];
    }

    /**
     * Get referrals at risk (within alert threshold but not yet breached).
     */
    public function getAtRiskReferrals(): \Illuminate\Database\Eloquent\Collection
    {
        return TriageResult::pendingHpgResponse()
            ->with('patient.user')
            ->get()
            ->filter(function ($triage) {
                $remaining = $triage->hpg_sla_minutes_remaining;
                return $remaining !== null && $remaining > 0 && $remaining <= self::ALERT_THRESHOLD_MINUTES;
            });
    }

    /**
     * Get referrals that have breached SLA and are still pending response.
     */
    public function getBreachedPendingReferrals(): \Illuminate\Database\Eloquent\Collection
    {
        return TriageResult::pendingHpgResponse()
            ->with('patient.user')
            ->get()
            ->filter(function ($triage) {
                return $triage->hpg_sla_minutes_remaining === 0;
            });
    }

    /**
     * Get daily response statistics.
     */
    public function getDailyStats(int $days = 7): array
    {
        $stats = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $endDate = $date->copy()->endOfDay();

            $dayStats = TriageResult::whereNotNull('hpg_received_at')
                ->whereBetween('hpg_received_at', [$date, $endDate])
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN hpg_responded_at IS NOT NULL THEN 1 ELSE 0 END) as responded,
                    SUM(CASE WHEN hpg_responded_at IS NOT NULL AND TIMESTAMPDIFF(MINUTE, hpg_received_at, hpg_responded_at) > ? THEN 1 ELSE 0 END) as breached
                ', [TriageResult::HPG_RESPONSE_SLA_MINUTES])
                ->first();

            $stats[] = [
                'date' => $date->toDateString(),
                'total' => $dayStats->total ?? 0,
                'responded' => $dayStats->responded ?? 0,
                'breached' => $dayStats->breached ?? 0,
                'compliance_rate' => ($dayStats->responded ?? 0) > 0
                    ? round((($dayStats->responded - $dayStats->breached) / $dayStats->responded) * 100, 1)
                    : 100,
            ];
        }

        return $stats;
    }
}
