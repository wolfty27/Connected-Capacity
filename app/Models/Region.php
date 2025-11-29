<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Region model for geographic area management.
 *
 * Regions are used for:
 * - Patient geographic assignment (via RegionService)
 * - Staff service area matching
 * - Travel time optimization
 * - OHAH (Ontario Health at Home) integration
 *
 * IMPORTANT: Region assignment is ALWAYS metadata-driven via RegionArea lookups.
 * Never hardcode region names or codes in business logic.
 *
 * @property int $id
 * @property string $code Unique region code (e.g., TORONTO_CENTRAL)
 * @property string $name Human-readable region name
 * @property string|null $ohah_code OHAH region code for integration
 * @property bool $is_active Whether region is currently active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'ohah_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the region areas (FSA prefixes) that belong to this region.
     */
    public function areas(): HasMany
    {
        return $this->hasMany(RegionArea::class);
    }

    /**
     * Get all patients in this region.
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    /**
     * Scope to only active regions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find a region by its code.
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
