<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareBundleService extends Model
{
    use HasFactory;

    protected $fillable = [
        'care_bundle_template_id',
        'service_type_id',
        'hours_per_week',
        'visits_per_plan',
        'is_required',
    ];

    protected $casts = [
        'hours_per_week' => 'decimal:2',
        'is_required' => 'boolean',
    ];

    public function careBundleTemplate(): BelongsTo
    {
        return $this->belongsTo(CareBundleTemplate::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * Get the required units based on service type's scheduling mode
     */
    public function getRequiredUnits(): float
    {
        $serviceType = $this->serviceType;

        if ($serviceType->isFixedVisits()) {
            // For fixed visits, return visits_per_plan (or fall back to service type default)
            return $this->visits_per_plan ?? $serviceType->fixed_visits_per_plan ?? 0;
        }

        // For weekly scheduling, return hours per week
        return $this->hours_per_week ?? 0;
    }

    /**
     * Get the unit type for display
     */
    public function getUnitType(): string
    {
        return $this->serviceType->isFixedVisits() ? 'visits' : 'hours';
    }
}
