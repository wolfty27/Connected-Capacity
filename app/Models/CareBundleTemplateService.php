<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * CareBundleTemplateService Model
 *
 * Pivot model linking care bundle templates to service types.
 * Defines the default configuration for each service in a template.
 *
 * @see docs/CC21_RUG_Bundle_Templates.md
 */
class CareBundleTemplateService extends Model
{
    use HasFactory;

    protected $fillable = [
        'care_bundle_template_id',
        'service_type_id',
        'default_frequency_per_week',
        'default_duration_minutes',
        'default_duration_weeks',
        'cost_per_visit_cents',
        'is_required',
        'is_conditional',
        'condition_flags',
        'assignment_type',
        'role_required',
    ];

    protected $casts = [
        'default_frequency_per_week' => 'integer',
        'default_duration_minutes' => 'integer',
        'default_duration_weeks' => 'integer',
        'cost_per_visit_cents' => 'integer',
        'is_required' => 'boolean',
        'is_conditional' => 'boolean',
        'condition_flags' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the template this service belongs to.
     */
    public function template()
    {
        return $this->belongsTo(CareBundleTemplate::class, 'care_bundle_template_id');
    }

    /**
     * Get the service type.
     */
    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this service should be included based on flags.
     */
    public function shouldIncludeForFlags(array $flags): bool
    {
        // Always include required services
        if ($this->is_required) {
            return true;
        }

        // Check conditional flags
        if ($this->is_conditional && $this->condition_flags) {
            foreach ($this->condition_flags as $flag) {
                if ($flags[$flag] ?? false) {
                    return true;
                }
            }
            return false;
        }

        // Non-conditional services are included by default
        return !$this->is_conditional;
    }

    /**
     * Get the effective cost per visit.
     */
    public function getEffectiveCostPerVisit(): int
    {
        return $this->cost_per_visit_cents
            ?? $this->serviceType?->default_cost_per_visit_cents
            ?? 10000; // $100 default
    }

    /**
     * Calculate weekly cost for this service.
     */
    public function calculateWeeklyCost(): int
    {
        return $this->default_frequency_per_week * $this->getEffectiveCostPerVisit();
    }

    /**
     * Get a summary array for API responses.
     */
    public function toSummaryArray(): array
    {
        return [
            'service_type_id' => $this->service_type_id,
            'service_code' => $this->serviceType?->code,
            'service_name' => $this->serviceType?->name,
            'frequency_per_week' => $this->default_frequency_per_week,
            'duration_minutes' => $this->default_duration_minutes,
            'duration_weeks' => $this->default_duration_weeks,
            'cost_per_visit' => $this->getEffectiveCostPerVisit() / 100,
            'weekly_cost' => $this->calculateWeeklyCost() / 100,
            'is_required' => $this->is_required,
            'is_conditional' => $this->is_conditional,
            'condition_flags' => $this->condition_flags,
            'assignment_type' => $this->assignment_type,
            'role_required' => $this->role_required,
        ];
    }
}
