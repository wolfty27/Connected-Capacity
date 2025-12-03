<?php

namespace App\Services\BundleEngine\Engines;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * CAPTriggerEngine - Interprets YAML-based CAP trigger definitions
 * 
 * This engine evaluates InterRAI Clinical Assessment Protocol (CAP) triggers
 * defined in YAML configuration files. It supports:
 * - Multiple trigger levels (IMPROVE, PREVENT, NOT_TRIGGERED)
 * - Complex condition logic (all, any, min_count)
 * - Service recommendations and care guidelines
 * 
 * @see docs/ALGORITHM_DSL.md for schema specification
 */
class CAPTriggerEngine
{
    private string $capTriggersPath;
    private array $loadedCAPs = [];

    // CAP trigger levels
    public const LEVEL_IMPROVE = 'IMPROVE';
    public const LEVEL_PREVENT = 'PREVENT';
    public const LEVEL_FACILITATE = 'FACILITATE';
    public const LEVEL_NOT_TRIGGERED = 'NOT_TRIGGERED';

    public function __construct(?string $capTriggersPath = null)
    {
        $this->capTriggersPath = $capTriggersPath 
            ?? config_path('bundle_engine/cap_triggers');
    }

    /**
     * Load a CAP trigger definition from a YAML file.
     * Searches in subdirectories (functional, clinical, cognition, social)
     */
    public function loadCAP(string $capName): array
    {
        if (isset($this->loadedCAPs[$capName])) {
            return $this->loadedCAPs[$capName];
        }

        $filePath = $this->findCapFile($capName);
        
        if (!$filePath) {
            throw new RuntimeException("CAP file not found: {$capName}");
        }

        $yaml = file_get_contents($filePath);
        $definition = Yaml::parse($yaml);

        if (!$definition) {
            throw new RuntimeException("Invalid YAML in CAP file: {$capName}");
        }

        $this->validateCAP($definition);
        $this->loadedCAPs[$capName] = $definition;

        return $definition;
    }

    /**
     * Evaluate a CAP against profile data.
     * 
     * @param string $capName e.g., 'falls', 'pain', 'adl'
     * @param array $profileData Output of PatientNeedsProfile::toCAPInput()
     * @return array{level: string, description: string, recommendations: array, guidelines: array}
     */
    public function evaluate(string $capName, array $profileData): array
    {
        $cap = $this->loadCAP($capName);
        
        foreach ($cap['triggers'] ?? [] as $trigger) {
            // Check for default trigger (NOT_TRIGGERED)
            if (isset($trigger['conditions']['default']) && $trigger['conditions']['default'] === true) {
                return $this->formatTriggerResult($trigger, $capName);
            }

            // Evaluate conditions
            if ($this->evaluateTriggerConditions($trigger['conditions'] ?? [], $profileData)) {
                return $this->formatTriggerResult($trigger, $capName);
            }
        }

        // Default: NOT_TRIGGERED
        return [
            'level' => self::LEVEL_NOT_TRIGGERED,
            'cap_name' => $capName,
            'description' => 'No CAP triggered',
            'recommendations' => [],
            'guidelines' => [],
        ];
    }

    /**
     * Evaluate all applicable CAPs for a profile.
     * 
     * @return array<string, array> Map of CAP name to trigger result
     */
    public function evaluateAll(array $profileData): array
    {
        $results = [];
        
        foreach ($this->getAvailableCAPs() as $capName) {
            try {
                $result = $this->evaluate($capName, $profileData);
                
                // Only include triggered CAPs
                if ($result['level'] !== self::LEVEL_NOT_TRIGGERED) {
                    $results[$capName] = $result;
                }
            } catch (\Exception $e) {
                Log::warning("Failed to evaluate CAP {$capName}: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Get list of available CAPs.
     */
    public function getAvailableCAPs(): array
    {
        $caps = [];
        
        // Search in all subdirectories
        $subdirs = ['functional', 'clinical', 'cognition', 'social'];
        
        foreach ($subdirs as $subdir) {
            $dirPath = $this->capTriggersPath . '/' . $subdir;
            
            if (!is_dir($dirPath)) {
                continue;
            }
            
            $files = glob($dirPath . '/*.yaml') ?: [];
            
            foreach ($files as $file) {
                $caps[] = basename($file, '.yaml');
            }
        }
        
        // Also check root directory
        $rootFiles = glob($this->capTriggersPath . '/*.yaml') ?: [];
        foreach ($rootFiles as $file) {
            $caps[] = basename($file, '.yaml');
        }
        
        return array_unique($caps);
    }

    /**
     * Get metadata about a CAP.
     */
    public function getCAPMeta(string $capName): array
    {
        $cap = $this->loadCAP($capName);
        
        return [
            'name' => $cap['name'] ?? $capName,
            'version' => $cap['version'] ?? 'unknown',
            'source' => $cap['source'] ?? null,
            'applicable_instruments' => $cap['applicable_instruments'] ?? [],
            'category' => $cap['category'] ?? 'unknown',
            'trigger_levels' => array_column($cap['triggers'] ?? [], 'level'),
        ];
    }

    /**
     * Validate a CAP definition.
     */
    public function validateCAP(array $definition): bool
    {
        $required = ['name', 'version', 'triggers'];
        
        foreach ($required as $field) {
            if (!isset($definition[$field])) {
                throw new RuntimeException("CAP missing required field: {$field}");
            }
        }

        if (!is_array($definition['triggers']) || empty($definition['triggers'])) {
            throw new RuntimeException("CAP must have at least one trigger");
        }

        foreach ($definition['triggers'] as $index => $trigger) {
            if (!isset($trigger['level'])) {
                throw new RuntimeException("Trigger {$index} missing 'level'");
            }
            
            $validLevels = [self::LEVEL_IMPROVE, self::LEVEL_PREVENT, self::LEVEL_FACILITATE, self::LEVEL_NOT_TRIGGERED];
            if (!in_array($trigger['level'], $validLevels)) {
                throw new RuntimeException("Invalid trigger level: {$trigger['level']}");
            }
        }

        return true;
    }

    /**
     * Find CAP file in subdirectories.
     */
    private function findCapFile(string $capName): ?string
    {
        // Check subdirectories first
        $subdirs = ['functional', 'clinical', 'cognition', 'social'];
        
        foreach ($subdirs as $subdir) {
            $filePath = $this->capTriggersPath . '/' . $subdir . '/' . $capName . '.yaml';
            if (file_exists($filePath)) {
                return $filePath;
            }
        }
        
        // Check root directory
        $rootPath = $this->capTriggersPath . '/' . $capName . '.yaml';
        if (file_exists($rootPath)) {
            return $rootPath;
        }
        
        return null;
    }

    /**
     * Evaluate trigger conditions.
     */
    private function evaluateTriggerConditions(array $conditions, array $profileData): bool
    {
        // Handle 'all' - all conditions must be true
        if (isset($conditions['all'])) {
            foreach ($conditions['all'] as $condition) {
                if (!$this->evaluateSingleCondition($condition, $profileData)) {
                    return false;
                }
            }
            // Don't return true yet - check other condition types
        }

        // Handle 'any' - at least one condition must be true
        if (isset($conditions['any'])) {
            $anyMatch = false;
            foreach ($conditions['any'] as $condition) {
                if ($this->evaluateSingleCondition($condition, $profileData)) {
                    $anyMatch = true;
                    break;
                }
            }
            if (!$anyMatch && !empty($conditions['any'])) {
                return false;
            }
        }

        // Handle 'min_count' - at least N conditions must be true
        if (isset($conditions['min_count'])) {
            $minCount = $conditions['min_count']['count'] ?? $conditions['min_count'];
            $fromConditions = $conditions['min_count']['from'] ?? $conditions['from'] ?? [];
            
            $trueCount = 0;
            foreach ($fromConditions as $condition) {
                if ($this->evaluateSingleCondition($condition, $profileData)) {
                    $trueCount++;
                }
            }
            
            if ($trueCount < $minCount) {
                return false;
            }
        }

        // If we get here and had conditions, they all passed
        return !empty($conditions['all']) || !empty($conditions['any']) || !empty($conditions['min_count']);
    }

    /**
     * Evaluate a single condition.
     */
    private function evaluateSingleCondition(array $condition, array $profileData): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '==';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return false;
        }

        $actualValue = $profileData[$field] ?? null;

        return match ($operator) {
            '==' => $actualValue == $value,
            '!=' => $actualValue != $value,
            '>=' => $actualValue >= $value,
            '<=' => $actualValue <= $value,
            '>' => $actualValue > $value,
            '<' => $actualValue < $value,
            default => false,
        };
    }

    /**
     * Format a trigger result.
     */
    private function formatTriggerResult(array $trigger, string $capName): array
    {
        return [
            'level' => $trigger['level'],
            'cap_name' => $capName,
            'description' => $trigger['description'] ?? '',
            'recommendations' => $trigger['service_recommendations'] ?? [],
            'guidelines' => $trigger['care_guidelines'] ?? [],
        ];
    }
}

