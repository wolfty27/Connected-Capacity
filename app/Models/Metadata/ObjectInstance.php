<?php

namespace App\Models\Metadata;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ObjectInstance - Runtime storage for metadata-driven object data
 *
 * Links actual database records to their metadata definitions and stores
 * extended attributes and computed values that don't fit in the main table.
 */
class ObjectInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'object_definition_id',
        'entity_id',
        'metadata',
        'computed_values',
        'status',
        'last_computed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'computed_values' => 'array',
        'last_computed_at' => 'datetime',
    ];

    /**
     * Get the object definition for this instance.
     */
    public function objectDefinition()
    {
        return $this->belongsTo(ObjectDefinition::class);
    }

    /**
     * Get or create an instance for a given entity.
     */
    public static function forEntity(string $objectCode, int $entityId): self
    {
        $definition = ObjectDefinition::findByCode($objectCode);
        if (!$definition) {
            throw new \InvalidArgumentException("Unknown object code: {$objectCode}");
        }

        return static::firstOrCreate([
            'object_definition_id' => $definition->id,
            'entity_id' => $entityId,
        ], [
            'status' => 'active',
            'metadata' => [],
            'computed_values' => [],
        ]);
    }

    /**
     * Get a metadata value.
     */
    public function getMeta(string $key, $default = null)
    {
        return data_get($this->metadata ?? [], $key, $default);
    }

    /**
     * Set a metadata value.
     */
    public function setMeta(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Get a computed value.
     */
    public function getComputed(string $key, $default = null)
    {
        return data_get($this->computed_values ?? [], $key, $default);
    }

    /**
     * Set a computed value.
     */
    public function setComputed(string $key, $value): self
    {
        $computed = $this->computed_values ?? [];
        data_set($computed, $key, $value);
        $this->computed_values = $computed;
        $this->last_computed_at = now();
        return $this;
    }

    /**
     * Resolve the actual Eloquent model for this instance.
     */
    public function resolveModel()
    {
        return $this->objectDefinition?->resolveModel($this->entity_id);
    }
}
