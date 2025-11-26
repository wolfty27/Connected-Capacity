<?php

namespace App\Models\Metadata;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ObjectAttribute - Defines properties/fields of metadata object types
 *
 * Each attribute represents a field that can be stored on an object,
 * including its data type, validation rules, and UI configuration.
 */
class ObjectAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'object_definition_id',
        'name',
        'code',
        'display_name',
        'data_type',
        'is_required',
        'is_readonly',
        'is_searchable',
        'is_indexed',
        'default_value',
        'validation_rules',
        'options',
        'sort_order',
        'group',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_readonly' => 'boolean',
        'is_searchable' => 'boolean',
        'is_indexed' => 'boolean',
        'validation_rules' => 'array',
        'options' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Data types supported by the metadata engine.
     */
    public const DATA_TYPES = [
        'string',
        'text',
        'integer',
        'decimal',
        'boolean',
        'date',
        'datetime',
        'json',
        'reference',    // Foreign key to another object
        'enum',         // Fixed set of options
        'computed',     // Calculated at runtime
    ];

    /**
     * Get the object definition this attribute belongs to.
     */
    public function objectDefinition()
    {
        return $this->belongsTo(ObjectDefinition::class);
    }

    /**
     * Cast a value to this attribute's data type.
     */
    public function castValue($value)
    {
        return match ($this->data_type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => (bool) $value,
            'date' => $value ? date('Y-m-d', strtotime($value)) : null,
            'datetime' => $value ? date('Y-m-d H:i:s', strtotime($value)) : null,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }

    /**
     * Validate a value against this attribute's rules.
     */
    public function validate($value): array
    {
        $errors = [];

        if ($this->is_required && ($value === null || $value === '')) {
            $errors[] = "{$this->display_name} is required.";
        }

        if ($this->data_type === 'enum' && $value !== null) {
            $options = $this->options ?? [];
            if (!in_array($value, array_column($options, 'value'))) {
                $errors[] = "{$this->display_name} must be one of the allowed values.";
            }
        }

        return $errors;
    }
}
