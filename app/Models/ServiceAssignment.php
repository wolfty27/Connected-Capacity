<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'care_plan_id',
        'patient_id',
        'service_provider_organization_id',
        'service_type_id',
        'assigned_user_id',
        'status',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'frequency_rule',
        'notes',
        'source',
        'rpm_alert_id',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
    ];

    public function carePlan()
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function serviceProviderOrganization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class);
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function rpmAlert()
    {
        return $this->belongsTo(RpmAlert::class);
    }

    public function interdisciplinaryNotes()
    {
        return $this->hasMany(InterdisciplinaryNote::class);
    }
}
