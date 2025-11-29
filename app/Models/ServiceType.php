<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'color',
        'default_duration_minutes',
        'scheduling_mode',
        'fixed_visits_per_plan',
        'fixed_visit_labels',
        'min_gap_between_visits_minutes',
        'is_active',
    ];

    protected $casts = [
        'fixed_visit_labels' => 'array',
        'is_active' => 'boolean',
    ];

    // Scheduling modes
    public const SCHEDULING_MODE_WEEKLY = 'weekly';
    public const SCHEDULING_MODE_FIXED_VISITS = 'fixed_visits';

    // Common service type codes
    public const CODE_PSW = 'PSW';    // Personal Support Worker
    public const CODE_PT = 'PT';       // Physiotherapy
    public const CODE_OT = 'OT';       // Occupational Therapy
    public const CODE_NUR = 'NUR';     // Nursing
    public const CODE_RPM = 'RPM';     // Remote Patient Monitoring
    public const CODE_SW = 'SW';       // Social Work
    public const CODE_MEAL = 'MEAL';   // Meal Service
    public const CODE_RESP = 'RESP';   // Respite Care

    // Categories
    public const CATEGORY_PERSONAL_CARE = 'personal_care';
    public const CATEGORY_THERAPY = 'therapy';
    public const CATEGORY_NURSING = 'nursing';
    public const CATEGORY_MONITORING = 'monitoring';
    public const CATEGORY_SUPPORT = 'support';

    public function serviceAssignments(): HasMany
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    public function careBundleServices(): HasMany
    {
        return $this->hasMany(CareBundleService::class);
    }

    /**
     * Check if this service type uses fixed visits (like RPM)
     */
    public function isFixedVisits(): bool
    {
        return $this->scheduling_mode === self::SCHEDULING_MODE_FIXED_VISITS;
    }

    /**
     * Check if this service type uses weekly scheduling (hours per week)
     */
    public function isWeeklyScheduled(): bool
    {
        return $this->scheduling_mode === self::SCHEDULING_MODE_WEEKLY;
    }

    /**
     * Get the label for a specific visit number (1-indexed)
     */
    public function getVisitLabel(int $visitNumber): ?string
    {
        $labels = $this->fixed_visit_labels ?? [];
        return $labels[$visitNumber - 1] ?? null;
    }

    /**
     * Check if this service type has a spacing rule
     */
    public function hasSpacingRule(): bool
    {
        return $this->min_gap_between_visits_minutes !== null
            && $this->min_gap_between_visits_minutes > 0;
    }

    /**
     * Scope: only active service types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: service types with spacing rules
     */
    public function scopeWithSpacingRules($query)
    {
        return $query->whereNotNull('min_gap_between_visits_minutes')
            ->where('min_gap_between_visits_minutes', '>', 0);
    }
}
