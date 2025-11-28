<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * CareBundleTemplate Model
 *
 * Represents a RUG-III/HC-specific care bundle template that defines
 * default service configurations. Templates are matched to patients
 * based on their RUG classification.
 *
 * There are 23 templates corresponding to the 23 RUG-III/HC groups:
 * - Special Rehabilitation: RB0, RA2, RA1
 * - Extensive Services: SE3, SE2, SE1
 * - Special Care: SSB, SSA
 * - Clinically Complex: CC0, CB0, CA2, CA1
 * - Impaired Cognition: IB0, IA2, IA1
 * - Behaviour Problems: BB0, BA2, BA1
 * - Reduced Physical Function: PD0, PC0, PB0, PA2, PA1
 *
 * @see docs/CC21_RUG_Bundle_Templates.md
 */
class CareBundleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'rug_group',
        'rug_category',
        'funding_stream',
        'min_adl_sum',
        'max_adl_sum',
        'min_iadl_sum',
        'max_iadl_sum',
        'required_flags',
        'excluded_flags',
        'weekly_cap_cents',
        'monthly_cap_cents',
        'base_cost_cents',
        'priority_weight',
        'auto_recommend',
        'is_active',
        'version',
        'is_current_version',
        'metadata',
        'clinical_notes',
    ];

    protected $casts = [
        'min_adl_sum' => 'integer',
        'max_adl_sum' => 'integer',
        'min_iadl_sum' => 'integer',
        'max_iadl_sum' => 'integer',
        'required_flags' => 'array',
        'excluded_flags' => 'array',
        'weekly_cap_cents' => 'integer',
        'monthly_cap_cents' => 'integer',
        'base_cost_cents' => 'integer',
        'priority_weight' => 'integer',
        'auto_recommend' => 'boolean',
        'is_active' => 'boolean',
        'version' => 'integer',
        'is_current_version' => 'boolean',
        'metadata' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the services included in this template.
     */
    public function services()
    {
        return $this->hasMany(CareBundleTemplateService::class);
    }

    /**
     * Get service types through the pivot.
     */
    public function serviceTypes()
    {
        return $this->belongsToMany(ServiceType::class, 'care_bundle_template_services')
            ->withPivot([
                'default_frequency_per_week',
                'default_duration_minutes',
                'default_duration_weeks',
                'cost_per_visit_cents',
                'is_required',
                'is_conditional',
                'condition_flags',
                'assignment_type',
                'role_required',
            ])
            ->withTimestamps();
    }

    /**
     * Get care bundles instantiated from this template.
     */
    public function careBundles()
    {
        return $this->hasMany(CareBundle::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for active templates.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('is_current_version', true);
    }

    /**
     * Scope by RUG group.
     */
    public function scopeForRugGroup(Builder $query, string $rugGroup): Builder
    {
        return $query->where('rug_group', $rugGroup);
    }

    /**
     * Scope by RUG category.
     */
    public function scopeForRugCategory(Builder $query, string $rugCategory): Builder
    {
        return $query->where('rug_category', $rugCategory);
    }

    /**
     * Scope by funding stream.
     */
    public function scopeForFundingStream(Builder $query, string $stream): Builder
    {
        return $query->where('funding_stream', $stream);
    }

    /**
     * Scope for templates matching ADL range.
     */
    public function scopeMatchingAdl(Builder $query, int $adlSum): Builder
    {
        return $query->where('min_adl_sum', '<=', $adlSum)
            ->where('max_adl_sum', '>=', $adlSum);
    }

    /**
     * Scope for templates matching IADL range.
     */
    public function scopeMatchingIadl(Builder $query, int $iadlSum): Builder
    {
        return $query->where('min_iadl_sum', '<=', $iadlSum)
            ->where('max_iadl_sum', '>=', $iadlSum);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if a set of flags matches this template's requirements.
     */
    public function matchesFlags(array $flags): bool
    {
        // Check required flags
        if ($this->required_flags) {
            foreach ($this->required_flags as $requiredFlag) {
                if (!($flags[$requiredFlag] ?? false)) {
                    return false;
                }
            }
        }

        // Check excluded flags
        if ($this->excluded_flags) {
            foreach ($this->excluded_flags as $excludedFlag) {
                if ($flags[$excludedFlag] ?? false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if template matches a RUG classification.
     */
    public function matchesClassification(RUGClassification $rug): bool
    {
        // Check RUG group match (if specified)
        if ($this->rug_group && $this->rug_group !== $rug->rug_group) {
            return false;
        }

        // Check RUG category match (if specified and no group match)
        if (!$this->rug_group && $this->rug_category && $this->rug_category !== $rug->rug_category) {
            return false;
        }

        // Check ADL range
        if ($rug->adl_sum < $this->min_adl_sum || $rug->adl_sum > $this->max_adl_sum) {
            return false;
        }

        // Check IADL range
        if ($rug->iadl_sum < $this->min_iadl_sum || $rug->iadl_sum > $this->max_iadl_sum) {
            return false;
        }

        // Check flags
        return $this->matchesFlags($rug->flags ?? []);
    }

    /**
     * Get the weekly cap in dollars.
     */
    public function getWeeklyCapAttribute(): float
    {
        return $this->weekly_cap_cents / 100;
    }

    /**
     * Get required services (always included).
     */
    public function getRequiredServices()
    {
        return $this->services()->where('is_required', true)->get();
    }

    /**
     * Get conditional services.
     */
    public function getConditionalServices()
    {
        return $this->services()->where('is_conditional', true)->get();
    }

    /**
     * Get services applicable for a given set of flags.
     */
    public function getServicesForFlags(array $flags): \Illuminate\Support\Collection
    {
        $services = $this->services()->with('serviceType')->get();

        return $services->filter(function ($service) use ($flags) {
            // Always include required services
            if ($service->is_required) {
                return true;
            }

            // Include conditional services if flags match
            if ($service->is_conditional && $service->condition_flags) {
                foreach ($service->condition_flags as $flag) {
                    if ($flags[$flag] ?? false) {
                        return true;
                    }
                }
                return false;
            }

            // Include non-conditional, non-required services by default
            return !$service->is_conditional;
        });
    }

    /**
     * Calculate estimated weekly cost for this template.
     */
    public function calculateEstimatedWeeklyCost(): int
    {
        return $this->services->sum(function ($service) {
            $costPerVisit = $service->cost_per_visit_cents
                ?? $service->serviceType?->default_cost_per_visit_cents
                ?? 10000; // $100 default

            return $service->default_frequency_per_week * $costPerVisit;
        });
    }

    /**
     * Get a summary array for API responses.
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'rug_group' => $this->rug_group,
            'rug_category' => $this->rug_category,
            'funding_stream' => $this->funding_stream,
            'weekly_cap' => $this->weekly_cap,
            'service_count' => $this->services()->count(),
            'estimated_weekly_cost' => $this->calculateEstimatedWeeklyCost() / 100,
            'is_active' => $this->is_active,
        ];
    }
}
