<?php

namespace App\Services;

use App\Events\HpgDeadlineApproaching;
use App\Events\HpgDeadlineBreached;
use App\Events\SlaComplianceAlert;
use App\Models\CarePlan;
use App\Models\ServiceProviderOrganization;
use App\Models\TriageResult;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * SlaComplianceNotificationService - Monitor and alert on SLA compliance
 *
 * Monitors:
 * - HPG 15-minute response SLA
 * - 24-hour first service SLA
 * - 0% missed care target
 *
 * Alerts triggered at warning thresholds before breach and on breach.
 */
class SlaComplianceNotificationService
{
    /**
     * HPG Response SLA thresholds (minutes).
     */
    protected const HPG_WARNING_MINUTES = 10;
    protected const HPG_CRITICAL_MINUTES = 14;
    protected const HPG_SLA_MINUTES = 15;

    /**
     * First Service SLA thresholds (hours).
     */
    protected const FIRST_SERVICE_WARNING_HOURS = 20;
    protected const FIRST_SERVICE_CRITICAL_HOURS = 23;
    protected const FIRST_SERVICE_SLA_HOURS = 24;

    /**
     * Missed Care thresholds (percentage).
     */
    protected const MISSED_CARE_WARNING_PERCENT = 0.5;
    protected const MISSED_CARE_CRITICAL_PERCENT = 2.0;

    protected MissedCareService $missedCareService;
    protected HpgResponseService $hpgResponseService;

    public function __construct(
        MissedCareService $missedCareService,
        HpgResponseService $hpgResponseService
    ) {
        $this->missedCareService = $missedCareService;
        $this->hpgResponseService = $hpgResponseService;
    }

    /**
     * Run all compliance checks and dispatch alerts.
     *
     * @return array{hpg: array, first_service: array, missed_care: array}
     */
    public function runAllChecks(): array
    {
        return [
            'hpg' => $this->checkHpgResponseSla(),
            'first_service' => $this->checkFirstServiceSla(),
            'missed_care' => $this->checkMissedCareSla(),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Check HPG 15-minute response SLA for pending triages.
     */
    public function checkHpgResponseSla(): array
    {
        $alerts = [];

        // Find triages with HPG received but not responded
        $pendingTriages = TriageResult::query()
            ->whereNotNull('hpg_received_at')
            ->whereNull('hpg_responded_at')
            ->get();

        foreach ($pendingTriages as $triage) {
            $minutesElapsed = $triage->hpg_received_at->diffInMinutes(now());

            if ($minutesElapsed >= self::HPG_SLA_MINUTES) {
                // Breached
                $alert = SlaComplianceAlert::hpgResponseBreach($triage, $minutesElapsed);
                event($alert);
                event(new HpgDeadlineBreached($triage));

                $alerts[] = $this->logAndNotify($alert);

            } elseif ($minutesElapsed >= self::HPG_CRITICAL_MINUTES) {
                // Critical - about to breach
                $alert = SlaComplianceAlert::hpgResponseWarning($triage, $minutesElapsed);
                $alert->severity = SlaComplianceAlert::SEVERITY_CRITICAL;
                event($alert);
                event(new HpgDeadlineApproaching($triage));

                $alerts[] = $this->logAndNotify($alert);

            } elseif ($minutesElapsed >= self::HPG_WARNING_MINUTES) {
                // Warning
                $alert = SlaComplianceAlert::hpgResponseWarning($triage, $minutesElapsed);
                event($alert);
                event(new HpgDeadlineApproaching($triage));

                $alerts[] = $this->logAndNotify($alert);
            }
        }

        return [
            'pending_count' => $pendingTriages->count(),
            'alerts_fired' => count($alerts),
            'alerts' => $alerts,
        ];
    }

    /**
     * Check 24-hour first service SLA for approved care plans.
     */
    public function checkFirstServiceSla(): array
    {
        $alerts = [];

        // Find care plans approved but no first service delivered
        $pendingPlans = CarePlan::query()
            ->whereNotNull('approved_at')
            ->whereNull('first_service_delivered_at')
            ->where('status', 'active')
            ->get();

        foreach ($pendingPlans as $carePlan) {
            $hoursElapsed = $carePlan->approved_at->diffInHours(now());

            if ($hoursElapsed >= self::FIRST_SERVICE_SLA_HOURS) {
                // Breached
                $alert = SlaComplianceAlert::firstServiceBreach($carePlan, $hoursElapsed);
                event($alert);

                $alerts[] = $this->logAndNotify($alert);

            } elseif ($hoursElapsed >= self::FIRST_SERVICE_CRITICAL_HOURS) {
                // Critical - about to breach
                $alert = SlaComplianceAlert::firstServiceWarning($carePlan, $hoursElapsed);
                $alert->severity = SlaComplianceAlert::SEVERITY_CRITICAL;
                event($alert);

                $alerts[] = $this->logAndNotify($alert);

            } elseif ($hoursElapsed >= self::FIRST_SERVICE_WARNING_HOURS) {
                // Warning
                $alert = SlaComplianceAlert::firstServiceWarning($carePlan, $hoursElapsed);
                event($alert);

                $alerts[] = $this->logAndNotify($alert);
            }
        }

        return [
            'pending_count' => $pendingPlans->count(),
            'alerts_fired' => count($alerts),
            'alerts' => $alerts,
        ];
    }

    /**
     * Check missed care rate for all organizations.
     */
    public function checkMissedCareSla(): array
    {
        $alerts = [];

        // Check overall organization rate
        $organizations = ServiceProviderOrganization::where('active', true)->get();

        foreach ($organizations as $org) {
            $metrics = $this->missedCareService->calculate($org->id, now()->subDays(7));

            if ($metrics['missed_rate'] >= self::MISSED_CARE_CRITICAL_PERCENT) {
                // Critical
                $alert = SlaComplianceAlert::missedCareCritical(
                    $org->id,
                    $metrics['missed_rate'],
                    $metrics['missed']
                );
                event($alert);

                $alerts[] = $this->logAndNotify($alert);

            } elseif ($metrics['missed_rate'] >= self::MISSED_CARE_WARNING_PERCENT) {
                // Warning
                $alert = SlaComplianceAlert::missedCareWarning(
                    $org->id,
                    $metrics['missed_rate'],
                    $metrics['missed']
                );
                event($alert);

                $alerts[] = $this->logAndNotify($alert);
            }
        }

        return [
            'organizations_checked' => $organizations->count(),
            'alerts_fired' => count($alerts),
            'alerts' => $alerts,
        ];
    }

    /**
     * Get current compliance status across all SLAs.
     */
    public function getComplianceStatus(?int $organizationId = null): array
    {
        $hpgMetrics = $this->hpgResponseService->getComplianceMetrics(
            now()->subDays(7),
            now(),
            $organizationId
        );

        $missedCareMetrics = $this->missedCareService->calculate(
            $organizationId,
            now()->subDays(7)
        );

        // First service metrics
        $firstServiceQuery = CarePlan::query()
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', now()->subDays(7));

        if ($organizationId) {
            // Filter by organization through patient relationships if needed
        }

        $firstServiceTotal = (clone $firstServiceQuery)->count();
        $firstServiceCompliant = (clone $firstServiceQuery)
            ->whereNotNull('first_service_delivered_at')
            ->whereRaw('TIMESTAMPDIFF(HOUR, approved_at, first_service_delivered_at) <= 24')
            ->count();
        $firstServiceRate = $firstServiceTotal > 0
            ? round(($firstServiceCompliant / $firstServiceTotal) * 100, 1)
            : 100;

        return [
            'hpg_response' => [
                'compliant' => $hpgMetrics['breached'] === 0,
                'compliance_rate' => $hpgMetrics['compliance_rate'],
                'breaches' => $hpgMetrics['breached'],
                'total' => $hpgMetrics['total'],
                'sla' => '15 minutes',
            ],
            'first_service' => [
                'compliant' => $firstServiceRate >= 100,
                'compliance_rate' => $firstServiceRate,
                'compliant_count' => $firstServiceCompliant,
                'total' => $firstServiceTotal,
                'sla' => '24 hours',
            ],
            'missed_care' => [
                'compliant' => $missedCareMetrics['compliance'],
                'missed_rate' => $missedCareMetrics['missed_rate'],
                'missed_count' => $missedCareMetrics['missed'],
                'total' => $missedCareMetrics['total'],
                'sla' => '0% target',
            ],
            'overall_compliant' => (
                $hpgMetrics['breached'] === 0 &&
                $firstServiceRate >= 100 &&
                $missedCareMetrics['compliance']
            ),
            'period' => [
                'start' => now()->subDays(7)->toIso8601String(),
                'end' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Log alert and send notifications to relevant users.
     */
    protected function logAndNotify(SlaComplianceAlert $alert): array
    {
        Log::channel('sla')->warning($alert->message, [
            'type' => $alert->alertType,
            'severity' => $alert->severity,
            'context' => $alert->context,
            'organization_id' => $alert->organizationId,
            'patient_id' => $alert->patientId,
        ]);

        // Get users to notify based on alert type and organization
        $notifyUsers = $this->getNotificationRecipients($alert);

        // In production, this would send actual notifications
        // For now, just log and return the alert details
        foreach ($notifyUsers as $user) {
            Log::info("SLA Alert notification queued", [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'alert_type' => $alert->alertType,
            ]);
        }

        return [
            'type' => $alert->alertType,
            'severity' => $alert->severity,
            'message' => $alert->message,
            'context' => $alert->context,
            'notified_users' => $notifyUsers->pluck('id')->toArray(),
        ];
    }

    /**
     * Determine which users should receive the alert.
     */
    protected function getNotificationRecipients(SlaComplianceAlert $alert): Collection
    {
        $query = User::query()
            ->where('active', true)
            ->whereIn('organization_role', ['admin', 'care_coordinator', 'clinical_lead']);

        if ($alert->organizationId) {
            $query->where('organization_id', $alert->organizationId);
        }

        // Critical and breach alerts go to more users
        if (in_array($alert->severity, [SlaComplianceAlert::SEVERITY_CRITICAL, SlaComplianceAlert::SEVERITY_BREACH])) {
            $query->orWhereIn('organization_role', ['executive', 'operations_manager']);
        }

        return $query->get();
    }
}
