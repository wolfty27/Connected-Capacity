<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CareBundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'price',
        'default_notes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function serviceTypes()
    {
        return $this->belongsToMany(ServiceType::class)
            ->withPivot(['default_frequency_per_week', 'default_provider_org_id'])
            ->withTimestamps();
    }

    public function carePlans()
    {
        return $this->hasMany(CarePlan::class);
    }
}
