<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TriageResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'received_at',
        'triaged_at',
        'acuity_level',
        'dementia_flag',
        'mh_flag',
        'rpm_required',
        'fall_risk',
        'behavioural_risk',
        'notes',
        'raw_referral_payload',
        'triaged_by',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'triaged_at' => 'datetime',
        'dementia_flag' => 'boolean',
        'mh_flag' => 'boolean',
        'rpm_required' => 'boolean',
        'fall_risk' => 'boolean',
        'behavioural_risk' => 'boolean',
        'raw_referral_payload' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function triagedBy()
    {
        return $this->belongsTo(User::class, 'triaged_by');
    }
}
