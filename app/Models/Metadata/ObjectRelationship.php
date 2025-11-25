<?php

namespace App\Models\Metadata;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ObjectRelationship - Defines relationships between metadata object types
 *
 * Supports one-to-one, one-to-many, many-to-one, and many-to-many relationships,
 * with optional pivot table configuration for many-to-many relationships.
 */
class ObjectRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_object_id',
        'target_object_id',
        'name',
        'code',
        'relationship_type',
        'inverse_name',
        'pivot_table',
        'pivot_attributes',
        'is_required',
        'cascade_delete',
    ];

    protected $casts = [
        'pivot_attributes' => 'array',
        'is_required' => 'boolean',
        'cascade_delete' => 'boolean',
    ];

    /**
     * Relationship types supported by the metadata engine.
     */
    public const TYPES = [
        'one_to_one',
        'one_to_many',
        'many_to_one',
        'many_to_many',
    ];

    /**
     * Get the source object definition.
     */
    public function sourceObject()
    {
        return $this->belongsTo(ObjectDefinition::class, 'source_object_id');
    }

    /**
     * Get the target object definition.
     */
    public function targetObject()
    {
        return $this->belongsTo(ObjectDefinition::class, 'target_object_id');
    }

    /**
     * Check if this is a many-to-many relationship.
     */
    public function isManyToMany(): bool
    {
        return $this->relationship_type === 'many_to_many';
    }

    /**
     * Check if this is a "has many" style relationship.
     */
    public function isHasMany(): bool
    {
        return in_array($this->relationship_type, ['one_to_many', 'many_to_many']);
    }
}
