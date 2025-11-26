<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class CarePlan extends Model
{
    use HasFactory, SoftDeletes;

    // First service delivery SLA (24 hours per OHaH RFS)
    public const FIRST_SERVICE_SLA_HOURS = 24;

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'patient_id',
        'care_bundle_id',
        'version',
        'status',
        'goals',
        'risks',
        'interventions',
        'approved_by',
        'approved_at',
        'first_service_delivered_at',
        'notes',
    ];

    protected $casts = [
        'version' => 'integer',
        'goals' => 'array',
        'risks' => 'array',
        'interventions' => 'array',
        'approved_at' => 'datetime',
        'first_service_delivered_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function careBundle()
    {
        return $this->belongsTo(CareBundle::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function serviceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    public function interdisciplinaryNotes()
    {
        return $this->hasManyThrough(
            InterdisciplinaryNote::class,
            ServiceAssignment::class,
            'care_plan_id',
            'service_assignment_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for active care plans.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for care plans pending first service.
     */
    public function scopePendingFirstService(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNull('first_service_delivered_at');
    }

    /**
     * Scope for care plans that breached first service SLA.
     */
    public function scopeFirstServiceSlaBreached(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('approved_at')
            ->whereNotNull('first_service_delivered_at')
            ->whereRaw('TIMESTAMPDIFF(HOUR, approved_at, first_service_delivered_at) > ?', [self::FIRST_SERVICE_SLA_HOURS]);
    }

    /*
    |--------------------------------------------------------------------------
    | First Service SLA Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if first service has been delivered.
     */
    public function hasFirstServiceDelivered(): bool
    {
        return $this->first_service_delivered_at !== null;
    }

    /**
     * Get hours remaining until first service SLA breach.
     */
    public function getFirstServiceSlaHoursRemainingAttribute(): ?float
    {
        if (!$this->approved_at || $this->first_service_delivered_at) {
            return null;
        }

        $deadline = $this->approved_at->copy()->addHours(self::FIRST_SERVICE_SLA_HOURS);
        $remaining = now()->diffInHours($deadline, false);

        return max(0, $remaining);
    }

    /**
     * Check if first service SLA was breached.
     */
    public function isFirstServiceSlaBreached(): bool
    {
        if (!$this->approved_at || !$this->first_service_delivered_at) {
            return false;
        }

        return $this->approved_at->diffInHours($this->first_service_delivered_at) > self::FIRST_SERVICE_SLA_HOURS;
    }

    /**
     * Get time to first service in hours.
     */
    public function getTimeToFirstServiceHoursAttribute(): ?float
    {
        if (!$this->approved_at || !$this->first_service_delivered_at) {
            return null;
        }

        return round($this->approved_at->diffInMinutes($this->first_service_delivered_at) / 60, 2);
    }

    /**
     * Check if first service SLA is at risk (within 4 hours of deadline).
     */
    public function isFirstServiceSlaAtRisk(): bool
    {
        $remaining = $this->first_service_sla_hours_remaining;
        return $remaining !== null && $remaining <= 4 && $remaining > 0;
    }

    /**
     * Mark first service as delivered.
     */
    public function markFirstServiceDelivered(): self
    {
        if (!$this->first_service_delivered_at) {
            $this->update([
                'first_service_delivered_at' => now(),
            ]);
        }

        return $this;
    }

    /**
     * Get first service SLA compliance status.
     */
    public function getFirstServiceSlaStatusAttribute(): string
    {
        if (!$this->approved_at) {
            return 'not_applicable';
        }

        if (!$this->first_service_delivered_at) {
            if ($this->first_service_sla_hours_remaining === 0) {
                return 'breached';
            }
            return $this->isFirstServiceSlaAtRisk() ? 'at_risk' : 'pending';
        }

        return $this->isFirstServiceSlaBreached() ? 'breached' : 'compliant';
    }
}
