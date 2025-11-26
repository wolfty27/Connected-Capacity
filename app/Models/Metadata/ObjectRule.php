<?php

namespace App\Models\Metadata;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ObjectRule - Business logic/rules applied to metadata objects at runtime
 *
 * Rules can define validation, calculations, state transitions, triggers,
 * constraints, and workflow logic. They are evaluated by the metadata engine
 * at appropriate trigger points.
 */
class ObjectRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'object_definition_id',
        'name',
        'code',
        'rule_type',
        'trigger_event',
        'conditions',
        'actions',
        'expression',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Rule types supported by the metadata engine.
     */
    public const RULE_TYPES = [
        'validation',
        'calculation',
        'transition',
        'trigger',
        'constraint',
        'workflow',
    ];

    /**
     * Events that can trigger rule evaluation.
     */
    public const TRIGGER_EVENTS = [
        'on_create',
        'on_update',
        'on_delete',
        'on_status_change',
        'on_relationship_change',
        'scheduled',
        'manual',
    ];

    /**
     * Get the object definition this rule belongs to.
     */
    public function objectDefinition()
    {
        return $this->belongsTo(ObjectDefinition::class);
    }

    /**
     * Check if this rule should be triggered for a given event.
     */
    public function shouldTrigger(string $event): bool
    {
        return $this->is_active && $this->trigger_event === $event;
    }

    /**
     * Evaluate the rule's conditions against provided data.
     */
    public function evaluateConditions(array $data): bool
    {
        $conditions = $this->conditions ?? [];
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? null;

            if (!$field) continue;

            $fieldValue = data_get($data, $field);

            $result = match ($operator) {
                'equals' => $fieldValue == $value,
                'not_equals' => $fieldValue != $value,
                'greater_than' => $fieldValue > $value,
                'less_than' => $fieldValue < $value,
                'in' => in_array($fieldValue, (array) $value),
                'not_in' => !in_array($fieldValue, (array) $value),
                'contains' => str_contains((string) $fieldValue, (string) $value),
                'is_empty' => empty($fieldValue),
                'is_not_empty' => !empty($fieldValue),
                default => true,
            };

            if (!$result) {
                return false;
            }
        }

        return true;
    }
}
