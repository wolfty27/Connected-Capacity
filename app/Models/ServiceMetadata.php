<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ServiceMetadata - Extended configuration for service types
 *
 * Provides flexible key-value storage for additional service configuration
 * that supports the metadata-driven architecture.
 */
class ServiceMetadata extends Model
{
    use HasFactory;

    protected $table = 'service_metadata';

    protected $fillable = [
        'service_type_id',
        'key',
        'value',
        'value_type',
        'is_configurable',
    ];

    protected $casts = [
        'is_configurable' => 'boolean',
    ];

    /**
     * Value types supported.
     */
    public const VALUE_TYPES = [
        'string',
        'integer',
        'boolean',
        'json',
        'decimal',
    ];

    /**
     * Get the service type this metadata belongs to.
     */
    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * Get the typed value.
     */
    public function getTypedValueAttribute()
    {
        return match ($this->value_type) {
            'integer' => (int) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'decimal' => (float) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Set the value with type conversion.
     */
    public function setTypedValue($value): self
    {
        $this->value = match ($this->value_type) {
            'json' => is_string($value) ? $value : json_encode($value),
            'boolean' => $value ? 'true' : 'false',
            default => (string) $value,
        };
        return $this;
    }

    /**
     * Get or create a metadata entry for a service type.
     */
    public static function getOrCreate(int $serviceTypeId, string $key, $defaultValue = null, string $valueType = 'string'): self
    {
        return static::firstOrCreate(
            [
                'service_type_id' => $serviceTypeId,
                'key' => $key,
            ],
            [
                'value' => (string) $defaultValue,
                'value_type' => $valueType,
                'is_configurable' => true,
            ]
        );
    }
}
