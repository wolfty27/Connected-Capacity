<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'service_type_id',
        'care_plan_id',
        'assigned_user_id',
        'scheduled_start',
        'scheduled_end',
        'duration_minutes',
        'status',
        'visit_label',
        'verification_status',
        'verified_at',
        'verification_method',
        'notes',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'verified_at' => 'datetime',
    ];

    // Assignment statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_MISSED = 'missed';

    // Verification statuses
    public const VERIFICATION_PENDING = 'PENDING';
    public const VERIFICATION_VERIFIED = 'VERIFIED';
    public const VERIFICATION_MISSED = 'MISSED';

    // Statuses that count as "scheduled" (not cancelled/missed)
    public const SCHEDULED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PLANNED,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }

    /**
     * Check if this assignment overlaps with a given time range
     */
    public function overlapsWithTimeRange(Carbon $start, Carbon $end): bool
    {
        // Two ranges overlap if: start1 < end2 AND start2 < end1
        return $this->scheduled_start < $end && $start < $this->scheduled_end;
    }

    /**
     * Get formatted time range for display
     */
    public function getTimeRangeAttribute(): string
    {
        return $this->scheduled_start->format('H:i') . '-' . $this->scheduled_end->format('H:i');
    }

    /**
     * Get formatted date for display
     */
    public function getScheduledDateAttribute(): string
    {
        return $this->scheduled_start->format('Y-m-d');
    }

    /**
     * Check if this assignment is in a scheduled (countable) status
     */
    public function isScheduled(): bool
    {
        return in_array($this->status, self::SCHEDULED_STATUSES);
    }

    /**
     * Calculate duration in hours
     */
    public function getDurationHoursAttribute(): float
    {
        return $this->duration_minutes / 60;
    }

    /**
     * Scope: only scheduled (not cancelled/missed) assignments
     */
    public function scopeScheduled($query)
    {
        return $query->whereIn('status', self::SCHEDULED_STATUSES);
    }

    /**
     * Scope: assignments for a specific patient
     */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope: assignments for a specific service type
     */
    public function scopeForServiceType($query, int $serviceTypeId)
    {
        return $query->where('service_type_id', $serviceTypeId);
    }

    /**
     * Scope: assignments within a date range
     */
    public function scopeInDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->where('scheduled_start', '>=', $start)
            ->where('scheduled_end', '<=', $end);
    }

    /**
     * Scope: assignments on a specific date
     */
    public function scopeOnDate($query, Carbon $date)
    {
        return $query->whereDate('scheduled_start', $date);
    }
}
