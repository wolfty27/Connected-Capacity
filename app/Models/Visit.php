<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'care_assignment_id',
        'patient_id',
        'scheduled_at',
        'actual_start_at',
        'actual_end_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'actual_start_at' => 'datetime',
        'actual_end_at' => 'datetime',
    ];

    public function careAssignment()
    {
        return $this->belongsTo(CareAssignment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}