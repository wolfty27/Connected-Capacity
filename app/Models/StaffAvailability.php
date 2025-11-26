<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAvailability extends Model
{
    use HasFactory;

    // Days of week constants (matches PHP date('w'))
    public const SUNDAY = 0;
    public const MONDAY = 1;
    public const TUESDAY = 2;
    public const WEDNESDAY = 3;
    public const THURSDAY = 4;
    public const FRIDAY = 5;
    public const SATURDAY = 6;

    protected $table = 'staff_availabilities';

    protected $fillable = [
        'user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'effective_from',
        'effective_until',
        'is_recurring',
        'service_areas',
        'notes',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_recurring' => 'boolean',
        'service_areas' => 'array',
    ];

    /**
     * Staff member this availability belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Active availability windows on a specific date
     */
    public function scopeActiveOn($query, Carbon $date)
    {
        return $query->where('day_of_week', $date->dayOfWeek)
            ->where('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', $date->toDateString());
            });
    }

    /**
     * Scope: Filter by day of week
     */
    public function scopeOnDay($query, int $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    /**
     * Scope: Currently effective (not expired)
     */
    public function scopeCurrentlyEffective($query)
    {
        $today = Carbon::today();
        return $query->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', $today);
            });
    }

    /**
     * Scope: Availability windows that cover a specific time range
     */
    public function scopeCoveringTime($query, string $startTime, string $endTime)
    {
        return $query->where('start_time', '<=', $startTime)
                     ->where('end_time', '>=', $endTime);
    }

    /**
     * Get day name
     */
    public function getDayNameAttribute(): string
    {
        return match($this->day_of_week) {
            self::SUNDAY => 'Sunday',
            self::MONDAY => 'Monday',
            self::TUESDAY => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY => 'Thursday',
            self::FRIDAY => 'Friday',
            self::SATURDAY => 'Saturday',
            default => 'Unknown',
        };
    }

    /**
     * Get formatted time range
     */
    public function getTimeRangeAttribute(): string
    {
        $start = Carbon::parse($this->start_time)->format('g:i A');
        $end = Carbon::parse($this->end_time)->format('g:i A');
        return "{$start} - {$end}";
    }

    /**
     * Get duration in hours
     */
    public function getDurationHoursAttribute(): float
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return $start->diffInMinutes($end) / 60;
    }

    /**
     * Check if this availability is currently effective
     */
    public function isCurrentlyEffective(): bool
    {
        $today = Carbon::today();
        return $this->effective_from <= $today
            && ($this->effective_until === null || $this->effective_until >= $today);
    }

    /**
     * Check if availability covers a specific datetime
     */
    public function coversDatetime(Carbon $datetime): bool
    {
        // Check day of week
        if ($datetime->dayOfWeek !== $this->day_of_week) {
            return false;
        }

        // Check effective date range
        if (!$this->isCurrentlyEffective()) {
            return false;
        }

        // Check time range
        $time = $datetime->format('H:i:s');
        return $time >= $this->start_time && $time <= $this->end_time;
    }
}
