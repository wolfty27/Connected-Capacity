<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffUnavailability extends Model
{
    use HasFactory, SoftDeletes;

    // Unavailability types
    public const TYPE_VACATION = 'vacation';
    public const TYPE_SICK = 'sick';
    public const TYPE_PERSONAL = 'personal';
    public const TYPE_TRAINING = 'training';
    public const TYPE_JURY_DUTY = 'jury_duty';
    public const TYPE_BEREAVEMENT = 'bereavement';
    public const TYPE_MATERNITY = 'maternity';
    public const TYPE_PATERNITY = 'paternity';
    public const TYPE_MEDICAL = 'medical';
    public const TYPE_OTHER = 'other';

    // Approval statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'staff_unavailabilities';

    protected $fillable = [
        'user_id',
        'unavailability_type',
        'start_datetime',
        'end_datetime',
        'is_all_day',
        'reason',
        'notes',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'requested_by',
        'requested_at',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'is_all_day' => 'boolean',
        'approved_at' => 'datetime',
        'requested_at' => 'datetime',
    ];

    /**
     * Staff member this unavailability belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * User who approved/denied the request
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * User who submitted the request
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Scope: Unavailabilities overlapping with a time period
     */
    public function scopeOverlapping($query, Carbon $start, Carbon $end)
    {
        return $query->where('start_datetime', '<', $end)
                     ->where('end_datetime', '>', $start);
    }

    /**
     * Scope: Unavailabilities on a specific date
     */
    public function scopeOnDate($query, Carbon $date)
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();
        return $query->overlapping($startOfDay, $endOfDay);
    }

    /**
     * Scope: Only approved unavailabilities
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', self::STATUS_APPROVED);
    }

    /**
     * Scope: Pending approval
     */
    public function scopePending($query)
    {
        return $query->where('approval_status', self::STATUS_PENDING);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('unavailability_type', $type);
    }

    /**
     * Scope: Future unavailabilities
     */
    public function scopeFuture($query)
    {
        return $query->where('start_datetime', '>', Carbon::now());
    }

    /**
     * Scope: Current/active unavailabilities
     */
    public function scopeCurrent($query)
    {
        $now = Carbon::now();
        return $query->where('start_datetime', '<=', $now)
                     ->where('end_datetime', '>=', $now);
    }

    /**
     * Get type display label
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->unavailability_type) {
            self::TYPE_VACATION => 'Vacation',
            self::TYPE_SICK => 'Sick Leave',
            self::TYPE_PERSONAL => 'Personal Day',
            self::TYPE_TRAINING => 'Training',
            self::TYPE_JURY_DUTY => 'Jury Duty',
            self::TYPE_BEREAVEMENT => 'Bereavement',
            self::TYPE_MATERNITY => 'Maternity Leave',
            self::TYPE_PATERNITY => 'Paternity Leave',
            self::TYPE_MEDICAL => 'Medical',
            self::TYPE_OTHER => 'Other',
            default => ucfirst(str_replace('_', ' ', $this->unavailability_type)),
        };
    }

    /**
     * Get status display label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->approval_status) {
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_DENIED => 'Denied',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->approval_status),
        };
    }

    /**
     * Get duration in hours
     */
    public function getDurationHoursAttribute(): float
    {
        return $this->start_datetime->diffInMinutes($this->end_datetime) / 60;
    }

    /**
     * Get duration in days
     */
    public function getDurationDaysAttribute(): float
    {
        return $this->start_datetime->diffInDays($this->end_datetime);
    }

    /**
     * Check if unavailability overlaps with a time period
     */
    public function overlaps(Carbon $start, Carbon $end): bool
    {
        return $this->start_datetime < $end && $this->end_datetime > $start;
    }

    /**
     * Check if currently active
     */
    public function isActive(): bool
    {
        $now = Carbon::now();
        return $this->start_datetime <= $now
            && $this->end_datetime >= $now
            && $this->approval_status === self::STATUS_APPROVED;
    }

    /**
     * Check if pending approval
     */
    public function isPending(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    /**
     * Check if approved
     */
    public function isApproved(): bool
    {
        return $this->approval_status === self::STATUS_APPROVED;
    }

    /**
     * Approve the unavailability request
     */
    public function approve(User $approver, ?string $notes = null): bool
    {
        $this->approval_status = self::STATUS_APPROVED;
        $this->approved_by = $approver->id;
        $this->approved_at = Carbon::now();
        $this->approval_notes = $notes;
        return $this->save();
    }

    /**
     * Deny the unavailability request
     */
    public function deny(User $approver, ?string $notes = null): bool
    {
        $this->approval_status = self::STATUS_DENIED;
        $this->approved_by = $approver->id;
        $this->approved_at = Carbon::now();
        $this->approval_notes = $notes;
        return $this->save();
    }

    /**
     * Cancel the unavailability request
     */
    public function cancel(): bool
    {
        $this->approval_status = self::STATUS_CANCELLED;
        return $this->save();
    }
}
