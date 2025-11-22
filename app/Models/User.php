<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_HOSPITAL = 'hospital';
    public const ROLE_RETIREMENT_HOME = 'retirement-home';
    public const ROLE_SPO_ADMIN = 'SPO_ADMIN';
    public const ROLE_FIELD_STAFF = 'FIELD_STAFF';
    public const ROLE_PATIENT = 'patient';
    public const ROLE_SPO_COORDINATOR = 'SPO_COORDINATOR';
    public const ROLE_SSPO_ADMIN = 'SSPO_ADMIN';
    public const ROLE_SSPO_COORDINATOR = 'SSPO_COORDINATOR';
    public const ROLE_ORG_ADMIN = 'ORG_ADMIN';
    public const ROLE_MASTER = 'MASTER';

    public function isMaster(): bool
    {
        return $this->role === self::ROLE_MASTER;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone_number',
        'country',
        'image',
        'address',
        'city',
        'state',
        'timezone',
        'zipcode',
        'latitude',
        'longitude',
        'calendly_status',
        'calendly_username',
        'organization_id',
        'organization_role'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function hospitals()
    {
        return $this->hasOne(Hospital::class,'user_id');
    }

    public function organization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'organization_id');
    }

    public function memberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function coordinatedPatients()
    {
        return $this->hasMany(Patient::class, 'primary_coordinator_id');
    }

    public function assignedServiceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class, 'assigned_user_id');
    }

    public function handledRpmAlerts()
    {
        return $this->hasMany(RpmAlert::class, 'handled_by');
    }
}
