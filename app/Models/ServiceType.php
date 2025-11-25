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
        'category_id',
        'default_duration_minutes',
        'description',
        'cost_code',
        'cost_driver',
        'cost_per_visit',
        'source',
        'active',
    ];

    protected $casts = [
        'default_duration_minutes' => 'integer',
        'cost_per_visit' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function careBundles()
    {
        return $this->belongsToMany(CareBundle::class)
            ->withPivot(['default_frequency_per_week', 'default_provider_org_id', 'assignment_type', 'role_required'])
            ->withTimestamps();
    }

    public function serviceAssignments()
    {
        return $this->hasMany(ServiceAssignment::class);
    }

    /**
     * Get the metadata entries for this service type.
     */
    public function metadata()
    {
        return $this->hasMany(ServiceMetadata::class);
    }

    /**
     * Get a specific metadata value.
     */
    public function getMeta(string $key, $default = null)
    {
        $meta = $this->metadata()->where('key', $key)->first();
        return $meta ? $meta->typed_value : $default;
    }

    /**
     * Set a metadata value.
     */
    public function setMeta(string $key, $value, string $valueType = 'string'): ServiceMetadata
    {
        return ServiceMetadata::updateOrCreate(
            ['service_type_id' => $this->id, 'key' => $key],
            ['value' => (string) $value, 'value_type' => $valueType]
        );
    }
}
