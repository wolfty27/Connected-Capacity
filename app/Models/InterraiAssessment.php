<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * InterraiAssessment Model
 *
 * Stores InterRAI Home Care (HC) assessment data required for OHaH compliance.
 * Tracks MAPLe scores, CPS, ADL hierarchy, and other clinical indicators
 * used for care planning and Ontario Health reporting.
 *
 * @property int $id
 * @property int $patient_id
 * @property string $assessment_type
 * @property Carbon $assessment_date
 * @property int|null $assessor_id
 * @property string|null $assessor_role
 * @property string $source
 * @property string|null $maple_score
 * @property string|null $rai_cha_score
 * @property int|null $adl_hierarchy
 * @property int|null $iadl_difficulty
 * @property int|null $cognitive_performance_scale
 * @property int|null $depression_rating_scale
 * @property int|null $pain_scale
 * @property int|null $chess_score
 * @property string|null $method_for_locomotion
 * @property bool $falls_in_last_90_days
 * @property bool $wandering_flag
 * @property array|null $caps_triggered
 * @property string|null $primary_diagnosis_icd10
 * @property array|null $secondary_diagnoses
 * @property string $iar_upload_status
 * @property Carbon|null $iar_upload_timestamp
 * @property string|null $iar_confirmation_id
 * @property string $chris_sync_status
 * @property Carbon|null $chris_sync_timestamp
 * @property array|null $raw_assessment_data
 */
class InterraiAssessment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'assessment_type',
        'assessment_date',
        'assessor_id',
        'assessor_role',
        'source',
        'maple_score',
        'rai_cha_score',
        'adl_hierarchy',
        'iadl_difficulty',
        'cognitive_performance_scale',
        'depression_rating_scale',
        'pain_scale',
        'chess_score',
        'method_for_locomotion',
        'falls_in_last_90_days',
        'wandering_flag',
        'caps_triggered',
        'primary_diagnosis_icd10',
        'secondary_diagnoses',
        'iar_upload_status',
        'iar_upload_timestamp',
        'iar_confirmation_id',
        'chris_sync_status',
        'chris_sync_timestamp',
        'raw_assessment_data',
        // New versioning and workflow fields
        'version',
        'is_current',
        'previous_assessment_id',
        'reassessment_reason',
        'workflow_status',
        'sections_completed',
        'raw_items',
        'object_instance_id',
        'started_at',
        'submitted_at',
        'time_spent_minutes',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'iadl_capacity',
        'communication_scale',
        'social_engagement',
        'self_reliance',
        'maple_description',
        'adl_description',
        'cps_description',
        'notes',
    ];

    protected $casts = [
        'assessment_date' => 'datetime',
        'falls_in_last_90_days' => 'boolean',
        'wandering_flag' => 'boolean',
        'caps_triggered' => 'array',
        'secondary_diagnoses' => 'array',
        'iar_upload_timestamp' => 'datetime',
        'chris_sync_timestamp' => 'datetime',
        'raw_assessment_data' => 'array',
        'adl_hierarchy' => 'integer',
        'iadl_difficulty' => 'integer',
        'cognitive_performance_scale' => 'integer',
        'depression_rating_scale' => 'integer',
        'pain_scale' => 'integer',
        'chess_score' => 'integer',
        // New casts
        'version' => 'integer',
        'is_current' => 'boolean',
        'sections_completed' => 'array',
        'raw_items' => 'array',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'time_spent_minutes' => 'integer',
        'iadl_capacity' => 'integer',
        'communication_scale' => 'integer',
        'social_engagement' => 'integer',
        'self_reliance' => 'integer',
    ];

    // Assessment types
    public const TYPE_HC = 'hc';           // Home Care
    public const TYPE_CHA = 'cha';         // Contact Assessment
    public const TYPE_CONTACT = 'contact'; // Contact Assessment

    // Source types
    public const SOURCE_HPG = 'hpg_referral';
    public const SOURCE_SPO = 'spo_completed';
    public const SOURCE_OHAH = 'ohah_provided';

    // IAR upload statuses
    public const IAR_PENDING = 'pending';
    public const IAR_UPLOADED = 'uploaded';
    public const IAR_FAILED = 'failed';
    public const IAR_NOT_REQUIRED = 'not_required';

    // CHRIS sync statuses
    public const CHRIS_PENDING = 'pending';
    public const CHRIS_SYNCED = 'synced';
    public const CHRIS_FAILED = 'failed';
    public const CHRIS_NOT_REQUIRED = 'not_required';

    // Staleness threshold (OHaH requires reassessment if >3 months old)
    public const STALENESS_MONTHS = 3;

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function previousAssessment()
    {
        return $this->belongsTo(InterraiAssessment::class, 'previous_assessment_id');
    }

    public function nextAssessment()
    {
        return $this->hasOne(InterraiAssessment::class, 'previous_assessment_id');
    }

    public function documents()
    {
        return $this->hasMany(InterraiDocument::class);
    }

    /**
     * Get reassessment triggers resolved by this assessment.
     */
    public function resolvedTriggers()
    {
        return $this->hasMany(ReassessmentTrigger::class, 'resolution_assessment_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for assessments pending IAR upload.
     */
    public function scopePendingIarUpload(Builder $query): Builder
    {
        return $query->where('iar_upload_status', self::IAR_PENDING);
    }

    /**
     * Scope for assessments pending CHRIS sync.
     */
    public function scopePendingChrisSync(Builder $query): Builder
    {
        return $query->where('chris_sync_status', self::CHRIS_PENDING);
    }

    /**
     * Scope for stale assessments (>3 months old per OHaH RFS).
     */
    public function scopeStale(Builder $query): Builder
    {
        return $query->where('assessment_date', '<', now()->subMonths(self::STALENESS_MONTHS));
    }

    /**
     * Scope for current (non-stale) assessments.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('assessment_date', '>=', now()->subMonths(self::STALENESS_MONTHS));
    }

    /**
     * Scope for Home Care assessments.
     */
    public function scopeHomeCare(Builder $query): Builder
    {
        return $query->where('assessment_type', self::TYPE_HC);
    }

    /**
     * Scope for assessments from HPG referrals.
     */
    public function scopeFromHpg(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_HPG);
    }

    /**
     * Scope for SPO-completed assessments.
     */
    public function scopeSpoCompleted(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_SPO);
    }

    /**
     * Get the most recent assessment for a patient.
     */
    public function scopeLatestForPatient(Builder $query, int $patientId): Builder
    {
        return $query->where('patient_id', $patientId)
            ->orderBy('assessment_date', 'desc');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this assessment is stale (>3 months old).
     */
    public function isStale(): bool
    {
        return $this->assessment_date->lt(now()->subMonths(self::STALENESS_MONTHS));
    }

    /**
     * Check if this assessment needs IAR upload.
     */
    public function needsIarUpload(): bool
    {
        return $this->iar_upload_status === self::IAR_PENDING;
    }

    /**
     * Check if this assessment needs CHRIS sync.
     */
    public function needsChrisSync(): bool
    {
        return $this->chris_sync_status === self::CHRIS_PENDING;
    }

    /**
     * Get days until assessment becomes stale.
     */
    public function getDaysUntilStaleAttribute(): int
    {
        $staleDate = $this->assessment_date->copy()->addMonths(self::STALENESS_MONTHS);
        return max(0, now()->diffInDays($staleDate, false));
    }

    /**
     * Get human-readable MAPLe description.
     */
    public function getMapleDescriptionAttribute(): ?string
    {
        if (!$this->maple_score) {
            return null;
        }

        return match ($this->maple_score) {
            '1' => 'Low',
            '2' => 'Mild',
            '3' => 'Moderate',
            '4' => 'High',
            '5' => 'Very High',
            default => $this->maple_score,
        };
    }

    /**
     * Get human-readable CPS description.
     */
    public function getCpsDescriptionAttribute(): ?string
    {
        if ($this->cognitive_performance_scale === null) {
            return null;
        }

        return match ($this->cognitive_performance_scale) {
            0 => 'Intact',
            1 => 'Borderline Intact',
            2 => 'Mild Impairment',
            3 => 'Moderate Impairment',
            4 => 'Moderate-Severe Impairment',
            5 => 'Severe Impairment',
            6 => 'Very Severe Impairment',
            default => "CPS {$this->cognitive_performance_scale}",
        };
    }

    /**
     * Get human-readable ADL hierarchy description.
     */
    public function getAdlDescriptionAttribute(): ?string
    {
        if ($this->adl_hierarchy === null) {
            return null;
        }

        return match ($this->adl_hierarchy) {
            0 => 'Independent',
            1 => 'Supervision Required',
            2 => 'Limited Assistance',
            3 => 'Extensive Assistance (1)',
            4 => 'Extensive Assistance (2)',
            5 => 'Dependent',
            6 => 'Total Dependence',
            default => "ADL {$this->adl_hierarchy}",
        };
    }

    /**
     * Get high-risk flags as array.
     */
    public function getHighRiskFlagsAttribute(): array
    {
        $flags = [];

        if ($this->falls_in_last_90_days) {
            $flags[] = 'Fall Risk';
        }
        if ($this->wandering_flag) {
            $flags[] = 'Wandering/Elopement Risk';
        }
        if ($this->cognitive_performance_scale >= 3) {
            $flags[] = 'Cognitive Impairment';
        }
        if ($this->chess_score >= 3) {
            $flags[] = 'Health Instability';
        }
        if ($this->pain_scale >= 2) {
            $flags[] = 'Significant Pain';
        }
        if ($this->depression_rating_scale >= 3) {
            $flags[] = 'Depression Risk';
        }

        return $flags;
    }

    /**
     * Mark assessment as uploaded to IAR.
     */
    public function markIarUploaded(string $confirmationId): self
    {
        $this->update([
            'iar_upload_status' => self::IAR_UPLOADED,
            'iar_upload_timestamp' => now(),
            'iar_confirmation_id' => $confirmationId,
        ]);

        return $this;
    }

    /**
     * Mark IAR upload as failed.
     */
    public function markIarFailed(): self
    {
        $this->update([
            'iar_upload_status' => self::IAR_FAILED,
        ]);

        return $this;
    }

    /**
     * Mark assessment as synced to CHRIS.
     */
    public function markChrisSynced(): self
    {
        $this->update([
            'chris_sync_status' => self::CHRIS_SYNCED,
            'chris_sync_timestamp' => now(),
        ]);

        return $this;
    }

    /**
     * Mark CHRIS sync as failed.
     */
    public function markChrisFailed(): self
    {
        $this->update([
            'chris_sync_status' => self::CHRIS_FAILED,
        ]);

        return $this;
    }

    /**
     * Check if patient needs a new assessment based on this one being stale.
     */
    public function requiresReassessment(): bool
    {
        return $this->isStale();
    }

    /**
     * Get a summary array for API responses.
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'assessment_date' => $this->assessment_date->toIso8601String(),
            'assessment_type' => $this->assessment_type,
            'source' => $this->source,
            'is_stale' => $this->isStale(),
            'days_until_stale' => $this->days_until_stale,
            'maple_score' => $this->maple_score,
            'maple_description' => $this->maple_description,
            'cps' => $this->cognitive_performance_scale,
            'cps_description' => $this->cps_description,
            'adl_hierarchy' => $this->adl_hierarchy,
            'adl_description' => $this->adl_description,
            'high_risk_flags' => $this->high_risk_flags,
            'iar_status' => $this->iar_upload_status,
            'chris_status' => $this->chris_sync_status,
        ];
    }
}
