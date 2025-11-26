<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TriageResult extends Model
{
    use HasFactory;

    // HPG Response SLA (15 minutes per OHaH RFS)
    public const HPG_RESPONSE_SLA_MINUTES = 15;

    protected $fillable = [
        'patient_id',
        'received_at',
        'hpg_received_at',
        'hpg_responded_at',
        'hpg_responded_by',
        'triaged_at',
        'acuity_level',
        'dementia_flag',
        'mh_flag',
        'rpm_required',
        'fall_risk',
        'behavioural_risk',
        'crisis_designation',
        'notes',
        'raw_referral_payload',
        'triaged_by',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'hpg_received_at' => 'datetime',
        'hpg_responded_at' => 'datetime',
        'triaged_at' => 'datetime',
        'dementia_flag' => 'boolean',
        'mh_flag' => 'boolean',
        'rpm_required' => 'boolean',
        'fall_risk' => 'boolean',
        'behavioural_risk' => 'boolean',
        'crisis_designation' => 'boolean',
        'raw_referral_payload' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function triagedBy()
    {
        return $this->belongsTo(User::class, 'triaged_by');
    }

    public function hpgRespondedBy()
    {
        return $this->belongsTo(User::class, 'hpg_responded_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for referrals pending HPG response.
     */
    public function scopePendingHpgResponse(Builder $query): Builder
    {
        return $query->whereNotNull('hpg_received_at')
            ->whereNull('hpg_responded_at');
    }

    /**
     * Scope for referrals that breached HPG SLA.
     */
    public function scopeHpgSlaBreached(Builder $query): Builder
    {
        return $query->whereNotNull('hpg_received_at')
            ->whereNotNull('hpg_responded_at')
            ->whereRaw('TIMESTAMPDIFF(MINUTE, hpg_received_at, hpg_responded_at) > ?', [self::HPG_RESPONSE_SLA_MINUTES]);
    }

    /**
     * Scope for crisis-designated referrals.
     */
    public function scopeCrisis(Builder $query): Builder
    {
        return $query->where('crisis_designation', true);
    }

    /*
    |--------------------------------------------------------------------------
    | SLA Tracking Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if HPG response is still pending.
     */
    public function isHpgResponsePending(): bool
    {
        return $this->hpg_received_at && !$this->hpg_responded_at;
    }

    /**
     * Get minutes remaining in HPG response SLA.
     */
    public function getHpgSlaMinutesRemainingAttribute(): ?int
    {
        if (!$this->hpg_received_at || $this->hpg_responded_at) {
            return null;
        }

        $deadline = $this->hpg_received_at->copy()->addMinutes(self::HPG_RESPONSE_SLA_MINUTES);
        $remaining = now()->diffInMinutes($deadline, false);

        return max(0, $remaining);
    }

    /**
     * Check if HPG SLA was breached.
     */
    public function isHpgSlaBreached(): bool
    {
        if (!$this->hpg_received_at || !$this->hpg_responded_at) {
            return false;
        }

        return $this->hpg_received_at->diffInMinutes($this->hpg_responded_at) > self::HPG_RESPONSE_SLA_MINUTES;
    }

    /**
     * Get HPG response time in minutes.
     */
    public function getHpgResponseTimeMinutesAttribute(): ?int
    {
        if (!$this->hpg_received_at || !$this->hpg_responded_at) {
            return null;
        }

        return $this->hpg_received_at->diffInMinutes($this->hpg_responded_at);
    }

    /**
     * Check if HPG SLA is at risk (within 5 minutes of deadline).
     */
    public function isHpgSlaAtRisk(): bool
    {
        $remaining = $this->hpg_sla_minutes_remaining;
        return $remaining !== null && $remaining <= 5 && $remaining > 0;
    }

    /**
     * Mark HPG referral as responded.
     */
    public function markHpgResponded(?int $userId = null): self
    {
        $this->update([
            'hpg_responded_at' => now(),
            'hpg_responded_by' => $userId,
        ]);

        return $this;
    }

    /**
     * Get SLA compliance status.
     */
    public function getHpgSlaStatusAttribute(): string
    {
        if (!$this->hpg_received_at) {
            return 'not_applicable';
        }

        if (!$this->hpg_responded_at) {
            if ($this->hpg_sla_minutes_remaining === 0) {
                return 'breached';
            }
            return $this->isHpgSlaAtRisk() ? 'at_risk' : 'pending';
        }

        return $this->isHpgSlaBreached() ? 'breached' : 'compliant';
    }
}
