<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransitionNeedsProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'clinical_flags',
        'narrative_summary',
        'status',
        'bundle_recommendation_id',
        'ai_summary_status',
        'ai_summary_text',
    ];

    protected $casts = [
        'clinical_flags' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}