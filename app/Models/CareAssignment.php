<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CareAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'assigned_user_id',
        'service_provider_organization_id',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function organization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'service_provider_organization_id');
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}