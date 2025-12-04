<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * AI Suggestion Log Model
 * 
 * Tracks all AI scheduling suggestions and their outcomes for:
 * - Acceptance rate analytics
 * - Learning loop (what modifications users make)
 * - Model improvement feedback
 * - BigQuery export for ML training
 * 
 * @property int $id
 * @property string $suggestion_uuid
 * @property int $organization_id
 * @property int $patient_id
 * @property int $service_type_id
 * @property string $week_start
 * @property int|null $suggested_staff_id
 * @property string $match_status
 * @property float|null $confidence_score
 * @property array|null $scoring_factors
 * @property string|null $explanation_text
 * @property string|null $explanation_model
 * @property string $outcome
 * @property \Carbon\Carbon|null $outcome_at
 * @property int|null $outcome_user_id
 * @property int|null $final_staff_id
 * @property \Carbon\Carbon|null $final_scheduled_start
 * @property \Carbon\Carbon|null $final_scheduled_end
 * @property array|null $modifications
 * @property string|null $rejection_reason
 * @property int|null $time_to_decision_seconds
 * @property int|null $created_assignment_id
 * @property string $source
 * @property string|null $session_id
 */
class AiSuggestionLog extends Model
{
    // Outcome constants
    public const OUTCOME_PENDING = 'pending';
    public const OUTCOME_ACCEPTED = 'accepted';
    public const OUTCOME_MODIFIED = 'modified';
    public const OUTCOME_REJECTED = 'rejected';
    public const OUTCOME_EXPIRED = 'expired';

    // Match status constants (from AutoAssignEngine)
    public const MATCH_STRONG = 'strong';
    public const MATCH_MODERATE = 'moderate';
    public const MATCH_WEAK = 'weak';
    public const MATCH_NONE = 'none';

    // Source constants
    public const SOURCE_AUTO_ASSIGN = 'auto_assign';
    public const SOURCE_BATCH_SUGGEST = 'batch_suggest';
    public const SOURCE_MANUAL_REQUEST = 'manual_request';

    protected $fillable = [
        'suggestion_uuid',
        'organization_id',
        'patient_id',
        'service_type_id',
        'week_start',
        'suggested_staff_id',
        'match_status',
        'confidence_score',
        'scoring_factors',
        'explanation_text',
        'explanation_model',
        'outcome',
        'outcome_at',
        'outcome_user_id',
        'final_staff_id',
        'final_scheduled_start',
        'final_scheduled_end',
        'modifications',
        'rejection_reason',
        'time_to_decision_seconds',
        'created_assignment_id',
        'source',
        'session_id',
    ];

    protected $casts = [
        'week_start' => 'date',
        'confidence_score' => 'decimal:2',
        'scoring_factors' => 'array',
        'modifications' => 'array',
        'outcome_at' => 'datetime',
        'final_scheduled_start' => 'datetime',
        'final_scheduled_end' => 'datetime',
        'time_to_decision_seconds' => 'integer',
    ];

    // ==========================================
    // Boot Method
    // ==========================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->suggestion_uuid)) {
                $model->suggestion_uuid = Str::uuid()->toString();
            }
        });
    }

    // ==========================================
    // Relationships
    // ==========================================

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function suggestedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_staff_id');
    }

    public function finalStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_staff_id');
    }

    public function outcomeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outcome_user_id');
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Filter by organization
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Filter by week
     */
    public function scopeForWeek(Builder $query, string $weekStart): Builder
    {
        return $query->where('week_start', $weekStart);
    }

    /**
     * Filter by outcome
     */
    public function scopeWithOutcome(Builder $query, string $outcome): Builder
    {
        return $query->where('outcome', $outcome);
    }

    /**
     * Filter by match status
     */
    public function scopeWithMatchStatus(Builder $query, string $status): Builder
    {
        return $query->where('match_status', $status);
    }

    /**
     * Pending suggestions only
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('outcome', self::OUTCOME_PENDING);
    }

    /**
     * Accepted (as-is or modified) suggestions
     */
    public function scopeActioned(Builder $query): Builder
    {
        return $query->whereIn('outcome', [self::OUTCOME_ACCEPTED, self::OUTCOME_MODIFIED]);
    }

    // ==========================================
    // Instance Methods
    // ==========================================

    /**
     * Mark as accepted (no modifications)
     */
    public function markAccepted(int $userId, int $assignmentId): self
    {
        $this->update([
            'outcome' => self::OUTCOME_ACCEPTED,
            'outcome_at' => now(),
            'outcome_user_id' => $userId,
            'final_staff_id' => $this->suggested_staff_id,
            'created_assignment_id' => $assignmentId,
            'time_to_decision_seconds' => now()->diffInSeconds($this->created_at),
        ]);

        return $this;
    }

    /**
     * Mark as modified (user changed something)
     */
    public function markModified(
        int $userId,
        int $assignmentId,
        array $modifications,
        ?int $finalStaffId = null,
        ?\DateTimeInterface $finalStart = null,
        ?\DateTimeInterface $finalEnd = null
    ): self {
        $this->update([
            'outcome' => self::OUTCOME_MODIFIED,
            'outcome_at' => now(),
            'outcome_user_id' => $userId,
            'final_staff_id' => $finalStaffId ?? $this->suggested_staff_id,
            'final_scheduled_start' => $finalStart,
            'final_scheduled_end' => $finalEnd,
            'modifications' => $modifications,
            'created_assignment_id' => $assignmentId,
            'time_to_decision_seconds' => now()->diffInSeconds($this->created_at),
        ]);

        return $this;
    }

    /**
     * Mark as rejected
     */
    public function markRejected(int $userId, ?string $reason = null): self
    {
        $this->update([
            'outcome' => self::OUTCOME_REJECTED,
            'outcome_at' => now(),
            'outcome_user_id' => $userId,
            'rejection_reason' => $reason,
            'time_to_decision_seconds' => now()->diffInSeconds($this->created_at),
        ]);

        return $this;
    }

    /**
     * Mark as expired
     */
    public function markExpired(): self
    {
        $this->update([
            'outcome' => self::OUTCOME_EXPIRED,
            'outcome_at' => now(),
        ]);

        return $this;
    }

    // ==========================================
    // Static Factory Methods
    // ==========================================

    /**
     * Create a log entry from an AutoAssignEngine suggestion
     */
    public static function fromSuggestion(array $suggestion, int $organizationId, string $weekStart): self
    {
        return static::create([
            'organization_id' => $organizationId,
            'patient_id' => $suggestion['patient_id'],
            'service_type_id' => $suggestion['service_type_id'],
            'week_start' => $weekStart,
            'suggested_staff_id' => $suggestion['suggested_staff_id'] ?? null,
            'match_status' => $suggestion['match_status'] ?? self::MATCH_NONE,
            'confidence_score' => $suggestion['confidence_score'] ?? null,
            'scoring_factors' => $suggestion['scoring_factors'] ?? null,
            'source' => self::SOURCE_AUTO_ASSIGN,
        ]);
    }

    // ==========================================
    // Analytics Helpers
    // ==========================================

    /**
     * Calculate acceptance rate for an organization/period
     */
    public static function getAcceptanceRate(int $organizationId, ?string $weekStart = null): array
    {
        $query = static::forOrganization($organizationId);
        
        if ($weekStart) {
            $query->forWeek($weekStart);
        }

        $total = $query->count();
        
        if ($total === 0) {
            return [
                'total' => 0,
                'accepted' => 0,
                'modified' => 0,
                'rejected' => 0,
                'expired' => 0,
                'pending' => 0,
                'acceptance_rate' => 0,
                'modification_rate' => 0,
            ];
        }

        $byOutcome = static::forOrganization($organizationId)
            ->when($weekStart, fn ($q) => $q->forWeek($weekStart))
            ->selectRaw('outcome, count(*) as count')
            ->groupBy('outcome')
            ->pluck('count', 'outcome')
            ->toArray();

        $accepted = $byOutcome[self::OUTCOME_ACCEPTED] ?? 0;
        $modified = $byOutcome[self::OUTCOME_MODIFIED] ?? 0;
        $rejected = $byOutcome[self::OUTCOME_REJECTED] ?? 0;
        $expired = $byOutcome[self::OUTCOME_EXPIRED] ?? 0;
        $pending = $byOutcome[self::OUTCOME_PENDING] ?? 0;

        $actioned = $accepted + $modified;
        $decided = $actioned + $rejected;

        return [
            'total' => $total,
            'accepted' => $accepted,
            'modified' => $modified,
            'rejected' => $rejected,
            'expired' => $expired,
            'pending' => $pending,
            'acceptance_rate' => $decided > 0 ? round(($actioned / $decided) * 100, 1) : 0,
            'modification_rate' => $actioned > 0 ? round(($modified / $actioned) * 100, 1) : 0,
        ];
    }
}

