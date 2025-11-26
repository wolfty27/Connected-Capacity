<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * ReassessmentTrigger Model
 *
 * Tracks requests for InterRAI reassessment. Used to flag patients
 * who need a new assessment due to condition changes, clinical events,
 * or manual coordinator requests.
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $triggered_by
 * @property string $trigger_reason
 * @property string|null $reason_notes
 * @property string $priority
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property int|null $resolution_assessment_id
 * @property string|null $resolution_notes
 */
class ReassessmentTrigger extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'assessment_reassessment_triggers';

    protected $fillable = [
        'patient_id',
        'triggered_by',
        'trigger_reason',
        'reason_notes',
        'priority',
        'resolved_at',
        'resolved_by',
        'resolution_assessment_id',
        'resolution_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // Trigger reasons
    public const REASON_CONDITION_CHANGE = 'condition_change';
    public const REASON_MANUAL_REQUEST = 'manual_request';
    public const REASON_CLINICAL_EVENT = 'clinical_event';
    public const REASON_STALE_ASSESSMENT = 'stale_assessment';

    // Priority levels
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function resolutionAssessment(): BelongsTo
    {
        return $this->belongsTo(InterraiAssessment::class, 'resolution_assessment_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for pending (unresolved) triggers.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope for resolved triggers.
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Scope by priority.
     */
    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for high priority or urgent triggers.
     */
    public function scopeHighPriorityOrAbove(Builder $query): Builder
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    /**
     * Scope by trigger reason.
     */
    public function scopeByReason(Builder $query, string $reason): Builder
    {
        return $query->where('trigger_reason', $reason);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this trigger is pending resolution.
     */
    public function isPending(): bool
    {
        return $this->resolved_at === null;
    }

    /**
     * Check if this trigger is resolved.
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Get human-readable trigger reason.
     */
    public function getReasonLabelAttribute(): string
    {
        return match ($this->trigger_reason) {
            self::REASON_CONDITION_CHANGE => 'Condition Change',
            self::REASON_MANUAL_REQUEST => 'Manual Request',
            self::REASON_CLINICAL_EVENT => 'Clinical Event',
            self::REASON_STALE_ASSESSMENT => 'Stale Assessment',
            default => ucfirst(str_replace('_', ' ', $this->trigger_reason)),
        };
    }

    /**
     * Get human-readable priority label.
     */
    public function getPriorityLabelAttribute(): string
    {
        return ucfirst($this->priority);
    }

    /**
     * Get priority color for UI.
     */
    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'red',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_MEDIUM => 'yellow',
            self::PRIORITY_LOW => 'gray',
            default => 'gray',
        };
    }

    /**
     * Resolve this trigger with an assessment.
     */
    public function resolve(
        InterraiAssessment $assessment,
        ?User $resolvedBy = null,
        ?string $notes = null
    ): self {
        $this->update([
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy?->id,
            'resolution_assessment_id' => $assessment->id,
            'resolution_notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Convert to API response array.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient?->name,
            'trigger_reason' => $this->trigger_reason,
            'reason_label' => $this->reason_label,
            'reason_notes' => $this->reason_notes,
            'priority' => $this->priority,
            'priority_label' => $this->priority_label,
            'priority_color' => $this->priority_color,
            'is_pending' => $this->isPending(),
            'triggered_by' => $this->triggeredByUser?->name,
            'triggered_at' => $this->created_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->resolvedByUser?->name,
            'resolution_notes' => $this->resolution_notes,
        ];
    }

    /**
     * Get available trigger reasons for forms.
     */
    public static function getReasonOptions(): array
    {
        return [
            ['value' => self::REASON_CONDITION_CHANGE, 'label' => 'Condition Change'],
            ['value' => self::REASON_MANUAL_REQUEST, 'label' => 'Manual Request'],
            ['value' => self::REASON_CLINICAL_EVENT, 'label' => 'Clinical Event'],
        ];
    }

    /**
     * Get available priorities for forms.
     */
    public static function getPriorityOptions(): array
    {
        return [
            ['value' => self::PRIORITY_LOW, 'label' => 'Low'],
            ['value' => self::PRIORITY_MEDIUM, 'label' => 'Medium'],
            ['value' => self::PRIORITY_HIGH, 'label' => 'High'],
            ['value' => self::PRIORITY_URGENT, 'label' => 'Urgent'],
        ];
    }
}
