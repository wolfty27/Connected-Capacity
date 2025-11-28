<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ServiceRate Model
 *
 * Represents billing rates per service type in the rate card system.
 * Supports Ontario-aligned SPO billing rates with organization-specific overrides.
 *
 * Rate hierarchy:
 * 1. Organization-specific active rate (if exists)
 * 2. System default rate (organization_id = null)
 *
 * @property int $id
 * @property int $service_type_id
 * @property int|null $organization_id
 * @property string $unit_type
 * @property int $rate_cents
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $notes
 * @property int|null $created_by
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class ServiceRate extends Model
{
    use HasFactory;

    /**
     * Valid unit types for service rates.
     */
    public const UNIT_HOUR = 'hour';
    public const UNIT_VISIT = 'visit';
    public const UNIT_MONTH = 'month';
    public const UNIT_TRIP = 'trip';
    public const UNIT_CALL = 'call';
    public const UNIT_SERVICE = 'service';
    public const UNIT_NIGHT = 'night';
    public const UNIT_BLOCK = 'block';

    public const VALID_UNIT_TYPES = [
        self::UNIT_HOUR,
        self::UNIT_VISIT,
        self::UNIT_MONTH,
        self::UNIT_TRIP,
        self::UNIT_CALL,
        self::UNIT_SERVICE,
        self::UNIT_NIGHT,
        self::UNIT_BLOCK,
    ];

    protected $fillable = [
        'service_type_id',
        'organization_id',
        'unit_type',
        'rate_cents',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'service_type_id' => 'integer',
        'organization_id' => 'integer',
        'rate_cents' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'created_by' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the service type this rate applies to.
     */
    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * Get the organization this rate belongs to (null = system default).
     */
    public function organization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'organization_id');
    }

    /**
     * Get the user who created this rate.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for system-wide default rates (no organization).
     */
    public function scopeSystemDefault(Builder $query): Builder
    {
        return $query->whereNull('organization_id');
    }

    /**
     * Scope for organization-specific rates.
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Scope for rates effective on a given date.
     */
    public function scopeEffectiveOn(Builder $query, Carbon $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    /**
     * Scope for currently active rates.
     */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->effectiveOn(Carbon::today());
    }

    /**
     * Scope for a specific service type.
     */
    public function scopeForServiceType(Builder $query, int $serviceTypeId): Builder
    {
        return $query->where('service_type_id', $serviceTypeId);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the rate in dollars.
     */
    public function getRateDollarsAttribute(): float
    {
        return $this->rate_cents / 100;
    }

    /**
     * Check if this is a system default rate.
     */
    public function getIsSystemDefaultAttribute(): bool
    {
        return $this->organization_id === null;
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
     * Get a human-readable unit label.
     */
    public function getUnitLabelAttribute(): string
    {
        return match ($this->unit_type) {
            self::UNIT_HOUR => 'per hour',
            self::UNIT_VISIT => 'per visit',
            self::UNIT_MONTH => 'per month',
            self::UNIT_TRIP => 'per trip',
            self::UNIT_CALL => 'per call',
            self::UNIT_SERVICE => 'per service',
            self::UNIT_NIGHT => 'per night',
            self::UNIT_BLOCK => 'per block',
            default => $this->unit_type,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Close this rate by setting effective_to to given date.
     */
    public function closeRate(Carbon $closingDate): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $this->effective_to = $closingDate;
        return $this->save();
    }

    /**
     * Calculate cost for a given quantity of units.
     */
    public function calculateCost(float $quantity): int
    {
        return (int) round($this->rate_cents * $quantity);
    }

    /**
     * Get array representation for API responses.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'service_type_id' => $this->service_type_id,
            'service_type_code' => $this->serviceType?->code,
            'service_type_name' => $this->serviceType?->name,
            'organization_id' => $this->organization_id,
            'organization_name' => $this->organization?->name,
            'is_system_default' => $this->is_system_default,
            'unit_type' => $this->unit_type,
            'unit_label' => $this->unit_label,
            'rate_cents' => $this->rate_cents,
            'rate_dollars' => $this->rate_dollars,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
