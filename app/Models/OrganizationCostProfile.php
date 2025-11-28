<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * OrganizationCostProfile Model
 *
 * Represents cost profile settings for an organization, including:
 * - Overhead multipliers
 * - Travel costs (flat per visit + per km)
 * - Administrative overhead
 * - Supplies costs
 *
 * Used by BillingEngine to calculate true underlying costs.
 *
 * @property int $id
 * @property int $organization_id
 * @property float $overhead_multiplier
 * @property int $travel_flat_cents_per_visit
 * @property int $travel_cents_per_km
 * @property float $travel_average_distance_km
 * @property float $admin_overhead_percent
 * @property float $supplies_percent
 * @property bool $use_actual_travel
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class OrganizationCostProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'overhead_multiplier',
        'travel_flat_cents_per_visit',
        'travel_cents_per_km',
        'travel_average_distance_km',
        'admin_overhead_percent',
        'supplies_percent',
        'use_actual_travel',
        'effective_from',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'overhead_multiplier' => 'decimal:2',
        'travel_flat_cents_per_visit' => 'integer',
        'travel_cents_per_km' => 'integer',
        'travel_average_distance_km' => 'decimal:2',
        'admin_overhead_percent' => 'decimal:2',
        'supplies_percent' => 'decimal:2',
        'use_actual_travel' => 'boolean',
        'effective_from' => 'date',
        'created_by' => 'integer',
    ];

    /**
     * Default profile values for new organizations.
     */
    public const DEFAULTS = [
        'overhead_multiplier' => 1.40,
        'travel_flat_cents_per_visit' => 500, // $5.00
        'travel_cents_per_km' => 60, // $0.60/km
        'travel_average_distance_km' => 10.00,
        'admin_overhead_percent' => 15.00,
        'supplies_percent' => 5.00,
        'use_actual_travel' => false,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function organization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'organization_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get travel flat cost in dollars.
     */
    public function getTravelFlatDollarsAttribute(): float
    {
        return $this->travel_flat_cents_per_visit / 100;
    }

    /**
     * Get travel per km cost in dollars.
     */
    public function getTravelDollarsPerKmAttribute(): float
    {
        return $this->travel_cents_per_km / 100;
    }

    /**
     * Get total overhead percentage.
     */
    public function getTotalOverheadPercentAttribute(): float
    {
        return (($this->overhead_multiplier - 1) * 100)
            + $this->admin_overhead_percent
            + $this->supplies_percent;
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate travel cost for a visit.
     *
     * @param float|null $distanceKm Actual distance (null = use average)
     * @return int Travel cost in cents
     */
    public function calculateTravelCost(?float $distanceKm = null): int
    {
        $flatCost = $this->travel_flat_cents_per_visit;

        $distance = $this->use_actual_travel && $distanceKm !== null
            ? $distanceKm
            : $this->travel_average_distance_km;

        $variableCost = (int) round($distance * $this->travel_cents_per_km);

        return $flatCost + $variableCost;
    }

    /**
     * Apply overhead multiplier to a base cost.
     *
     * @param int $baseCostCents Base cost in cents
     * @return int Cost with overhead in cents
     */
    public function applyOverhead(int $baseCostCents): int
    {
        return (int) round($baseCostCents * $this->overhead_multiplier);
    }

    /**
     * Apply full cost markup (overhead + admin + supplies).
     *
     * @param int $baseCostCents Base cost in cents
     * @return int Fully loaded cost in cents
     */
    public function applyFullMarkup(int $baseCostCents): int
    {
        $totalMultiplier = $this->overhead_multiplier
            + ($this->admin_overhead_percent / 100)
            + ($this->supplies_percent / 100);

        return (int) round($baseCostCents * $totalMultiplier);
    }

    /**
     * Calculate total cost for a service visit.
     *
     * @param int $laborCostCents Base labor cost in cents
     * @param float|null $distanceKm Travel distance
     * @param bool $includeTravel Whether to include travel costs
     * @return int Total cost in cents
     */
    public function calculateTotalServiceCost(
        int $laborCostCents,
        ?float $distanceKm = null,
        bool $includeTravel = true
    ): int {
        $withOverhead = $this->applyOverhead($laborCostCents);

        if (!$includeTravel) {
            return $withOverhead;
        }

        return $withOverhead + $this->calculateTravelCost($distanceKm);
    }

    /**
     * Get or create a profile for an organization.
     */
    public static function getOrCreateForOrganization(int $organizationId): self
    {
        return self::firstOrCreate(
            ['organization_id' => $organizationId],
            self::DEFAULTS
        );
    }

    /**
     * Get array representation for API responses.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'organization_name' => $this->organization?->name,
            'overhead_multiplier' => $this->overhead_multiplier,
            'overhead_percent' => ($this->overhead_multiplier - 1) * 100,
            'travel_flat_cents_per_visit' => $this->travel_flat_cents_per_visit,
            'travel_flat_dollars' => $this->travel_flat_dollars,
            'travel_cents_per_km' => $this->travel_cents_per_km,
            'travel_dollars_per_km' => $this->travel_dollars_per_km,
            'travel_average_distance_km' => $this->travel_average_distance_km,
            'admin_overhead_percent' => $this->admin_overhead_percent,
            'supplies_percent' => $this->supplies_percent,
            'total_overhead_percent' => $this->total_overhead_percent,
            'use_actual_travel' => $this->use_actual_travel,
            'effective_from' => $this->effective_from?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
