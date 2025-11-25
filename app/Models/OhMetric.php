<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OhMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_start',
        'period_end',
        'metric_key',
        'metric_value',
        'breakdown',
        'computed_at',
        'computed_by_job_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'metric_value' => 'float',
        'breakdown' => 'array',
        'computed_at' => 'datetime',
    ];
}
