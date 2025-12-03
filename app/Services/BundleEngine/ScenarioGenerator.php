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
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * ScenarioGenerator
 *
 * Generates 3-5 scenario bundles for a patient based on their needs profile.
 *
 * Generation Process:
 * 1. Get applicable scenario axes from ScenarioAxisSelector
 * 2. Find base template (by RUG group or NeedsCluster)
 * 3. For each applicable axis, generate a scenario variant
 * 4. Apply axis-specific modifiers to service frequencies
 * 5. Annotate with costs and validate safety
 *
 * Key Design Principles:
 * - Patient-experience framing, NOT "budget vs clinical"
 * - Cost is a reference, not a hard constraint
 * - All scenarios must meet minimum safety requirements
 * - Scenarios vary in emphasis, not just cost
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 5
 */
class ScenarioGenerator implements ScenarioGeneratorInterface
{
    protected ScenarioAxisSelector $axisSelector;
    protected CostAnnotationServiceInterface $costService;
    protected ServiceRateRepository $rateRepository;

    public function __construct(
        ?ScenarioAxisSelector $axisSelector = null,
        ?CostAnnotationServiceInterface $costService = null,
        ?ServiceRateRepository $rateRepository = null
    ) {
        $this->axisSelector = $axisSelector ?? new ScenarioAxisSelector();
        $this->costService = $costService ?? new CostAnnotationService();
        $this->rateRepository = $rateRepository ?? new ServiceRateRepository();
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
     * Always returns at least baseline services to ensure scenarios have content.
     * Uses rate menu (ServiceRateRepository) for pricing.
     */
    protected function getDefaultServices(PatientNeedsProfile $profile): array
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
            $category = $serviceType->category ?? 'unknown';

            if (isset($modifiers[$category])) {
                $modifier = $modifiers[$category];
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
     */
    protected function buildServiceLines(array $services, ScenarioAxis $axis, PatientNeedsProfile $profile): array
    {
        $serviceLines = [];

        foreach ($services as $service) {
            $serviceType = $service['service_type'];

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
                clinicalRationale: $this->generateClinicalRationale($serviceType, $profile),
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
     */
    protected function generateClinicalRationale(ServiceType $serviceType, PatientNeedsProfile $profile): string
    {
        $category = strtolower($serviceType->category ?? '');

        return match ($category) {
            'nursing' => 'Clinical monitoring and care coordination',
            'psw' => 'Personal care and daily living support',
            'therapy', 'pt', 'ot' => 'Functional restoration and mobility support',
            'respite' => 'Caregiver support and sustainability',
            'remote_monitoring' => 'Continuous health monitoring',
            default => 'Comprehensive care support',
        };
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

