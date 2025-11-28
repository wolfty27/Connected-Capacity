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

    /**
     * Get RUG classifications derived from this assessment.
     */
    public function rugClassifications()
    {
        return $this->hasMany(RUGClassification::class, 'assessment_id');
    }

    /**
     * Get the latest RUG classification for this assessment.
     */
    public function latestRugClassification()
    {
        return $this->hasOne(RUGClassification::class, 'assessment_id')
            ->where('is_current', true)
            ->latestOfMany();
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
        // Load RUG classification if not already loaded
        $rugClassification = $this->relationLoaded('latestRugClassification')
            ? $this->latestRugClassification
            : $this->latestRugClassification()->first();

        return [
            'id' => $this->id,
            'assessment_date' => $this->assessment_date->toIso8601String(),
            'assessment_type' => $this->assessment_type,
            'source' => $this->source,
            'workflow_status' => $this->workflow_status ?? 'completed',
            'is_current' => $this->is_current ?? true,
            'is_stale' => $this->isStale(),
            'days_until_stale' => $this->days_until_stale,
            'maple_score' => $this->maple_score,
            'maple_description' => $this->maple_description,
            'cps' => $this->cognitive_performance_scale,
            'cps_description' => $this->cps_description,
            'adl_hierarchy' => $this->adl_hierarchy,
            'adl_description' => $this->adl_description,
            'iadl_difficulty' => $this->iadl_difficulty,
            'chess_score' => $this->chess_score,
            'depression_rating_scale' => $this->depression_rating_scale,
            'pain_scale' => $this->pain_scale,
            'falls_in_last_90_days' => $this->falls_in_last_90_days,
            'wandering_flag' => $this->wandering_flag,
            'high_risk_flags' => $this->high_risk_flags,
            'iar_status' => $this->iar_upload_status,
            'chris_status' => $this->chris_sync_status,
            // RUG Classification data
            'rug_group' => $rugClassification?->rug_group,
            'rug_category' => $rugClassification?->rug_category,
            'rug_classification' => $rugClassification ? [
                'id' => $rugClassification->id,
                'rug_group' => $rugClassification->rug_group,
                'rug_category' => $rugClassification->rug_category,
                'category_description' => $rugClassification->category_description,
                'adl_sum' => $rugClassification->adl_sum,
                'numeric_rank' => $rugClassification->numeric_rank,
            ] : null,
            // Full assessment sections derived from raw_items
            'sections' => $this->getAssessmentSections(),
        ];
    }

    /**
     * Map raw_items to structured assessment sections.
     *
     * Transforms iCODE-keyed raw items into human-readable sections
     * for display in the full InterRAI HC assessment view.
     */
    public function getAssessmentSections(): array
    {
        $raw = $this->raw_items ?? [];

        if (empty($raw)) {
            return [];
        }

        return [
            'identification' => $this->mapIdentificationSection($raw),
            'cognition' => $this->mapCognitionSection($raw),
            'communication' => $this->mapCommunicationSection($raw),
            'mood' => $this->mapMoodSection($raw),
            'adl' => $this->mapAdlSection($raw),
            'iadl' => $this->mapIadlSection($raw),
            'continence' => $this->mapContinenceSection($raw),
            'health_conditions' => $this->mapHealthConditionsSection($raw),
            'diseases' => $this->mapDiseasesSection($raw),
            'treatments' => $this->mapTreatmentsSection($raw),
            'social_supports' => $this->mapSocialSupportsSection($raw),
        ];
    }

    /**
     * Map identification section.
     */
    protected function mapIdentificationSection(array $raw): array
    {
        return [
            'assessment_type' => match ($this->assessment_type) {
                'hc' => 'Home Care (RAI-HC)',
                'cha' => 'Contact Assessment (RAI-CHA)',
                'contact' => 'Contact',
                default => $this->assessment_type,
            },
            'assessment_date' => $this->assessment_date?->format('Y-m-d'),
            'source' => match ($this->source) {
                'hpg_referral' => 'HPG Referral',
                'spo_completed' => 'SPO Completed',
                'ohah_provided' => 'OHaH Provided',
                default => $this->source,
            },
            'assessor_role' => $this->assessor_role,
            'primary_diagnosis' => $this->primary_diagnosis_icd10,
        ];
    }

    /**
     * Map cognition section (Section C).
     */
    protected function mapCognitionSection(array $raw): array
    {
        $cpsLabels = [
            0 => 'Independent',
            1 => 'Modified Independence',
            2 => 'Minimally Impaired',
            3 => 'Moderately Impaired',
            4 => 'Severely Impaired',
            5 => 'No Discernible Consciousness',
        ];

        $memoryLabels = [
            0 => 'Memory OK',
            1 => 'Memory Problem',
        ];

        $communicationLabels = [
            0 => 'Understood',
            1 => 'Usually Understood',
            2 => 'Often Understood',
            3 => 'Sometimes Understood',
            4 => 'Rarely/Never Understood',
        ];

        return [
            'cps_score' => $this->cognitive_performance_scale,
            'cps_description' => $this->cps_description,
            'decision_making' => [
                'value' => $raw['cps_decision_making'] ?? null,
                'label' => $cpsLabels[$raw['cps_decision_making'] ?? 0] ?? null,
            ],
            'short_term_memory' => [
                'value' => $raw['cps_short_term_memory'] ?? null,
                'label' => $memoryLabels[$raw['cps_short_term_memory'] ?? 0] ?? null,
            ],
            'making_self_understood' => [
                'value' => $raw['cps_communication'] ?? null,
                'label' => $communicationLabels[$raw['cps_communication'] ?? 0] ?? null,
            ],
        ];
    }

    /**
     * Map communication section (Section D).
     */
    protected function mapCommunicationSection(array $raw): array
    {
        $hearingLabels = [
            0 => 'Adequate',
            1 => 'Minimal Difficulty',
            2 => 'Moderate Difficulty',
            3 => 'Severe Difficulty',
            4 => 'No Hearing',
        ];

        $visionLabels = [
            0 => 'Adequate',
            1 => 'Impaired',
            2 => 'Moderately Impaired',
            3 => 'Severely Impaired',
            4 => 'No Vision',
        ];

        return [
            'hearing' => [
                'value' => $raw['hearing'] ?? null,
                'label' => $hearingLabels[$raw['hearing'] ?? 0] ?? null,
            ],
            'vision' => [
                'value' => $raw['vision'] ?? null,
                'label' => $visionLabels[$raw['vision'] ?? 0] ?? null,
            ],
        ];
    }

    /**
     * Map mood/behaviour section (Section E).
     */
    protected function mapMoodSection(array $raw): array
    {
        $presenceLabels = [
            0 => 'Not Present',
            1 => 'Present 1-2 days',
            2 => 'Present Daily',
        ];

        return [
            'depression_rating_scale' => $this->depression_rating_scale,
            'indicators' => [
                'negative_statements' => [
                    'value' => $raw['mood_negative_statements'] ?? null,
                    'label' => $presenceLabels[$raw['mood_negative_statements'] ?? 0] ?? null,
                ],
                'persistent_anger' => [
                    'value' => $raw['mood_persistent_anger'] ?? null,
                    'label' => $presenceLabels[$raw['mood_persistent_anger'] ?? 0] ?? null,
                ],
                'unrealistic_fears' => [
                    'value' => $raw['mood_unrealistic_fears'] ?? null,
                    'label' => $presenceLabels[$raw['mood_unrealistic_fears'] ?? 0] ?? null,
                ],
                'sad_expressions' => [
                    'value' => $raw['mood_sad_expressions'] ?? null,
                    'label' => $presenceLabels[$raw['mood_sad_expressions'] ?? 0] ?? null,
                ],
                'crying' => [
                    'value' => $raw['mood_crying'] ?? null,
                    'label' => $presenceLabels[$raw['mood_crying'] ?? 0] ?? null,
                ],
            ],
        ];
    }

    /**
     * Map ADL (Activities of Daily Living) section.
     */
    protected function mapAdlSection(array $raw): array
    {
        $adlLabels = [
            0 => 'Independent',
            1 => 'Setup Help Only',
            2 => 'Supervision',
            3 => 'Limited Assistance',
            4 => 'Extensive Assistance',
            5 => 'Maximal Assistance',
            6 => 'Total Dependence',
            8 => 'Activity Did Not Occur',
        ];

        $mapAdlValue = function ($key) use ($raw, $adlLabels) {
            $value = $raw[$key] ?? null;
            return [
                'value' => $value,
                'label' => $adlLabels[$value] ?? null,
            ];
        };

        return [
            'adl_hierarchy_score' => $this->adl_hierarchy,
            'adl_hierarchy_description' => $this->adl_description,
            'activities' => [
                'bathing' => $mapAdlValue('adl_bathing'),
                'personal_hygiene' => $mapAdlValue('adl_hygiene'),
                'dressing_upper' => $mapAdlValue('adl_dressing_upper'),
                'dressing_lower' => $mapAdlValue('adl_dressing_lower'),
                'locomotion' => $mapAdlValue('adl_locomotion'),
                'transfer' => $mapAdlValue('adl_transfer'),
                'toilet_use' => $mapAdlValue('adl_toilet_use'),
                'bed_mobility' => $mapAdlValue('adl_bed_mobility'),
                'eating' => $mapAdlValue('adl_eating'),
            ],
        ];
    }

    /**
     * Map IADL (Instrumental Activities of Daily Living) section.
     */
    protected function mapIadlSection(array $raw): array
    {
        $iadlLabels = [
            0 => 'Independent',
            1 => 'Setup Help Only',
            2 => 'Supervision',
            3 => 'Limited Assistance',
            4 => 'Extensive Assistance',
            5 => 'Maximal Assistance',
            6 => 'Total Dependence',
            8 => 'Activity Did Not Occur',
        ];

        $mapIadlValue = function ($key) use ($raw, $iadlLabels) {
            $value = $raw[$key] ?? null;
            return [
                'value' => $value,
                'label' => $iadlLabels[$value] ?? null,
            ];
        };

        return [
            'iadl_difficulty_score' => $this->iadl_difficulty,
            'activities' => [
                'meal_preparation' => $mapIadlValue('iadl_meal_prep'),
                'housework' => $mapIadlValue('iadl_housework'),
                'managing_finances' => $mapIadlValue('iadl_finances'),
                'managing_medications' => $mapIadlValue('iadl_medications'),
                'phone_use' => $mapIadlValue('iadl_phone'),
                'shopping' => $mapIadlValue('iadl_shopping'),
                'transportation' => $mapIadlValue('iadl_transportation'),
            ],
        ];
    }

    /**
     * Map continence section (Section H).
     */
    protected function mapContinenceSection(array $raw): array
    {
        $continenceLabels = [
            0 => 'Continent',
            1 => 'Control with Device',
            2 => 'Infrequently Incontinent',
            3 => 'Occasionally Incontinent',
            4 => 'Frequently Incontinent',
            5 => 'Incontinent',
        ];

        return [
            'bladder_continence' => [
                'value' => $raw['bladder_continence'] ?? null,
                'label' => $continenceLabels[$raw['bladder_continence'] ?? 0] ?? null,
            ],
            'bowel_continence' => [
                'value' => $raw['bowel_continence'] ?? null,
                'label' => $continenceLabels[$raw['bowel_continence'] ?? 0] ?? null,
            ],
        ];
    }

    /**
     * Map health conditions section (Section J).
     */
    protected function mapHealthConditionsSection(array $raw): array
    {
        $painFreqLabels = [
            0 => 'No Pain',
            1 => 'Not in Last 3 Days',
            2 => 'Less Than Daily',
            3 => 'Daily',
        ];

        $painIntensityLabels = [
            1 => 'Mild',
            2 => 'Moderate',
            3 => 'Severe',
            4 => 'Horrible/Excruciating',
        ];

        $fallLabels = [
            0 => 'No Falls',
            1 => 'Fall, No Injury',
            2 => 'Fall with Injury',
        ];

        return [
            'pain' => [
                'pain_scale' => $this->pain_scale,
                'frequency' => [
                    'value' => $raw['pain_frequency'] ?? null,
                    'label' => $painFreqLabels[$raw['pain_frequency'] ?? 0] ?? null,
                ],
                'intensity' => [
                    'value' => $raw['pain_intensity'] ?? null,
                    'label' => $painIntensityLabels[$raw['pain_intensity'] ?? 1] ?? null,
                ],
            ],
            'chess_score' => $this->chess_score,
            'symptoms' => [
                'shortness_of_breath' => (bool) ($raw['dyspnea'] ?? false),
                'fatigue' => (bool) ($raw['fatigue'] ?? false),
                'edema' => (bool) ($raw['edema'] ?? false),
                'dizziness' => (bool) ($raw['dizziness'] ?? false),
                'chest_pain' => (bool) ($raw['chest_pain'] ?? false),
            ],
            'falls' => [
                'value' => $raw['fall_history'] ?? null,
                'label' => $fallLabels[$raw['fall_history'] ?? 0] ?? null,
                'in_last_90_days' => $this->falls_in_last_90_days,
            ],
            'weight_loss' => (bool) ($raw['weight_loss'] ?? false),
            'dehydration' => (bool) ($raw['dehydration'] ?? false),
            'vomiting' => (bool) ($raw['vomiting'] ?? false),
        ];
    }

    /**
     * Map diseases/diagnoses section.
     */
    protected function mapDiseasesSection(array $raw): array
    {
        $diseases = [];

        // Neurological
        $neurological = [];
        if ($raw['special_ms'] ?? false) $neurological[] = 'Multiple Sclerosis';
        if ($raw['special_quadriplegia'] ?? false) $neurological[] = 'Quadriplegia';
        if ($raw['special_coma'] ?? false) $neurological[] = 'Coma';

        // Cardiac/Respiratory
        $cardiac = [];
        if ($raw['clinical_chf'] ?? false) $cardiac[] = 'Congestive Heart Failure';
        if ($raw['clinical_copd'] ?? false) $cardiac[] = 'COPD';
        if ($raw['clinical_pneumonia'] ?? false) $cardiac[] = 'Pneumonia';

        // Other conditions
        $other = [];
        if ($raw['clinical_diabetes'] ?? false) $other[] = 'Diabetes';
        if ($raw['clinical_wound'] ?? false) $other[] = 'Wound/Pressure Ulcer';
        if ($raw['special_burns'] ?? false) $other[] = 'Burns';

        return [
            'primary_diagnosis_icd10' => $this->primary_diagnosis_icd10,
            'secondary_diagnoses' => $this->secondary_diagnoses ?? [],
            'categories' => [
                'neurological' => $neurological,
                'cardiac_respiratory' => $cardiac,
                'other' => $other,
            ],
        ];
    }

    /**
     * Map treatments/procedures section.
     */
    protected function mapTreatmentsSection(array $raw): array
    {
        return [
            'extensive_services' => [
                'dialysis' => (bool) ($raw['extensive_dialysis'] ?? false),
                'iv_therapy' => (bool) ($raw['extensive_iv'] ?? false),
                'ventilator' => (bool) ($raw['extensive_ventilator'] ?? false),
                'tracheostomy' => (bool) ($raw['extensive_trach'] ?? false),
            ],
            'clinical_treatments' => [
                'oxygen_therapy' => (bool) ($raw['clinical_oxygen'] ?? false),
                'tube_feeding' => (bool) ($raw['clinical_tube_feeding'] ?? false),
                'wound_care' => (bool) ($raw['clinical_wound'] ?? false),
                'dialysis' => (bool) ($raw['clinical_dialysis'] ?? false),
            ],
            'therapy_services' => [
                'physical_therapy_minutes' => $raw['therapy_pt'] ?? 0,
                'occupational_therapy_minutes' => $raw['therapy_ot'] ?? 0,
                'speech_language_therapy_minutes' => $raw['therapy_slp'] ?? 0,
            ],
        ];
    }

    /**
     * Map social supports section (Section P).
     */
    protected function mapSocialSupportsSection(array $raw): array
    {
        return [
            'primary_caregiver_lives_with_client' => (bool) ($raw['caregiver_lives_with'] ?? false),
            'caregiver_unable_to_continue' => (bool) ($raw['caregiver_stress'] ?? false),
            'wandering_behaviour' => $this->wandering_flag,
        ];
    }
}
