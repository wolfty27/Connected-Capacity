<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Employment Type Metadata Model
 *
 * Represents employment categories (Full-Time, Part-Time, Casual, SSPO-Contract)
 * that support the Ontario Health atHome 80% FTE compliance requirement.
 *
 * Per Q&A:
 * - FTE ratio = [Number of active full-time direct staff ÷ Number of active direct staff] × 100%
 * - Full-time aligns with Ontario's Employment Standards Act (typically 40h/week)
 * - SSPO staff do NOT count in FTE ratio (either numerator or denominator)
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property float $standard_hours_per_week
 * @property float|null $min_hours_per_week
 * @property float|null $max_hours_per_week
 * @property bool $is_direct_staff
 * @property bool $is_full_time
 * @property bool $counts_for_capacity
 * @property bool $benefits_eligible
 * @property float|null $fte_equivalent
 * @property string|null $badge_color
 * @property int $sort_order
 * @property bool $is_active
 */
class EmploymentType extends Model
{
    use HasFactory;

    // Employment type codes
    public const CODE_FULL_TIME = 'FT';
    public const CODE_PART_TIME = 'PT';
    public const CODE_CASUAL = 'CASUAL';
    public const CODE_SSPO = 'SSPO';

    // Badge colors for UI
    public const BADGE_GREEN = 'green';     // Full-time
    public const BADGE_BLUE = 'blue';       // Part-time
    public const BADGE_ORANGE = 'orange';   // Casual
    public const BADGE_PURPLE = 'purple';   // SSPO

    protected $fillable = [
        'code',
        'name',
        'description',
        'standard_hours_per_week',
        'min_hours_per_week',
        'max_hours_per_week',
        'is_direct_staff',
        'is_full_time',
        'counts_for_capacity',
        'benefits_eligible',
        'fte_equivalent',
        'badge_color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'standard_hours_per_week' => 'decimal:2',
        'min_hours_per_week' => 'decimal:2',
        'max_hours_per_week' => 'decimal:2',
        'is_direct_staff' => 'boolean',
        'is_full_time' => 'boolean',
        'counts_for_capacity' => 'boolean',
        'benefits_eligible' => 'boolean',
        'fte_equivalent' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Staff members with this employment type
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'employment_type_id');
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope: Active employment types only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Direct staff types only (for FTE denominator)
     * Excludes SSPO/subcontracted staff per Q&A requirement
     */
    public function scopeDirectStaff(Builder $query): Builder
    {
        return $query->where('is_direct_staff', true);
    }

    /**
     * Scope: Full-time types only (for FTE numerator)
     */
    public function scopeFullTime(Builder $query): Builder
    {
        return $query->where('is_full_time', true);
    }

    /**
     * Scope: Part-time types (PT + Casual)
     */
    public function scopePartTime(Builder $query): Builder
    {
        return $query->where('is_direct_staff', true)
            ->where('is_full_time', false);
    }

    /**
     * Scope: SSPO/subcontracted types only
     */
    public function scopeSspo(Builder $query): Builder
    {
        return $query->where('is_direct_staff', false);
    }

    /**
     * Scope: Types that count for capacity
     */
    public function scopeCountsForCapacity(Builder $query): Builder
    {
        return $query->where('counts_for_capacity', true);
    }

    /**
     * Scope: Order by sort order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ==========================================
    // Accessors
    // ==========================================

    /**
     * Get badge CSS class
     */
    public function getBadgeClassAttribute(): string
    {
        return match ($this->badge_color) {
            self::BADGE_GREEN => 'bg-green-100 text-green-800',
            self::BADGE_BLUE => 'bg-blue-100 text-blue-800',
            self::BADGE_ORANGE => 'bg-orange-100 text-orange-800',
            self::BADGE_PURPLE => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Check if this is SSPO employment type
     */
    public function getIsSspoAttribute(): bool
    {
        return $this->code === self::CODE_SSPO || !$this->is_direct_staff;
    }

    // ==========================================
    // Static Helpers
    // ==========================================

    /**
     * Find employment type by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper($code))->first();
    }

    /**
     * Get Full-Time employment type
     */
    public static function fullTime(): ?self
    {
        return static::findByCode(self::CODE_FULL_TIME);
    }

    /**
     * Get Part-Time employment type
     */
    public static function partTime(): ?self
    {
        return static::findByCode(self::CODE_PART_TIME);
    }

    /**
     * Get Casual employment type
     */
    public static function casual(): ?self
    {
        return static::findByCode(self::CODE_CASUAL);
    }

    /**
     * Get SSPO employment type
     */
    public static function sspo(): ?self
    {
        return static::findByCode(self::CODE_SSPO);
    }

    /**
     * Get all active employment types as options array
     */
    public static function getOptions(): array
    {
        return static::active()
            ->ordered()
            ->get()
            ->map(fn ($type) => [
                'value' => $type->id,
                'label' => $type->name,
                'code' => $type->code,
                'is_direct_staff' => $type->is_direct_staff,
                'is_full_time' => $type->is_full_time,
                'badge_color' => $type->badge_color,
            ])
            ->toArray();
    }

    /**
     * Get IDs for direct staff employment types
     */
    public static function getDirectStaffIds(): array
    {
        return static::active()
            ->directStaff()
            ->pluck('id')
            ->toArray();
    }

    /**
     * Get IDs for full-time employment types
     */
    public static function getFullTimeIds(): array
    {
        return static::active()
            ->fullTime()
            ->pluck('id')
            ->toArray();
    }
}
