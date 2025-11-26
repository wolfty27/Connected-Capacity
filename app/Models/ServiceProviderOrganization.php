<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceProviderOrganization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'province',
        'postal_code',
        'regions',
        'capabilities',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'regions' => 'array',
        'capabilities' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'organization_id');
    }

    public function memberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function serviceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    public function rpmDevices()
    {
        return $this->hasMany(RpmDevice::class);
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
