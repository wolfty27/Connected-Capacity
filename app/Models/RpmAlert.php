<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RpmAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'rpm_device_id',
        'service_assignment_id',
        'event_type',
        'severity',
        'payload',
        'triggered_at',
        'handled_by',
        'handled_at',
        'resolution_notes',
        'status',
        'source_reference',
    ];

    protected $casts = [
        'payload' => 'array',
        'triggered_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function device()
    {
        return $this->belongsTo(RpmDevice::class, 'rpm_device_id');
    }

    public function serviceAssignment()
    {
        return $this->belongsTo(ServiceAssignment::class);
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
