<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ServiceProviderOrganization extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Organization type constants.
     */
    public const TYPE_SE_HEALTH = 'se_health';
    public const TYPE_PARTNER = 'partner';
    public const TYPE_SSPO = 'sspo';
    public const TYPE_EXTERNAL = 'external';

    /**
     * Organization status constants.
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'website_url',
        'logo_url',
        'cover_photo_url',
        'description',
        'tagline',
        'notes',
        'status',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'province',
        'postal_code',
        'region_code',
        'regions',
        'capabilities',
        'capacity_metadata',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'regions' => 'array',
        'capabilities' => 'array',
        'capacity_metadata' => 'array',
    ];

    /**
     * Boot method to auto-generate slug.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    /**
     * Check if this is an SSPO organization.
     */
    public function isSspo(): bool
    {
        return $this->type === self::TYPE_SSPO || $this->type === self::TYPE_PARTNER;
    }

    /**
     * Check if this organization is active.
     */
    public function isActive(): bool
    {
        return $this->active && $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Get the initials for avatar display.
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        return $initials;
    }

    /**
     * Get a short description/tagline.
     */
    public function getShortDescriptionAttribute(): ?string
    {
        if ($this->tagline) {
            return $this->tagline;
        }

        if ($this->description) {
            return Str::limit($this->description, 150);
        }

        return null;
    }

    /**
     * Get all users associated with this organization.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'organization_id');
    }

    /**
     * Get organization memberships.
     */
    public function memberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * Get service assignments for this organization.
     */
    public function serviceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    /**
     * Get RPM devices associated with this organization.
     */
    public function rpmDevices()
    {
        return $this->hasMany(RpmDevice::class);
    }

    /**
     * Get audit logs for this organization.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Get service types offered by this organization.
     */
    public function serviceTypes()
    {
        return $this->belongsToMany(ServiceType::class, 'organization_service_types')
            ->withPivot(['is_primary', 'metadata'])
            ->withTimestamps();
    }

    /**
     * Get primary service types.
     */
    public function primaryServiceTypes()
    {
        return $this->serviceTypes()->wherePivot('is_primary', true);
    }

    /**
     * Scope to get only SSPOs.
     */
    public function scopeSspo($query)
    {
        return $query->whereIn('type', [self::TYPE_SSPO, self::TYPE_PARTNER]);
    }

    /**
     * Scope to get only active organizations.
     */
    public function scopeActiveOnly($query)
    {
        return $query->where('active', true)->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to filter by region.
     */
    public function scopeInRegion($query, string $regionCode)
    {
        return $query->where('region_code', $regionCode);
    }

    /**
     * Scope to filter by service type.
     */
    public function scopeOfferingService($query, int $serviceTypeId)
    {
        return $query->whereHas('serviceTypes', function ($q) use ($serviceTypeId) {
            $q->where('service_types.id', $serviceTypeId);
        });
    }

    /**
     * Scope to search by name or description.
     */
    public function scopeSearch($query, string $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'ILIKE', "%{$searchTerm}%")
              ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
              ->orWhere('tagline', 'ILIKE', "%{$searchTerm}%");
        });
    }
}
