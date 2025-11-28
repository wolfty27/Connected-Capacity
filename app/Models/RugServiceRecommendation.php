<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * RugServiceRecommendation Model
 *
 * Metadata-driven service recommendations based on RUG/interRAI criteria.
 * Used by RugServicePlanner to add clinically indicated services to care bundles.
 *
 * Examples:
 * - ADL ≥ 11 with IADL issues → Homemaking (2-3 h/wk)
 * - Behaviour Problems category → Behavioural Supports (several visits/week)
 * - Impaired Cognition with ADL issues → Social/Recreational (2-3 h/wk)
 *
 * @property int $id
 * @property string|null $rug_group
 * @property string|null $rug_category
 * @property int $service_type_id
 * @property int $min_frequency_per_week
 * @property int|null $max_frequency_per_week
 * @property int|null $default_duration_minutes
 * @property array|null $trigger_conditions
 * @property string|null $justification
 * @property string|null $clinical_notes
 * @property int $priority_weight
 * @property bool $is_required
 * @property bool $is_active
 */
class RugServiceRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'rug_group',
        'rug_category',
        'service_type_id',
        'min_frequency_per_week',
        'max_frequency_per_week',
        'default_duration_minutes',
        'trigger_conditions',
        'justification',
        'clinical_notes',
        'priority_weight',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'min_frequency_per_week' => 'integer',
        'max_frequency_per_week' => 'integer',
        'default_duration_minutes' => 'integer',
        'trigger_conditions' => 'array',
        'priority_weight' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * The service type this recommendation suggests.
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope: Active recommendations only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Required recommendations only.
     */
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope: Recommendations for a specific RUG group.
     */
    public function scopeForRugGroup(Builder $query, string $rugGroup): Builder
    {
        return $query->where(function ($q) use ($rugGroup) {
            $q->where('rug_group', $rugGroup)
              ->orWhereNull('rug_group'); // Include category-level rules
        });
    }

    /**
     * Scope: Recommendations for a specific RUG category.
     */
    public function scopeForRugCategory(Builder $query, string $rugCategory): Builder
    {
        return $query->where(function ($q) use ($rugCategory) {
            $q->where('rug_category', $rugCategory)
              ->orWhereNull('rug_category'); // Include global rules
        });
    }

    /**
     * Scope: Ordered by priority (highest first).
     */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority_weight');
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Check if this recommendation applies to a RUG classification.
     */
    public function appliesTo(RUGClassification $rug): bool
    {
        // Check RUG group match
        if ($this->rug_group && $this->rug_group !== $rug->rug_group) {
            return false;
        }

        // Check RUG category match
        if ($this->rug_category && $this->rug_category !== $rug->rug_category) {
            return false;
        }

        // Check trigger conditions
        if ($this->trigger_conditions) {
            return $this->evaluateTriggerConditions($rug);
        }

        return true;
    }

    /**
     * Evaluate trigger conditions against a RUG classification.
     */
    protected function evaluateTriggerConditions(RUGClassification $rug): bool
    {
        $conditions = $this->trigger_conditions;
        $flags = $rug->flags ?? [];

        // ADL minimum
        if (isset($conditions['adl_min']) && $rug->adl_sum < $conditions['adl_min']) {
            return false;
        }

        // ADL maximum
        if (isset($conditions['adl_max']) && $rug->adl_sum > $conditions['adl_max']) {
            return false;
        }

        // IADL minimum
        if (isset($conditions['iadl_min']) && $rug->iadl_sum < $conditions['iadl_min']) {
            return false;
        }

        // IADL maximum
        if (isset($conditions['iadl_max']) && $rug->iadl_sum > $conditions['iadl_max']) {
            return false;
        }

        // CPS minimum (cognition)
        if (isset($conditions['cps_min']) && $rug->cps_score < $conditions['cps_min']) {
            return false;
        }

        // Required flags (any must be present)
        if (isset($conditions['flags']) && is_array($conditions['flags'])) {
            $hasAnyFlag = false;
            foreach ($conditions['flags'] as $flag) {
                if ($flags[$flag] ?? false) {
                    $hasAnyFlag = true;
                    break;
                }
            }
            if (!$hasAnyFlag) {
                return false;
            }
        }

        // All required flags must be present
        if (isset($conditions['flags_all']) && is_array($conditions['flags_all'])) {
            foreach ($conditions['flags_all'] as $flag) {
                if (!($flags[$flag] ?? false)) {
                    return false;
                }
            }
        }

        // Excluded flags (none should be present)
        if (isset($conditions['flags_excluded']) && is_array($conditions['flags_excluded'])) {
            foreach ($conditions['flags_excluded'] as $flag) {
                if ($flags[$flag] ?? false) {
                    return false;
                }
            }
        }

        return true;
    }

    // ==========================================
    // Static Helpers
    // ==========================================

    /**
     * Get all recommendations that apply to a RUG classification.
     */
    public static function getForClassification(RUGClassification $rug): Collection
    {
        return static::active()
            ->forRugGroup($rug->rug_group)
            ->forRugCategory($rug->rug_category)
            ->byPriority()
            ->with('serviceType')
            ->get()
            ->filter(fn($rec) => $rec->appliesTo($rug));
    }

    /**
     * Get required recommendations for a RUG classification.
     */
    public static function getRequiredForClassification(RUGClassification $rug): Collection
    {
        return static::getForClassification($rug)->where('is_required', true);
    }
}
