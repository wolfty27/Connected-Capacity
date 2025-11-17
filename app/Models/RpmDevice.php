<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RpmDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'service_provider_organization_id',
        'device_type',
        'manufacturer',
        'model',
        'serial_number',
        'assigned_at',
        'returned_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function organization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'service_provider_organization_id');
    }

    public function alerts()
    {
        return $this->hasMany(RpmAlert::class);
    }
}
