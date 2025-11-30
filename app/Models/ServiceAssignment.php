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

    // Source constants (for FTE and capacity tracking)
    public const SOURCE_INTERNAL = 'INTERNAL';  // SPO direct staff
    public const SOURCE_SSPO = 'SSPO';          // Subcontracted SSPO staff

    // Visit verification status constants
    public const VERIFICATION_PENDING = 'PENDING';
    public const VERIFICATION_VERIFIED = 'VERIFIED';
    public const VERIFICATION_MISSED = 'MISSED';

    // Verification source constants
    public const VERIFICATION_SOURCE_STAFF_MANUAL = 'staff_manual';
    public const VERIFICATION_SOURCE_SSPO_SYSTEM = 'sspo_system';
    public const VERIFICATION_SOURCE_DEVICE = 'device';
    public const VERIFICATION_SOURCE_COORDINATOR = 'coordinator_override';

    // Default grace period in minutes before a visit is considered overdue
    // Per OHaH contract: 12-hour window for timely visit verification
    public const DEFAULT_VERIFICATION_GRACE_MINUTES = 720; // 12 hours

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
        // Billing rate snapshot fields
        'billing_rate_cents',
        'billing_unit_type',
        'frequency_per_week',
        'duration_minutes',
        'calculated_weekly_cost_cents',
        // Visit verification fields
        'verification_status',
        'verified_at',
        'verification_source',
        'verified_by_user_id',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'sspo_responded_at' => 'datetime',
        'sspo_notified_at' => 'datetime',
        'after_hours_required' => 'boolean',
        // Billing rate snapshot casts
        'billing_rate_cents' => 'integer',
        'frequency_per_week' => 'integer',
        'duration_minutes' => 'integer',
        'calculated_weekly_cost_cents' => 'integer',
        // Visit verification casts
        'verified_at' => 'datetime',
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

    /**
     * Scope for upcoming assignments (scheduled in the future).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_start', '>', now())
            ->whereIn('status', [self::STATUS_PLANNED, self::STATUS_PENDING, self::STATUS_ACTIVE])
            ->orderBy('scheduled_start', 'asc');
    }

    /**
     * Scope for past/completed assignments.
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('scheduled_start', '<', now())
              ->orWhere('status', self::STATUS_COMPLETED);
        })->orderBy('scheduled_start', 'desc');
    }

    /**
     * Scope for assignments within the current week.
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        return $query->whereBetween('scheduled_start', [$weekStart, $weekEnd])
            ->orderBy('scheduled_start', 'asc');
    }

    /**
     * Scope for assignments today.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('scheduled_start', now()->toDateString())
            ->orderBy('scheduled_start', 'asc');
    }

    /**
     * Scope for assignments assigned to a specific staff member.
     */
    public function scopeForStaff(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_user_id', $userId);
    }

    /**
     * Scope for active (in-progress or scheduled for today/future).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PLANNED,
            self::STATUS_ACTIVE,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PENDING,
        ]);
    }

    /**
     * Scope for completed assignments.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for internal (SPO direct staff) assignments.
     */
    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_INTERNAL);
    }

    /**
     * Scope for SSPO (subcontracted) assignments.
     */
    public function scopeSspoSource(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_SSPO);
    }

    /**
     * Scope for assignments within a specific week.
     */
    public function scopeInWeek(Builder $query, $weekStart, $weekEnd): Builder
    {
        return $query->whereBetween('scheduled_start', [$weekStart, $weekEnd]);
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

    /*
    |--------------------------------------------------------------------------
    | Visit Verification Relationships & Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * User who verified the visit.
     */
    public function verifiedByUser()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * Scope for assignments pending verification.
     */
    public function scopeVerificationPending(Builder $query): Builder
    {
        return $query->where('verification_status', self::VERIFICATION_PENDING);
    }

    /**
     * Scope for verified assignments.
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', self::VERIFICATION_VERIFIED);
    }

    /**
     * Scope for missed assignments.
     */
    public function scopeVerificationMissed(Builder $query): Builder
    {
        return $query->where('verification_status', self::VERIFICATION_MISSED);
    }

    /**
     * Scope for overdue unverified appointments.
     * An appointment is overdue if:
     * - verification_status = PENDING
     * - scheduled_start + grace period < now
     */
    public function scopeOverdueUnverified(Builder $query, ?int $graceMinutes = null): Builder
    {
        $grace = $graceMinutes ?? self::DEFAULT_VERIFICATION_GRACE_MINUTES;
        $threshold = now()->subMinutes($grace);

        return $query->where('verification_status', self::VERIFICATION_PENDING)
            ->where('scheduled_start', '<', $threshold);
    }

    /*
    |--------------------------------------------------------------------------
    | Visit Verification Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if visit verification is pending.
     */
    public function isVerificationPending(): bool
    {
        return $this->verification_status === self::VERIFICATION_PENDING;
    }

    /**
     * Check if visit is verified.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED;
    }

    /**
     * Check if visit is missed.
     */
    public function isVerificationMissed(): bool
    {
        return $this->verification_status === self::VERIFICATION_MISSED;
    }

    /**
     * Check if this appointment is overdue for verification.
     */
    public function isOverdueForVerification(?int $graceMinutes = null): bool
    {
        if (!$this->isVerificationPending()) {
            return false;
        }

        $grace = $graceMinutes ?? self::DEFAULT_VERIFICATION_GRACE_MINUTES;
        $threshold = now()->subMinutes($grace);

        return $this->scheduled_start && $this->scheduled_start->lt($threshold);
    }

    /**
     * Calculate how long ago this appointment should have been verified.
     */
    public function getOverdueDurationAttribute(): ?string
    {
        if (!$this->scheduled_start) {
            return null;
        }

        return $this->scheduled_start->diffForHumans();
    }

    /**
     * Get verification status label for display.
     */
    public function getVerificationStatusLabelAttribute(): string
    {
        return match ($this->verification_status) {
            self::VERIFICATION_PENDING => 'Pending Verification',
            self::VERIFICATION_VERIFIED => 'Verified',
            self::VERIFICATION_MISSED => 'Missed',
            default => 'Unknown',
        };
    }
}
