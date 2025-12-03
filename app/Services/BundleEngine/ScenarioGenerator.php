<?php

namespace App\Services\BundleEngine;

use App\Models\CareBundleTemplate;
use App\Models\ServiceType;
use App\Repositories\ServiceRateRepository;
use App\Services\BundleEngine\Contracts\CostAnnotationServiceInterface;
use App\Services\BundleEngine\Contracts\ScenarioGeneratorInterface;
use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;
use App\Services\BundleEngine\DTOs\ScenarioServiceLine;
use App\Services\BundleEngine\Enums\NeedsCluster;
use App\Services\BundleEngine\Enums\ScenarioAxis;
use App\Services\BundleEngine\Engines\ServiceIntensityResolver;
use App\Services\BundleEngine\Engines\CategoryIntensityResolver;
use App\Services\BundleEngine\Engines\ScenarioCompositionEngine;
use App\Services\BundleEngine\Engines\CAPTriggerEngine;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * ScenarioGenerator v2.3
 *
 * Generates 3-5 scenario bundles for a patient based on their needs profile.
 *
 * v2.3 Generation Pipeline:
 * 1. Get applicable scenario axes from ScenarioAxisSelector
 * 2. Extract algorithm scores from profile (PSA, Rehab, CHESS)
 * 3. Evaluate CAP triggers for the profile
 * 4. CategoryIntensityResolver → category floors (personal_support, clinical_monitoring, etc.)
 * 5. ScenarioCompositionEngine → compose service mix per axis + CAPs + substitution rules
 * 6. Build service lines and annotate with costs
 * 7. Validate safety requirements
 *
 * Key v2.3 Improvements:
 * - Algorithms define FLOORS (minimums), not fixed SKUs
 * - Axes define TARGET MIX across categories, not just multipliers
 * - CAPs drive SERVICE INCLUSION, not just labels
 * - Substitution rules enable genuinely DIFFERENT service mixes
 * - Scenarios differ in COMPOSITION, not just intensity
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 5
 */
class ScenarioGenerator implements ScenarioGeneratorInterface
{
    protected ScenarioAxisSelector $axisSelector;
    protected CostAnnotationServiceInterface $costService;
    protected ServiceRateRepository $rateRepository;
    protected ?ServiceIntensityResolver $intensityResolver;
    protected ?CAPTriggerEngine $capEngine;
    protected ?CategoryIntensityResolver $categoryResolver;
    protected ?ScenarioCompositionEngine $compositionEngine;

    public function __construct(
        ?ScenarioAxisSelector $axisSelector = null,
        ?CostAnnotationServiceInterface $costService = null,
        ?ServiceRateRepository $rateRepository = null,
        ?ServiceIntensityResolver $intensityResolver = null,
        ?CAPTriggerEngine $capEngine = null,
        ?CategoryIntensityResolver $categoryResolver = null,
        ?ScenarioCompositionEngine $compositionEngine = null
    ) {
        $this->axisSelector = $axisSelector ?? new ScenarioAxisSelector();
        $this->costService = $costService ?? new CostAnnotationService();
        $this->rateRepository = $rateRepository ?? new ServiceRateRepository();
        $this->intensityResolver = $intensityResolver;
        $this->capEngine = $capEngine;
        
        // v2.3: Initialize new category-based composition engines
        $this->categoryResolver = $categoryResolver;
        $this->compositionEngine = $compositionEngine;
    }

    /**
     * Generate scenario bundles for a patient.
     *
     * @inheritDoc
     */
    public function generateScenarios(PatientNeedsProfile $profile, array $options = []): array
    {
        $minScenarios = $options['min_scenarios'] ?? 3;
        $maxScenarios = $options['max_scenarios'] ?? 5;
        $includeBalanced = $options['include_balanced'] ?? true;
        $referenceCap = $options['reference_cap'] ?? 5000.0;

        // Get applicable axes for this profile
        $applicableAxes = $this->getApplicableAxes($profile, $maxScenarios);

        // Find base template
        $baseTemplate = $this->findBaseTemplate($profile);

        // Generate scenarios for each axis
        $scenarios = [];
        $order = 1;

        foreach ($applicableAxes as $axis) {
            // Skip BALANCED if we'll add it at the end
            if ($axis === ScenarioAxis::BALANCED && $includeBalanced) {
                continue;
            }

            $scenario = $this->generateSingleScenario($profile, $axis, [], [
                'base_template' => $baseTemplate,
                'reference_cap' => $referenceCap,
            ]);

            // Annotate with cost
            $scenario = $this->costService->annotateScenario($scenario, $referenceCap);

            // Validate safety
            $validation = $this->validateScenario($scenario, $profile);

            // Add safety warnings if any
            if (!empty($validation['warnings'])) {
                $scenario = $this->addSafetyWarnings($scenario, $validation['warnings']);
            }

            // Set display order
            $scenario = $this->setDisplayOrder($scenario, $order++);
            $scenarios[] = $scenario;

            // Stop if we have enough
            if (count($scenarios) >= $maxScenarios - ($includeBalanced ? 1 : 0)) {
                break;
            }
        }

        // Always add BALANCED as last option if requested
        if ($includeBalanced) {
            $balancedScenario = $this->generateSingleScenario($profile, ScenarioAxis::BALANCED, [], [
                'base_template' => $baseTemplate,
                'reference_cap' => $referenceCap,
            ]);
            $balancedScenario = $this->costService->annotateScenario($balancedScenario, $referenceCap);
            $balancedScenario = $this->setDisplayOrder($balancedScenario, $order++);
            $scenarios[] = $balancedScenario;
        }

        // Ensure minimum scenarios using varied axes
        $fillInAxes = [
            ScenarioAxis::SAFETY_STABILITY,
            ScenarioAxis::TECH_ENABLED,
            ScenarioAxis::CAREGIVER_RELIEF,
        ];
        $fillInIndex = 0;
        while (count($scenarios) < $minScenarios && $fillInIndex < count($fillInAxes)) {
            $fillAxis = $fillInAxes[$fillInIndex++];
            // Skip if we already have this axis
            if (collect($scenarios)->contains(fn($s) => $s->primaryAxis === $fillAxis)) {
                continue;
            }
            $additionalScenario = $this->generateSingleScenario($profile, $fillAxis, [], [
                'base_template' => $baseTemplate,
                'reference_cap' => $referenceCap,
            ]);
            $additionalScenario = $this->costService->annotateScenario($additionalScenario, $referenceCap);
            $additionalScenario = $this->setDisplayOrder($additionalScenario, $order++);
            $scenarios[] = $additionalScenario;
        }

        // Mark first scenario as recommended
        if (!empty($scenarios)) {
            $scenarios[0] = $this->markAsRecommended($scenarios[0]);
        }

        return $scenarios;
    }

    /**
     * Generate a single scenario for a specific axis.
     *
     * v2.3: Uses category-based composition pipeline:
     * 1. Extract algorithm scores → 2. Evaluate CAPs → 3. Resolve category floors
     * 4. Compose service mix per axis template + CAPs + substitution rules
     *
     * @inheritDoc
     */
    public function generateSingleScenario(
        PatientNeedsProfile $profile,
        ScenarioAxis $axis,
        array $secondaryAxes = [],
        array $options = []
    ): ScenarioBundleDTO {
        $baseTemplate = $options['base_template'] ?? $this->findBaseTemplate($profile);
        $referenceCap = $options['reference_cap'] ?? 5000.0;

        // v2.3: Try the new category-based composition pipeline first
        if ($this->categoryResolver && $this->compositionEngine) {
            \Log::info('ScenarioGenerator: Using v2.3 Category-Based Composition', [
                'patient_id' => $profile->patientId,
                'axis' => $axis->value,
            ]);
            return $this->generateWithCategoryComposition(
                $profile, $axis, $secondaryAxes, $baseTemplate, $referenceCap
            );
        }

        // Fallback to legacy pipeline if new engines not available
        \Log::warning('ScenarioGenerator: Falling back to legacy pipeline', [
            'patient_id' => $profile->patientId,
            'categoryResolver_null' => $this->categoryResolver === null,
            'compositionEngine_null' => $this->compositionEngine === null,
        ]);
        return $this->generateWithLegacyPipeline(
            $profile, $axis, $secondaryAxes, $baseTemplate, $referenceCap
        );
    }

    /**
     * v2.3: Generate scenario using category-based composition.
     *
     * This creates genuinely differentiated bundles by:
     * - Using algorithms for category FLOORS (minimums)
     * - Using axis templates for TARGET MIX allocation
     * - Using CAPs to drive SERVICE INCLUSION
     * - Using substitution rules for COMPOSITIONAL variety
     */
    protected function generateWithCategoryComposition(
        PatientNeedsProfile $profile,
        ScenarioAxis $axis,
        array $secondaryAxes,
        ?CareBundleTemplate $baseTemplate,
        float $referenceCap
    ): ScenarioBundleDTO {
        // 1. Extract algorithm scores from profile
        $algorithmScores = [
            'personal_support' => $profile->personalSupportScore,
            'rehabilitation' => $profile->rehabilitationScore,
            'chess_ca' => $profile->chessCAScore,
            'pain' => $profile->painScore,
            'distressed_mood' => $profile->distressedMoodScore,
            'service_urgency' => $profile->serviceUrgencyScore,
        ];

        // 2. Evaluate CAP triggers
        $triggeredCAPs = $this->evaluateCAPs($profile);

        // 3. Resolve algorithm scores + CAPs to category floors
        $categoryFloors = $this->categoryResolver->resolveToCategories(
            $algorithmScores,
            $triggeredCAPs,
            $profile
        );

        // 4. Compose service mix for this axis using category floors + templates + substitution
        $composedServices = $this->compositionEngine->composeForAxis(
            $axis,
            $categoryFloors,
            $triggeredCAPs,
            $profile
        );

        // 5. Build service lines from composed services
        $serviceLines = $this->buildServiceLinesFromComposition($composedServices, $axis, $profile);

        // 6. Generate metadata
        $title = $this->generateTitle($axis, $secondaryAxes);
        $description = $this->generateDescriptionWithCAPs($axis, $profile, $triggeredCAPs);

        // Get benefits and goals
        $keyBenefits = $this->getKeyBenefits($axis, $profile);
        $patientGoals = $axis->getEmphasizedGoals();
        $risksAddressed = $this->getRisksAddressedWithCAPs($serviceLines, $profile, $triggeredCAPs);

        // Determine confidence
        $confidence = $this->determineConfidence($profile, $baseTemplate);

        return new ScenarioBundleDTO(
            scenarioId: (string) Str::uuid(),
            patientId: $profile->patientId,
            primaryAxis: $axis,
            title: $title,
            description: $description,
            serviceLines: $serviceLines,
            secondaryAxes: $secondaryAxes,
            subtitle: $axis->getLabel(),
            icon: $axis->getEmoji(),
            tradeOffs: $this->getAxisTradeOffsWithContext($axis, $triggeredCAPs),
            keyBenefits: $keyBenefits,
            patientGoalsSupported: $patientGoals,
            risksAddressed: $risksAddressed,
            source: 'category_composition_v2.3',
            confidenceLevel: $confidence,
            confidenceNotes: $this->getConfidenceNotesWithAlgorithms($profile, $categoryFloors),
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * Legacy pipeline for backward compatibility.
     */
    protected function generateWithLegacyPipeline(
        PatientNeedsProfile $profile,
        ScenarioAxis $axis,
        array $secondaryAxes,
        ?CareBundleTemplate $baseTemplate,
        float $referenceCap
    ): ScenarioBundleDTO {
        // Get base services from template
        $baseServices = $this->getBaseServicesFromTemplate($baseTemplate, $profile);

        // Apply axis modifiers
        $modifiedServices = $this->applyAxisModifiers($baseServices, $axis, $profile);

        // Apply secondary axis modifiers if any
        foreach ($secondaryAxes as $secondaryAxis) {
            $modifiedServices = $this->applyAxisModifiers($modifiedServices, $secondaryAxis, $profile, 0.5);
        }

        // Build service lines
        $serviceLines = $this->buildServiceLines($modifiedServices, $axis, $profile);

        // Generate scenario title and description
        $title = $this->generateTitle($axis, $secondaryAxes);
        $description = $this->generateDescription($axis, $profile);

        // Get benefits and goals
        $keyBenefits = $this->getKeyBenefits($axis, $profile);
        $patientGoals = $axis->getEmphasizedGoals();
        $risksAddressed = $this->getRisksAddressed($serviceLines, $profile);

        // Determine confidence
        $confidence = $this->determineConfidence($profile, $baseTemplate);

        return new ScenarioBundleDTO(
            scenarioId: (string) Str::uuid(),
            patientId: $profile->patientId,
            primaryAxis: $axis,
            title: $title,
            description: $description,
            serviceLines: $serviceLines,
            secondaryAxes: $secondaryAxes,
            subtitle: $axis->getLabel(),
            icon: $axis->getEmoji(),
            tradeOffs: $axis->getTradeOffs(),
            keyBenefits: $keyBenefits,
            patientGoalsSupported: $patientGoals,
            risksAddressed: $risksAddressed,
            source: 'rule_engine',
            confidenceLevel: $confidence,
            confidenceNotes: $this->getConfidenceNotes($profile, $baseTemplate),
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * Evaluate all CAP triggers for a profile.
     */
    protected function evaluateCAPs(PatientNeedsProfile $profile): array
    {
        if (!$this->capEngine) {
            return [];
        }

        try {
            $capInput = $profile->toCAPInput();
            return $this->capEngine->evaluateAll($capInput);
        } catch (\Exception $e) {
            \Log::warning('Failed to evaluate CAPs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Build service lines from category-composed services.
     */
    protected function buildServiceLinesFromComposition(
        array $composedServices,
        ScenarioAxis $axis,
        PatientNeedsProfile $profile
    ): array {
        $serviceLines = [];

        foreach ($composedServices as $service) {
            $serviceType = $service['service_type'] ?? null;
            if (!$serviceType) {
                continue;
            }

            // Build enhanced rationale showing composition source
            $rationale = $service['rationale'] ?? '';
            if ($service['source'] === 'floor') {
                $rationale = "[Clinical Floor] " . $rationale;
            } elseif ($service['source'] === 'substitution') {
                $rationale = "[Axis Substitution] " . $rationale;
            } elseif ($service['source'] === 'cap_package') {
                $rationale = "[CAP-Driven] " . $rationale;
            }

            $serviceLine = new ScenarioServiceLine(
                serviceCategory: $service['category'] ?? ($serviceType->category ?? 'general'),
                serviceName: $serviceType->name,
                frequencyCount: $service['frequency'],
                frequencyPeriod: $service['frequency_period'] ?? 'week',
                durationMinutes: $service['duration'],
                discipline: $this->getDisciplineForServiceType($serviceType),
                serviceModuleId: $serviceType->id,
                serviceCode: $serviceType->code ?? $service['service_code'],
                requiresSpecialization: (bool) ($serviceType->requires_specialization ?? false),
                deliveryMode: $serviceType->delivery_mode ?? 'in_person',
                costPerVisit: $service['cost_per_visit'] ?? $this->getEffectiveRate($serviceType),
                weeklyEstimatedCost: $this->calculateWeeklyCostFromComposed($service),
                priorityLevel: $this->determinePriorityLevel($service),
                isSafetyCritical: $service['source'] === 'floor',
                clinicalRationale: $rationale,
                patientGoalSupported: $this->getGoalForService($serviceType, $axis),
                axisContribution: $this->getAxisContributionFromComposition($service, $axis),
            );

            $serviceLines[] = $serviceLine;
        }

        return $serviceLines;
    }

    /**
     * Calculate weekly cost from composed service.
     */
    protected function calculateWeeklyCostFromComposed(array $service): float
    {
        $frequency = $service['frequency'] ?? 1;
        $period = $service['frequency_period'] ?? 'week';
        $costPerVisit = $service['cost_per_visit'] ?? 100.0;

        $weeklyVisits = match ($period) {
            'day' => $frequency * 7,
            'week' => $frequency,
            'month' => $frequency / 4.33,
            default => $frequency,
        };

        return $weeklyVisits * $costPerVisit;
    }

    /**
     * Determine priority level from service composition source.
     */
    protected function determinePriorityLevel(array $service): string
    {
        if ($service['source'] === 'floor') {
            return 'core';
        }
        if ($service['source'] === 'cap_package') {
            return 'core';
        }
        if ($service['source'] === 'primary') {
            return 'recommended';
        }
        return $service['priority_level'] ?? 'optional';
    }

    /**
     * Get axis contribution from composition context.
     */
    protected function getAxisContributionFromComposition(array $service, ScenarioAxis $axis): string
    {
        $source = $service['source'] ?? 'unknown';

        return match ($source) {
            'floor' => 'Clinical requirement (algorithm-driven)',
            'substitution' => "Axis preference: {$axis->getLabel()}",
            'cap_package' => 'CAP-triggered intervention',
            'primary' => "Primary for {$service['category']}",
            default => 'Supporting service',
        };
    }

    /**
     * Generate description including CAP context.
     */
    protected function generateDescriptionWithCAPs(
        ScenarioAxis $axis,
        PatientNeedsProfile $profile,
        array $triggeredCAPs
    ): string {
        $description = $axis->getDescription();

        // Add CAP context if relevant
        $activeCaps = array_filter($triggeredCAPs, fn($cap) => 
            ($cap['level'] ?? 'NOT_TRIGGERED') !== 'NOT_TRIGGERED'
        );

        if (!empty($activeCaps)) {
            $capNames = array_map(fn($cap) => $cap['name'] ?? 'Unknown', array_values($activeCaps));
            $capList = implode(', ', array_slice($capNames, 0, 3));
            $description .= " This bundle addresses active clinical protocols: {$capList}.";
        }

        return $description;
    }

    /**
     * Get risks addressed including CAP context.
     */
    protected function getRisksAddressedWithCAPs(
        array $serviceLines,
        PatientNeedsProfile $profile,
        array $triggeredCAPs
    ): array {
        $risks = $this->getRisksAddressed($serviceLines, $profile);

        // Add CAP-driven risks
        foreach ($triggeredCAPs as $capName => $capResult) {
            if (($capResult['level'] ?? 'NOT_TRIGGERED') === 'NOT_TRIGGERED') {
                continue;
            }

            $capRisk = match ($capName) {
                'falls' => 'Falls prevention (CAP triggered)',
                'pain' => 'Pain management (CAP triggered)',
                'pressure_ulcer' => 'Pressure injury prevention (CAP triggered)',
                'medications' => 'Medication safety (CAP triggered)',
                'cardiorespiratory' => 'Cardiorespiratory monitoring (CAP triggered)',
                'mood' => 'Mood and wellbeing support (CAP triggered)',
                'cognitive_loss' => 'Cognitive support (CAP triggered)',
                'informal_support' => 'Caregiver sustainability (CAP triggered)',
                'undernutrition' => 'Nutritional support (CAP triggered)',
                default => null,
            };

            if ($capRisk && !in_array($capRisk, $risks)) {
                $risks[] = $capRisk;
            }
        }

        return $risks;
    }

    /**
     * Get axis trade-offs with CAP context.
     */
    protected function getAxisTradeOffsWithContext(ScenarioAxis $axis, array $triggeredCAPs): array
    {
        $tradeOffs = $axis->getTradeOffs();

        // Adjust trade-offs based on CAPs
        $activeCaps = array_filter($triggeredCAPs, fn($cap) => 
            ($cap['level'] ?? 'NOT_TRIGGERED') !== 'NOT_TRIGGERED'
        );

        if (!empty($activeCaps)) {
            $tradeOffs[] = 'CAP-driven services are non-negotiable';
        }

        return $tradeOffs;
    }

    /**
     * Get confidence notes including algorithm scores.
     */
    protected function getConfidenceNotesWithAlgorithms(
        PatientNeedsProfile $profile,
        array $categoryFloors
    ): string {
        $parts = [];

        if ($profile->rugGroup) {
            $parts[] = "RUG-III/HC: {$profile->rugGroup}";
        }

        // Add algorithm-derived floor info
        $significantFloors = array_filter($categoryFloors, fn($cat) => ($cat['floor'] ?? 0) > 0);
        if (!empty($significantFloors)) {
            $floorSummary = [];
            foreach ($significantFloors as $catName => $cat) {
                $floorSummary[] = "{$catName}: {$cat['floor']} {$cat['unit']} floor";
            }
            $parts[] = "Floors: " . implode(', ', array_slice($floorSummary, 0, 3));
        }

        if ($profile->confidenceLevel) {
            $parts[] = "Profile confidence: {$profile->confidenceLevel}";
        }

        return empty($parts) ? 'Category-based composition v2.3' : implode(' | ', $parts);
    }

    /**
     * Validate a scenario bundle against safety requirements.
     *
     * @inheritDoc
     */
    public function validateScenario(ScenarioBundleDTO $scenario, PatientNeedsProfile $profile): array
    {
        $errors = [];
        $warnings = [];

        // Check for minimum nursing if health instability is high
        if ($profile->healthInstability >= 3) {
            $hasNursing = $scenario->hasServiceCategory('nursing');
            if (!$hasNursing) {
                $errors[] = 'High health instability requires nursing services';
            }
        }

        // Check for falls risk coverage
        if ($profile->fallsRiskLevel >= 2) {
            $hasFallsPrevention = $this->hasFallsPreventionServices($scenario);
            if (!$hasFallsPrevention) {
                $warnings[] = 'High falls risk - consider additional monitoring';
            }
        }

        // Check for cognitive support if needed
        if ($profile->cognitiveComplexity >= 4) {
            $hasCognitiveSupport = $this->hasCognitiveSupportServices($scenario);
            if (!$hasCognitiveSupport) {
                $warnings[] = 'Significant cognitive impairment - consider supervision services';
            }
        }

        // Check for extensive services coverage
        if ($profile->requiresExtensiveServices) {
            $hasExtensive = $this->hasExtensiveServiceCoverage($scenario, $profile);
            if (!$hasExtensive) {
                $errors[] = 'Required extensive services not included';
            }
        }

        // Check minimum weekly hours for high ADL
        if ($profile->adlSupportLevel >= 4) {
            $weeklyHours = $scenario->totalWeeklyHours;
            if ($weeklyHours < 10) {
                $warnings[] = 'High ADL dependency may need more weekly support hours';
            }
        }

        return [
            'valid' => empty($errors),
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * Compare two scenarios and return difference analysis.
     *
     * @inheritDoc
     */
    public function compareScenarios(ScenarioBundleDTO $scenario1, ScenarioBundleDTO $scenario2): array
    {
        $services1 = collect($scenario1->serviceLines)->keyBy('serviceCategory');
        $services2 = collect($scenario2->serviceLines)->keyBy('serviceCategory');

        $allCategories = $services1->keys()->merge($services2->keys())->unique();

        $added = [];
        $removed = [];
        $frequencyChanges = [];

        foreach ($allCategories as $category) {
            $s1 = $services1->get($category);
            $s2 = $services2->get($category);

            if (!$s1 && $s2) {
                $added[] = $s2->serviceName;
            } elseif ($s1 && !$s2) {
                $removed[] = $s1->serviceName;
            } elseif ($s1 && $s2) {
                if ($s1->frequencyCount !== $s2->frequencyCount) {
                    $frequencyChanges[] = sprintf(
                        '%s: %s → %s',
                        $s1->serviceName,
                        $s1->getFrequencyLabel(),
                        $s2->getFrequencyLabel()
                    );
                }
            }
        }

        $costDiff = $scenario2->weeklyEstimatedCost - $scenario1->weeklyEstimatedCost;
        $hoursDiff = $scenario2->totalWeeklyHours - $scenario1->totalWeeklyHours;

        return [
            'services_added' => $added,
            'services_removed' => $removed,
            'frequency_changes' => $frequencyChanges,
            'cost_difference' => $costDiff,
            'hours_difference' => $hoursDiff,
            'emphasis_shift' => $this->describeEmphasisShift($scenario1, $scenario2),
        ];
    }

    /**
     * Get applicable scenario axes for a profile.
     *
     * @inheritDoc
     */
    public function getApplicableAxes(PatientNeedsProfile $profile, int $maxAxes = 4): array
    {
        return $this->axisSelector->getApplicableAxes($profile, $maxAxes);
    }

    /**
     * Find the best matching base template for a profile.
     */
    protected function findBaseTemplate(PatientNeedsProfile $profile): ?CareBundleTemplate
    {
        // Priority 1: Match by RUG group if available
        if ($profile->rugGroup) {
            $template = CareBundleTemplate::active()
                ->forRugGroup($profile->rugGroup)
                ->first();

            if ($template) {
                return $template;
            }
        }

        // Priority 2: Match by RUG category if available
        if ($profile->rugCategory) {
            $template = CareBundleTemplate::active()
                ->forRugCategory($profile->rugCategory)
                ->first();

            if ($template) {
                return $template;
            }
        }

        // Priority 3: Match by NeedsCluster
        if ($profile->needsCluster) {
            $needsCluster = NeedsCluster::tryFrom($profile->needsCluster);
            if ($needsCluster) {
                $rugCategories = $needsCluster->getApproximateRugCategories();
                foreach ($rugCategories as $rugCategory) {
                    $template = CareBundleTemplate::active()
                        ->forRugCategory($rugCategory)
                        ->first();

                    if ($template) {
                        return $template;
                    }
                }
            }
        }

        // Fallback: Return a default/general template
        return CareBundleTemplate::active()
            ->where('code', 'DEFAULT')
            ->orWhere('code', 'GENERAL')
            ->first();
    }

    /**
     * Get base services from a template.
     * Uses rate menu (ServiceRateRepository) for pricing when available.
     */
    protected function getBaseServicesFromTemplate(?CareBundleTemplate $template, PatientNeedsProfile $profile): array
    {
        if (!$template) {
            return $this->getDefaultServices($profile);
        }

        // Build flags from profile for conditional service selection
        $flags = $this->buildFlagsFromProfile($profile);

        // Get services applicable for these flags
        $templateServices = $template->getServicesForFlags($flags);

        $services = [];
        foreach ($templateServices as $templateService) {
            $serviceType = $templateService->serviceType;
            if (!$serviceType) {
                continue;
            }

            // Use rate menu pricing, falling back to template/service type defaults
            $costPerVisit = $this->getEffectiveRate($serviceType);

            $services[] = [
                'service_type' => $serviceType,
                'frequency_count' => $templateService->default_frequency_per_week,
                'frequency_period' => 'week',
                'duration_minutes' => $templateService->default_duration_minutes ?? $serviceType->default_duration_minutes ?? 60,
                'cost_per_visit' => $costPerVisit,
                'is_required' => $templateService->is_required,
                'priority_level' => $templateService->is_required ? 'core' : 'recommended',
            ];
        }

        return $services;
    }

    /**
     * Get the effective rate for a service type from the rate menu.
     *
     * Uses ServiceRateRepository to respect:
     * 1. Organization-specific rates (if applicable)
     * 2. System default rates
     * 3. Fallback to ServiceType.cost_per_visit
     */
    protected function getEffectiveRate(ServiceType $serviceType): float
    {
        // Try to get rate from ServiceRateRepository (respects rate menu)
        $rate = $this->rateRepository->getCurrentRate($serviceType, null);

        if ($rate) {
            return $rate->rate_dollars;
        }

        // Fallback to ServiceType.cost_per_visit
        return $serviceType->cost_per_visit ?? 100.0;
    }

    /**
     * Get default services when no template is available.
     *
     * v2.2: Now uses algorithm scores and ServiceIntensityResolver for evidence-based
     * service frequencies when available, with fallback to profile-based rules.
     *
     * @param PatientNeedsProfile $profile
     * @param ScenarioAxis|null $axis Optional axis for intensity modifiers
     * @return array
     */
    protected function getDefaultServices(PatientNeedsProfile $profile, ?ScenarioAxis $axis = null): array
    {
        // Try algorithm-driven approach first (v2.2)
        if ($this->intensityResolver) {
            return $this->getAlgorithmDrivenServices($profile, $axis);
        }

        // Fallback to rule-based approach
        return $this->getRuleBasedServices($profile);
    }

    /**
     * Get services using algorithm scores and ServiceIntensityResolver.
     * This provides evidence-based service frequencies based on CA algorithms.
     */
    protected function getAlgorithmDrivenServices(PatientNeedsProfile $profile, ?ScenarioAxis $axis = null): array
    {
        $services = [];

        // Build algorithm scores from profile
        $algorithmScores = [
            'personal_support' => $profile->personalSupportScore,
            'rehabilitation' => $profile->rehabilitationScore,
            'chess_ca' => $profile->chessCAScore,
        ];

        // Get triggered CAPs for service adjustments
        $triggeredCAPs = [];
        if ($this->capEngine && $profile->hasFullHcAssessment) {
            $capInput = $profile->toCAPInput();
            $triggeredCAPs = $this->capEngine->evaluateAll($capInput);
        }

        // Resolve algorithm scores to service intensities
        $axisName = $axis?->value;
        $resolvedServices = $this->intensityResolver->resolve($algorithmScores, $triggeredCAPs, $axisName);

        // Build service array from resolved intensities
        foreach ($resolvedServices as $serviceCode => $intensity) {
            $serviceType = ServiceType::where('code', $serviceCode)->first();
            if (!$serviceType) {
                continue;
            }

            // Skip if no hours/visits
            $hours = $intensity['hours'] ?? 0;
            $visits = $intensity['visits'] ?? 0;
            if ($hours <= 0 && $visits <= 0) {
                continue;
            }

            // Calculate frequency based on hours or visits
            $frequency = $visits > 0 ? (int) ceil($visits) : (int) ceil($hours / 1.5); // Assume 1.5h/visit
            $duration = $visits > 0 ? 60 : (int) round(($hours * 60) / max(1, $frequency));

            $services[] = [
                'service_type' => $serviceType,
                'frequency_count' => max(1, $frequency),
                'frequency_period' => 'week',
                'duration_minutes' => min(180, max(30, $duration)), // 30-180 min range
                'cost_per_visit' => $this->getEffectiveRate($serviceType),
                'is_required' => ($intensity['priority'] ?? 'recommended') === 'core',
                'priority_level' => $intensity['priority'] ?? 'recommended',
                'algorithm_source' => $intensity['source'] ?? 'algorithm',
                'clinical_rationale' => $intensity['rationale'] ?? null,
                'cap_triggered' => $intensity['cap_triggered'] ?? false,
            ];
        }

        // Ensure minimum baseline services
        $services = $this->ensureBaselineServices($services, $profile);

        // Add axis-specific services that aren't in the base algorithms
        if ($axis) {
            $services = $this->addAxisSpecificServices($services, $axis, $profile);
        }

        return $services;
    }

    /**
     * Add axis-specific services on TOP of algorithm-driven baseline.
     *
     * IMPORTANT: This method RESPECTS the algorithm-driven baseline:
     * - Algorithms (PSA, CHESS, Rehab) determine CLINICAL NEED
     * - Axis adds COMPLEMENTARY services for patient experience
     * - We do NOT reduce algorithm-indicated services (that's clinical need)
     * - We only ADD services that support the axis focus
     *
     * The differentiation comes from WHAT we add, not from reducing clinical services.
     */
    protected function addAxisSpecificServices(array $services, ScenarioAxis $axis, PatientNeedsProfile $profile): array
    {
        // Define axis-specific ADDITIONS only
        // These complement (not replace) the algorithm-driven baseline
        $axisAdditions = match ($axis) {
            ScenarioAxis::COMMUNITY_INTEGRATED => [
                // Focus: Social engagement, community connection
                'ADP' => ['frequency' => 2, 'duration' => 240, 'priority' => 'core', 'rationale' => 'Adult Day Program for social engagement'],
                'TRANS' => ['frequency' => 2, 'duration' => 60, 'priority' => 'core', 'rationale' => 'Transportation enables community participation'],
                'REC' => ['frequency' => 1, 'duration' => 120, 'priority' => 'recommended', 'rationale' => 'Social/recreational activities'],
                'MEAL' => ['frequency' => 5, 'duration' => 15, 'priority' => 'recommended', 'rationale' => 'Meal delivery supports independence'],
            ],
            ScenarioAxis::SAFETY_STABILITY => [
                // Focus: Monitoring, fall prevention, crisis avoidance
                'RPM' => ['frequency' => 7, 'duration' => 15, 'priority' => 'core', 'rationale' => 'Continuous health monitoring'],
                'PERS' => ['frequency' => 7, 'duration' => 5, 'priority' => 'core', 'rationale' => 'Emergency response system'],
                'FALL-MON' => ['frequency' => 7, 'duration' => 10, 'priority' => 'core', 'rationale' => 'Falls detection and prevention'],
                'SEC' => ['frequency' => 3, 'duration' => 15, 'priority' => 'recommended', 'rationale' => 'Regular safety checks'],
            ],
            ScenarioAxis::CAREGIVER_RELIEF => [
                // Focus: Caregiver support, respite, sustainability
                'RES' => ['frequency' => 2, 'duration' => 240, 'priority' => 'core', 'rationale' => 'Respite gives caregiver essential breaks'],
                'ADP' => ['frequency' => 2, 'duration' => 240, 'priority' => 'core', 'rationale' => 'Day program provides structured relief'],
                'CGC' => ['frequency' => 1, 'duration' => 60, 'priority' => 'recommended', 'rationale' => 'Caregiver coaching and support'],
                'HMK' => ['frequency' => 2, 'duration' => 120, 'priority' => 'recommended', 'rationale' => 'Homemaking reduces caregiver burden'],
            ],
            ScenarioAxis::TECH_ENABLED => [
                // Focus: Remote monitoring, virtual care, reduced travel
                'RPM' => ['frequency' => 7, 'duration' => 15, 'priority' => 'core', 'rationale' => 'Remote vital sign monitoring'],
                'TELE' => ['frequency' => 2, 'duration' => 30, 'priority' => 'core', 'rationale' => 'Telehealth replaces some in-person visits'],
                'VPC' => ['frequency' => 1, 'duration' => 20, 'priority' => 'recommended', 'rationale' => 'Virtual primary care access'],
                'MED-DISP' => ['frequency' => 7, 'duration' => 5, 'priority' => 'recommended', 'rationale' => 'Automated medication management'],
            ],
            ScenarioAxis::COGNITIVE_SUPPORT => [
                // Focus: Cognitive stimulation, behaviour support, structure
                'DEM' => ['frequency' => 3, 'duration' => 120, 'priority' => 'core', 'rationale' => 'Specialized dementia care'],
                'BEH' => ['frequency' => 2, 'duration' => 90, 'priority' => 'core', 'rationale' => 'Behavioural support interventions'],
                'ADP' => ['frequency' => 2, 'duration' => 240, 'priority' => 'recommended', 'rationale' => 'Structured programming aids cognition'],
            ],
            ScenarioAxis::RECOVERY_REHAB => [
                // Focus: Intensive therapy, functional restoration
                // Note: PT/OT already boosted by algorithm modifiers - add complementary services
                'SLP' => ['frequency' => 1, 'duration' => 45, 'priority' => 'recommended', 'rationale' => 'Speech therapy if communication affected'],
            ],
            ScenarioAxis::MEDICAL_INTENSIVE => [
                // Focus: Complex medical management
                'DEL-ACTS' => ['frequency' => 5, 'duration' => 45, 'priority' => 'core', 'rationale' => 'Delegated nursing acts for complex care'],
                'RPM' => ['frequency' => 7, 'duration' => 15, 'priority' => 'core', 'rationale' => 'Continuous vital sign monitoring'],
            ],
            ScenarioAxis::BALANCED => [
                // Balanced uses algorithm baseline with minimal additions
                'MEAL' => ['frequency' => 3, 'duration' => 15, 'priority' => 'recommended', 'rationale' => 'Nutritional support'],
            ],
            default => [],
        };

        // Get existing service codes to avoid duplicates
        $existingCodes = array_map(fn($s) => $s['service_type']->code ?? '', $services);

        // Add axis-specific services
        foreach ($axisAdditions as $code => $config) {
            if (in_array($code, $existingCodes)) {
                continue; // Algorithm already included this service
            }

            $serviceType = ServiceType::where('code', $code)->first();
            if (!$serviceType) {
                continue;
            }

            $services[] = [
                'service_type' => $serviceType,
                'frequency_count' => $config['frequency'],
                'frequency_period' => 'week',
                'duration_minutes' => $config['duration'],
                'cost_per_visit' => $this->getEffectiveRate($serviceType),
                'is_required' => $config['priority'] === 'core',
                'priority_level' => $config['priority'],
                'algorithm_source' => 'axis_addition',
                'clinical_rationale' => $config['rationale'],
            ];
        }

        return $services;
    }

    /**
     * Fallback rule-based service calculation (original logic).
     */
    protected function getRuleBasedServices(PatientNeedsProfile $profile): array
    {
        $services = [];

        // Always include nursing for baseline monitoring
        $nursing = ServiceType::where('code', 'NUR')->first();
        if ($nursing) {
            $frequency = match (true) {
                $profile->healthInstability >= 4 => 5,
                $profile->healthInstability >= 3 => 3,
                $profile->healthInstability >= 2 => 2,
                default => 1, // Minimum baseline
            };
            $services[] = [
                'service_type' => $nursing,
                'frequency_count' => $frequency,
                'frequency_period' => 'week',
                'duration_minutes' => $nursing->default_duration_minutes ?? 60,
                'cost_per_visit' => $this->getEffectiveRate($nursing),
                'is_required' => true,
                'priority_level' => 'core',
            ];
        }

        // Always include PSW for daily living support
        $psw = ServiceType::where('code', 'PSW')->first();
        if ($psw) {
            $frequency = match (true) {
                $profile->adlSupportLevel >= 5 => 14, // 2x daily
                $profile->adlSupportLevel >= 4 => 7,  // daily
                $profile->adlSupportLevel >= 3 => 5,  // 5x/week
                $profile->adlSupportLevel >= 2 => 3,  // 3x/week
                default => 2, // Minimum baseline
            };
            $services[] = [
                'service_type' => $psw,
                'frequency_count' => $frequency,
                'frequency_period' => 'week',
                'duration_minutes' => $psw->default_duration_minutes ?? 60,
                'cost_per_visit' => $this->getEffectiveRate($psw),
                'is_required' => $profile->adlSupportLevel >= 3,
                'priority_level' => $profile->adlSupportLevel >= 3 ? 'core' : 'recommended',
            ];
        }

        // Add therapy if rehab potential
        if ($profile->hasRehabPotential || $profile->rehabPotentialScore >= 30) {
            $pt = ServiceType::where('code', 'PT')->first();
            $ot = ServiceType::where('code', 'OT')->first();

            if ($pt) {
                $services[] = [
                    'service_type' => $pt,
                    'frequency_count' => 2,
                    'frequency_period' => 'week',
                    'duration_minutes' => 45,
                    'cost_per_visit' => $this->getEffectiveRate($pt),
                    'is_required' => false,
                    'priority_level' => 'recommended',
                ];
            }

            if ($ot) {
                $services[] = [
                    'service_type' => $ot,
                    'frequency_count' => 1,
                    'frequency_period' => 'week',
                    'duration_minutes' => 45,
                    'cost_per_visit' => $this->getEffectiveRate($ot),
                    'is_required' => false,
                    'priority_level' => 'recommended',
                ];
            }
        }

        // Add social work if cognitive/behavioural complexity
        if ($profile->cognitiveComplexity >= 2 || $profile->behaviouralComplexity >= 2) {
            $sw = ServiceType::where('code', 'SW')->first();
            if ($sw) {
                $services[] = [
                    'service_type' => $sw,
                    'frequency_count' => 1,
                    'frequency_period' => 'week',
                    'duration_minutes' => 60,
                    'cost_per_visit' => $this->getEffectiveRate($sw),
                    'is_required' => false,
                    'priority_level' => 'recommended',
                ];
            }
        }

        // Add homemaking if IADL needs
        if ($profile->iadlSupportLevel >= 2) {
            $hmk = ServiceType::where('code', 'HMK')->first();
            if ($hmk) {
                $services[] = [
                    'service_type' => $hmk,
                    'frequency_count' => 1,
                    'frequency_period' => 'week',
                    'duration_minutes' => 120,
                    'cost_per_visit' => $this->getEffectiveRate($hmk),
                    'is_required' => false,
                    'priority_level' => 'optional',
                ];
            }
        }

        return $services;
    }

    /**
     * Ensure baseline services are present in the service list.
     */
    protected function ensureBaselineServices(array $services, PatientNeedsProfile $profile): array
    {
        $serviceCodes = collect($services)->pluck('service_type.code')->toArray();

        // Always need nursing for monitoring
        if (!in_array('NUR', $serviceCodes)) {
            $nursing = ServiceType::where('code', 'NUR')->first();
            if ($nursing) {
                $services[] = [
                    'service_type' => $nursing,
                    'frequency_count' => 1,
                    'frequency_period' => 'week',
                    'duration_minutes' => 60,
                    'cost_per_visit' => $this->getEffectiveRate($nursing),
                    'is_required' => true,
                    'priority_level' => 'core',
                    'clinical_rationale' => 'Baseline nursing for care coordination',
                ];
            }
        }

        // Need PSW if ADL support required
        if (!in_array('PSW', $serviceCodes) && $profile->adlSupportLevel >= 2) {
            $psw = ServiceType::where('code', 'PSW')->first();
            if ($psw) {
                $services[] = [
                    'service_type' => $psw,
                    'frequency_count' => max(2, $profile->adlSupportLevel),
                    'frequency_period' => 'week',
                    'duration_minutes' => 60,
                    'cost_per_visit' => $this->getEffectiveRate($psw),
                    'is_required' => $profile->adlSupportLevel >= 3,
                    'priority_level' => 'recommended',
                    'clinical_rationale' => 'ADL support based on functional needs',
                ];
            }
        }

        return $services;
    }

    /**
     * Map service codes to modifier category keys.
     * This allows axis modifiers to use generic categories while
     * matching actual service type codes.
     */
    protected const SERVICE_CODE_TO_MODIFIER_MAP = [
        // Therapy services
        'PT' => 'therapy',
        'OT' => 'therapy',
        'SLP' => 'therapy',
        'RT' => 'respiratory',
        
        // Nursing services
        'NUR' => 'nursing',
        'NP' => 'nursing',
        
        // Personal support
        'PSW' => 'psw',
        'DEM' => 'behavioural_psw',
        'BEH' => 'behavioural_psw',
        
        // Remote monitoring & tech
        'RPM' => 'remote_monitoring',
        'PERS' => 'remote_monitoring',
        'PERS-ADV' => 'remote_monitoring',
        'FALL-MON' => 'remote_monitoring',
        'CDM' => 'remote_monitoring',
        'MED-DISP' => 'remote_monitoring',
        
        // Telehealth
        'TELE' => 'telehealth',
        'VPC' => 'telehealth',
        
        // Respite & caregiver
        'RES' => 'respite',
        'CGC' => 'caregiver_education',
        
        // Homemaking & meals
        'HMK' => 'homemaking',
        'MEAL' => 'meals',
        'MOW' => 'meals',
        
        // Day programs & activation
        'ADP' => 'day_program',
        'REC' => 'activation',
        
        // Transportation
        'TRANS' => 'transportation',
        
        // Wound care
        'DEL-ACTS' => 'wound_care',
    ];

    /**
     * Apply axis modifiers to services.
     */
    protected function applyAxisModifiers(array $services, ScenarioAxis $axis, PatientNeedsProfile $profile, float $weight = 1.0): array
    {
        $modifiers = $axis->getServiceModifiers();

        if (empty($modifiers)) {
            return $services;
        }

        foreach ($services as &$service) {
            $serviceType = $service['service_type'];
            $code = $serviceType->code ?? '';
            
            // Map service code to modifier category
            $modifierKey = self::SERVICE_CODE_TO_MODIFIER_MAP[$code] ?? null;

            if ($modifierKey && isset($modifiers[$modifierKey])) {
                $modifier = $modifiers[$modifierKey];
                $multiplier = 1 + (($modifier['multiplier'] - 1) * $weight);

                // Apply frequency multiplier
                $service['frequency_count'] = max(1, (int) round($service['frequency_count'] * $multiplier));

                // Update priority level
                if ($modifier['priority'] === 'core') {
                    $service['priority_level'] = 'core';
                    $service['is_required'] = true;
                }
            }
        }

        return $services;
    }

    /**
     * Build service lines from modified services.
     *
     * v2.2: Now includes algorithm-driven clinical rationale.
     */
    protected function buildServiceLines(array $services, ScenarioAxis $axis, PatientNeedsProfile $profile): array
    {
        $serviceLines = [];

        foreach ($services as $service) {
            $serviceType = $service['service_type'];

            // Use algorithm-provided rationale if available, otherwise generate
            $rationale = $service['clinical_rationale'] 
                ?? $this->generateClinicalRationale($serviceType, $profile, $service);

            // Add CAP indicator to rationale if triggered
            if (!empty($service['cap_triggered'])) {
                $rationale .= ' [CAP-triggered]';
            }

            $serviceLine = new ScenarioServiceLine(
                // Required parameters first
                serviceCategory: $serviceType->category ?? 'general',
                serviceName: $serviceType->name,
                frequencyCount: $service['frequency_count'],
                frequencyPeriod: $service['frequency_period'],
                durationMinutes: $service['duration_minutes'],
                discipline: $this->getDisciplineForServiceType($serviceType),
                // Optional parameters
                serviceModuleId: $serviceType->id,
                serviceCode: $serviceType->code,
                requiresSpecialization: (bool) ($serviceType->requires_specialization ?? false),
                deliveryMode: $serviceType->delivery_mode ?? 'in_person',
                costPerVisit: $service['cost_per_visit'],
                weeklyEstimatedCost: $this->calculateWeeklyCost($service),
                priorityLevel: $service['priority_level'],
                isSafetyCritical: $service['is_required'] ?? false,
                clinicalRationale: $rationale,
                patientGoalSupported: $this->getGoalForService($serviceType, $axis),
                axisContribution: $this->getAxisContribution($serviceType, $axis),
            );

            $serviceLines[] = $serviceLine;
        }

        return $serviceLines;
    }

    /**
     * Build flags from profile for template matching.
     */
    protected function buildFlagsFromProfile(PatientNeedsProfile $profile): array
    {
        return [
            'high_adl' => $profile->adlSupportLevel >= 4,
            'moderate_adl' => $profile->adlSupportLevel >= 2 && $profile->adlSupportLevel < 4,
            'cognitive_impairment' => $profile->cognitiveComplexity >= 3,
            'behavioural' => $profile->behaviouralComplexity >= 2,
            'falls_risk' => $profile->fallsRiskLevel >= 2,
            'health_instability' => $profile->healthInstability >= 3,
            'skin_risk' => $profile->skinIntegrityRisk >= 2,
            'rehab_potential' => $profile->hasRehabPotential,
            'extensive_services' => $profile->requiresExtensiveServices,
            'caregiver_stress' => $profile->caregiverStressLevel >= 3,
            'lives_alone' => $profile->livesAlone,
            'tech_ready' => $profile->technologyReadiness >= 2,
        ];
    }

    /**
     * Generate scenario title.
     */
    protected function generateTitle(ScenarioAxis $axis, array $secondaryAxes): string
    {
        $title = $axis->getLabel();

        if (!empty($secondaryAxes)) {
            $secondary = array_map(fn($a) => $a->getLabel(), $secondaryAxes);
            $title .= ' + ' . implode(' + ', $secondary);
        }

        return $title;
    }

    /**
     * Generate scenario description.
     */
    protected function generateDescription(ScenarioAxis $axis, PatientNeedsProfile $profile): string
    {
        return $axis->getDescription();
    }

    /**
     * Get key benefits for an axis.
     */
    protected function getKeyBenefits(ScenarioAxis $axis, PatientNeedsProfile $profile): array
    {
        $benefits = [];

        switch ($axis) {
            case ScenarioAxis::RECOVERY_REHAB:
                $benefits = [
                    'Intensive therapy to accelerate recovery',
                    'Goal-focused approach to restore function',
                    'Support for returning to independence',
                ];
                break;

            case ScenarioAxis::SAFETY_STABILITY:
                $benefits = [
                    'Daily monitoring for early problem detection',
                    'Consistent support to prevent falls and crises',
                    'Peace of mind for patient and family',
                ];
                break;

            case ScenarioAxis::TECH_ENABLED:
                $benefits = [
                    'Continuous monitoring without disruption',
                    'Fewer in-person visits while maintaining oversight',
                    'Quick response to changes in condition',
                ];
                break;

            case ScenarioAxis::CAREGIVER_RELIEF:
                $benefits = [
                    'Scheduled respite for family caregivers',
                    'Professional support to sustain caregiving',
                    'Reduced caregiver burnout risk',
                ];
                break;

            default:
                $benefits = [
                    'Comprehensive coverage across all care domains',
                    'Balanced approach to patient needs',
                    'Flexibility to adjust as needs change',
                ];
        }

        return $benefits;
    }

    /**
     * Get risks addressed by service lines.
     */
    protected function getRisksAddressed(array $serviceLines, PatientNeedsProfile $profile): array
    {
        $risks = [];

        if ($profile->fallsRiskLevel >= 2) {
            $risks[] = 'Falls prevention';
        }
        if ($profile->healthInstability >= 3) {
            $risks[] = 'Health stability monitoring';
        }
        if ($profile->skinIntegrityRisk >= 2) {
            $risks[] = 'Skin integrity management';
        }
        if ($profile->cognitiveComplexity >= 3) {
            $risks[] = 'Cognitive support and supervision';
        }
        if ($profile->behaviouralComplexity >= 2) {
            $risks[] = 'Behavioural support';
        }

        return $risks;
    }

    /**
     * Determine confidence level for generated scenario.
     */
    protected function determineConfidence(PatientNeedsProfile $profile, ?CareBundleTemplate $template): string
    {
        if ($template && $profile->rugGroup) {
            return 'high';
        }
        if ($template) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Get confidence notes.
     */
    protected function getConfidenceNotes(PatientNeedsProfile $profile, ?CareBundleTemplate $template): string
    {
        if ($template && $profile->rugGroup) {
            return "Based on RUG-III/HC classification ({$profile->rugGroup}) with matched template";
        }
        if ($template && $profile->needsCluster) {
            return "Based on needs cluster ({$profile->needsCluster}) with approximate template match";
        }
        if (!$template) {
            return 'Default services based on profile characteristics';
        }
        return 'Template-based scenario';
    }

    /**
     * Get discipline for a service type.
     */
    protected function getDisciplineForServiceType(ServiceType $serviceType): string
    {
        $code = strtoupper($serviceType->code ?? '');
        $category = strtolower($serviceType->category ?? '');

        if (str_starts_with($code, 'NUR') || $category === 'nursing') {
            return 'rn';
        }
        if (str_starts_with($code, 'PSW') || $category === 'psw') {
            return 'psw';
        }
        if ($code === 'PT' || str_contains($category, 'physio')) {
            return 'pt';
        }
        if ($code === 'OT' || str_contains($category, 'occupational')) {
            return 'ot';
        }
        if ($code === 'SLP' || str_contains($category, 'speech')) {
            return 'slp';
        }
        if (str_contains($category, 'social')) {
            return 'sw';
        }

        return 'css';
    }

    /**
     * Calculate weekly cost for a service.
     */
    protected function calculateWeeklyCost(array $service): float
    {
        $frequency = $service['frequency_count'];
        $period = $service['frequency_period'];
        $costPerVisit = $service['cost_per_visit'];

        $weeklyVisits = match ($period) {
            'day' => $frequency * 7,
            'week' => $frequency,
            'month' => $frequency / 4.33,
            default => $frequency,
        };

        return $weeklyVisits * $costPerVisit;
    }

    /**
     * Generate clinical rationale for a service.
     *
     * v2.2: Now includes algorithm scores and CAP triggers in rationale.
     */
    protected function generateClinicalRationale(ServiceType $serviceType, PatientNeedsProfile $profile, ?array $serviceData = null): string
    {
        $code = strtoupper($serviceType->code ?? '');
        $category = strtolower($serviceType->category ?? '');

        // Check if we have algorithm-driven rationale
        if ($serviceData && isset($serviceData['clinical_rationale'])) {
            return $serviceData['clinical_rationale'];
        }

        // Build algorithm-aware rationale
        $rationale = match ($code) {
            'NUR' => $this->getNursingRationale($profile),
            'PSW' => $this->getPswRationale($profile),
            'PT' => $this->getPtRationale($profile),
            'OT' => $this->getOtRationale($profile),
            'SW' => $this->getSwRationale($profile),
            default => match ($category) {
                'nursing' => 'Clinical monitoring and care coordination',
                'psw' => 'Personal care and daily living support',
                'therapy', 'pt', 'ot' => 'Functional restoration and mobility support',
                'respite' => 'Caregiver support and sustainability',
                'remote_monitoring' => 'Continuous health monitoring',
                default => 'Comprehensive care support',
            },
        };

        return $rationale;
    }

    /**
     * Generate nursing-specific rationale based on algorithm scores.
     */
    protected function getNursingRationale(PatientNeedsProfile $profile): string
    {
        $reasons = [];

        if ($profile->chessCAScore >= 3) {
            $reasons[] = "CHESS-CA {$profile->chessCAScore}/5 indicates health instability";
        }
        if ($profile->painScore >= 3) {
            $reasons[] = "Pain Scale {$profile->painScore}/4 requires monitoring";
        }
        if ($profile->serviceUrgencyScore >= 3) {
            $reasons[] = "Service Urgency {$profile->serviceUrgencyScore}/4 - clinical services needed within 72h";
        }

        if (empty($reasons)) {
            return 'Baseline nursing for care coordination and monitoring';
        }

        return implode('; ', $reasons);
    }

    /**
     * Generate PSW-specific rationale based on algorithm scores.
     */
    protected function getPswRationale(PatientNeedsProfile $profile): string
    {
        $psa = $profile->personalSupportScore;
        $label = match (true) {
            $psa >= 5 => 'high',
            $psa >= 3 => 'moderate',
            default => 'light',
        };

        $rationale = "PSA {$psa}/6 indicates {$label} personal support need";

        if (!$profile->selfRelianceIndex) {
            $rationale .= '; not self-reliant in ADL/cognition';
        }

        return $rationale;
    }

    /**
     * Generate PT-specific rationale based on algorithm scores.
     */
    protected function getPtRationale(PatientNeedsProfile $profile): string
    {
        $rehab = $profile->rehabilitationScore;
        $label = match (true) {
            $rehab >= 4 => 'high',
            $rehab >= 3 => 'moderate',
            default => 'maintenance',
        };

        return "Rehabilitation {$rehab}/5 indicates {$label} PT/OT rehabilitation potential";
    }

    /**
     * Generate OT-specific rationale based on algorithm scores.
     */
    protected function getOtRationale(PatientNeedsProfile $profile): string
    {
        $reasons = [];

        if ($profile->rehabilitationScore >= 3) {
            $reasons[] = "Rehab {$profile->rehabilitationScore}/5 for functional improvement";
        }
        if ($profile->iadlSupportLevel >= 3) {
            $reasons[] = 'IADL deficits for skill-building';
        }
        if ($profile->hasHomeEnvironmentRisk) {
            $reasons[] = 'Home environment safety assessment';
        }

        return empty($reasons) ? 'Occupational therapy for daily function' : implode('; ', $reasons);
    }

    /**
     * Generate SW-specific rationale based on algorithm scores.
     */
    protected function getSwRationale(PatientNeedsProfile $profile): string
    {
        $reasons = [];

        if ($profile->distressedMoodScore >= 3) {
            $reasons[] = "DMS {$profile->distressedMoodScore}/9 - mood support needed";
        }
        if ($profile->caregiverStressLevel >= 3) {
            $reasons[] = 'Caregiver stress - support/respite planning';
        }
        if ($profile->livesAlone && $profile->cognitiveComplexity >= 2) {
            $reasons[] = 'Lives alone with cognitive needs - community linkage';
        }

        return empty($reasons) ? 'Psychosocial support and care coordination' : implode('; ', $reasons);
    }

    /**
     * Get patient goal supported by a service.
     */
    protected function getGoalForService(ServiceType $serviceType, ScenarioAxis $axis): string
    {
        $goals = $axis->getEmphasizedGoals();
        return $goals[0] ?? 'overall_wellbeing';
    }

    /**
     * Get axis contribution description.
     */
    protected function getAxisContribution(ServiceType $serviceType, ScenarioAxis $axis): string
    {
        $emphases = $axis->getEmphasizedServiceCategories();
        $category = $serviceType->category ?? '';

        if (in_array($category, $emphases)) {
            return "Primary contributor to {$axis->getLabel()}";
        }
        return 'Supporting service';
    }

    /**
     * Check if scenario has falls prevention services.
     */
    protected function hasFallsPreventionServices(ScenarioBundleDTO $scenario): bool
    {
        foreach ($scenario->serviceLines as $line) {
            if (in_array($line->serviceCategory, ['nursing', 'pt', 'ot', 'remote_monitoring'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if scenario has cognitive support services.
     */
    protected function hasCognitiveSupportServices(ScenarioBundleDTO $scenario): bool
    {
        foreach ($scenario->serviceLines as $line) {
            if (in_array($line->serviceCategory, ['psw', 'behavioural_psw', 'activation', 'day_program'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if scenario covers required extensive services.
     */
    protected function hasExtensiveServiceCoverage(ScenarioBundleDTO $scenario, PatientNeedsProfile $profile): bool
    {
        $extensiveServices = $profile->extensiveServices ?? [];
        if (empty($extensiveServices)) {
            return true;
        }

        $hasNursing = $scenario->hasServiceCategory('nursing');
        return $hasNursing;
    }

    /**
     * Describe emphasis shift between two scenarios.
     */
    protected function describeEmphasisShift(ScenarioBundleDTO $s1, ScenarioBundleDTO $s2): string
    {
        return sprintf(
            'From %s to %s emphasis',
            $s1->primaryAxis->getLabel(),
            $s2->primaryAxis->getLabel()
        );
    }

    /**
     * Add safety warnings to a scenario.
     */
    protected function addSafetyWarnings(ScenarioBundleDTO $scenario, array $warnings): ScenarioBundleDTO
    {
        return new ScenarioBundleDTO(
            scenarioId: $scenario->scenarioId,
            patientId: $scenario->patientId,
            primaryAxis: $scenario->primaryAxis,
            secondaryAxes: $scenario->secondaryAxes,
            title: $scenario->title,
            subtitle: $scenario->subtitle,
            description: $scenario->description,
            icon: $scenario->icon,
            serviceLines: $scenario->serviceLines,
            weeklyEstimatedCost: $scenario->weeklyEstimatedCost,
            referenceCap: $scenario->referenceCap,
            costStatus: $scenario->costStatus,
            capUtilization: $scenario->capUtilization,
            costNote: $scenario->costNote,
            totalWeeklyHours: $scenario->totalWeeklyHours,
            totalWeeklyVisits: $scenario->totalWeeklyVisits,
            inPersonPercentage: $scenario->inPersonPercentage,
            virtualPercentage: $scenario->virtualPercentage,
            disciplineCount: $scenario->disciplineCount,
            tradeOffs: $scenario->tradeOffs,
            keyBenefits: $scenario->keyBenefits,
            patientGoalsSupported: $scenario->patientGoalsSupported,
            risksAddressed: $scenario->risksAddressed,
            meetsSafetyRequirements: empty($warnings),
            safetyWarnings: $warnings,
            isValidated: true,
            source: $scenario->source,
            confidenceLevel: $scenario->confidenceLevel,
            confidenceNotes: $scenario->confidenceNotes,
            aiExplanation: $scenario->aiExplanation,
            hasAiExplanation: $scenario->hasAiExplanation,
            generatedAt: $scenario->generatedAt,
            displayOrder: $scenario->displayOrder,
            isRecommended: $scenario->isRecommended,
        );
    }

    /**
     * Set display order on a scenario.
     */
    protected function setDisplayOrder(ScenarioBundleDTO $scenario, int $order): ScenarioBundleDTO
    {
        return new ScenarioBundleDTO(
            scenarioId: $scenario->scenarioId,
            patientId: $scenario->patientId,
            primaryAxis: $scenario->primaryAxis,
            secondaryAxes: $scenario->secondaryAxes,
            title: $scenario->title,
            subtitle: $scenario->subtitle,
            description: $scenario->description,
            icon: $scenario->icon,
            serviceLines: $scenario->serviceLines,
            weeklyEstimatedCost: $scenario->weeklyEstimatedCost,
            referenceCap: $scenario->referenceCap,
            costStatus: $scenario->costStatus,
            capUtilization: $scenario->capUtilization,
            costNote: $scenario->costNote,
            totalWeeklyHours: $scenario->totalWeeklyHours,
            totalWeeklyVisits: $scenario->totalWeeklyVisits,
            inPersonPercentage: $scenario->inPersonPercentage,
            virtualPercentage: $scenario->virtualPercentage,
            disciplineCount: $scenario->disciplineCount,
            tradeOffs: $scenario->tradeOffs,
            keyBenefits: $scenario->keyBenefits,
            patientGoalsSupported: $scenario->patientGoalsSupported,
            risksAddressed: $scenario->risksAddressed,
            meetsSafetyRequirements: $scenario->meetsSafetyRequirements,
            safetyWarnings: $scenario->safetyWarnings,
            isValidated: $scenario->isValidated,
            source: $scenario->source,
            confidenceLevel: $scenario->confidenceLevel,
            confidenceNotes: $scenario->confidenceNotes,
            aiExplanation: $scenario->aiExplanation,
            hasAiExplanation: $scenario->hasAiExplanation,
            generatedAt: $scenario->generatedAt,
            displayOrder: $order,
            isRecommended: $scenario->isRecommended,
        );
    }

    /**
     * Mark a scenario as recommended.
     */
    protected function markAsRecommended(ScenarioBundleDTO $scenario): ScenarioBundleDTO
    {
        return new ScenarioBundleDTO(
            scenarioId: $scenario->scenarioId,
            patientId: $scenario->patientId,
            primaryAxis: $scenario->primaryAxis,
            secondaryAxes: $scenario->secondaryAxes,
            title: $scenario->title,
            subtitle: $scenario->subtitle,
            description: $scenario->description,
            icon: $scenario->icon,
            serviceLines: $scenario->serviceLines,
            weeklyEstimatedCost: $scenario->weeklyEstimatedCost,
            referenceCap: $scenario->referenceCap,
            costStatus: $scenario->costStatus,
            capUtilization: $scenario->capUtilization,
            costNote: $scenario->costNote,
            totalWeeklyHours: $scenario->totalWeeklyHours,
            totalWeeklyVisits: $scenario->totalWeeklyVisits,
            inPersonPercentage: $scenario->inPersonPercentage,
            virtualPercentage: $scenario->virtualPercentage,
            disciplineCount: $scenario->disciplineCount,
            tradeOffs: $scenario->tradeOffs,
            keyBenefits: $scenario->keyBenefits,
            patientGoalsSupported: $scenario->patientGoalsSupported,
            risksAddressed: $scenario->risksAddressed,
            meetsSafetyRequirements: $scenario->meetsSafetyRequirements,
            safetyWarnings: $scenario->safetyWarnings,
            isValidated: $scenario->isValidated,
            source: $scenario->source,
            confidenceLevel: $scenario->confidenceLevel,
            confidenceNotes: $scenario->confidenceNotes,
            aiExplanation: $scenario->aiExplanation,
            hasAiExplanation: $scenario->hasAiExplanation,
            generatedAt: $scenario->generatedAt,
            displayOrder: $scenario->displayOrder,
            isRecommended: true,
        );
    }
}

