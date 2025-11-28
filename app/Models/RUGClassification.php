<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * RUGClassification Model
 *
 * Represents the output of CIHI's RUG-III/HC classification algorithm.
 * Each classification is derived from an InterRAI HC assessment and
 * determines which care bundle templates are appropriate for the patient.
 *
 * RUG Categories (in hierarchy order):
 * 1. Special Rehabilitation (RB0, RA2, RA1)
 * 2. Extensive Services (SE3, SE2, SE1)
 * 3. Special Care (SSB, SSA)
 * 4. Clinically Complex (CC0, CB0, CA2, CA1)
 * 5. Impaired Cognition (IB0, IA2, IA1)
 * 6. Behaviour Problems (BB0, BA2, BA1)
 * 7. Reduced Physical Function (PD0, PC0, PB0, PA2, PA1)
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 * @see docs/CC21_RUG_Bundle_Templates.md
 */
class RUGClassification extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     * Explicitly set to avoid Laravel converting RUG to r_u_g.
     */
    protected $table = 'rug_classifications';

    protected $fillable = [
        'patient_id',
        'assessment_id',
        'rug_group',
        'rug_category',
        'adl_sum',
        'iadl_sum',
        'cps_score',
        'flags',
        'numeric_rank',
        'therapy_minutes',
        'extensive_count',
        'is_current',
        'computation_details',
    ];

    protected $casts = [
        'adl_sum' => 'integer',
        'iadl_sum' => 'integer',
        'cps_score' => 'integer',
        'flags' => 'array',
        'numeric_rank' => 'integer',
        'therapy_minutes' => 'integer',
        'extensive_count' => 'integer',
        'is_current' => 'boolean',
        'computation_details' => 'array',
    ];

    // RUG Categories
    public const CATEGORY_SPECIAL_REHABILITATION = 'Special Rehabilitation';
    public const CATEGORY_EXTENSIVE_SERVICES = 'Extensive Services';
    public const CATEGORY_SPECIAL_CARE = 'Special Care';
    public const CATEGORY_CLINICALLY_COMPLEX = 'Clinically Complex';
    public const CATEGORY_IMPAIRED_COGNITION = 'Impaired Cognition';
    public const CATEGORY_BEHAVIOUR_PROBLEMS = 'Behaviour Problems';
    public const CATEGORY_REDUCED_PHYSICAL_FUNCTION = 'Reduced Physical Function';

    // RUG Groups mapped to categories
    public const GROUP_CATEGORIES = [
        'RB0' => self::CATEGORY_SPECIAL_REHABILITATION,
        'RA2' => self::CATEGORY_SPECIAL_REHABILITATION,
        'RA1' => self::CATEGORY_SPECIAL_REHABILITATION,
        'SE3' => self::CATEGORY_EXTENSIVE_SERVICES,
        'SE2' => self::CATEGORY_EXTENSIVE_SERVICES,
        'SE1' => self::CATEGORY_EXTENSIVE_SERVICES,
        'SSB' => self::CATEGORY_SPECIAL_CARE,
        'SSA' => self::CATEGORY_SPECIAL_CARE,
        'CC0' => self::CATEGORY_CLINICALLY_COMPLEX,
        'CB0' => self::CATEGORY_CLINICALLY_COMPLEX,
        'CA2' => self::CATEGORY_CLINICALLY_COMPLEX,
        'CA1' => self::CATEGORY_CLINICALLY_COMPLEX,
        'IB0' => self::CATEGORY_IMPAIRED_COGNITION,
        'IA2' => self::CATEGORY_IMPAIRED_COGNITION,
        'IA1' => self::CATEGORY_IMPAIRED_COGNITION,
        'BB0' => self::CATEGORY_BEHAVIOUR_PROBLEMS,
        'BA2' => self::CATEGORY_BEHAVIOUR_PROBLEMS,
        'BA1' => self::CATEGORY_BEHAVIOUR_PROBLEMS,
        'PD0' => self::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
        'PC0' => self::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
        'PB0' => self::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
        'PA2' => self::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
        'PA1' => self::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
    ];

    // Numeric ranks (aNR3H) - higher = more resource intensive
    public const GROUP_RANKS = [
        'SE3' => 23, 'SE2' => 22, 'SE1' => 21,
        'RB0' => 20, 'RA2' => 19, 'RA1' => 18,
        'SSB' => 17, 'SSA' => 16,
        'CC0' => 15, 'CB0' => 14, 'CA2' => 13, 'CA1' => 12,
        'IB0' => 11, 'IA2' => 10, 'IA1' => 9,
        'BB0' => 8, 'BA2' => 7, 'BA1' => 6,
        'PD0' => 5, 'PC0' => 4, 'PB0' => 3, 'PA2' => 2, 'PA1' => 1,
    ];

    // Short descriptions for each RUG group (from CC21_RUG_Bundle_Templates.md)
    public const GROUP_DESCRIPTIONS = [
        'RB0' => 'Special Rehabilitation, High ADL',
        'RA2' => 'Special Rehabilitation, Lower ADL, Higher IADL',
        'RA1' => 'Special Rehabilitation, Lower ADL, Lower IADL',
        'SE3' => 'Extensive Services, Highest Complexity',
        'SE2' => 'Extensive Services, Moderate Complexity',
        'SE1' => 'Extensive Services, Lower Complexity',
        'SSB' => 'Special Care, High ADL',
        'SSA' => 'Special Care, Lower ADL',
        'CC0' => 'Clinically Complex, High ADL',
        'CB0' => 'Clinically Complex, Moderate ADL',
        'CA2' => 'Clinically Complex, Low ADL, Higher IADL',
        'CA1' => 'Clinically Complex, Low ADL, Low IADL',
        'IB0' => 'Impaired Cognition, Moderate ADL',
        'IA2' => 'Impaired Cognition, Lower ADL, Higher IADL',
        'IA1' => 'Impaired Cognition, Lower ADL, Lower IADL',
        'BB0' => 'Behaviour Problems, Moderate ADL',
        'BA2' => 'Behaviour Problems, Lower ADL, Higher IADL',
        'BA1' => 'Behaviour Problems, Lower ADL, Lower IADL',
        'PD0' => 'Reduced Physical Function, High ADL',
        'PC0' => 'Reduced Physical Function, ADL 9-10',
        'PB0' => 'Reduced Physical Function, ADL 6-8',
        'PA2' => 'Reduced Physical Function, Low ADL, Higher IADL',
        'PA1' => 'Reduced Physical Function, Low ADL, Lower IADL',
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

    public function assessment()
    {
        return $this->belongsTo(InterraiAssessment::class, 'assessment_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for current (latest) classifications only.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope by RUG category.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('rug_category', $category);
    }

    /**
     * Scope by RUG group.
     */
    public function scopeByGroup(Builder $query, string $group): Builder
    {
        return $query->where('rug_group', $group);
    }

    /**
     * Get the latest classification for a patient.
     */
    public function scopeLatestForPatient(Builder $query, int $patientId): Builder
    {
        return $query->where('patient_id', $patientId)
            ->where('is_current', true)
            ->orderBy('created_at', 'desc');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get the bundle template code for this RUG group.
     */
    public function getBundleTemplateCodeAttribute(): string
    {
        return 'LTC_' . $this->rug_group . '_STANDARD';
    }

    /**
     * Get human-readable category description.
     */
    public function getCategoryDescriptionAttribute(): string
    {
        return match ($this->rug_category) {
            self::CATEGORY_SPECIAL_REHABILITATION => 'Intensive rehabilitation with therapy focus',
            self::CATEGORY_EXTENSIVE_SERVICES => 'Complex medical treatments (IV, ventilator, etc.)',
            self::CATEGORY_SPECIAL_CARE => 'High clinical complexity with physical dependency',
            self::CATEGORY_CLINICALLY_COMPLEX => 'Multiple clinical conditions requiring monitoring',
            self::CATEGORY_IMPAIRED_COGNITION => 'Cognitive impairment requiring structured support',
            self::CATEGORY_BEHAVIOUR_PROBLEMS => 'Behavioural symptoms requiring specialized care',
            self::CATEGORY_REDUCED_PHYSICAL_FUNCTION => 'Physical assistance needs without clinical complexity',
            default => 'Care needs assessment required',
        };
    }

    /**
     * Get short RUG group description (e.g., "Special Rehabilitation, Lower ADL, Higher IADL").
     */
    public function getRugDescriptionAttribute(): string
    {
        return self::GROUP_DESCRIPTIONS[$this->rug_group] ?? $this->rug_category ?? 'Unknown';
    }

    /**
     * Get full RUG label (code + description).
     * Example: "RA2 – Special Rehabilitation, Lower ADL, Higher IADL"
     */
    public function getRugLabelAttribute(): string
    {
        $description = $this->rug_description;
        return $this->rug_group . ' – ' . $description;
    }

    /**
     * Get ADL level description.
     */
    public function getAdlLevelAttribute(): string
    {
        return match (true) {
            $this->adl_sum >= 14 => 'Very High ADL Needs',
            $this->adl_sum >= 11 => 'High ADL Needs',
            $this->adl_sum >= 6 => 'Moderate ADL Needs',
            default => 'Lower ADL Needs',
        };
    }

    /**
     * Get CPS level description.
     */
    public function getCpsLevelAttribute(): string
    {
        return match ($this->cps_score) {
            0 => 'Intact',
            1 => 'Borderline Intact',
            2 => 'Mild Impairment',
            3 => 'Moderate Impairment',
            4 => 'Moderate-Severe Impairment',
            5 => 'Severe Impairment',
            6 => 'Very Severe Impairment',
            default => 'Unknown',
        };
    }

    /**
     * Check if this classification indicates high care needs.
     */
    public function isHighCareNeeds(): bool
    {
        return $this->numeric_rank >= 15; // CC0 and above
    }

    /**
     * Check if classification has a specific flag.
     */
    public function hasFlag(string $flag): bool
    {
        return ($this->flags[$flag] ?? false) === true;
    }

    /**
     * Get active flags as array of strings.
     */
    public function getActiveFlagsAttribute(): array
    {
        if (!$this->flags) {
            return [];
        }

        return collect($this->flags)
            ->filter(fn($value) => $value === true)
            ->keys()
            ->toArray();
    }

    /**
     * Get a summary array for API responses.
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'rug_group' => $this->rug_group,
            'rug_category' => $this->rug_category,
            'category_description' => $this->category_description,
            'adl_sum' => $this->adl_sum,
            'adl_level' => $this->adl_level,
            'iadl_sum' => $this->iadl_sum,
            'cps_score' => $this->cps_score,
            'cps_level' => $this->cps_level,
            'numeric_rank' => $this->numeric_rank,
            'is_high_care_needs' => $this->isHighCareNeeds(),
            'active_flags' => $this->active_flags,
            'bundle_template_code' => $this->bundle_template_code,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Mark this classification as no longer current.
     */
    public function markSuperseded(): self
    {
        $this->update(['is_current' => false]);
        return $this;
    }

    /**
     * Get the category for a given RUG group code.
     */
    public static function getCategoryForGroup(string $group): string
    {
        return self::GROUP_CATEGORIES[$group] ?? self::CATEGORY_REDUCED_PHYSICAL_FUNCTION;
    }

    /**
     * Get the numeric rank for a given RUG group code.
     */
    public static function getRankForGroup(string $group): int
    {
        return self::GROUP_RANKS[$group] ?? 1;
    }
}
