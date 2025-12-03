<?php

namespace App\Services\BundleEngine\Engines;

use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * CategoryIntensityResolver - Resolves algorithm scores to category floors & envelopes
 *
 * v2.3: Returns category-level floors (minimums) and recommended allocations,
 * NOT fixed service SKUs. This allows ScenarioCompositionEngine to select
 * different service mixes within each category based on axis and CAPs.
 *
 * Key Concepts:
 * - Floor: Clinical minimum that must be met (from algorithms)
 * - Recommended: Suggested allocation based on best practices
 * - CAP boosters: Additional floor increases from triggered CAPs
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 3
 */
class CategoryIntensityResolver
{
    private array $categoryConfig;
    private array $floorMatrix;
    private array $capAdjustments;

    private const CONFIG_PATHS = [
        'categories' => 'bundle_engine/service_categories.json',
        'matrix' => 'bundle_engine/service_intensity_matrix.json',
    ];

    public function __construct()
    {
        $this->loadConfigurations();
    }

    /**
     * Load all configuration files.
     */
    private function loadConfigurations(): void
    {
        // Load service categories
        $categoriesPath = config_path(self::CONFIG_PATHS['categories']);
        if (File::exists($categoriesPath)) {
            $this->categoryConfig = json_decode(File::get($categoriesPath), true);
        } else {
            Log::warning("Service categories config not found: {$categoriesPath}");
            $this->categoryConfig = ['categories' => []];
        }

        // Load intensity matrix for algorithm->floor mappings
        $matrixPath = config_path(self::CONFIG_PATHS['matrix']);
        if (File::exists($matrixPath)) {
            $matrix = json_decode(File::get($matrixPath), true);
            $this->floorMatrix = $this->buildFloorMatrix($matrix);
            $this->capAdjustments = $matrix['cap_floor_adjustments'] ?? [];
        } else {
            Log::warning("Service intensity matrix not found: {$matrixPath}");
            $this->floorMatrix = [];
            $this->capAdjustments = [];
        }
    }

    /**
     * Build a category-oriented floor matrix from the service-oriented matrix.
     */
    private function buildFloorMatrix(array $matrix): array
    {
        // Map algorithm mappings to categories
        return [
            'personal_support' => [
                'algorithm' => 'personal_support',
                'unit' => 'hours',
                'floors' => $this->extractFloors($matrix['psa_to_psw_hours'] ?? []),
            ],
            'clinical_monitoring' => [
                'algorithm' => 'chess_ca',
                'unit' => 'visits',
                'floors' => $this->extractFloors($matrix['chess_to_nursing_visits'] ?? []),
            ],
            'rehab_support' => [
                'algorithm' => 'rehabilitation',
                'unit' => 'visits',
                'floors' => $this->extractFloors($matrix['rehab_to_therapy_visits'] ?? []),
            ],
            // These categories get floors from CAPs, not algorithms
            'risk_mgmt_and_complexity' => [
                'algorithm' => null,
                'unit' => 'units',
                'floors' => [],
            ],
            'nutrition_support' => [
                'algorithm' => null,
                'unit' => 'units',
                'floors' => [],
            ],
            'social_support' => [
                'algorithm' => null,
                'unit' => 'units',
                'floors' => [],
            ],
        ];
    }

    /**
     * Extract floor/recommended values from a mapping config.
     */
    private function extractFloors(array $mappingConfig): array
    {
        $floors = [];
        $mappings = $mappingConfig['mappings'] ?? [];

        foreach ($mappings as $score => $data) {
            $floors[$score] = [
                'floor' => $data['hours'] ?? $data['visits'] ?? 0,
                'recommended' => ($data['hours'] ?? $data['visits'] ?? 0) * 1.2, // 20% buffer
            ];
        }

        return $floors;
    }

    /**
     * Resolve algorithm scores + CAP triggers to category floors and envelopes.
     *
     * @param array $algorithmScores ['personal_support' => 4, 'rehabilitation' => 2, 'chess_ca' => 3, ...]
     * @param array $triggeredCAPs ['falls' => ['level' => 'IMPROVE', ...], ...]
     * @param PatientNeedsProfile $profile
     * @return array<string, array{floor: float, recommended: float, unit: string, triggered_caps: array}>
     */
    public function resolveToCategories(
        array $algorithmScores,
        array $triggeredCAPs,
        PatientNeedsProfile $profile
    ): array {
        $categories = [];

        // 1. Initialize all categories with base floors from algorithms
        foreach ($this->categoryConfig['categories'] ?? [] as $catName => $catDef) {
            $floor = 0;
            $recommended = 0;

            // Check if this category has an algorithm driver
            foreach ($catDef['algorithm_drivers'] ?? [] as $algoKey) {
                if (isset($algorithmScores[$algoKey])) {
                    $score = $algorithmScores[$algoKey];
                    $catFloors = $this->floorMatrix[$catName]['floors'] ?? [];

                    if (!empty($catFloors)) {
                        $scoreKey = (string) $score;
                        $floorData = $catFloors[$scoreKey] ?? $this->findClosestFloor($score, $catFloors);
                        $floor = max($floor, $floorData['floor'] ?? 0);
                        $recommended = max($recommended, $floorData['recommended'] ?? 0);
                    }
                }
            }

            $categories[$catName] = [
                'floor' => $floor,
                'recommended' => $recommended,
                'unit' => $catDef['unit'] ?? 'units',
                'triggered_caps' => [],
                'cap_boosts' => [],
            ];
        }

        // 2. Apply CAP-based floor adjustments
        foreach ($triggeredCAPs as $capName => $capResult) {
            $level = $capResult['level'] ?? 'NOT_TRIGGERED';
            if ($level === 'NOT_TRIGGERED') {
                continue;
            }

            // Check CAP adjustments in matrix
            $adjustments = $this->capAdjustments[$capName] ?? [];
            foreach ($adjustments as $catName => $adjustment) {
                if (isset($categories[$catName])) {
                    $floorAdd = $adjustment['floor_add'] ?? 0;
                    $recommendedAdd = $adjustment['recommended_add'] ?? 0;

                    // Scale by CAP level intensity
                    $levelMultiplier = match ($level) {
                        'IMPROVE' => 1.0,
                        'PREVENT' => 0.7,
                        'FACILITATE' => 0.5,
                        'MAINTAIN' => 0.3,
                        default => 0,
                    };

                    $categories[$catName]['floor'] += $floorAdd * $levelMultiplier;
                    $categories[$catName]['recommended'] += $recommendedAdd * $levelMultiplier;
                    $categories[$catName]['triggered_caps'][] = $capName;
                    $categories[$catName]['cap_boosts'][$capName] = [
                        'floor_add' => $floorAdd * $levelMultiplier,
                        'recommended_add' => $recommendedAdd * $levelMultiplier,
                        'level' => $level,
                    ];
                }
            }

            // Also check category cap_boosters
            foreach ($this->categoryConfig['categories'] ?? [] as $catName => $catDef) {
                if (in_array($capName, $catDef['cap_boosters'] ?? [])) {
                    if (!in_array($capName, $categories[$catName]['triggered_caps'] ?? [])) {
                        // Add a baseline boost if this CAP affects this category
                        $categories[$catName]['floor'] += 1;
                        $categories[$catName]['recommended'] += 2;
                        $categories[$catName]['triggered_caps'][] = $capName;
                    }
                }
            }
        }

        // 3. Apply profile-based adjustments
        $categories = $this->applyProfileAdjustments($categories, $profile);

        // 4. Ensure recommended >= floor
        foreach ($categories as $catName => &$cat) {
            $cat['recommended'] = max($cat['floor'], $cat['recommended']);
        }

        return $categories;
    }

    /**
     * Apply profile-based adjustments to category floors.
     */
    private function applyProfileAdjustments(array $categories, PatientNeedsProfile $profile): array
    {
        // Cognitive complexity boosts personal support and risk management
        if ($profile->cognitiveComplexity >= 3) {
            $boost = ($profile->cognitiveComplexity - 2) * 2;
            $categories['personal_support']['floor'] += $boost;
            $categories['personal_support']['recommended'] += $boost * 1.5;
            $categories['risk_mgmt_and_complexity']['floor'] += 1;
            $categories['risk_mgmt_and_complexity']['recommended'] += 2;
        }

        // Falls risk boosts risk management
        if ($profile->fallsRiskLevel >= 2) {
            $categories['risk_mgmt_and_complexity']['floor'] += $profile->fallsRiskLevel;
            $categories['risk_mgmt_and_complexity']['recommended'] += $profile->fallsRiskLevel * 1.5;
        }

        // Lives alone boosts safety and social
        if ($profile->livesAlone) {
            $categories['risk_mgmt_and_complexity']['floor'] += 2;
            $categories['risk_mgmt_and_complexity']['recommended'] += 3;
            $categories['social_support']['floor'] += 1;
            $categories['social_support']['recommended'] += 2;
        }

        // Caregiver stress boosts social support
        if ($profile->caregiverStressLevel >= 3) {
            $categories['social_support']['floor'] += 2;
            $categories['social_support']['recommended'] += 4;
        }

        // Pain boosts clinical monitoring
        if (($profile->painScore ?? 0) >= 2) {
            $categories['clinical_monitoring']['floor'] += 1;
            $categories['clinical_monitoring']['recommended'] += 1;
        }

        return $categories;
    }

    /**
     * Find the closest floor data for a score not in the mapping.
     */
    private function findClosestFloor(int $score, array $floors): array
    {
        if (empty($floors)) {
            return ['floor' => 0, 'recommended' => 0];
        }

        $closestKey = null;
        $minDiff = PHP_INT_MAX;

        foreach (array_keys($floors) as $key) {
            $diff = abs($score - (int) $key);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closestKey = $key;
            }
        }

        return $floors[$closestKey] ?? ['floor' => 0, 'recommended' => 0];
    }

    /**
     * Get available services for a category, filtered by profile eligibility.
     *
     * @param string $category Category name
     * @param PatientNeedsProfile $profile Patient profile
     * @param array $triggeredCAPs Triggered CAPs
     * @return array<string, array> Eligible services with their definitions
     */
    public function getEligibleServices(
        string $category,
        PatientNeedsProfile $profile,
        array $triggeredCAPs
    ): array {
        $catDef = $this->categoryConfig['categories'][$category] ?? null;
        if (!$catDef) {
            return [];
        }

        $eligible = [];

        foreach ($catDef['services'] ?? [] as $serviceCode => $serviceDef) {
            // Check tech requirements
            if (!empty($serviceDef['requires_tech'])) {
                if (($profile->technologyReadiness ?? 0) < 2 || !($profile->hasInternet ?? false)) {
                    continue;
                }
            }

            // Check CAP requirements
            if (!empty($serviceDef['requires_cap'])) {
                $hasRequiredCap = false;
                foreach ($serviceDef['requires_cap'] as $requiredCap) {
                    if (isset($triggeredCAPs[$requiredCap]) &&
                        ($triggeredCAPs[$requiredCap]['level'] ?? 'NOT_TRIGGERED') !== 'NOT_TRIGGERED') {
                        $hasRequiredCap = true;
                        break;
                    }
                }
                if (!$hasRequiredCap) {
                    continue;
                }
            }

            // Check clinical requirements
            if (!empty($serviceDef['requires_clinical'])) {
                if (!($profile->requiresExtensiveServices ?? false)) {
                    continue;
                }
            }

            $eligible[$serviceCode] = $serviceDef;
        }

        return $eligible;
    }

    /**
     * Get all category definitions.
     */
    public function getCategoryDefinitions(): array
    {
        return $this->categoryConfig['categories'] ?? [];
    }

    /**
     * Get category floor matrix.
     */
    public function getFloorMatrix(): array
    {
        return $this->floorMatrix;
    }

    /**
     * Get CAP adjustments configuration.
     */
    public function getCapAdjustments(): array
    {
        return $this->capAdjustments;
    }
}

