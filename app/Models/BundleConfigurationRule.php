<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * BundleConfigurationRule - Metadata-driven rules for care bundle configuration
 *
 * Implements Workday-style business rules that automatically configure
 * care bundles based on patient characteristics, TNP flags, and other
 * contextual data.
 *
 * Rule Types:
 * - inclusion: Automatically include a service
 * - exclusion: Exclude a service from the bundle
 * - frequency_adjustment: Modify service frequency
 * - duration_adjustment: Modify service duration
 * - provider_assignment: Auto-assign to specific provider
 * - cost_modifier: Apply cost adjustments
 */
class BundleConfigurationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'care_bundle_id',
        'rule_name',
        'rule_type',
        'conditions',
        'actions',
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
     * Supported rule types.
     */
    public const RULE_TYPES = [
        'inclusion',
        'exclusion',
        'frequency_adjustment',
        'duration_adjustment',
        'provider_assignment',
        'cost_modifier',
    ];

    /**
     * Get the care bundle this rule belongs to.
     */
    public function careBundle()
    {
        return $this->belongsTo(CareBundle::class);
    }

    /**
     * Evaluate the rule's conditions against patient/TNP data.
     *
     * @param array $context Contains 'patient', 'tnp', 'clinical_flags', etc.
     * @return bool True if conditions are met
     */
    public function evaluateConditions(array $context): bool
    {
        $conditions = $this->conditions ?? [];
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateSingleCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateSingleCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return true;
        }

        $fieldValue = data_get($context, $field);

        return match ($operator) {
            'equals' => $fieldValue == $value,
            'not_equals' => $fieldValue != $value,
            'greater_than' => is_numeric($fieldValue) && $fieldValue > $value,
            'less_than' => is_numeric($fieldValue) && $fieldValue < $value,
            'greater_than_or_equals' => is_numeric($fieldValue) && $fieldValue >= $value,
            'less_than_or_equals' => is_numeric($fieldValue) && $fieldValue <= $value,
            'in' => in_array($fieldValue, (array) $value),
            'not_in' => !in_array($fieldValue, (array) $value),
            'contains' => is_string($fieldValue) && str_contains($fieldValue, (string) $value),
            'contains_any' => $this->containsAny($fieldValue, (array) $value),
            'is_empty' => empty($fieldValue),
            'is_not_empty' => !empty($fieldValue),
            'has_flag' => is_array($fieldValue) && in_array($value, $fieldValue),
            default => true,
        };
    }

    /**
     * Check if value contains any of the given items.
     */
    protected function containsAny($haystack, array $needles): bool
    {
        if (is_array($haystack)) {
            return !empty(array_intersect($haystack, $needles));
        }
        if (is_string($haystack)) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Apply this rule's actions to a service configuration.
     *
     * @param array $serviceConfig Current service configuration
     * @param array $context Evaluation context
     * @return array Modified service configuration
     */
    public function applyActions(array $serviceConfig, array $context): array
    {
        $actions = $this->actions ?? [];

        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            $params = $action['params'] ?? [];

            $serviceConfig = match ($type) {
                'set_frequency' => $this->setFrequency($serviceConfig, $params),
                'adjust_frequency' => $this->adjustFrequency($serviceConfig, $params),
                'set_duration' => $this->setDuration($serviceConfig, $params),
                'adjust_duration' => $this->adjustDuration($serviceConfig, $params),
                'set_provider' => $this->setProvider($serviceConfig, $params),
                'set_flag' => $this->setFlag($serviceConfig, $params),
                'apply_cost_modifier' => $this->applyCostModifier($serviceConfig, $params),
                default => $serviceConfig,
            };
        }

        return $serviceConfig;
    }

    protected function setFrequency(array $config, array $params): array
    {
        $config['currentFrequency'] = $params['value'] ?? $config['currentFrequency'];
        return $config;
    }

    protected function adjustFrequency(array $config, array $params): array
    {
        $adjustment = $params['adjustment'] ?? 0;
        $config['currentFrequency'] = max(0, ($config['currentFrequency'] ?? 0) + $adjustment);
        return $config;
    }

    protected function setDuration(array $config, array $params): array
    {
        $config['currentDuration'] = $params['value'] ?? $config['currentDuration'];
        return $config;
    }

    protected function adjustDuration(array $config, array $params): array
    {
        $adjustment = $params['adjustment'] ?? 0;
        $config['currentDuration'] = max(0, ($config['currentDuration'] ?? 0) + $adjustment);
        return $config;
    }

    protected function setProvider(array $config, array $params): array
    {
        $config['provider'] = $params['provider'] ?? $config['provider'];
        $config['provider_id'] = $params['provider_id'] ?? $config['provider_id'] ?? null;
        return $config;
    }

    protected function setFlag(array $config, array $params): array
    {
        $flag = $params['flag'] ?? null;
        $value = $params['value'] ?? true;
        if ($flag) {
            $config['flags'] = $config['flags'] ?? [];
            $config['flags'][$flag] = $value;
        }
        return $config;
    }

    protected function applyCostModifier(array $config, array $params): array
    {
        $modifier = $params['modifier'] ?? 1.0;
        $config['costPerVisit'] = round(($config['costPerVisit'] ?? 0) * $modifier, 2);
        return $config;
    }

    /**
     * Scope to get active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get rules by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('rule_type', $type);
    }

    /**
     * Scope to order by priority.
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }
}
