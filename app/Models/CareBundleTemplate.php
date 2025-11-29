<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareBundleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rug_group',
        'category',
        'description',
        'weekly_cost_cents',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // RUG Categories (CIHI RUG-III/HC hierarchy)
    public const CATEGORY_REHAB = 'Rehab';
    public const CATEGORY_EXTENSIVE = 'Extensive';
    public const CATEGORY_SPECIAL_CARE = 'Special Care';
    public const CATEGORY_CLINICALLY_COMPLEX = 'Clinically Complex';
    public const CATEGORY_IMPAIRED_COGNITION = 'Impaired Cognition';
    public const CATEGORY_BEHAVIOUR = 'Behaviour';
    public const CATEGORY_PHYSICAL = 'Physical';

    public function services(): HasMany
    {
        return $this->hasMany(CareBundleService::class);
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }

    /**
     * Get the weekly cost formatted in dollars
     */
    public function getWeeklyCostAttribute(): float
    {
        return ($this->weekly_cost_cents ?? 0) / 100;
    }

    /**
     * Get all required services for this bundle
     */
    public function getRequiredServices()
    {
        return $this->services()->where('is_required', true)->with('serviceType')->get();
    }

    /**
     * Scope: only active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: templates for a specific RUG group
     */
    public function scopeForRug($query, string $rugGroup)
    {
        return $query->where('rug_group', $rugGroup);
    }
}
