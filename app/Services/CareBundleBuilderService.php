<?php

namespace App\Services;

use App\Models\BundleConfigurationRule;
use App\Models\CareBundle;
use App\Models\CareBundleTemplate;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\RUGClassification;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Models\TransitionNeedsProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CareBundleBuilderService - Metadata-driven care bundle construction
 *
 * This service implements the Workday-style model-at-runtime approach for
 * building care bundles. It:
 *
 * 1. Retrieves bundle templates with their service configurations
 * 2. Applies metadata-driven rules based on patient context (TNP, clinical flags)
 * 3. Generates customized service lists for the patient
 * 4. Handles the transition from queue to active patient profile
 *
 * The service consumes metadata to make decisions, allowing business rules
 * to be modified without code changes.
 */
class CareBundleBuilderService
{
    protected MetadataEngine $metadataEngine;
    protected CareBundleTemplateRepository $templateRepository;
    protected RUGClassificationService $rugService;

    public function __construct(
        MetadataEngine $metadataEngine,
        ?CareBundleTemplateRepository $templateRepository = null,
        ?RUGClassificationService $rugService = null
    ) {
        $this->metadataEngine = $metadataEngine;
        $this->templateRepository = $templateRepository ?? new CareBundleTemplateRepository();
        $this->rugService = $rugService ?? new RUGClassificationService();
    }

    /*
    |--------------------------------------------------------------------------
    | RUG-BASED BUNDLE METHODS (CC2.1 Architecture)
    |--------------------------------------------------------------------------
    */

    /**
     * Get RUG-based bundle recommendations for a patient.
     *
     * This is the new primary method for getting bundle recommendations.
     * It uses the patient's RUG classification to find matching templates.
     *
     * @param int $patientId
     * @return array
     */
    public function getRugBasedBundles(int $patientId): array
    {
        $patient = Patient::with([
            'latestInterraiAssessment',
            'latestRugClassification',
        ])->find($patientId);

        if (!$patient) {
            return ['error' => 'Patient not found', 'bundles' => []];
        }

        // Check for RUG classification
        $rug = $patient->latestRugClassification;

        if (!$rug) {
            // Try to generate classification from assessment
            $assessment = $patient->latestInterraiAssessment;
            if ($assessment) {
                $rug = $this->rugService->classify($assessment);
            } else {
                return [
                    'error' => 'No InterRAI assessment available',
                    'message' => 'Patient requires InterRAI HC assessment before bundle selection',
                    'bundles' => [],
                ];
            }
        }

        // Get matching templates
        $matches = $this->templateRepository->findAllMatchingTemplates($rug);

        // Build response with configured bundles
        $bundles = $matches->map(function ($match) use ($patient, $rug) {
            return $this->configureTemplateForPatient(
                $match['template'],
                $patient,
                $rug,
                $match['is_recommended'],
                $match['match_score'],
                $match['match_type']
            );
        })->toArray();

        return [
            'patient_id' => $patientId,
            'rug_classification' => $rug->toSummaryArray(),
            'bundles' => $bundles,
        ];
    }

    /**
     * Get a specific RUG template configured for a patient.
     *
     * @param int $templateId
     * @param int $patientId
     * @return array|null
     */
    public function getRugTemplateForPatient(int $templateId, int $patientId): ?array
    {
        $patient = Patient::with([
            'latestInterraiAssessment',
            'latestRugClassification',
        ])->find($patientId);

        if (!$patient) {
            return null;
        }

        $template = $this->templateRepository->findById($templateId);
        if (!$template) {
            return null;
        }

        $rug = $patient->latestRugClassification;
        $isRecommended = $rug && $template->rug_group === $rug->rug_group;
        $matchScore = $rug ? $this->calculateTemplateMatchScore($template, $rug) : 50;

        return $this->configureTemplateForPatient(
            $template,
            $patient,
            $rug,
            $isRecommended,
            $matchScore,
            $isRecommended ? 'exact' : 'manual'
        );
    }

    /**
     * Configure a template's services for a specific patient.
     */
    protected function configureTemplateForPatient(
        CareBundleTemplate $template,
        Patient $patient,
        ?RUGClassification $rug,
        bool $isRecommended,
        int $matchScore,
        string $matchType
    ): array {
        $services = [];
        $flags = $rug?->flags ?? [];

        // Get services applicable for this patient's flags
        $templateServices = $template->getServicesForFlags($flags);

        foreach ($templateServices as $templateService) {
            $serviceType = $templateService->serviceType;
            if (!$serviceType) {
                continue;
            }

            $services[] = [
                'id' => (string) $serviceType->id,
                'service_type_id' => $serviceType->id,
                'category' => $serviceType->serviceCategory?->code ?? $serviceType->category ?? 'OTHER',
                'name' => $serviceType->name,
                'code' => $serviceType->code,
                'description' => $serviceType->description,
                'defaultFrequency' => $templateService->default_frequency_per_week,
                'defaultDuration' => $templateService->default_duration_weeks,
                'defaultDurationMinutes' => $templateService->default_duration_minutes,
                'currentFrequency' => $templateService->default_frequency_per_week,
                'currentDuration' => $templateService->default_duration_weeks,
                'provider' => '',
                'provider_id' => null,
                'costPerVisit' => $templateService->getEffectiveCostPerVisit() / 100,
                'weeklyCost' => $templateService->calculateWeeklyCost() / 100,
                'assignment_type' => $templateService->assignment_type,
                'role_required' => $templateService->role_required,
                'is_required' => $templateService->is_required,
                'is_conditional' => $templateService->is_conditional,
                'condition_flags' => $templateService->condition_flags,
            ];
        }

        // Calculate costs
        $estimatedWeeklyCost = array_sum(array_column($services, 'weeklyCost'));
        $estimatedMonthlyCost = $estimatedWeeklyCost * 4;

        return [
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'description' => $template->description,
            'rug_group' => $template->rug_group,
            'rug_category' => $template->rug_category,
            'funding_stream' => $template->funding_stream,
            'weekly_cap' => $template->weekly_cap,
            'colorTheme' => $this->getRugColorTheme($template->rug_category),
            'band' => $this->getRugBand($template->rug_category),
            'isRecommended' => $isRecommended,
            'matchScore' => $matchScore,
            'matchType' => $matchType,
            'recommendationReason' => $isRecommended
                ? $this->getRugRecommendationReason($template, $rug)
                : null,
            'services' => $services,
            'serviceCount' => count($services),
            'estimatedWeeklyCost' => $estimatedWeeklyCost,
            'estimatedMonthlyCost' => $estimatedMonthlyCost,
            'withinBudget' => $estimatedWeeklyCost <= $template->weekly_cap,
            'clinical_notes' => $template->clinical_notes,
        ];
    }

    /**
     * Calculate template match score.
     */
    protected function calculateTemplateMatchScore(CareBundleTemplate $template, RUGClassification $rug): int
    {
        $score = 50; // Base score

        if ($template->rug_group === $rug->rug_group) {
            $score += 50;
        } elseif ($template->rug_category === $rug->rug_category) {
            $score += 25;
        }

        if ($template->matchesFlags($rug->flags ?? [])) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Get RUG-based color theme.
     */
    protected function getRugColorTheme(?string $category): string
    {
        return match ($category) {
            RUGClassification::CATEGORY_SPECIAL_REHABILITATION => 'green',
            RUGClassification::CATEGORY_EXTENSIVE_SERVICES => 'red',
            RUGClassification::CATEGORY_SPECIAL_CARE => 'orange',
            RUGClassification::CATEGORY_CLINICALLY_COMPLEX => 'purple',
            RUGClassification::CATEGORY_IMPAIRED_COGNITION => 'amber',
            RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS => 'pink',
            RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION => 'blue',
            default => 'gray',
        };
    }

    /**
     * Get RUG-based band.
     */
    protected function getRugBand(?string $category): string
    {
        return match ($category) {
            RUGClassification::CATEGORY_SPECIAL_REHABILITATION,
            RUGClassification::CATEGORY_EXTENSIVE_SERVICES => 'Band C',
            RUGClassification::CATEGORY_SPECIAL_CARE,
            RUGClassification::CATEGORY_CLINICALLY_COMPLEX => 'Band B',
            default => 'Band A',
        };
    }

    /**
     * Get RUG-based recommendation reason.
     */
    protected function getRugRecommendationReason(CareBundleTemplate $template, ?RUGClassification $rug): string
    {
        if (!$rug) {
            return 'Default template for patient profile';
        }

        $category = $rug->rug_category;
        $group = $rug->rug_group;
        $adl = $rug->adl_level;
        $cps = $rug->cps_level;

        return "RUG classification {$group} ({$category}) with {$adl} and {$cps}. " .
            "Template matched based on clinical indicators and ADL/IADL scores.";
    }

    /**
     * Build care plan from RUG template.
     *
     * @param int $patientId
     * @param int $templateId
     * @param array $serviceConfigurations
     * @param int|null $userId
     * @return CarePlan
     */
    public function buildCarePlanFromTemplate(
        int $patientId,
        int $templateId,
        array $serviceConfigurations,
        ?int $userId = null
    ): CarePlan {
        return DB::transaction(function () use ($patientId, $templateId, $serviceConfigurations, $userId) {
            $patient = Patient::findOrFail($patientId);
            $template = CareBundleTemplate::findOrFail($templateId);

            // Get or create the underlying CareBundle record for backward compatibility
            $careBundle = $this->getOrCreateCareBundleFromTemplate($template);

            // Get latest version for this patient
            $latestVersion = CarePlan::where('patient_id', $patientId)->max('version') ?? 0;

            // Create the care plan with service requirements (NO ServiceAssignments yet)
            // ServiceAssignments are created during scheduling, not plan building
            $carePlan = CarePlan::create([
                'patient_id' => $patientId,
                'care_bundle_id' => $careBundle->id,
                'care_bundle_template_id' => $template->id,
                'version' => $latestVersion + 1,
                'status' => 'draft',
                'goals' => $this->extractGoals($serviceConfigurations),
                'risks' => $patient->risk_flags ?? [],
                'interventions' => $this->extractInterventions($serviceConfigurations),
                'service_requirements' => $this->extractServiceRequirements($serviceConfigurations),
                'notes' => "Created from RUG template: {$template->name} ({$template->code})",
            ]);

            // NOTE: ServiceAssignments are NOT created here anymore.
            // They are created during the scheduling phase when staff/times are assigned.
            // The service_requirements field stores the customized requirements.

            // Update patient queue status
            try {
                $this->updateQueueStatus($patient, 'bundle_building', $userId);
            } catch (\Exception $e) {
                Log::warning("Failed to update queue status", ['error' => $e->getMessage()]);
            }

            Log::info("Care plan created from RUG template", [
                'care_plan_id' => $carePlan->id,
                'patient_id' => $patientId,
                'template_id' => $templateId,
                'template_code' => $template->code,
            ]);

            return $carePlan;
        });
    }

    /**
     * Get or create a CareBundle record from a template.
     * This maintains backward compatibility with existing CarePlan→CareBundle relationship.
     */
    protected function getOrCreateCareBundleFromTemplate(CareBundleTemplate $template): CareBundle
    {
        return CareBundle::firstOrCreate(
            ['code' => $template->code],
            [
                'name' => $template->name,
                'description' => $template->description,
                'price' => $template->weekly_cap_cents / 100 * 4, // Monthly price
                'active' => true,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LEGACY METHODS (Backward Compatibility)
    |--------------------------------------------------------------------------
    */

    /**
     * Get available bundles with their services configured for a patient.
     *
     * @param int $patientId The patient ID
     * @return array Array of bundles with configured services
     */
    public function getAvailableBundles(int $patientId): array
    {
        $patient = Patient::with(['transitionNeedsProfile', 'latestInterraiAssessment'])->find($patientId);
        if (!$patient) {
            return [];
        }

        $bundles = CareBundle::with(['serviceTypes.serviceCategory', 'configurationRules'])
            ->where('active', true)
            ->get();

        $context = $this->buildPatientContext($patient);

        return $bundles->map(function ($bundle) use ($context) {
            return $this->configureBundleForPatient($bundle, $context);
        })->toArray();
    }

    /**
     * Get a specific bundle configured for a patient.
     */
    public function getBundleForPatient(int $bundleId, int $patientId): ?array
    {
        $patient = Patient::with(['transitionNeedsProfile', 'latestInterraiAssessment'])->find($patientId);
        if (!$patient) {
            return null;
        }

        $bundle = CareBundle::with(['serviceTypes.serviceCategory', 'configurationRules'])
            ->where('id', $bundleId)
            ->where('active', true)
            ->first();

        if (!$bundle) {
            return null;
        }

        $context = $this->buildPatientContext($patient);
        return $this->configureBundleForPatient($bundle, $context);
    }

    // ... (skipping unchanged methods) ...

    /**
     * Build patient context for rule evaluation.
     *
     * IR-008-01: Enhanced to include InterRAI assessment scores for
     * bundle eligibility and service intensity recommendations.
     */
    protected function buildPatientContext(Patient $patient): array
    {
        $tnp = $patient->transitionNeedsProfile;
        $interrai = $patient->latestInterraiAssessment;

        return [
            'patient' => [
                'id' => $patient->id,
                'status' => $patient->status,
                'maple_score' => $patient->maple_score,
                'rai_cha_score' => $patient->rai_cha_score,
                'interrai_status' => $patient->interrai_status,
                'risk_flags' => $patient->risk_flags ?? [],
            ],
            'tnp' => $tnp ? [
                'clinical_flags' => $tnp->clinical_flags ?? [],
                'narrative_summary' => $tnp->narrative_summary ?? null,
                'status' => $tnp->status ?? null,
            ] : null,
            // InterRAI assessment data for bundle configuration
            'interrai' => $interrai ? [
                'assessment_date' => $interrai->assessment_date->toIso8601String(),
                'is_stale' => $interrai->isStale(),
                'days_until_stale' => $interrai->days_until_stale,
                'maple_score' => $interrai->maple_score,
                'maple_description' => $interrai->maple_description,
                'cps' => $interrai->cognitive_performance_scale,
                'cps_description' => $interrai->cps_description,
                'adl_hierarchy' => $interrai->adl_hierarchy,
                'adl_description' => $interrai->adl_description,
                'iadl_difficulty' => $interrai->iadl_difficulty,
                'chess_score' => $interrai->chess_score,
                'drs' => $interrai->depression_rating_scale,
                'pain_scale' => $interrai->pain_scale,
                'falls_risk' => $interrai->falls_in_last_90_days,
                'wandering_risk' => $interrai->wandering_flag,
                'high_risk_flags' => $interrai->high_risk_flags,
                'caps_triggered' => $interrai->caps_triggered ?? [],
            ] : null,
            'clinical_flags' => $tnp->clinical_flags ?? [],
            // Enhanced flag detection using InterRAI data
            'has_cognitive_flag' => $this->hasCognitiveNeeds($tnp, $interrai),
            'has_wound_flag' => $this->hasFlag($tnp, 'Wound'),
            'has_palliative_flag' => $this->hasFlag($tnp, 'Palliative'),
            'has_respiratory_flag' => $this->hasFlag($tnp, 'Respiratory'),
            'has_fall_risk' => $interrai?->falls_in_last_90_days ?? false,
            'has_pain_needs' => ($interrai?->pain_scale ?? 0) >= 2,
            'has_depression_risk' => ($interrai?->depression_rating_scale ?? 0) >= 3,
            'has_health_instability' => ($interrai?->chess_score ?? 0) >= 3,
            // Calculated service intensity recommendations
            'recommended_psw_hours' => $this->calculateRecommendedPswHours($interrai),
            'service_intensity_level' => $this->determineServiceIntensity($interrai),
        ];
    }

    /**
     * Check if patient has cognitive needs based on TNP and InterRAI.
     *
     * IR-008-02: Uses CPS score from InterRAI to enhance detection.
     */
    protected function hasCognitiveNeeds(?TransitionNeedsProfile $tnp, $interrai): bool
    {
        // Check TNP clinical flags first
        if ($this->hasFlag($tnp, 'Cognitive') || $this->hasFlag($tnp, 'Dementia')) {
            return true;
        }

        // Check InterRAI CPS score (3+ indicates moderate impairment)
        if ($interrai && $interrai->cognitive_performance_scale >= 3) {
            return true;
        }

        // Check for wandering flag
        if ($interrai && $interrai->wandering_flag) {
            return true;
        }

        return false;
    }

    /**
     * Calculate recommended PSW hours based on ADL hierarchy.
     *
     * IR-008-01: Uses ADL score to recommend personal support hours.
     */
    protected function calculateRecommendedPswHours($interrai): float
    {
        if (!$interrai) {
            return 0;
        }

        // ADL hierarchy to recommended weekly PSW hours mapping
        return match ($interrai->adl_hierarchy) {
            0 => 0,      // Independent - no PSW needed
            1 => 3.5,    // Supervision - minimal support
            2 => 7,      // Limited assistance - 1 hour/day
            3 => 14,     // Extensive assistance (1) - 2 hours/day
            4 => 21,     // Extensive assistance (2) - 3 hours/day
            5 => 28,     // Dependent - 4 hours/day
            6 => 35,     // Total dependence - 5 hours/day
            default => 7,
        };
    }

    /**
     * Determine service intensity level based on MAPLe and CHESS.
     *
     * IR-008-01: Combines MAPLe priority with CHESS health instability.
     */
    protected function determineServiceIntensity($interrai): string
    {
        if (!$interrai) {
            return 'standard';
        }

        $maple = (int) $interrai->maple_score;
        $chess = $interrai->chess_score ?? 0;

        // Very High MAPLe or High CHESS = intensive
        if ($maple >= 5 || $chess >= 4) {
            return 'intensive';
        }

        // High MAPLe or Moderate CHESS = enhanced
        if ($maple >= 4 || $chess >= 3) {
            return 'enhanced';
        }

        // Moderate MAPLe = moderate
        if ($maple >= 3) {
            return 'moderate';
        }

        return 'standard';
    }

    /**
     * Check if TNP has a specific flag.
     */
    protected function hasFlag(?TransitionNeedsProfile $tnp, string $flag): bool
    {
        if (!$tnp) {
            return false;
        }

        // Only use clinical_flags as functional_flags and social_flags don't exist in the TNP table
        $flags = $tnp->clinical_flags ?? [];

        foreach ($flags as $f) {
            if (str_contains(strtolower($f), strtolower($flag))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Configure a bundle's services for a specific patient.
     */
    protected function configureBundleForPatient(CareBundle $bundle, array $context): array
    {
        $services = [];

        // Get services from the bundle template
        foreach ($bundle->serviceTypes as $serviceType) {
            $pivot = $serviceType->pivot;

            $serviceConfig = [
                'id' => (string) $serviceType->id,
                'service_type_id' => $serviceType->id,
                'category' => $serviceType->serviceCategory?->code ?? $serviceType->category ?? 'OTHER',
                'name' => $serviceType->name,
                'code' => $serviceType->code,
                'description' => $serviceType->description,
                'defaultFrequency' => $pivot->default_frequency_per_week ?? 1,
                'defaultDuration' => 12, // Default 12 weeks
                'currentFrequency' => $pivot->default_frequency_per_week ?? 0,
                'currentDuration' => 12,
                'provider' => '',
                'provider_id' => $pivot->default_provider_org_id,
                'costPerVisit' => $this->getServiceCost($serviceType),
                'assignment_type' => $pivot->assignment_type ?? 'Either',
                'role_required' => $pivot->role_required,
                'flags' => [],
                'metadata' => $this->getServiceMetadata($serviceType),
            ];

            // Apply bundle configuration rules
            $serviceConfig = $this->applyBundleRules($bundle, $serviceConfig, $context);

            $services[] = $serviceConfig;
        }

        // Determine recommended status
        $isRecommended = $this->isBundleRecommended($bundle, $context);

        return [
            'id' => $bundle->id,
            'code' => $bundle->code,
            'name' => $bundle->name,
            'description' => $bundle->description,
            'price' => $bundle->price ?? $this->calculateBundlePrice($services),
            'version' => $bundle->version ?? 1,
            'colorTheme' => $this->getBundleColorTheme($bundle->code),
            'band' => $this->getBundleBand($bundle->code),
            'isRecommended' => $isRecommended,
            'recommendationReason' => $isRecommended ? $this->getRecommendationReason($bundle, $context) : null,
            'services' => $services,
            'serviceCount' => count($services),
            'estimatedMonthlyCost' => $this->calculateMonthlyCost($services),
        ];
    }

    /**
     * Apply bundle configuration rules to a service.
     */
    protected function applyBundleRules(CareBundle $bundle, array $serviceConfig, array $context): array
    {
        $rules = $bundle->configurationRules()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->evaluateConditions($context)) {
                $serviceConfig = $rule->applyActions($serviceConfig, $context);
            }
        }

        return $serviceConfig;
    }

    /**
     * Get cost for a service type.
     */
    protected function getServiceCost(ServiceType $serviceType): float
    {
        // Check metadata for custom cost
        $metadata = $serviceType->metadata ?? collect();
        $costMeta = $metadata->firstWhere('key', 'cost_per_visit');

        if ($costMeta) {
            return (float) $costMeta->value;
        }

        // Default costs by category
        return match ($serviceType->code) {
            'NUR' => 120,
            'PT' => 140,
            'OT' => 150,
            'RT' => 130,
            'SW' => 135,
            'RD' => 125,
            'SLP' => 145,
            'NP' => 200,
            'PSW' => 45,
            'HMK' => 40,
            'DEL-ACTS' => 50,
            'RES' => 45,
            'PERS' => 50,
            'RPM' => 150,
            'SEC' => 30,
            'TRANS' => 80,
            'LAB' => 60,
            'PHAR' => 25,
            'INTERP' => 100,
            'MEAL' => 15,
            'REC' => 50,
            'BEH' => 100,
            default => 100,
        };
    }

    /**
     * Get service metadata.
     */
    protected function getServiceMetadata(ServiceType $serviceType): array
    {
        $metadata = $serviceType->metadata ?? collect();

        return $metadata->mapWithKeys(function ($item) {
            return [$item->key => $item->typed_value];
        })->toArray();
    }

    /**
     * Determine if a bundle is recommended for the patient context.
     *
     * IR-008-02: Enhanced to use InterRAI scores for recommendations.
     */
    protected function isBundleRecommended(CareBundle $bundle, array $context): bool
    {
        $interrai = $context['interrai'] ?? null;
        $intensity = $context['service_intensity_level'] ?? 'standard';

        // Cognitive flag or CPS >= 3 -> DEM-SUP
        if ($context['has_cognitive_flag'] && $bundle->code === 'DEM-SUP') {
            return true;
        }

        // Wound flag -> COMPLEX
        if ($context['has_wound_flag'] && $bundle->code === 'COMPLEX') {
            return true;
        }

        // Palliative flag -> PALLIATIVE
        if ($context['has_palliative_flag'] && $bundle->code === 'PALLIATIVE') {
            return true;
        }

        // High health instability (CHESS >= 3) -> COMPLEX
        if ($context['has_health_instability'] && $bundle->code === 'COMPLEX') {
            return true;
        }

        // Intensive service level (MAPLe 5 or CHESS 4+) -> COMPLEX
        if ($intensity === 'intensive' && $bundle->code === 'COMPLEX') {
            return true;
        }

        // Enhanced service level (MAPLe 4 or CHESS 3) -> STD-MED or COMPLEX
        if ($intensity === 'enhanced' && in_array($bundle->code, ['STD-MED', 'COMPLEX'])) {
            return $bundle->code === 'STD-MED'; // Prefer STD-MED unless other flags
        }

        // Default to STD-MED for standard/moderate intensity
        if (!$context['has_cognitive_flag'] && !$context['has_wound_flag'] && !$context['has_palliative_flag']) {
            return $bundle->code === 'STD-MED';
        }

        return false;
    }

    /**
     * Get recommendation reason.
     *
     * IR-008-02: Enhanced with InterRAI-based reasoning.
     */
    protected function getRecommendationReason(CareBundle $bundle, array $context): string
    {
        $interrai = $context['interrai'] ?? null;

        if ($context['has_cognitive_flag'] && $bundle->code === 'DEM-SUP') {
            if ($interrai && $interrai['cps'] >= 3) {
                return "CPS score of {$interrai['cps']} indicates {$interrai['cps_description']} - dementia support bundle recommended";
            }
            return 'Patient has cognitive/dementia-related clinical flags';
        }

        if ($context['has_wound_flag'] && $bundle->code === 'COMPLEX') {
            return 'Patient has wound care needs requiring complex care';
        }

        if ($context['has_palliative_flag'] && $bundle->code === 'PALLIATIVE') {
            return 'Patient has palliative care needs';
        }

        if ($context['has_health_instability'] && $bundle->code === 'COMPLEX') {
            $chess = $interrai['chess_score'] ?? 'elevated';
            return "CHESS score of {$chess} indicates health instability - complex care bundle recommended";
        }

        if ($interrai) {
            $maple = $interrai['maple_score'] ?? null;
            if ($maple) {
                return "MAPLe score {$maple} ({$interrai['maple_description']}) - {$context['service_intensity_level']} service intensity";
            }
        }

        return 'Recommended based on patient assessment';
    }

    /**
     * Get bundle color theme for UI.
     */
    protected function getBundleColorTheme(string $code): string
    {
        return match ($code) {
            'COMPLEX' => 'green',
            'PALLIATIVE' => 'purple',
            'DEM-SUP' => 'amber',
            default => 'blue',
        };
    }

    /**
     * Get bundle band for UI.
     */
    protected function getBundleBand(string $code): string
    {
        return match ($code) {
            'COMPLEX' => 'Band B',
            'PALLIATIVE' => 'Band C',
            'DEM-SUP' => 'Band B',
            default => 'Band A',
        };
    }

    /**
     * Calculate total bundle price.
     */
    protected function calculateBundlePrice(array $services): float
    {
        return array_reduce($services, function ($carry, $service) {
            return $carry + ($service['costPerVisit'] * $service['currentFrequency'] * 4); // Monthly
        }, 0);
    }

    /**
     * Calculate estimated monthly cost.
     */
    protected function calculateMonthlyCost(array $services): float
    {
        return array_reduce($services, function ($carry, $service) {
            $frequency = $service['currentFrequency'] ?? 0;
            $cost = $service['costPerVisit'] ?? 0;
            return $carry + ($cost * $frequency * 4); // 4 weeks per month
        }, 0);
    }

    /**
     * Build and save a care plan from bundle configuration.
     *
     * @param int $patientId
     * @param int $bundleId
     * @param array $serviceConfigurations Customized service settings
     * @param int|null $userId User creating the plan
     * @return CarePlan
     */
    public function buildCarePlan(
        int $patientId,
        int $bundleId,
        array $serviceConfigurations,
        ?int $userId = null
    ): CarePlan {
        return DB::transaction(function () use ($patientId, $bundleId, $serviceConfigurations, $userId) {
            $patient = Patient::findOrFail($patientId);
            $bundle = CareBundle::findOrFail($bundleId);

            // Get latest version for this patient (version is unique per patient, not per bundle)
            $latestVersion = CarePlan::where('patient_id', $patientId)
                ->max('version') ?? 0;

            // Create the care plan with service requirements (NO ServiceAssignments yet)
            // ServiceAssignments are created during scheduling, not plan building
            $carePlan = CarePlan::create([
                'patient_id' => $patientId,
                'care_bundle_id' => $bundleId,
                'version' => $latestVersion + 1,
                'status' => 'draft',
                'goals' => $this->extractGoals($serviceConfigurations),
                'risks' => $patient->risk_flags ?? [],
                'interventions' => $this->extractInterventions($serviceConfigurations),
                'service_requirements' => $this->extractServiceRequirements($serviceConfigurations),
                'notes' => "Created from bundle: {$bundle->name}",
            ]);

            // NOTE: ServiceAssignments are NOT created here anymore.
            // They are created during the scheduling phase when staff/times are assigned.
            // The service_requirements field stores the customized requirements.

            // Update patient queue status (optional - won't fail if no queue entry)
            try {
                $this->updateQueueStatus($patient, 'bundle_building', $userId);
            } catch (\Exception $e) {
                Log::warning("Failed to update queue status", ['error' => $e->getMessage()]);
            }

            // Apply metadata rules (optional - won't fail if metadata not configured)
            try {
                $this->metadataEngine->applyRules('PATIENT', $patientId, 'on_update', [
                    'care_plan_created' => true,
                    'bundle_id' => $bundleId,
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to apply metadata rules", ['error' => $e->getMessage()]);
            }

            Log::info("Care plan created", [
                'care_plan_id' => $carePlan->id,
                'patient_id' => $patientId,
                'bundle_id' => $bundleId,
            ]);

            return $carePlan;
        });
    }

    /**
     * Create a service assignment from configuration.
     */
    protected function createServiceAssignment(CarePlan $carePlan, array $config): ServiceAssignment
    {
        return ServiceAssignment::create([
            'care_plan_id' => $carePlan->id,
            'patient_id' => $carePlan->patient_id,
            'service_type_id' => $config['service_type_id'],
            'service_provider_organization_id' => $config['provider_id'] ?? null,
            'status' => 'pending',
            'frequency_rule' => $this->buildFrequencyRule($config),
            'estimated_hours_per_week' => $this->calculateHoursPerWeek($config),
            'notes' => $config['notes'] ?? null,
        ]);
    }

    /**
     * Build frequency rule string.
     */
    protected function buildFrequencyRule(array $config): string
    {
        $frequency = $config['currentFrequency'] ?? 1;
        return "{$frequency}x per week";
    }

    /**
     * Calculate estimated hours per week.
     */
    protected function calculateHoursPerWeek(array $config): float
    {
        $frequency = $config['currentFrequency'] ?? 0;
        $durationMinutes = $config['default_duration_minutes'] ?? 60;
        return ($frequency * $durationMinutes) / 60;
    }

    /**
     * Extract goals from service configurations.
     */
    protected function extractGoals(array $configurations): array
    {
        $goals = [];

        foreach ($configurations as $config) {
            if (($config['currentFrequency'] ?? 0) > 0) {
                // Get service name from database if not provided
                $serviceName = $config['name'] ?? null;
                if (!$serviceName && isset($config['service_type_id'])) {
                    $serviceType = ServiceType::find($config['service_type_id']);
                    $serviceName = $serviceType?->name ?? 'Service';
                }
                $goals[] = "Provide {$serviceName} services {$config['currentFrequency']}x per week";
            }
        }

        return $goals;
    }

    /**
     * Extract interventions from service configurations.
     */
    protected function extractInterventions(array $configurations): array
    {
        $interventions = [];

        foreach ($configurations as $config) {
            if (($config['currentFrequency'] ?? 0) > 0) {
                // Get service details from database if not provided
                $serviceName = $config['name'] ?? null;
                $description = $config['description'] ?? null;

                if ((!$serviceName || !$description) && isset($config['service_type_id'])) {
                    $serviceType = ServiceType::find($config['service_type_id']);
                    if ($serviceType) {
                        $serviceName = $serviceName ?? $serviceType->name;
                        $description = $description ?? $serviceType->description;
                    }
                }

                if ($serviceName) {
                    $interventions[] = [
                        'service' => $serviceName,
                        'description' => $description ?? 'As prescribed',
                        'frequency' => "{$config['currentFrequency']}x/week",
                    ];
                }
            }
        }

        return $interventions;
    }

    /**
     * Extract machine-readable service requirements from configurations.
     *
     * This stores the customized service requirements (frequency, duration, etc.)
     * that the user selected in the bundle wizard. These requirements are used
     * by the scheduling system to show unscheduled care needs.
     *
     * @param array $configurations Service configurations from the wizard
     * @return array Array of service requirement objects
     */
    protected function extractServiceRequirements(array $configurations): array
    {
        $requirements = [];

        foreach ($configurations as $config) {
            $frequency = $config['currentFrequency'] ?? 0;
            if ($frequency <= 0) {
                continue;
            }

            $serviceTypeId = $config['service_type_id'] ?? null;
            if (!$serviceTypeId) {
                continue;
            }

            $requirements[] = [
                'service_type_id' => (int) $serviceTypeId,
                'frequency_per_week' => (int) $frequency,
                'duration_minutes' => (int) ($config['defaultDurationMinutes'] ?? $config['default_duration_minutes'] ?? 60),
                'duration_weeks' => (int) ($config['currentDuration'] ?? $config['defaultDuration'] ?? 12),
                'provider_preference' => $config['provider_id'] ?? null,
                'assignment_type' => $config['assignment_type'] ?? 'Either',
                'role_required' => $config['role_required'] ?? null,
            ];
        }

        return $requirements;
    }

    /**
     * Update patient queue status.
     */
    protected function updateQueueStatus(Patient $patient, string $status, ?int $userId): void
    {
        $queue = PatientQueue::where('patient_id', $patient->id)->first();

        if ($queue && $queue->canTransitionTo($status)) {
            $queue->transitionTo($status, $userId, 'Care bundle building initiated');
        }
    }

    /**
     * Publish a care plan and transition patient to active.
     *
     * @param CarePlan $carePlan
     * @param int|null $userId User approving the plan
     * @return CarePlan
     */
    public function publishCarePlan(CarePlan $carePlan, ?int $userId = null): CarePlan
    {
        return DB::transaction(function () use ($carePlan, $userId) {
            // Archive any existing active care plans for this patient
            CarePlan::where('patient_id', $carePlan->patient_id)
                ->where('id', '!=', $carePlan->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'archived',
                    'notes' => DB::raw("CONCAT(COALESCE(notes, ''), ' | Replaced by plan #" . $carePlan->id . " on " . now()->toDateString() . "')")
                ]);

            // Update care plan status
            $carePlan->update([
                'status' => 'active',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            // Update any existing service assignments to active (for backward compatibility)
            // Note: With plan vs schedule separation, new plans may not have ServiceAssignments yet.
            // ServiceAssignments are created during scheduling, not plan building.
            if ($carePlan->serviceAssignments()->exists()) {
                $carePlan->serviceAssignments()->update(['status' => 'active']);
            }

            // Transition patient from queue to active profile
            $this->transitionPatientToActive($carePlan->patient_id, $userId);

            Log::info("Care plan published", [
                'care_plan_id' => $carePlan->id,
                'patient_id' => $carePlan->patient_id,
            ]);

            return $carePlan->fresh();
        });
    }

    /**
     * Transition patient from queue to active profile.
     */
    public function transitionPatientToActive(int $patientId, ?int $userId = null): Patient
    {
        $patient = Patient::findOrFail($patientId);

        // Update patient status
        $patient->update([
            'status' => 'Active',
            'is_in_queue' => false,
            'activated_at' => now(),
            'activated_by' => $userId,
        ]);

        // Complete queue transition (optional - won't fail if no queue entry)
        try {
            $queue = PatientQueue::where('patient_id', $patientId)->first();
            if ($queue) {
                // Progress through remaining statuses to transitioned
                $transitionPath = ['bundle_review', 'bundle_approved', 'transitioned'];

                foreach ($transitionPath as $status) {
                    if ($queue->canTransitionTo($status)) {
                        $queue->transitionTo($status, $userId, 'Care bundle published - transitioning to active');
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to complete queue transition", ['error' => $e->getMessage()]);
        }

        // Apply metadata rules for activation (optional)
        try {
            $this->metadataEngine->applyRules('PATIENT', $patientId, 'on_status_change', [
                'new_status' => 'Active',
                'transitioned_from_queue' => true,
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to apply activation metadata rules", ['error' => $e->getMessage()]);
        }

        Log::info("Patient transitioned to active", [
            'patient_id' => $patientId,
            'user_id' => $userId,
        ]);

        return $patient->fresh();
    }
}
