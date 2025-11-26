<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ServiceAssignment extends Model
{
    use HasFactory, SoftDeletes;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_MISSED = 'missed';
    public const STATUS_ESCALATED = 'escalated';

    // SSPO acceptance status constants
    public const SSPO_PENDING = 'pending';
    public const SSPO_ACCEPTED = 'accepted';
    public const SSPO_DECLINED = 'declined';
    public const SSPO_NOT_APPLICABLE = 'not_applicable';

    protected $fillable = [
        'care_plan_id',
        'patient_id',
        'service_provider_organization_id',
        'service_type_id',
        'assigned_user_id',
        'status',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'frequency_rule',
        'notes',
        'source',
        'rpm_alert_id',
        'estimated_hours_per_week',
        'estimated_total_hours',
        'estimated_travel_km_per_week',
        'after_hours_required',
        'sspo_acceptance_status',
        'sspo_responded_at',
        'sspo_responded_by',
        'sspo_decline_reason',
        'sspo_notified_at',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'sspo_responded_at' => 'datetime',
        'sspo_notified_at' => 'datetime',
        'after_hours_required' => 'boolean',
    ];

    public function carePlan()
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function serviceProviderOrganization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class);
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function rpmAlert()
    {
        return $this->belongsTo(RpmAlert::class);
    }

    public function interdisciplinaryNotes()
    {
        return $this->hasMany(InterdisciplinaryNote::class);
    }

    public function sspoRespondedBy()
    {
        return $this->belongsTo(User::class, 'sspo_responded_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for assignments pending SSPO acceptance.
     */
    public function scopePendingSspoAcceptance(Builder $query): Builder
    {
        return $query->where('sspo_acceptance_status', self::SSPO_PENDING);
    }

    /**
     * Scope for assignments accepted by SSPO.
     */
    public function scopeSspoAccepted(Builder $query): Builder
    {
        return $query->where('sspo_acceptance_status', self::SSPO_ACCEPTED);
    }

    /**
     * Scope for assignments declined by SSPO.
     */
    public function scopeSspoDeclined(Builder $query): Builder
    {
        return $query->where('sspo_acceptance_status', self::SSPO_DECLINED);
    }

    /**
     * Scope for assignments for a specific SSPO organization.
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('service_provider_organization_id', $organizationId);
    }

    /**
     * Scope for assignments that need SSPO response (subcontracted).
     */
    public function scopeRequiresSspoResponse(Builder $query): Builder
    {
        return $query->where('sspo_acceptance_status', '!=', self::SSPO_NOT_APPLICABLE);
    }

    /*
    |--------------------------------------------------------------------------
    | SSPO Acceptance Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this assignment requires SSPO acceptance.
     */
    public function requiresSspoAcceptance(): bool
    {
        return $this->sspo_acceptance_status !== self::SSPO_NOT_APPLICABLE;
    }

    /**
     * Check if SSPO acceptance is pending.
     */
    public function isSspoPending(): bool
    {
        return $this->sspo_acceptance_status === self::SSPO_PENDING;
    }

    /**
     * Check if SSPO has accepted.
     */
    public function isSspoAccepted(): bool
    {
        return $this->sspo_acceptance_status === self::SSPO_ACCEPTED;
    }

    /**
     * Check if SSPO has declined.
     */
    public function isSspoDeclined(): bool
    {
        return $this->sspo_acceptance_status === self::SSPO_DECLINED;
    }

    /**
     * Get SSPO response time in minutes.
     */
    public function getSspoResponseTimeMinutesAttribute(): ?int
    {
        if (!$this->sspo_notified_at || !$this->sspo_responded_at) {
            return null;
        }

        return $this->sspo_notified_at->diffInMinutes($this->sspo_responded_at);
    }

    /**
     * Mark assignment as sent to SSPO.
     */
    public function markSspoNotified(): self
    {
        $this->update([
            'sspo_acceptance_status' => self::SSPO_PENDING,
            'sspo_notified_at' => now(),
        ]);

        return $this;
    }

    /**
     * Accept assignment on behalf of SSPO.
     */
    public function acceptBySspo(?int $userId = null): self
    {
        $this->update([
            'sspo_acceptance_status' => self::SSPO_ACCEPTED,
            'sspo_responded_at' => now(),
            'sspo_responded_by' => $userId,
            'sspo_decline_reason' => null,
        ]);

        return $this;
    }

    /**
     * Decline assignment on behalf of SSPO.
     */
    public function declineBySspo(string $reason, ?int $userId = null): self
    {
        $this->update([
            'sspo_acceptance_status' => self::SSPO_DECLINED,
            'sspo_responded_at' => now(),
            'sspo_responded_by' => $userId,
            'sspo_decline_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Check if assignment is for an SSPO (partner organization).
     */
    public function isSubcontracted(): bool
    {
        if (!$this->serviceProviderOrganization) {
            return false;
        }

        return $this->serviceProviderOrganization->type === 'partner';
    }

    /**
     * Get status label for display.
     */
    public function getSspoStatusLabelAttribute(): string
    {
        return match ($this->sspo_acceptance_status) {
            self::SSPO_PENDING => 'Awaiting SSPO Response',
            self::SSPO_ACCEPTED => 'Accepted by SSPO',
            self::SSPO_DECLINED => 'Declined by SSPO',
            self::SSPO_NOT_APPLICABLE => 'Internal Assignment',
            default => 'Unknown',
        };
    }
}
