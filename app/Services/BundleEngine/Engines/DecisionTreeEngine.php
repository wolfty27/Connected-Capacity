<?php

namespace App\Services\BundleEngine\Engines;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * DecisionTreeEngine - Interprets JSON-based algorithm definitions
 * 
 * This engine evaluates InterRAI CA decision-support algorithms defined
 * in JSON configuration files. It supports:
 * - Binary decision trees with conditions
 * - Computed inputs (derived values)
 * - Simple expression evaluation
 * 
 * @see docs/ALGORITHM_DSL.md for schema specification
 */
class DecisionTreeEngine
{
    private string $algorithmsPath;
    private array $loadedAlgorithms = [];

    public function __construct(?string $algorithmsPath = null)
    {
        $this->algorithmsPath = $algorithmsPath 
            ?? config_path('bundle_engine/algorithms');
    }

    /**
     * Load an algorithm definition from a JSON file.
     */
    public function loadAlgorithm(string $algorithmName): array
    {
        if (isset($this->loadedAlgorithms[$algorithmName])) {
            return $this->loadedAlgorithms[$algorithmName];
        }

        $filePath = $this->algorithmsPath . '/' . $algorithmName . '.json';
        
        if (!file_exists($filePath)) {
            throw new RuntimeException("Algorithm file not found: {$filePath}");
        }

        $json = file_get_contents($filePath);
        $definition = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in algorithm file: " . json_last_error_msg());
        }

        $this->validateAlgorithm($definition);
        $this->loadedAlgorithms[$algorithmName] = $definition;

        return $definition;
    }

    /**
     * Evaluate an algorithm against input data.
     * 
     * @param string $algorithmName e.g., 'rehabilitation', 'personal_support'
     * @param array $input Key-value pairs of item codes and values
     * @return int|bool The algorithm output score
     */
    public function evaluate(string $algorithmName, array $input): int|bool
    {
        $algorithm = $this->loadAlgorithm($algorithmName);
        
        // Compute derived inputs first
        $context = $this->computeInputs($algorithm['computed_inputs'] ?? [], $input);
        
        // Merge with original input (computed values take precedence)
        $context = array_merge($input, $context);
        
        // Traverse the decision tree
        return $this->traverseTree($algorithm['tree'], $context);
    }

    /**
     * Get metadata about an algorithm without evaluating it.
     */
    public function getAlgorithmMeta(string $algorithmName): array
    {
        $algorithm = $this->loadAlgorithm($algorithmName);
        
        return [
            'name' => $algorithm['name'] ?? $algorithmName,
            'version' => $algorithm['version'] ?? 'unknown',
            'verification_status' => $algorithm['verification_status'] ?? 'unverified',
            'verification_source' => $algorithm['verification_source'] ?? null,
            'output_range' => $algorithm['output_range'] ?? [0, 1],
            'output_type' => $algorithm['output_type'] ?? 'integer',
            'description' => $algorithm['description'] ?? '',
            'items_used' => $algorithm['items_used'] ?? [],
        ];
    }

    /**
     * Validate an algorithm definition against the schema.
     */
    public function validateAlgorithm(array $definition): bool
    {
        $required = ['name', 'version', 'output_range', 'tree'];
        
        foreach ($required as $field) {
            if (!isset($definition[$field])) {
                throw new RuntimeException("Algorithm missing required field: {$field}");
            }
        }

        if (!is_array($definition['output_range']) || count($definition['output_range']) !== 2) {
            throw new RuntimeException("output_range must be an array of [min, max]");
        }

        if (!isset($definition['tree'])) {
            throw new RuntimeException("Algorithm must have a 'tree' definition");
        }

        $this->validateNode($definition['tree']);

        return true;
    }

    /**
     * Get list of available algorithms.
     */
    public function getAvailableAlgorithms(): array
    {
        $files = glob($this->algorithmsPath . '/*.json');
        $algorithms = [];
        
        foreach ($files as $file) {
            $name = basename($file, '.json');
            try {
                $meta = $this->getAlgorithmMeta($name);
                $algorithms[$name] = $meta;
            } catch (\Exception $e) {
                Log::warning("Failed to load algorithm {$name}: " . $e->getMessage());
            }
        }
        
        return $algorithms;
    }

    /**
     * Validate a tree node recursively.
     */
    private function validateNode(array $node): void
    {
        // Leaf node
        if (isset($node['return'])) {
            return;
        }

        // Branch node
        if (!isset($node['condition'])) {
            throw new RuntimeException("Branch node must have 'condition'");
        }
        if (!isset($node['true_branch'])) {
            throw new RuntimeException("Branch node must have 'true_branch'");
        }
        if (!isset($node['false_branch'])) {
            throw new RuntimeException("Branch node must have 'false_branch'");
        }

        $this->validateNode($node['true_branch']);
        $this->validateNode($node['false_branch']);
    }

    /**
     * Compute derived inputs from formulas.
     */
    private function computeInputs(array $computedInputs, array $input): array
    {
        $computed = [];
        
        foreach ($computedInputs as $name => $definition) {
            $formula = $definition['formula'] ?? $definition;
            $computed[$name] = $this->evaluateExpression($formula, array_merge($input, $computed));
        }
        
        return $computed;
    }

    /**
     * Traverse the decision tree and return the result.
     */
    private function traverseTree(array $node, array $context): int|bool
    {
        // Leaf node - return value
        if (isset($node['return'])) {
            return $node['return'];
        }

        // Branch node - evaluate condition
        $conditionResult = $this->evaluateCondition($node['condition'], $context);

        if ($conditionResult) {
            return $this->traverseTree($node['true_branch'], $context);
        } else {
            return $this->traverseTree($node['false_branch'], $context);
        }
    }

    /**
     * Evaluate a condition expression.
     */
    private function evaluateCondition(string $condition, array $context): bool
    {
        $result = $this->evaluateExpression($condition, $context);
        return (bool) $result;
    }

    /**
     * Evaluate an expression with support for arithmetic, comparison, and logical operators.
     * 
     * Supported syntax:
     * - Variables: C1, C2a, SRI
     * - Comparisons: ==, !=, >=, <=, >, <
     * - Logical: &&, ||
     * - Arithmetic: +, -, *, /
     * - Ternary: condition ? value_if_true : value_if_false
     * - Boolean literals: true, false
     */
    private function evaluateExpression(string $expression, array $context): mixed
    {
        $expression = trim($expression);
        
        // Handle outer parentheses for grouped expressions like "(X) + (Y)"
        // Split by + at the top level (outside parentheses)
        $parts = $this->splitByOperatorOutsideParens($expression, '+');
        if (count($parts) > 1) {
            $sum = 0;
            foreach ($parts as $part) {
                $sum += $this->evaluateExpression(trim($part), $context);
            }
            return $sum;
        }

        // Handle ternary operator - find the first unbalanced ? and :
        $ternaryParts = $this->parseTernary($expression);
        if ($ternaryParts) {
            if ($this->evaluateExpression($ternaryParts['condition'], $context)) {
                return $this->evaluateExpression($ternaryParts['true'], $context);
            }
            return $this->evaluateExpression($ternaryParts['false'], $context);
        }

        // Handle logical OR (lowest precedence)
        $orParts = $this->splitByOperatorOutsideParens($expression, '||');
        if (count($orParts) > 1) {
            foreach ($orParts as $part) {
                if ($this->evaluateExpression(trim($part), $context)) {
                    return true;
                }
            }
            return false;
        }

        // Handle logical AND
        $andParts = $this->splitByOperatorOutsideParens($expression, '&&');
        if (count($andParts) > 1) {
            foreach ($andParts as $part) {
                if (!$this->evaluateExpression(trim($part), $context)) {
                    return false;
                }
            }
            return true;
        }

        // Handle comparisons
        $comparisonPattern = '/^([A-Za-z_][A-Za-z0-9_]*)\s*(==|!=|>=|<=|>|<)\s*(.+)$/';
        if (preg_match($comparisonPattern, $expression, $matches)) {
            $varName = trim($matches[1]);
            $operator = $matches[2];
            $value = trim($matches[3]);

            $leftValue = $context[$varName] ?? 0;
            $rightValue = $this->parseValue($value, $context);

            return match ($operator) {
                '==' => $leftValue == $rightValue,
                '!=' => $leftValue != $rightValue,
                '>=' => $leftValue >= $rightValue,
                '<=' => $leftValue <= $rightValue,
                '>' => $leftValue > $rightValue,
                '<' => $leftValue < $rightValue,
                default => false,
            };
        }

        // Handle parentheses (single group)
        if (preg_match('/^\((.+)\)$/', $expression, $matches)) {
            return $this->evaluateExpression($matches[1], $context);
        }

        // Handle simple value
        return $this->parseValue($expression, $context);
    }

    /**
     * Split expression by operator, respecting parentheses.
     */
    private function splitByOperatorOutsideParens(string $expression, string $operator): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $opLen = strlen($operator);
        
        for ($i = 0; $i < strlen($expression); $i++) {
            $char = $expression[$i];
            
            if ($char === '(') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')') {
                $depth--;
                $current .= $char;
            } elseif ($depth === 0 && substr($expression, $i, $opLen) === $operator) {
                $parts[] = $current;
                $current = '';
                $i += $opLen - 1; // Skip the operator
            } else {
                $current .= $char;
            }
        }
        
        if ($current !== '') {
            $parts[] = $current;
        }
        
        return count($parts) > 1 ? $parts : [$expression];
    }

    /**
     * Parse a ternary expression into its parts.
     */
    private function parseTernary(string $expression): ?array
    {
        $depth = 0;
        $questionPos = null;
        $colonPos = null;
        
        for ($i = 0; $i < strlen($expression); $i++) {
            $char = $expression[$i];
            
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($depth === 0 && $char === '?' && $questionPos === null) {
                $questionPos = $i;
            } elseif ($depth === 0 && $char === ':' && $questionPos !== null && $colonPos === null) {
                $colonPos = $i;
            }
        }
        
        if ($questionPos !== null && $colonPos !== null) {
            return [
                'condition' => trim(substr($expression, 0, $questionPos)),
                'true' => trim(substr($expression, $questionPos + 1, $colonPos - $questionPos - 1)),
                'false' => trim(substr($expression, $colonPos + 1)),
            ];
        }
        
        return null;
    }

    /**
     * Parse a value (literal or variable reference).
     */
    private function parseValue(string $value, array $context): mixed
    {
        // Boolean literals
        if ($value === 'true') return true;
        if ($value === 'false') return false;

        // Numeric literals
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        // Variable reference
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            return $context[$value] ?? 0;
        }

        // Default
        return 0;
    }
}

