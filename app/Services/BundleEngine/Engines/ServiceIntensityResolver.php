<?php

namespace App\Services\BundleEngine\Engines;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ServiceIntensityResolver - Maps algorithm scores and CAP triggers to service intensities
 * 
 * This resolver uses a JSON configuration file to map:
 * - CA algorithm scores → service hours/visits
 * - CAP recommendations → service adjustments
 * - Scenario axes → intensity modifiers
 * 
 * @see docs/ALGORITHM_DSL.md for schema specification
 */
class ServiceIntensityResolver
{
    private string $matrixPath;
    private ?array $matrix = null;

    // Service type codes
    public const SERVICE_PSW = 'PSW';
    public const SERVICE_NUR = 'NUR';
    public const SERVICE_PT = 'PT';
    public const SERVICE_OT = 'OT';
    public const SERVICE_SLP = 'SLP';
    public const SERVICE_SW = 'SW';
    public const SERVICE_RD = 'RD';
    public const SERVICE_HM = 'HM';

    // Algorithm to service mapping keys
    public const MAPPING_PSA_TO_PSW = 'psa_to_psw_hours';
    public const MAPPING_REHAB_TO_THERAPY = 'rehab_to_therapy_visits';
    public const MAPPING_CHESS_TO_NURSING = 'chess_to_nursing_visits';
    public const MAPPING_PAIN_TO_NURSING = 'pain_to_nursing_visits';
    public const MAPPING_DMS_TO_MENTAL_HEALTH = 'dms_to_mental_health';

    public function __construct(?string $matrixPath = null)
    {
        $this->matrixPath = $matrixPath 
            ?? config_path('bundle_engine/service_intensity_matrix.json');
    }

    /**
     * Load the service intensity matrix.
     */
    public function loadMatrix(): array
    {
        if ($this->matrix !== null) {
            return $this->matrix;
        }

        if (!file_exists($this->matrixPath)) {
            throw new RuntimeException("Service intensity matrix not found: {$this->matrixPath}");
        }

        $json = file_get_contents($this->matrixPath);
        $this->matrix = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in service intensity matrix: " . json_last_error_msg());
        }

        return $this->matrix;
    }

    /**
     * Resolve algorithm scores to service intensities.
     * 
     * @param array $algorithmScores ['rehabilitation' => 3, 'personal_support' => 4, ...]
     * @param array $triggeredCAPs ['falls' => [...], 'pain' => [...], ...]
     * @param string|null $scenarioAxis Current scenario axis (for modifiers)
     * @return array<string, array{hours?: float, visits?: float, rationale: string, source: string}>
     */
    public function resolve(
        array $algorithmScores,
        array $triggeredCAPs = [],
        ?string $scenarioAxis = null
    ): array {
        $matrix = $this->loadMatrix();
        $services = [];

        // Map Personal Support Algorithm to PSW hours
        if (isset($algorithmScores['personal_support'])) {
            $pswResult = $this->getServiceIntensity(
                self::MAPPING_PSA_TO_PSW,
                $algorithmScores['personal_support']
            );
            $services[self::SERVICE_PSW] = $pswResult;
        }

        // Map Rehabilitation Algorithm to PT/OT visits
        if (isset($algorithmScores['rehabilitation'])) {
            $rehabResult = $this->getServiceIntensity(
                self::MAPPING_REHAB_TO_THERAPY,
                $algorithmScores['rehabilitation']
            );
            
            // Split between PT and OT based on profile (default 50/50)
            $totalVisits = $rehabResult['visits'] ?? 0;
            $ptRatio = 0.5; // Could be adjusted based on profile
            
            $services[self::SERVICE_PT] = [
                'visits' => round($totalVisits * $ptRatio, 1),
                'hours' => round($totalVisits * $ptRatio * 0.75, 1), // 45 min per visit
                'rationale' => $rehabResult['rationale'] . ' (PT portion)',
                'source' => $rehabResult['source'] ?? 'algorithm',
            ];
            
            $services[self::SERVICE_OT] = [
                'visits' => round($totalVisits * (1 - $ptRatio), 1),
                'hours' => round($totalVisits * (1 - $ptRatio) * 0.75, 1),
                'rationale' => $rehabResult['rationale'] . ' (OT portion)',
                'source' => $rehabResult['source'] ?? 'algorithm',
            ];
        }

        // Map CHESS-CA to Nursing visits
        if (isset($algorithmScores['chess_ca'])) {
            $nursingResult = $this->getServiceIntensity(
                self::MAPPING_CHESS_TO_NURSING,
                $algorithmScores['chess_ca']
            );
            $services[self::SERVICE_NUR] = $nursingResult;
        }

        // Apply CAP-based adjustments
        $services = $this->applyCapAdjustments($services, $triggeredCAPs);

        // Apply scenario axis modifiers
        if ($scenarioAxis) {
            $services = $this->applyScenarioModifiers($services, $scenarioAxis);
        }

        return $services;
    }

    /**
     * Get the service intensity for a specific algorithm mapping.
     */
    public function getServiceIntensity(string $mapping, int $score): array
    {
        $matrix = $this->loadMatrix();
        
        if (!isset($matrix[$mapping])) {
            return [
                'hours' => 0,
                'visits' => 0,
                'rationale' => 'No mapping defined',
                'source' => 'default',
            ];
        }

        $mappingConfig = $matrix[$mapping];
        $scoreKey = (string) $score;
        
        if (!isset($mappingConfig['mappings'][$scoreKey])) {
            // Use closest available score
            $availableScores = array_keys($mappingConfig['mappings']);
            $closestScore = $this->findClosestScore($score, $availableScores);
            $scoreKey = (string) $closestScore;
        }

        $result = $mappingConfig['mappings'][$scoreKey] ?? [];
        
        return [
            'hours' => $result['hours'] ?? 0,
            'visits' => $result['visits'] ?? 0,
            'label' => $result['label'] ?? '',
            'rationale' => $result['rationale'] ?? $mappingConfig['description'] ?? '',
            'confidence' => $result['confidence'] ?? 'medium',
            'source' => $mappingConfig['source']['primary'] ?? 'matrix',
        ];
    }

    /**
     * Get matrix metadata.
     */
    public function getMatrixMeta(): array
    {
        $matrix = $this->loadMatrix();
        
        return [
            'version' => $matrix['version'] ?? 'unknown',
            'last_updated' => $matrix['last_updated'] ?? null,
            'updated_by' => $matrix['updated_by'] ?? null,
            'review_status' => $matrix['review_status'] ?? 'unknown',
            'next_review_date' => $matrix['next_review_date'] ?? null,
            'available_mappings' => array_keys(array_filter($matrix, fn($v) => isset($v['mappings']))),
        ];
    }

    /**
     * Apply CAP-based adjustments to service intensities.
     */
    private function applyCapAdjustments(array $services, array $triggeredCAPs): array
    {
        foreach ($triggeredCAPs as $capName => $capResult) {
            $recommendations = $capResult['recommendations'] ?? [];
            
            foreach ($recommendations as $serviceCode => $recommendation) {
                $priority = $recommendation['priority'] ?? 'optional';
                $multiplier = $recommendation['frequency_multiplier'] ?? 1.0;
                $focus = $recommendation['focus'] ?? null;
                
                if (isset($services[$serviceCode])) {
                    // Apply multiplier to existing service
                    if (isset($services[$serviceCode]['hours'])) {
                        $services[$serviceCode]['hours'] *= $multiplier;
                    }
                    if (isset($services[$serviceCode]['visits'])) {
                        $services[$serviceCode]['visits'] *= $multiplier;
                    }
                    
                    // Add CAP rationale
                    $services[$serviceCode]['rationale'] .= " | CAP: {$capName} ({$priority})";
                    $services[$serviceCode]['cap_triggered'] = true;
                    
                    if ($focus) {
                        $services[$serviceCode]['focus'] = $focus;
                    }
                } else {
                    // Add new service recommendation from CAP
                    $services[$serviceCode] = [
                        'hours' => $priority === 'core' ? 2 : ($priority === 'recommended' ? 1 : 0.5),
                        'visits' => $priority === 'core' ? 2 : ($priority === 'recommended' ? 1 : 0),
                        'rationale' => "CAP: {$capName} ({$priority})",
                        'source' => 'cap_trigger',
                        'priority' => $priority,
                        'focus' => $focus,
                        'cap_triggered' => true,
                    ];
                }
            }
        }
        
        return $services;
    }

    /**
     * Apply scenario axis modifiers.
     */
    private function applyScenarioModifiers(array $services, string $scenarioAxis): array
    {
        $matrix = $this->loadMatrix();
        
        // Map scenario axes to modifier keys
        $axisModifiers = match($scenarioAxis) {
            'recovery_rehab' => ['PT' => 1.3, 'OT' => 1.3, 'SLP' => 1.2],
            'safety_stability' => ['NUR' => 1.2, 'PSW' => 1.1],
            'tech_enabled' => ['NUR' => 0.8, 'PSW' => 0.9], // Reduced in-person
            'caregiver_relief' => ['PSW' => 1.25, 'HM' => 1.3],
            'community_integrated' => ['SW' => 1.2],
            default => [],
        };
        
        foreach ($axisModifiers as $serviceCode => $modifier) {
            if (isset($services[$serviceCode])) {
                if (isset($services[$serviceCode]['hours'])) {
                    $services[$serviceCode]['hours'] = round($services[$serviceCode]['hours'] * $modifier, 1);
                }
                if (isset($services[$serviceCode]['visits'])) {
                    $services[$serviceCode]['visits'] = round($services[$serviceCode]['visits'] * $modifier, 1);
                }
                $services[$serviceCode]['scenario_modifier'] = $modifier;
                $services[$serviceCode]['scenario_axis'] = $scenarioAxis;
            }
        }
        
        // Also check for CAP-specific modifiers in the matrix
        foreach ($matrix as $mappingKey => $mappingConfig) {
            if (!isset($mappingConfig['modifiers'])) {
                continue;
            }
            
            $modifierKey = strtoupper($scenarioAxis) . '_AXIS';
            
            if (isset($mappingConfig['modifiers'][$modifierKey])) {
                $modifierConfig = $mappingConfig['modifiers'][$modifierKey];
                $multiplier = $modifierConfig['multiplier'] ?? 1.0;
                
                // Apply to relevant service based on mapping
                $targetService = match($mappingKey) {
                    self::MAPPING_PSA_TO_PSW => self::SERVICE_PSW,
                    self::MAPPING_REHAB_TO_THERAPY => self::SERVICE_PT, // Apply to both PT/OT
                    self::MAPPING_CHESS_TO_NURSING => self::SERVICE_NUR,
                    default => null,
                };
                
                if ($targetService && isset($services[$targetService])) {
                    if (isset($services[$targetService]['hours'])) {
                        $services[$targetService]['hours'] = round($services[$targetService]['hours'] * $multiplier, 1);
                    }
                    if (isset($services[$targetService]['visits'])) {
                        $services[$targetService]['visits'] = round($services[$targetService]['visits'] * $multiplier, 1);
                    }
                }
            }
        }
        
        return $services;
    }

    /**
     * Find the closest available score.
     */
    private function findClosestScore(int $score, array $availableScores): int
    {
        $numericScores = array_map('intval', $availableScores);
        
        $closest = $numericScores[0];
        $minDiff = abs($score - $closest);
        
        foreach ($numericScores as $available) {
            $diff = abs($score - $available);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $available;
            }
        }
        
        return $closest;
    }
}

