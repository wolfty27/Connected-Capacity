<?php

namespace App\Services\BundleEngine\Engines;

use App\Models\ServiceType;
use App\Repositories\ServiceRateRepository;
use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\Enums\ScenarioAxis;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * ScenarioCompositionEngine - Composes service mixes from category floors + axis templates
 *
 * v2.3: The central engine that creates genuinely differentiated bundles by:
 * 1. Taking category floors from CategoryIntensityResolver
 * 2. Applying axis template target_mix to allocate capacity
 * 3. Using substitution rules to select concrete services within categories
 * 4. Applying CAP-driven service packages
 *
 * This creates bundles that differ in SERVICE MIX, not just intensity.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 5
 */
class ScenarioCompositionEngine
{
    private array $axisTemplates;
    private array $substitutionRules;
    private CategoryIntensityResolver $categoryResolver;
    private ServiceRateRepository $rateRepository;

    private const CONFIG_PATHS = [
        'templates' => 'bundle_engine/scenario_templates.json',
        'substitutions' => 'bundle_engine/substitution_rules.json',
    ];

    public function __construct(
        CategoryIntensityResolver $categoryResolver,
        ?ServiceRateRepository $rateRepository = null
    ) {
        $this->categoryResolver = $categoryResolver;
        $this->rateRepository = $rateRepository ?? new ServiceRateRepository();
        $this->loadConfigurations();
    }

    /**
     * Load configuration files.
     */
    private function loadConfigurations(): void
    {
        // Load axis templates
        $templatesPath = config_path(self::CONFIG_PATHS['templates']);
        if (File::exists($templatesPath)) {
            $config = json_decode(File::get($templatesPath), true);
            $this->axisTemplates = $config['axes'] ?? [];
        } else {
            Log::warning("Scenario templates not found: {$templatesPath}");
            $this->axisTemplates = [];
        }

        // Load substitution rules
        $subsPath = config_path(self::CONFIG_PATHS['substitutions']);
        if (File::exists($subsPath)) {
            $this->substitutionRules = json_decode(File::get($subsPath), true);
        } else {
            Log::warning("Substitution rules not found: {$subsPath}");
            $this->substitutionRules = [];
        }
    }

    /**
     * Compose a service mix for a specific axis.
     *
     * @param ScenarioAxis $axis The scenario axis
     * @param array $categoryFloors Category floors from CategoryIntensityResolver
     * @param array $triggeredCAPs Triggered CAPs with levels
     * @param PatientNeedsProfile $profile Patient profile
     * @return array<string, array{service_code: string, frequency: int, duration: int, rationale: string, category: string, source: string}>
     */
    public function composeForAxis(
        ScenarioAxis $axis,
        array $categoryFloors,
        array $triggeredCAPs,
        PatientNeedsProfile $profile
    ): array {
        $axisKey = $axis->value;
        $template = $this->axisTemplates[$axisKey] ?? $this->axisTemplates['balanced'] ?? [];

        if (empty($template)) {
            Log::warning("No template found for axis: {$axisKey}, using default composition");
            return $this->composeDefault($categoryFloors, $profile);
        }

        // Check axis requirements
        if (!$this->checkAxisRequirements($template, $profile)) {
            Log::info("Axis {$axisKey} requirements not met, falling back to balanced");
            $template = $this->axisTemplates['balanced'] ?? [];
        }

        $services = [];

        // 1. Calculate total capacity budget (sum of all recommended values)
        $totalBudget = array_sum(array_column($categoryFloors, 'recommended'));
        if ($totalBudget <= 0) {
            $totalBudget = 20; // Minimum baseline
        }

        // 2. For each category, allocate services based on target_mix
        $targetMix = $template['target_mix'] ?? [];
        foreach ($targetMix as $category => $targetRatio) {
            if ($targetRatio <= 0) {
                continue;
            }

            $catFloors = $categoryFloors[$category] ?? ['floor' => 0, 'recommended' => 0, 'unit' => 'units'];

            // Calculate target allocation for this category
            // Must be at least the floor, scaled by target ratio
            $targetAllocation = max(
                $catFloors['floor'],
                $totalBudget * $targetRatio
            );

            // Get eligible services for this category
            $eligibleServices = $this->categoryResolver->getEligibleServices(
                $category,
                $profile,
                $triggeredCAPs
            );

            // Apply axis service preferences (filter to primary/secondary)
            $eligibleServices = $this->filterByAxisPreferences(
                $eligibleServices,
                $template,
                $category
            );

            // Allocate within category using substitution rules
            $categoryServices = $this->allocateWithinCategory(
                $category,
                $targetAllocation,
                $catFloors['floor'],
                $catFloors['unit'],
                $eligibleServices,
                $triggeredCAPs,
                $profile,
                $template['substitution_preferences'][$category] ?? []
            );

            $services = array_merge($services, $categoryServices);
        }

        // 3. Apply CAP-driven service packages
        $services = $this->applyCapPackages($services, $triggeredCAPs, $template, $profile);

        // 4. Remove excluded services per axis
        $excludedServices = $template['excluded_services'] ?? [];
        $services = array_filter($services, function ($service) use ($excludedServices) {
            return !in_array($service['service_code'], $excludedServices);
        });

        // 5. Consolidate duplicate services
        $services = $this->consolidateServices($services);

        return array_values($services);
    }

    /**
     * Check if axis requirements are met by the profile.
     */
    private function checkAxisRequirements(array $template, PatientNeedsProfile $profile): bool
    {
        $requirements = $template['requirements'] ?? [];

        if (isset($requirements['tech_readiness_min'])) {
            if (($profile->technologyReadiness ?? 0) < $requirements['tech_readiness_min']) {
                return false;
            }
        }

        if (isset($requirements['has_internet']) && $requirements['has_internet']) {
            if (!($profile->hasInternet ?? false)) {
                return false;
            }
        }

        if (isset($requirements['cognitive_complexity_max'])) {
            if (($profile->cognitiveComplexity ?? 0) > $requirements['cognitive_complexity_max']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filter eligible services by axis preferences (primary/secondary/excluded).
     */
    private function filterByAxisPreferences(array $eligibleServices, array $template, string $category): array
    {
        $primaryServices = $template['primary_services'] ?? [];
        $secondaryServices = $template['secondary_services'] ?? [];
        $excludedServices = $template['excluded_services'] ?? [];

        $filtered = [];

        foreach ($eligibleServices as $code => $def) {
            // Skip excluded services
            if (in_array($code, $excludedServices)) {
                continue;
            }

            // Mark priority based on axis preferences
            $def['axis_priority'] = match (true) {
                in_array($code, $primaryServices) => 'primary',
                in_array($code, $secondaryServices) => 'secondary',
                default => 'tertiary',
            };

            $filtered[$code] = $def;
        }

        return $filtered;
    }

    /**
     * Allocate capacity within a category using substitution rules.
     */
    private function allocateWithinCategory(
        string $category,
        float $targetAllocation,
        float $hardFloor,
        string $unit,
        array $eligibleServices,
        array $triggeredCAPs,
        PatientNeedsProfile $profile,
        array $substitutionPrefs
    ): array {
        $categoryRules = $this->substitutionRules['within_category_substitutions'][$category] ?? [];
        $hardFloorService = $categoryRules['hard_floor_service'] ?? null;
        $hardFloorRatio = $categoryRules['hard_floor_ratio'] ?? 0;

        $allocations = [];
        $remainingCapacity = $targetAllocation;

        // 1. First, satisfy the hard floor with primary service if required
        if ($hardFloorService && $hardFloor > 0) {
            $hardFloorAmount = max($hardFloor, $targetAllocation * $hardFloorRatio);

            // Only allocate if the service is eligible
            if (isset($eligibleServices[$hardFloorService]) || $this->serviceExists($hardFloorService)) {
                $allocations[$hardFloorService] = [
                    'amount' => $hardFloorAmount,
                    'rationale' => "Clinical floor requirement ({$category})",
                    'source' => 'floor',
                    'category' => $category,
                    'unit' => $unit,
                ];
                $remainingCapacity -= $hardFloorAmount;
            }
        }

        // 2. Apply substitution rules based on axis preferences
        foreach ($categoryRules['rules'] ?? [] as $rule) {
            if ($remainingCapacity <= 0) {
                break;
            }

            $substituteService = $rule['substitute'];
            $maxRatio = $rule['max_ratio'];

            // Check if this substitution is preferred by the axis
            if (!$this->isSubstitutionPreferred($substituteService, $substitutionPrefs, $category)) {
                continue;
            }

            // Check conditions
            if (!$this->checkSubstitutionConditions($rule['conditions'] ?? [], $profile, $triggeredCAPs)) {
                continue;
            }

            // Check if service is eligible (either in eligible list or exists)
            if (!isset($eligibleServices[$substituteService]) && !$this->serviceExists($substituteService)) {
                continue;
            }

            // Calculate substitution amount
            $maxSubAmount = $targetAllocation * $maxRatio;
            $subAmount = min($remainingCapacity, $maxSubAmount);

            if ($subAmount > 0) {
                $allocations[$substituteService] = [
                    'amount' => $subAmount,
                    'rationale' => "Substitution: " . ($rule['conversion']['rationale'] ?? $substituteService),
                    'source' => 'substitution',
                    'category' => $category,
                    'unit' => $unit,
                ];
                $remainingCapacity -= $subAmount;
            }
        }

        // 3. Allocate remaining capacity to primary services (axis-preferred first)
        if ($remainingCapacity > 0) {
            // Sort by axis priority
            uasort($eligibleServices, function ($a, $b) {
                $priorityOrder = ['primary' => 0, 'secondary' => 1, 'tertiary' => 2];
                return ($priorityOrder[$a['axis_priority'] ?? 'tertiary'] ?? 2)
                    <=> ($priorityOrder[$b['axis_priority'] ?? 'tertiary'] ?? 2);
            });

            // Get primary services (is_primary = true and axis_priority = primary)
            $primaryServices = array_filter($eligibleServices, function ($s) {
                return ($s['is_primary'] ?? false) || ($s['axis_priority'] ?? '') === 'primary';
            });

            if (!empty($primaryServices)) {
                $perServiceAmount = $remainingCapacity / count($primaryServices);

                foreach (array_keys($primaryServices) as $serviceCode) {
                    if (!isset($allocations[$serviceCode])) {
                        $allocations[$serviceCode] = [
                            'amount' => 0,
                            'rationale' => '',
                            'source' => 'primary',
                            'category' => $category,
                            'unit' => $unit,
                        ];
                    }
                    $allocations[$serviceCode]['amount'] += $perServiceAmount;
                    $allocations[$serviceCode]['rationale'] = "Primary service for {$category}";
                }
            }
        }

        // 4. Convert allocations to service definitions
        return $this->convertToServiceDefinitions($allocations, $category);
    }

    /**
     * Check if a substitution is preferred by the axis.
     */
    private function isSubstitutionPreferred(string $service, array $prefs, string $category): bool
    {
        // Check various preference flags
        if (!empty($prefs['prefer_in_person']) && in_array($service, ['RPM', 'TELE', 'CDM'])) {
            return false;
        }

        if (!empty($prefs['prefer_in_person']) === false && !empty($prefs['max_remote_ratio'])) {
            // Remote services are preferred
            if (in_array($service, $prefs['remote_services'] ?? [])) {
                return true;
            }
        }

        if (!empty($prefs['prefer_specialized']) && in_array($service, $prefs['specialized_services'] ?? [])) {
            return true;
        }

        if (!empty($prefs['maximize_tech']) && in_array($service, ['RPM', 'PERS', 'CDM', 'MED-DISP', 'TELE'])) {
            return true;
        }

        if (!empty($prefs['include_safety_checks']) && $service === 'SEC') {
            return true;
        }

        if (!empty($prefs['include_respite_ratio']) && $service === 'RES') {
            return true;
        }

        if (!empty($prefs['prioritize_respite']) && $service === 'RES') {
            return true;
        }

        if (!empty($prefs['include_day_program_ratio']) && $service === 'ADP') {
            return true;
        }

        if (!empty($prefs['express_as_day_program_ratio']) && $service === 'ADP') {
            return true;
        }

        // Default: allow if not explicitly excluded
        return true;
    }

    /**
     * Check substitution rule conditions.
     */
    private function checkSubstitutionConditions(array $conditions, PatientNeedsProfile $profile, array $triggeredCAPs): bool
    {
        foreach ($conditions as $condition) {
            // Parse condition string like "tech_readiness >= 2" or "cap_triggered:falls"
            if (str_starts_with($condition, 'cap_triggered:')) {
                $capName = str_replace('cap_triggered:', '', $condition);
                if (!isset($triggeredCAPs[$capName]) ||
                    ($triggeredCAPs[$capName]['level'] ?? 'NOT_TRIGGERED') === 'NOT_TRIGGERED') {
                    return false;
                }
                continue;
            }

            // Parse comparison conditions
            if (preg_match('/(\w+)\s*(>=|<=|==|>|<)\s*(\d+|true|false)/', $condition, $matches)) {
                $field = $matches[1];
                $operator = $matches[2];
                $value = $matches[3];

                // Convert string booleans
                if ($value === 'true') $value = true;
                if ($value === 'false') $value = false;
                if (is_numeric($value)) $value = (int) $value;

                $profileValue = $this->getProfileValue($profile, $field);

                $result = match ($operator) {
                    '>=' => $profileValue >= $value,
                    '<=' => $profileValue <= $value,
                    '==' => $profileValue == $value,
                    '>' => $profileValue > $value,
                    '<' => $profileValue < $value,
                    default => true,
                };

                if (!$result) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get a value from the patient profile by field name.
     */
    private function getProfileValue(PatientNeedsProfile $profile, string $field): mixed
    {
        return match ($field) {
            'tech_readiness' => $profile->technologyReadiness ?? 0,
            'health_instability' => $profile->healthInstability ?? 0,
            'cognitive_complexity' => $profile->cognitiveComplexity ?? 0,
            'iadl_support_level' => $profile->iadlSupportLevel ?? 0,
            'adl_support_level' => $profile->adlSupportLevel ?? 0,
            'lives_alone' => $profile->livesAlone ?? false,
            'caregiver_stress_level' => $profile->caregiverStressLevel ?? 0,
            'falls_risk_level' => $profile->fallsRiskLevel ?? 0,
            default => null,
        };
    }

    /**
     * Check if a service type exists in the database.
     */
    private function serviceExists(string $code): bool
    {
        return ServiceType::where('code', $code)->exists();
    }

    /**
     * Convert allocations to service definitions.
     */
    private function convertToServiceDefinitions(array $allocations, string $category): array
    {
        $services = [];

        foreach ($allocations as $serviceCode => $allocation) {
            $amount = $allocation['amount'] ?? 0;
            if ($amount <= 0) {
                continue;
            }

            $serviceType = ServiceType::where('code', $serviceCode)->first();
            if (!$serviceType) {
                continue;
            }

            // Convert amount to frequency based on unit
            $unit = $allocation['unit'] ?? 'units';
            $frequency = $this->amountToFrequency($amount, $unit, $serviceCode);
            $duration = $this->getDefaultDuration($serviceCode, $serviceType);

            $services[] = [
                'service_code' => $serviceCode,
                'service_type' => $serviceType,
                'frequency' => max(1, $frequency),
                'frequency_period' => 'week',
                'duration' => $duration,
                'rationale' => $allocation['rationale'] ?? "Service for {$category}",
                'category' => $category,
                'source' => $allocation['source'] ?? 'allocation',
                'cost_per_visit' => $this->getEffectiveRate($serviceType),
            ];
        }

        return $services;
    }

    /**
     * Convert amount to frequency based on unit type.
     */
    private function amountToFrequency(float $amount, string $unit, string $serviceCode): int
    {
        return match ($unit) {
            'hours' => (int) ceil($amount / $this->getAverageVisitDuration($serviceCode)),
            'visits' => (int) ceil($amount),
            'units' => match ($serviceCode) {
                'RPM', 'PERS', 'PERS-ADV', 'FALL-MON', 'CDM', 'MED-DISP' => 7, // Daily monitoring
                'MEAL', 'MOW' => (int) ceil($amount),
                'SEC' => (int) ceil($amount * 2), // Security checks are quick
                default => (int) ceil($amount),
            },
            default => (int) ceil($amount),
        };
    }

    /**
     * Get average visit duration in hours for a service.
     */
    private function getAverageVisitDuration(string $serviceCode): float
    {
        return match ($serviceCode) {
            'PSW' => 1.5,
            'HMK' => 2.0,
            'DEM' => 2.0,
            'NUR' => 1.0,
            'PT', 'OT', 'SLP', 'RT' => 0.75,
            'SW' => 1.0,
            'RES' => 4.0,
            'ADP' => 6.0,
            default => 1.0,
        };
    }

    /**
     * Get default duration in minutes for a service.
     */
    private function getDefaultDuration(string $serviceCode, ServiceType $serviceType): int
    {
        $default = $serviceType->default_duration_minutes ?? null;
        if ($default) {
            return $default;
        }

        return match ($serviceCode) {
            'PSW' => 90,
            'HMK' => 120,
            'DEM' => 120,
            'NUR' => 60,
            'NP' => 45,
            'PT', 'OT' => 45,
            'SLP', 'RT' => 45,
            'SW' => 60,
            'RD' => 45,
            'RES' => 240,
            'ADP' => 360,
            'SEC' => 15,
            'MEAL', 'MOW' => 15,
            'TELE' => 30,
            'RPM', 'PERS', 'CDM', 'FALL-MON', 'MED-DISP' => 15,
            'LAB', 'LAB-MOBILE' => 30,
            'PHAR' => 30,
            'BEH' => 90,
            'CGC' => 60,
            'REC' => 120,
            default => 60,
        };
    }

    /**
     * Get effective rate for a service type.
     */
    private function getEffectiveRate(ServiceType $serviceType): float
    {
        $rate = $this->rateRepository->getCurrentRate($serviceType, null);
        if ($rate) {
            return $rate->rate_dollars;
        }
        return $serviceType->cost_per_visit ?? 100.0;
    }

    /**
     * Apply CAP-driven service packages.
     */
    private function applyCapPackages(
        array $services,
        array $triggeredCAPs,
        array $template,
        PatientNeedsProfile $profile
    ): array {
        $packages = $this->substitutionRules['cross_category_packages'] ?? [];
        $capPriorities = $template['cap_priorities'] ?? [];

        foreach ($packages as $packageName => $packageDef) {
            // Evaluate trigger condition
            if (!$this->evaluateCapTrigger($packageDef['trigger_condition'] ?? '', $triggeredCAPs)) {
                continue;
            }

            // Check if this CAP is relevant to the axis (prioritized)
            $isAxisPriority = false;
            foreach ($capPriorities as $priorityCap) {
                if (str_contains($packageDef['trigger_condition'] ?? '', $priorityCap)) {
                    $isAxisPriority = true;
                    break;
                }
            }

            // Apply package services (with reduced intensity if not axis priority)
            $intensityMultiplier = $isAxisPriority ? 1.0 : 0.5;

            foreach ($packageDef['adds'] ?? [] as $category => $categoryAdds) {
                foreach ($categoryAdds as $serviceCode => $serviceConfig) {
                    // Skip boost entries (they modify existing services)
                    if (str_ends_with($serviceCode, '_boost')) {
                        // Find and boost the related service
                        $baseService = str_replace(['_boost', '_supervision_boost', '_wound_care_boost', '_positioning_boost', '_nutrition_monitoring'], '', $serviceCode);
                        foreach ($services as &$service) {
                            if ($service['service_code'] === $baseService) {
                                $addAmount = $serviceConfig['visits_add'] ?? $serviceConfig['hours_add'] ?? 0;
                                $service['frequency'] += (int) ceil($addAmount * $intensityMultiplier);
                                $service['rationale'] .= " + CAP boost";
                            }
                        }
                        unset($service);
                        continue;
                    }

                    // Check if service already exists
                    $existingKey = array_search($serviceCode, array_column($services, 'service_code'));
                    if ($existingKey !== false) {
                        continue;
                    }

                    // Check if service exists in database
                    $serviceType = ServiceType::where('code', $serviceCode)->first();
                    if (!$serviceType) {
                        continue;
                    }

                    $frequency = $serviceConfig['frequency'] ?? 1;
                    $frequency = (int) ceil($frequency * $intensityMultiplier);

                    $services[] = [
                        'service_code' => $serviceCode,
                        'service_type' => $serviceType,
                        'frequency' => max(1, $frequency),
                        'frequency_period' => $serviceConfig['unit'] ?? 'week',
                        'duration' => $this->getDefaultDuration($serviceCode, $serviceType),
                        'rationale' => "CAP package: {$packageName}",
                        'category' => $category,
                        'source' => 'cap_package',
                        'cost_per_visit' => $this->getEffectiveRate($serviceType),
                    ];
                }
            }
        }

        return $services;
    }

    /**
     * Evaluate a CAP trigger condition.
     */
    private function evaluateCapTrigger(string $condition, array $triggeredCAPs): bool
    {
        if (empty($condition)) {
            return false;
        }

        // Handle OR conditions
        if (str_contains($condition, ' OR ')) {
            $parts = explode(' OR ', $condition);
            foreach ($parts as $part) {
                if ($this->evaluateCapTrigger(trim($part), $triggeredCAPs)) {
                    return true;
                }
            }
            return false;
        }

        // Handle AND conditions
        if (str_contains($condition, ' AND ')) {
            $parts = explode(' AND ', $condition);
            foreach ($parts as $part) {
                if (!$this->evaluateCapTrigger(trim($part), $triggeredCAPs)) {
                    return false;
                }
            }
            return true;
        }

        // Handle cap_triggered:name conditions
        if (str_starts_with($condition, 'cap_triggered:')) {
            $capName = str_replace('cap_triggered:', '', $condition);
            return isset($triggeredCAPs[$capName]) &&
                ($triggeredCAPs[$capName]['level'] ?? 'NOT_TRIGGERED') !== 'NOT_TRIGGERED';
        }

        // Handle cap_level IN [...] conditions
        if (preg_match('/cap_level IN \[([^\]]+)\]/', $condition, $matches)) {
            $allowedLevels = array_map('trim', explode(',', str_replace("'", '', $matches[1])));
            foreach ($triggeredCAPs as $cap) {
                if (in_array($cap['level'] ?? '', $allowedLevels)) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Consolidate duplicate services.
     */
    private function consolidateServices(array $services): array
    {
        $consolidated = [];

        foreach ($services as $service) {
            $code = $service['service_code'];

            if (!isset($consolidated[$code])) {
                $consolidated[$code] = $service;
            } else {
                // Merge: add frequencies, keep higher duration, combine rationales
                $consolidated[$code]['frequency'] += $service['frequency'];
                $consolidated[$code]['duration'] = max($consolidated[$code]['duration'], $service['duration']);
                if (!str_contains($consolidated[$code]['rationale'], $service['rationale'])) {
                    $consolidated[$code]['rationale'] .= '; ' . $service['rationale'];
                }
            }
        }

        return array_values($consolidated);
    }

    /**
     * Compose default services when no axis template is available.
     */
    private function composeDefault(array $categoryFloors, PatientNeedsProfile $profile): array
    {
        $services = [];

        // Add PSW based on personal_support floor
        $pswFloor = $categoryFloors['personal_support']['floor'] ?? 0;
        if ($pswFloor > 0) {
            $psw = ServiceType::where('code', 'PSW')->first();
            if ($psw) {
                $services[] = [
                    'service_code' => 'PSW',
                    'service_type' => $psw,
                    'frequency' => max(1, (int) ceil($pswFloor / 1.5)),
                    'frequency_period' => 'week',
                    'duration' => 90,
                    'rationale' => 'Personal support floor',
                    'category' => 'personal_support',
                    'source' => 'floor',
                    'cost_per_visit' => $this->getEffectiveRate($psw),
                ];
            }
        }

        // Add NUR based on clinical_monitoring floor
        $nurFloor = $categoryFloors['clinical_monitoring']['floor'] ?? 0;
        if ($nurFloor > 0) {
            $nur = ServiceType::where('code', 'NUR')->first();
            if ($nur) {
                $services[] = [
                    'service_code' => 'NUR',
                    'service_type' => $nur,
                    'frequency' => max(1, (int) ceil($nurFloor)),
                    'frequency_period' => 'week',
                    'duration' => 60,
                    'rationale' => 'Clinical monitoring floor',
                    'category' => 'clinical_monitoring',
                    'source' => 'floor',
                    'cost_per_visit' => $this->getEffectiveRate($nur),
                ];
            }
        }

        // Add PT/OT based on rehab_support floor
        $rehabFloor = $categoryFloors['rehab_support']['floor'] ?? 0;
        if ($rehabFloor > 0) {
            $pt = ServiceType::where('code', 'PT')->first();
            $ot = ServiceType::where('code', 'OT')->first();
            $ptVisits = (int) ceil($rehabFloor / 2);
            $otVisits = (int) floor($rehabFloor / 2);

            if ($pt && $ptVisits > 0) {
                $services[] = [
                    'service_code' => 'PT',
                    'service_type' => $pt,
                    'frequency' => max(1, $ptVisits),
                    'frequency_period' => 'week',
                    'duration' => 45,
                    'rationale' => 'Rehab support floor',
                    'category' => 'rehab_support',
                    'source' => 'floor',
                    'cost_per_visit' => $this->getEffectiveRate($pt),
                ];
            }
            if ($ot && $otVisits > 0) {
                $services[] = [
                    'service_code' => 'OT',
                    'service_type' => $ot,
                    'frequency' => max(1, $otVisits),
                    'frequency_period' => 'week',
                    'duration' => 45,
                    'rationale' => 'Rehab support floor',
                    'category' => 'rehab_support',
                    'source' => 'floor',
                    'cost_per_visit' => $this->getEffectiveRate($ot),
                ];
            }
        }

        return $services;
    }

    /**
     * Get axis template for debugging/inspection.
     */
    public function getAxisTemplate(ScenarioAxis $axis): ?array
    {
        return $this->axisTemplates[$axis->value] ?? null;
    }

    /**
     * Get all axis templates.
     */
    public function getAllAxisTemplates(): array
    {
        return $this->axisTemplates;
    }
}

