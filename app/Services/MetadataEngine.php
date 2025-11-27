<?php

namespace App\Services;

use App\Models\Metadata\ObjectDefinition;
use App\Models\Metadata\ObjectInstance;
use App\Models\Metadata\ObjectRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MetadataEngine - Runtime engine for the Workday-style metadata object model
 *
 * This service consumes metadata definitions to:
 * - Interpret object models and relationships
 * - Enforce business rules
 * - Manage computed values
 * - Handle state transitions
 *
 * The engine provides the flexibility to modify business logic through
 * metadata changes without modifying code.
 */
class MetadataEngine
{
    /**
     * Cache for object definitions.
     */
    protected array $definitionCache = [];

    /**
     * Get an object definition by code.
     */
    /**
     * Get an object definition by code.
     */
    public function getDefinition(string $code): ?ObjectDefinition
    {
        if (!isset($this->definitionCache[$code])) {
            $this->definitionCache[$code] = \Illuminate\Support\Facades\Cache::remember(
                "metadata_definition_{$code}",
                now()->addMinutes(60),
                function () use ($code) {
                    return ObjectDefinition::with([
                        'attributes',
                        'sourceRelationships.targetObject',
                        'rules'
                    ])->where('code', $code)->where('is_active', true)->first();
                }
            );
        }

        return $this->definitionCache[$code];
    }

    /**
     * Get or create a metadata instance for an entity.
     */
    public function getInstance(string $objectCode, int $entityId): ObjectInstance
    {
        return ObjectInstance::forEntity($objectCode, $entityId);
    }

    /**
     * Get the underlying Eloquent model for an entity.
     */
    public function resolveEntity(string $objectCode, int $entityId)
    {
        $definition = $this->getDefinition($objectCode);
        return $definition?->resolveModel($entityId);
    }

    /**
     * Apply rules to an entity for a given event.
     *
     * @param string $objectCode The object type code
     * @param int $entityId The entity ID
     * @param string $event The trigger event
     * @param array $data Additional context data
     * @return array Results of rule applications
     */
    public function applyRules(string $objectCode, int $entityId, string $event, array $data = []): array
    {
        $definition = $this->getDefinition($objectCode);
        if (!$definition) {
            return ['status' => 'error', 'message' => "Unknown object: {$objectCode}"];
        }

        $results = [];
        $rules = $definition->rules()->where('is_active', true)->orderBy('priority')->get();

        foreach ($rules as $rule) {
            if (!$rule->shouldTrigger($event)) {
                continue;
            }

            // Get entity data for condition evaluation
            $entity = $this->resolveEntity($objectCode, $entityId);
            $entityData = $entity ? $entity->toArray() : [];
            $contextData = array_merge($entityData, $data);

            if (!$rule->evaluateConditions($contextData)) {
                continue;
            }

            try {
                $ruleResult = $this->executeRule($rule, $entity, $contextData);
                $results[] = [
                    'rule' => $rule->code,
                    'status' => 'success',
                    'result' => $ruleResult,
                ];
            } catch (\Exception $e) {
                Log::warning("Rule execution failed", [
                    'rule' => $rule->code,
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'rule' => $rule->code,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Execute a single rule.
     */
    protected function executeRule(ObjectRule $rule, $entity, array $context): mixed
    {
        $actions = $rule->actions ?? [];

        return match ($rule->rule_type) {
            'validation' => $this->executeValidationRule($actions, $context),
            'calculation' => $this->executeCalculationRule($rule, $entity, $context),
            'transition' => $this->executeTransitionRule($actions, $entity),
            'trigger' => $this->executeTriggerRule($actions, $context),
            default => null,
        };
    }

    /**
     * Execute a validation rule.
     */
    protected function executeValidationRule(array $actions, array $context): array
    {
        $errors = [];
        foreach ($actions as $action) {
            if ($action['type'] === 'validate_required') {
                $field = $action['field'];
                if (empty(data_get($context, $field))) {
                    $errors[] = $action['message'] ?? "{$field} is required";
                }
            }
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Execute a calculation rule.
     */
    protected function executeCalculationRule(ObjectRule $rule, $entity, array $context): mixed
    {
        $expression = $rule->expression;
        if (!$expression) {
            return null;
        }

        // Simple expression evaluation (can be extended with a proper expression parser)
        // For now, support basic operations like field references and arithmetic
        return $this->evaluateExpression($expression, $context);
    }

    /**
     * Execute a transition rule.
     */
    protected function executeTransitionRule(array $actions, $entity): bool
    {
        foreach ($actions as $action) {
            if ($action['type'] === 'set_status' && $entity && method_exists($entity, 'update')) {
                $entity->update(['status' => $action['value']]);
                return true;
            }
        }
        return false;
    }

    /**
     * Execute a trigger rule (dispatch events/jobs).
     */
    protected function executeTriggerRule(array $actions, array $context): array
    {
        $dispatched = [];
        foreach ($actions as $action) {
            if ($action['type'] === 'dispatch_event') {
                $eventClass = $action['event'];
                if (class_exists($eventClass)) {
                    event(new $eventClass($context));
                    $dispatched[] = $eventClass;
                }
            }
        }
        return ['dispatched' => $dispatched];
    }

    /**
     * Evaluate a simple expression.
     */
    protected function evaluateExpression(string $expression, array $context): mixed
    {
        // Replace field references with values
        // Format: ${field.path}
        $evaluated = preg_replace_callback(
            '/\$\{([^}]+)\}/',
            function ($matches) use ($context) {
                return data_get($context, $matches[1], 0);
            },
            $expression
        );

        // Only allow safe mathematical operations
        if (preg_match('/^[\d\s\+\-\*\/\.\(\)]+$/', $evaluated)) {
            try {
                return eval ("return {$evaluated};");
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $evaluated;
    }

    /**
     * Compute and cache derived values for an entity.
     */
    public function computeValues(string $objectCode, int $entityId): array
    {
        $instance = $this->getInstance($objectCode, $entityId);
        $definition = $this->getDefinition($objectCode);
        $entity = $this->resolveEntity($objectCode, $entityId);

        if (!$definition || !$entity) {
            return [];
        }

        $computed = [];
        $rules = $definition->rules()
            ->where('rule_type', 'calculation')
            ->where('is_active', true)
            ->get();

        $entityData = $entity->toArray();

        foreach ($rules as $rule) {
            if ($rule->evaluateConditions($entityData)) {
                $key = $rule->code;
                $value = $this->executeCalculationRule($rule, $entity, $entityData);
                $computed[$key] = $value;
                $instance->setComputed($key, $value);
            }
        }

        $instance->save();

        return $computed;
    }

    /**
     * Get relationships for an entity.
     */
    public function getRelationships(string $objectCode, int $entityId): array
    {
        $definition = $this->getDefinition($objectCode);
        if (!$definition) {
            return [];
        }

        $entity = $this->resolveEntity($objectCode, $entityId);
        if (!$entity) {
            return [];
        }

        $relationships = [];
        foreach ($definition->sourceRelationships as $rel) {
            $relationName = $rel->code;
            if (method_exists($entity, $relationName)) {
                $related = $entity->$relationName;
                $relationships[$relationName] = [
                    'type' => $rel->relationship_type,
                    'target' => $rel->targetObject->code,
                    'data' => $related,
                ];
            }
        }

        return $relationships;
    }

    /**
     * Clear the definition cache.
     */
    public function clearCache(): void
    {
        $this->definitionCache = [];
        // Note: We can't easily clear specific keys without knowing them, 
        // but we can rely on TTL or manual cache clearing commands.
        // For now, we just clear the runtime cache.
    }
}
