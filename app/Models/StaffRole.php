<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staff Role Metadata Model
 *
 * Represents clinical/professional roles (RN, RPN, PSW, OT, PT, SLP, SW, etc.)
 * that align with AlayaCare's discipline concepts and Ontario Health atHome
 * service delivery requirements.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property string|null $description
 * @property array|null $service_type_codes
 * @property bool $is_regulated
 * @property string|null $regulatory_body
 * @property bool $requires_license
 * @property int|null $default_hourly_rate_cents
 * @property string|null $billing_code
 * @property bool $counts_for_fte
 * @property int $sort_order
 * @property bool $is_active
 */
class StaffRole extends Model
{
    use HasFactory;

    // Role category constants
    public const CATEGORY_NURSING = 'nursing';
    public const CATEGORY_ALLIED_HEALTH = 'allied_health';
    public const CATEGORY_PERSONAL_SUPPORT = 'personal_support';
    public const CATEGORY_ADMINISTRATIVE = 'administrative';
    public const CATEGORY_COMMUNITY_SUPPORT = 'community_support';

    // Common role codes (aligned with AlayaCare disciplines)
    public const CODE_RN = 'RN';      // Registered Nurse
    public const CODE_RPN = 'RPN';    // Registered Practical Nurse
    public const CODE_PSW = 'PSW';    // Personal Support Worker
    public const CODE_OT = 'OT';      // Occupational Therapist
    public const CODE_PT = 'PT';      // Physiotherapist
    public const CODE_SLP = 'SLP';    // Speech-Language Pathologist
    public const CODE_SW = 'SW';      // Social Worker
    public const CODE_RD = 'RD';      // Registered Dietitian
    public const CODE_RT = 'RT';      // Respiratory Therapist
    public const CODE_NP = 'NP';      // Nurse Practitioner
    public const CODE_COORD = 'COORD'; // Care Coordinator

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'service_type_codes',
        'is_regulated',
        'regulatory_body',
        'requires_license',
        'default_hourly_rate_cents',
        'billing_code',
        'counts_for_fte',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'service_type_codes' => 'array',
        'is_regulated' => 'boolean',
        'requires_license' => 'boolean',
        'default_hourly_rate_cents' => 'integer',
        'counts_for_fte' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Staff members with this role
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'staff_role_id');
    }

    /**
     * Service types this role can deliver
     */
    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ServiceType::class, 'staff_role_service_types')
            ->withTimestamps();
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope: Active roles only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Roles by category
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Nursing roles (RN, RPN, NP)
     */
    public function scopeNursing(Builder $query): Builder
    {
        return $query->where('category', self::CATEGORY_NURSING);
    }

    /**
     * Scope: Allied health roles (OT, PT, SLP, SW, RD, RT)
     */
    public function scopeAlliedHealth(Builder $query): Builder
    {
        return $query->where('category', self::CATEGORY_ALLIED_HEALTH);
    }

    /**
     * Scope: Personal support roles (PSW)
     */
    public function scopePersonalSupport(Builder $query): Builder
    {
        return $query->where('category', self::CATEGORY_PERSONAL_SUPPORT);
    }

    /**
     * Scope: Regulated professions only
     */
    public function scopeRegulated(Builder $query): Builder
    {
        return $query->where('is_regulated', true);
    }

    /**
     * Scope: Roles that count towards FTE
     */
    public function scopeCountsForFte(Builder $query): Builder
    {
        return $query->where('counts_for_fte', true);
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
     * Get default hourly rate in dollars
     */
    public function getDefaultHourlyRateDollarsAttribute(): ?float
    {
        return $this->default_hourly_rate_cents
            ? $this->default_hourly_rate_cents / 100
            : null;
    }

    /**
     * Get category display label
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            self::CATEGORY_NURSING => 'Nursing',
            self::CATEGORY_ALLIED_HEALTH => 'Allied Health',
            self::CATEGORY_PERSONAL_SUPPORT => 'Personal Support',
            self::CATEGORY_ADMINISTRATIVE => 'Administrative',
            self::CATEGORY_COMMUNITY_SUPPORT => 'Community Support',
            default => ucfirst($this->category ?? 'Other'),
        };
    }

    // ==========================================
    // Static Helpers
    // ==========================================

    /**
     * Find role by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper($code))->first();
    }

    /**
     * Get all active roles as options array
     */
    public static function getOptions(): array
    {
        return static::active()
            ->ordered()
            ->get()
            ->map(fn ($role) => [
                'value' => $role->id,
                'label' => $role->name,
                'code' => $role->code,
                'category' => $role->category,
            ])
            ->toArray();
    }

    /**
     * Check if this role can deliver a service type
     */
    public function canDeliverService(string $serviceTypeCode): bool
    {
        if (empty($this->service_type_codes)) {
            return false;
        }

        return in_array($serviceTypeCode, $this->service_type_codes, true);
    }
}
