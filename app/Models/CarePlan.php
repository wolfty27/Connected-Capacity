<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarePlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'care_bundle_id',
        'version',
        'status',
        'goals',
        'risks',
        'interventions',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'version' => 'integer',
        'goals' => 'array',
        'risks' => 'array',
        'interventions' => 'array',
        'approved_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function careBundle()
    {
        return $this->belongsTo(CareBundle::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function serviceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    public function interdisciplinaryNotes()
    {
        return $this->hasManyThrough(
            InterdisciplinaryNote::class,
            ServiceAssignment::class,
            'care_plan_id',
            'service_assignment_id'
        );
    }
}
