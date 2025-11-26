<?php

namespace App\Events;

use App\Models\InterraiAssessment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * IarUploadFailed - Fired when IAR upload exhausts all retries
 *
 * Per IR-005: Triggers SPO escalation workflow when InterRAI assessment
 * cannot be uploaded to the IAR system. OHaH compliance requires all
 * assessments to be submitted within 72 hours.
 *
 * Listeners should:
 * - Notify SPO compliance team
 * - Create escalation ticket
 * - Log for audit trail
 */
class IarUploadFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    public InterraiAssessment $assessment;
    public string $errorMessage;
    public string $severity;
    public array $context;

    /**
     * Create a new event instance.
     */
    public function __construct(
        InterraiAssessment $assessment,
        string $errorMessage,
        string $severity = self::SEVERITY_CRITICAL,
    ) {
        $this->assessment = $assessment;
        $this->errorMessage = $errorMessage;
        $this->severity = $severity;
        $this->context = $this->buildContext();
    }

    /**
     * Build context array for alerting/logging.
     */
    protected function buildContext(): array
    {
        $patient = $this->assessment->patient;
        $hoursSinceAssessment = $this->assessment->assessment_date
            ? now()->diffInHours($this->assessment->assessment_date)
            : null;

        return [
            'assessment_id' => $this->assessment->id,
            'patient_id' => $this->assessment->patient_id,
            'patient_name' => $patient?->name ?? 'Unknown',
            'assessment_type' => $this->assessment->assessment_type,
            'assessment_date' => $this->assessment->assessment_date?->toIso8601String(),
            'hours_since_assessment' => $hoursSinceAssessment,
            'compliance_deadline_hours' => 72,
            'is_past_deadline' => $hoursSinceAssessment > 72,
            'source' => $this->assessment->source,
            'maple_score' => $this->assessment->maple_score,
            'error_message' => $this->errorMessage,
            'failed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if assessment is past OHaH 72-hour upload deadline.
     */
    public function isPastDeadline(): bool
    {
        return $this->context['is_past_deadline'] ?? false;
    }

    /**
     * Get hours remaining until compliance deadline.
     */
    public function getHoursUntilDeadline(): int
    {
        $hoursSince = $this->context['hours_since_assessment'] ?? 0;
        return max(0, 72 - $hoursSince);
    }

    /**
     * Get escalation priority based on deadline proximity.
     */
    public function getEscalationPriority(): string
    {
        $hoursUntil = $this->getHoursUntilDeadline();

        if ($hoursUntil <= 0) {
            return 'critical';
        } elseif ($hoursUntil <= 12) {
            return 'high';
        } elseif ($hoursUntil <= 24) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Format alert message for notifications.
     */
    public function getAlertMessage(): string
    {
        $patient = $this->context['patient_name'];
        $hoursUntil = $this->getHoursUntilDeadline();

        if ($this->isPastDeadline()) {
            return "COMPLIANCE BREACH: IAR upload failed for {$patient}'s InterRAI assessment. " .
                "72-hour deadline exceeded. Immediate manual intervention required.";
        }

        return "IAR Upload Failed: {$patient}'s InterRAI assessment could not be uploaded. " .
            "{$hoursUntil} hours until compliance deadline. Error: {$this->errorMessage}";
    }

    /**
     * Get data for audit logging.
     */
    public function toAuditArray(): array
    {
        return array_merge($this->context, [
            'event_type' => 'iar_upload_failed',
            'severity' => $this->severity,
            'escalation_priority' => $this->getEscalationPriority(),
            'requires_manual_intervention' => true,
        ]);
    }
}
