<?php

namespace App\Events;

use App\Models\CarePlan;
use App\Models\TriageResult;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SlaComplianceAlert - Fired when SLA compliance is at risk
 *
 * Alert types:
 * - hpg_response: HPG 15-minute response SLA at risk
 * - first_service: 24-hour first service SLA at risk
 * - missed_care: Missed care rate above threshold
 */
class SlaComplianceAlert
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const TYPE_HPG_RESPONSE = 'hpg_response';
    public const TYPE_FIRST_SERVICE = 'first_service';
    public const TYPE_MISSED_CARE = 'missed_care';

    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_BREACH = 'breach';

    public function __construct(
        public string $alertType,
        public string $severity,
        public string $message,
        public array $context = [],
        public ?int $organizationId = null,
        public ?int $patientId = null,
        public ?int $relatedEntityId = null,
    ) {}

    /**
     * Create HPG response warning alert.
     */
    public static function hpgResponseWarning(TriageResult $triage, int $minutesElapsed): self
    {
        return new self(
            alertType: self::TYPE_HPG_RESPONSE,
            severity: self::SEVERITY_WARNING,
            message: "HPG response approaching deadline: {$minutesElapsed} minutes elapsed (15 min SLA)",
            context: [
                'triage_id' => $triage->id,
                'patient_id' => $triage->patient_id,
                'minutes_elapsed' => $minutesElapsed,
                'sla_minutes' => 15,
                'received_at' => $triage->hpg_received_at?->toIso8601String(),
            ],
            patientId: $triage->patient_id,
            relatedEntityId: $triage->id,
        );
    }

    /**
     * Create HPG response breach alert.
     */
    public static function hpgResponseBreach(TriageResult $triage, int $minutesElapsed): self
    {
        return new self(
            alertType: self::TYPE_HPG_RESPONSE,
            severity: self::SEVERITY_BREACH,
            message: "HPG response SLA BREACHED: {$minutesElapsed} minutes (15 min SLA)",
            context: [
                'triage_id' => $triage->id,
                'patient_id' => $triage->patient_id,
                'minutes_elapsed' => $minutesElapsed,
                'sla_minutes' => 15,
                'breach_minutes' => $minutesElapsed - 15,
            ],
            patientId: $triage->patient_id,
            relatedEntityId: $triage->id,
        );
    }

    /**
     * Create first service warning alert.
     */
    public static function firstServiceWarning(CarePlan $carePlan, int $hoursElapsed): self
    {
        return new self(
            alertType: self::TYPE_FIRST_SERVICE,
            severity: self::SEVERITY_WARNING,
            message: "First service approaching deadline: {$hoursElapsed} hours elapsed (24 hr SLA)",
            context: [
                'care_plan_id' => $carePlan->id,
                'patient_id' => $carePlan->patient_id,
                'hours_elapsed' => $hoursElapsed,
                'sla_hours' => 24,
                'approved_at' => $carePlan->approved_at?->toIso8601String(),
            ],
            patientId: $carePlan->patient_id,
            relatedEntityId: $carePlan->id,
        );
    }

    /**
     * Create first service breach alert.
     */
    public static function firstServiceBreach(CarePlan $carePlan, int $hoursElapsed): self
    {
        return new self(
            alertType: self::TYPE_FIRST_SERVICE,
            severity: self::SEVERITY_BREACH,
            message: "First service SLA BREACHED: {$hoursElapsed} hours (24 hr SLA)",
            context: [
                'care_plan_id' => $carePlan->id,
                'patient_id' => $carePlan->patient_id,
                'hours_elapsed' => $hoursElapsed,
                'sla_hours' => 24,
                'breach_hours' => $hoursElapsed - 24,
            ],
            patientId: $carePlan->patient_id,
            relatedEntityId: $carePlan->id,
        );
    }

    /**
     * Create missed care warning alert.
     */
    public static function missedCareWarning(int $organizationId, float $missedRate, int $missedCount): self
    {
        return new self(
            alertType: self::TYPE_MISSED_CARE,
            severity: self::SEVERITY_WARNING,
            message: "Missed care rate elevated: {$missedRate}% ({$missedCount} visits)",
            context: [
                'missed_rate' => $missedRate,
                'missed_count' => $missedCount,
                'threshold' => 0.5,
                'target' => 0.0,
            ],
            organizationId: $organizationId,
        );
    }

    /**
     * Create missed care critical alert.
     */
    public static function missedCareCritical(int $organizationId, float $missedRate, int $missedCount): self
    {
        return new self(
            alertType: self::TYPE_MISSED_CARE,
            severity: self::SEVERITY_CRITICAL,
            message: "CRITICAL: Missed care rate at {$missedRate}% ({$missedCount} visits) - immediate action required",
            context: [
                'missed_rate' => $missedRate,
                'missed_count' => $missedCount,
                'threshold' => 2.0,
                'target' => 0.0,
            ],
            organizationId: $organizationId,
        );
    }
}
