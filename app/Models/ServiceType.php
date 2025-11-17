<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'category',
        'default_duration_minutes',
        'description',
        'active',
    ];

    protected $casts = [
        'default_duration_minutes' => 'integer',
        'active' => 'boolean',
    ];

    public function careBundles()
    {
        return $this->belongsToMany(CareBundle::class)
            ->withPivot(['default_frequency_per_week', 'default_provider_org_id'])
            ->withTimestamps();
    }

    public function serviceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class);
    }
}
