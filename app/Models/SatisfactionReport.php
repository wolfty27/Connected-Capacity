<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * SatisfactionReport Model
 * 
 * Represents patient feedback on service delivery by staff.
 * Staff satisfaction scores are computed from aggregated patient reports,
 * NOT self-reported job satisfaction.
 * 
 * Rating Scale:
 * 1 = Poor
 * 2 = Fair
 * 3 = Good
 * 4 = Very Good
 * 5 = Excellent
 * 
 * @property int $id
 * @property int $service_assignment_id
 * @property int $patient_id
 * @property int $staff_user_id
 * @property int $rating
 * @property string|null $feedback_text
 * @property array|null $aspect_ratings
 * @property Carbon $reported_at
 * @property int|null $reported_by_user_id
 * @property string $reporter_type
 */
class SatisfactionReport extends Model
{
    use HasFactory, SoftDeletes;

    // Rating scale constants
    public const RATING_POOR = 1;
    public const RATING_FAIR = 2;
    public const RATING_GOOD = 3;
    public const RATING_VERY_GOOD = 4;
    public const RATING_EXCELLENT = 5;

    // Rating labels
    public const RATING_LABELS = [
        self::RATING_POOR => 'Poor',
        self::RATING_FAIR => 'Fair',
        self::RATING_GOOD => 'Good',
        self::RATING_VERY_GOOD => 'Very Good',
        self::RATING_EXCELLENT => 'Excellent',
    ];

    // Reporter type constants
    public const REPORTER_PATIENT = 'patient';
    public const REPORTER_FAMILY = 'family_member';
    public const REPORTER_CAREGIVER = 'caregiver';
    public const REPORTER_OTHER = 'other';

    protected $fillable = [
        'service_assignment_id',
        'patient_id',
        'staff_user_id',
        'rating',
        'feedback_text',
        'aspect_ratings',
        'reported_at',
        'reported_by_user_id',
        'reporter_type',
    ];

    protected $casts = [
        'rating' => 'integer',
        'aspect_ratings' => 'array',
        'reported_at' => 'datetime',
    ];

    /**
     * The service assignment this report is for.
     */
    public function serviceAssignment(): BelongsTo
    {
        return $this->belongsTo(ServiceAssignment::class);
    }

    /**
     * The patient who received the service.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The staff member who delivered the service.
     */
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /**
     * The user who submitted the feedback (may be patient's family, etc.).
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /**
     * Get the human-readable rating label.
     */
    public function getRatingLabelAttribute(): string
    {
        return self::RATING_LABELS[$this->rating] ?? 'Unknown';
    }

    /**
     * Get rating as a percentage (1-5 scale to 0-100%).
     */
    public function getRatingPercentAttribute(): float
    {
        return (($this->rating - 1) / 4) * 100;
    }

    /**
     * Scope: Reports for a specific staff member.
     */
    public function scopeForStaff(Builder $query, int $staffUserId): Builder
    {
        return $query->where('staff_user_id', $staffUserId);
    }

    /**
     * Scope: Reports within a date range.
     */
    public function scopeWithinPeriod(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('reported_at', [$start, $end]);
    }

    /**
     * Scope: Reports in the last N days.
     */
    public function scopeLastDays(Builder $query, int $days): Builder
    {
        return $query->where('reported_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope: High satisfaction reports (4-5 rating).
     */
    public function scopeHighSatisfaction(Builder $query): Builder
    {
        return $query->where('rating', '>=', self::RATING_VERY_GOOD);
    }

    /**
     * Scope: Low satisfaction reports (1-2 rating).
     */
    public function scopeLowSatisfaction(Builder $query): Builder
    {
        return $query->where('rating', '<=', self::RATING_FAIR);
    }
}
