<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RegionArea model for FSA (Forward Sortation Area) to region mapping.
 *
 * FSA is the first 3 characters of a Canadian postal code.
 * This model enables metadata-driven region assignment:
 * - Primary lookup: FSA prefix matching (e.g., "M5G" → Toronto Central)
 * - Secondary fallback: Lat/lng bounding box for edge cases
 *
 * IMPORTANT: All region assignment logic uses this metadata.
 * Never hardcode FSA → region mappings in business logic.
 *
 * @property int $id
 * @property int $region_id FK to regions table
 * @property string $fsa_prefix First 3 characters of postal code (e.g., M5G)
 * @property float|null $min_lat Minimum latitude for bounding box
 * @property float|null $max_lat Maximum latitude for bounding box
 * @property float|null $min_lng Minimum longitude for bounding box
 * @property float|null $max_lng Maximum longitude for bounding box
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class RegionArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_id',
        'fsa_prefix',
        'min_lat',
        'max_lat',
        'min_lng',
        'max_lng',
    ];

    protected $casts = [
        'region_id' => 'integer',
        'min_lat' => 'decimal:7',
        'max_lat' => 'decimal:7',
        'min_lng' => 'decimal:7',
        'max_lng' => 'decimal:7',
    ];

    /**
     * Get the region this area belongs to.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Find a region area by FSA prefix.
     *
     * @param string $fsa The FSA prefix (first 3 chars of postal code)
     * @return RegionArea|null
     */
    public static function findByFsa(string $fsa): ?self
    {
        return static::where('fsa_prefix', strtoupper($fsa))->first();
    }

    /**
     * Find a region area by lat/lng using bounding box.
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return RegionArea|null
     */
    public static function findByCoordinates(float $lat, float $lng): ?self
    {
        return static::query()
            ->whereNotNull('min_lat')
            ->whereNotNull('max_lat')
            ->whereNotNull('min_lng')
            ->whereNotNull('max_lng')
            ->where('min_lat', '<=', $lat)
            ->where('max_lat', '>=', $lat)
            ->where('min_lng', '<=', $lng)
            ->where('max_lng', '>=', $lng)
            ->first();
    }

    /**
     * Check if this area has a valid bounding box defined.
     */
    public function hasBoundingBox(): bool
    {
        return $this->min_lat !== null
            && $this->max_lat !== null
            && $this->min_lng !== null
            && $this->max_lng !== null;
    }
}
