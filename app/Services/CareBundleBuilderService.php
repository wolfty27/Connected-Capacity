<?php

namespace App\Services;

use App\Models\BundleConfigurationRule;
use App\Models\CareBundle;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\PatientQueue;
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

    public function __construct(MetadataEngine $metadataEngine)
    {
        $this->metadataEngine = $metadataEngine;
    }

    /**
     * Get available bundles with their services configured for a patient.
     *
     * @param int $patientId The patient ID
     * @return array Array of bundles with configured services
     */
    public function getAvailableBundles(int $patientId): array
    {
        $patient = Patient::with(['transitionNeedsProfile'])->find($patientId);
        if (!$patient) {
            return [];
        }

        $bundles = CareBundle::with(['serviceTypes.category', 'configurationRules'])
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
        $patient = Patient::with(['transitionNeedsProfile'])->find($patientId);
        if (!$patient) {
            return null;
        }

        $bundle = CareBundle::with(['serviceTypes.category', 'configurationRules'])
            ->where('id', $bundleId)
            ->where('active', true)
            ->first();

        if (!$bundle) {
            return null;
        }

        $context = $this->buildPatientContext($patient);
        return $this->configureBundleForPatient($bundle, $context);
    }

    /**
     * Build patient context for rule evaluation.
     */
    protected function buildPatientContext(Patient $patient): array
    {
        $tnp = $patient->transitionNeedsProfile;

        return [
            'patient' => [
                'id' => $patient->id,
                'status' => $patient->status,
                'maple_score' => $patient->maple_score,
                'rai_cha_score' => $patient->rai_cha_score,
                'risk_flags' => $patient->risk_flags ?? [],
            ],
            'tnp' => $tnp ? [
                'clinical_flags' => $tnp->clinical_flags ?? [],
                'narrative_summary' => $tnp->narrative_summary ?? null,
                'status' => $tnp->status ?? null,
            ] : null,
            'clinical_flags' => $tnp->clinical_flags ?? [],
            'has_cognitive_flag' => $this->hasFlag($tnp, 'Cognitive'),
            'has_wound_flag' => $this->hasFlag($tnp, 'Wound'),
            'has_palliative_flag' => $this->hasFlag($tnp, 'Palliative'),
            'has_respiratory_flag' => $this->hasFlag($tnp, 'Respiratory'),
        ];
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
                'category' => $serviceType->category->code ?? $serviceType->category ?? 'OTHER',
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
     */
    protected function isBundleRecommended(CareBundle $bundle, array $context): bool
    {
        // Cognitive flag -> DEM-SUP
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

        // Default to STD-MED
        if (!$context['has_cognitive_flag'] && !$context['has_wound_flag'] && !$context['has_palliative_flag']) {
            return $bundle->code === 'STD-MED';
        }

        return false;
    }

    /**
     * Get recommendation reason.
     */
    protected function getRecommendationReason(CareBundle $bundle, array $context): string
    {
        if ($context['has_cognitive_flag'] && $bundle->code === 'DEM-SUP') {
            return 'Patient has cognitive/dementia-related clinical flags';
        }

        if ($context['has_wound_flag'] && $bundle->code === 'COMPLEX') {
            return 'Patient has wound care needs requiring complex care';
        }

        if ($context['has_palliative_flag'] && $bundle->code === 'PALLIATIVE') {
            return 'Patient has palliative care needs';
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

            // Get latest version for this patient/bundle combination
            $latestVersion = CarePlan::where('patient_id', $patientId)
                ->where('care_bundle_id', $bundleId)
                ->max('version') ?? 0;

            // Create the care plan
            $carePlan = CarePlan::create([
                'patient_id' => $patientId,
                'care_bundle_id' => $bundleId,
                'version' => $latestVersion + 1,
                'status' => 'draft',
                'goals' => $this->extractGoals($serviceConfigurations),
                'risks' => $patient->risk_flags ?? [],
                'interventions' => $this->extractInterventions($serviceConfigurations),
                'notes' => "Created from bundle: {$bundle->name}",
            ]);

            // Create service assignments
            foreach ($serviceConfigurations as $config) {
                if (($config['currentFrequency'] ?? 0) > 0) {
                    $this->createServiceAssignment($carePlan, $config);
                }
            }

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
            // Update care plan status
            $carePlan->update([
                'status' => 'active',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            // Update service assignments to active
            $carePlan->serviceAssignments()->update(['status' => 'active']);

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
