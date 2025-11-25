<?php

namespace App\Models\Metadata;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ObjectDefinition - Workday-style metadata object type definition
 *
 * Defines business object types (like "Patient", "CareBundle", "ServiceType")
 * with their configuration. The metadata engine uses these definitions to
 * interpret and manage objects at runtime.
 */
class ObjectDefinition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'display_name',
        'description',
        'category',
        'base_table',
        'is_active',
        'is_system',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'config' => 'array',
    ];

    /**
     * Get the attributes defined for this object type.
     */
    public function attributes()
    {
        return $this->hasMany(ObjectAttribute::class)->orderBy('sort_order');
    }

    /**
     * Get relationships where this object is the source.
     */
    public function sourceRelationships()
    {
        return $this->hasMany(ObjectRelationship::class, 'source_object_id');
    }

    /**
     * Get relationships where this object is the target.
     */
    public function targetRelationships()
    {
        return $this->hasMany(ObjectRelationship::class, 'target_object_id');
    }

    /**
     * Get all rules defined for this object type.
     */
    public function rules()
    {
        return $this->hasMany(ObjectRule::class)->orderBy('priority');
    }

    /**
     * Get all runtime instances of this object type.
     */
    public function instances()
    {
        return $this->hasMany(ObjectInstance::class);
    }

    /**
     * Find an object definition by its code.
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->where('is_active', true)->first();
    }

    /**
     * Get the Eloquent model class for this object definition.
     */
    public function getModelClass(): ?string
    {
        $config = $this->config ?? [];
        return $config['model_class'] ?? null;
    }

    /**
     * Resolve the actual Eloquent model instance.
     */
    public function resolveModel(int $entityId)
    {
        $modelClass = $this->getModelClass();
        if (!$modelClass || !class_exists($modelClass)) {
            return null;
        }

        return $modelClass::find($entityId);
    }
}
