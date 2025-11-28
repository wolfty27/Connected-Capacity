<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * StaffWageRate Model
 *
 * Represents actual wage rates for staff members or roles within an organization.
 * Used to calculate true underlying costs (wages + benefits) vs billing rates.
 *
 * Rate resolution:
 * 1. Staff-specific rate (if user_id set)
 * 2. Role-based rate for organization (user_id null, role set)
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $organization_id
 * @property string|null $role
 * @property int|null $service_type_id
 * @property int $wage_cents_per_hour
 * @property float $benefits_multiplier
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class StaffWageRate extends Model
{
    use HasFactory;

    /**
     * Common role codes for wage rates.
     */
    public const ROLE_PSW = 'PSW';
    public const ROLE_RN = 'RN';
    public const ROLE_RPN = 'RPN';
    public const ROLE_PT = 'PT';
    public const ROLE_OT = 'OT';
    public const ROLE_SLP = 'SLP';
    public const ROLE_SW = 'SW';
    public const ROLE_RD = 'RD';
    public const ROLE_RT = 'RT';
    public const ROLE_NP = 'NP';

    protected $fillable = [
        'user_id',
        'organization_id',
        'role',
        'service_type_id',
        'wage_cents_per_hour',
        'benefits_multiplier',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'organization_id' => 'integer',
        'service_type_id' => 'integer',
        'wage_cents_per_hour' => 'integer',
        'benefits_multiplier' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'created_by' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'organization_id');
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeRoleBased(Builder $query): Builder
    {
        return $query->whereNull('user_id')->whereNotNull('role');
    }

    public function scopeEffectiveOn(Builder $query, Carbon $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->effectiveOn(Carbon::today());
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the hourly wage in dollars.
     */
    public function getWageDollarsPerHourAttribute(): float
    {
        return $this->wage_cents_per_hour / 100;
    }

    /**
     * Get the fully-loaded hourly cost (wage * benefits multiplier).
     */
    public function getFullyLoadedCentsPerHourAttribute(): int
    {
        return (int) round($this->wage_cents_per_hour * $this->benefits_multiplier);
    }

    /**
     * Get the fully-loaded hourly cost in dollars.
     */
    public function getFullyLoadedDollarsPerHourAttribute(): float
    {
        return $this->fully_loaded_cents_per_hour / 100;
    }

    /**
     * Check if this rate is currently active.
     */
    public function getIsActiveAttribute(): bool
    {
        $today = Carbon::today();

        if ($this->effective_from > $today) {
            return false;
        }

        if ($this->effective_to !== null && $this->effective_to < $today) {
            return false;
        }

        return true;
    }

    /**
     * Check if this is a staff-specific rate.
     */
    public function getIsStaffSpecificAttribute(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Check if this is a role-based rate.
     */
    public function getIsRoleBasedAttribute(): bool
    {
        return $this->user_id === null && $this->role !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate cost for a given number of hours.
     *
     * @param float $hours Number of hours worked
     * @param bool $includesBenefits Whether to apply benefits multiplier
     * @return int Cost in cents
     */
    public function calculateCost(float $hours, bool $includesBenefits = true): int
    {
        $baseCost = $this->wage_cents_per_hour * $hours;

        if ($includesBenefits) {
            return (int) round($baseCost * $this->benefits_multiplier);
        }

        return (int) round($baseCost);
    }

    /**
     * Calculate cost for minutes worked.
     */
    public function calculateCostForMinutes(int $minutes, bool $includesBenefits = true): int
    {
        return $this->calculateCost($minutes / 60, $includesBenefits);
    }

    /**
     * Get array representation for API responses.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'organization_id' => $this->organization_id,
            'organization_name' => $this->organization?->name,
            'role' => $this->role,
            'service_type_id' => $this->service_type_id,
            'service_type_code' => $this->serviceType?->code,
            'wage_cents_per_hour' => $this->wage_cents_per_hour,
            'wage_dollars_per_hour' => $this->wage_dollars_per_hour,
            'benefits_multiplier' => $this->benefits_multiplier,
            'fully_loaded_cents_per_hour' => $this->fully_loaded_cents_per_hour,
            'fully_loaded_dollars_per_hour' => $this->fully_loaded_dollars_per_hour,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'is_staff_specific' => $this->is_staff_specific,
            'is_role_based' => $this->is_role_based,
            'notes' => $this->notes,
        ];
    }
}
