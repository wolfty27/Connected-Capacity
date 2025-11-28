<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * ServiceRoleMapping Model
 *
 * Metadata-driven mapping of which staff roles can deliver which services.
 * Supports the CC2.1 architecture requirement for metadata-controlled behavior.
 *
 * Example mappings:
 * - PSW → Personal Care, Homemaking, Safety Check, Respite, Social/Recreational
 * - RN/RPN → Nursing Visit, Wound Care, Palliative, Caregiver Coaching
 * - OT → In-home ADL rehab, Home Safety Assessment
 * - PT → Mobility rehab, Physiotherapy
 * - SW → Psychosocial support, Caregiver coaching
 *
 * @property int $id
 * @property int $staff_role_id
 * @property int $service_type_id
 * @property bool $is_primary
 * @property bool $requires_delegation
 * @property int $sort_order
 * @property bool $is_active
 */
class ServiceRoleMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_role_id',
        'service_type_id',
        'is_primary',
        'requires_delegation',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'requires_delegation' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * The staff role this mapping belongs to.
     */
    public function staffRole(): BelongsTo
    {
        return $this->belongsTo(StaffRole::class);
    }

    /**
     * The service type this mapping references.
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope: Active mappings only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Primary mappings (the main role for a service).
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope: Mappings for a specific staff role.
     */
    public function scopeForRole(Builder $query, int $roleId): Builder
    {
        return $query->where('staff_role_id', $roleId);
    }

    /**
     * Scope: Mappings for a specific staff role code.
     */
    public function scopeForRoleCode(Builder $query, string $roleCode): Builder
    {
        return $query->whereHas('staffRole', fn($q) => $q->where('code', $roleCode));
    }

    /**
     * Scope: Mappings for a specific service type.
     */
    public function scopeForServiceType(Builder $query, int $serviceTypeId): Builder
    {
        return $query->where('service_type_id', $serviceTypeId);
    }

    /**
     * Scope: Mappings for a specific service type code.
     */
    public function scopeForServiceCode(Builder $query, string $serviceCode): Builder
    {
        return $query->whereHas('serviceType', fn($q) => $q->where('code', $serviceCode));
    }

    /**
     * Scope: Ordered by sort order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    // ==========================================
    // Static Helpers
    // ==========================================

    /**
     * Get service type IDs that a role can deliver.
     */
    public static function getServiceTypesForRole(string $roleCode): array
    {
        return static::active()
            ->forRoleCode($roleCode)
            ->pluck('service_type_id')
            ->toArray();
    }

    /**
     * Get service type codes that a role can deliver.
     */
    public static function getServiceCodesForRole(string $roleCode): array
    {
        return static::active()
            ->forRoleCode($roleCode)
            ->with('serviceType')
            ->get()
            ->pluck('serviceType.code')
            ->filter()
            ->toArray();
    }

    /**
     * Get role IDs that can deliver a service type.
     */
    public static function getRolesForServiceType(string $serviceCode): array
    {
        return static::active()
            ->forServiceCode($serviceCode)
            ->pluck('staff_role_id')
            ->toArray();
    }

    /**
     * Get role codes that can deliver a service type.
     */
    public static function getRoleCodesForService(string $serviceCode): array
    {
        return static::active()
            ->forServiceCode($serviceCode)
            ->with('staffRole')
            ->get()
            ->pluck('staffRole.code')
            ->filter()
            ->toArray();
    }

    /**
     * Check if a role can deliver a service.
     */
    public static function canRoleDeliverService(string $roleCode, string $serviceCode): bool
    {
        return static::active()
            ->forRoleCode($roleCode)
            ->forServiceCode($serviceCode)
            ->exists();
    }

    /**
     * Get primary role for a service type.
     */
    public static function getPrimaryRoleForService(string $serviceCode): ?StaffRole
    {
        $mapping = static::active()
            ->primary()
            ->forServiceCode($serviceCode)
            ->with('staffRole')
            ->first();

        return $mapping?->staffRole;
    }
}
