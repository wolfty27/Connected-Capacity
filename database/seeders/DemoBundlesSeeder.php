<?php

namespace Database\Seeders;

use App\Models\CareBundle;
use App\Models\CareBundleTemplate;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\RUGClassification;
use Illuminate\Database\Seeder;

/**
 * DemoBundlesSeeder - Creates CarePlans for the 10 active demo patients
 *
 * For each active patient:
 * - Finds the RUG-matched CareBundleTemplate
 * - Creates a CareBundle record (for backward compatibility)
 * - Creates an active CarePlan with service assignments
 *
 * The 5 intake queue patients do NOT get care plans (they're waiting for bundle building).
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class DemoBundlesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating CarePlans for 10 active patients...');

        // Get only the 10 active demo patients
        $activePatients = Patient::where('status', 'Active')
            ->where('ohip', 'like', 'DEMO-A%')
            ->with(['latestRugClassification', 'user'])
            ->get();

        if ($activePatients->isEmpty()) {
            $this->command->error('No active demo patients found. Run DemoPatientsSeeder and DemoAssessmentsSeeder first.');
            return;
        }

        foreach ($activePatients as $patient) {
            $rug = $patient->latestRugClassification;
            if (!$rug) {
                $this->command->warn("  Skipping {$patient->user->name}: No RUG classification");
                continue;
            }

            // Find matching template
            $template = CareBundleTemplate::where('rug_group', $rug->rug_group)
                ->where('is_active', true)
                ->where('is_current_version', true)
                ->first();

            if (!$template) {
                // Fall back to any template in same category
                $template = CareBundleTemplate::where('rug_category', $rug->rug_category)
                    ->where('is_active', true)
                    ->where('is_current_version', true)
                    ->first();
            }

            if (!$template) {
                $this->command->warn("  Skipping {$patient->user->name}: No matching template for RUG {$rug->rug_group}");
                continue;
            }

            // Create or get CareBundle for backward compatibility
            $careBundle = $this->getOrCreateCareBundle($template);

            // Extract service requirements from template
            $serviceRequirements = $this->extractServiceRequirements($template, $rug);

            // Diagnostic: Log service requirements count
            $servicesCount = count($serviceRequirements);
            if ($servicesCount === 0) {
                $this->command->warn("  ⚠️  {$patient->user->name}: No service requirements extracted from template {$template->code}");
                $templateServicesCount = $template->services()->count();
                $this->command->warn("      Template has {$templateServicesCount} services in care_bundle_template_services");
            } else {
                $this->command->info("  {$patient->user->name}: Extracted {$servicesCount} service requirements");
            }

            // v2.3: Generate scenario metadata based on patient profile
            $scenarioData = $this->generateScenarioMetadata($rug, $patient);

            // Create active care plan with service requirements
            // NOTE: ServiceAssignments are NOT created here - they are created by WorkforceSeeder
            // with proper scheduled dates. This maintains plan vs schedule separation.
            $carePlan = CarePlan::create([
                'patient_id' => $patient->id,
                'care_bundle_id' => $careBundle->id,
                'care_bundle_template_id' => $template->id,
                // v2.3: AI Bundle Engine scenario fields
                'scenario_metadata' => $scenarioData['metadata'],
                'scenario_title' => $scenarioData['title'],
                'scenario_axis' => $scenarioData['axis'],
                'version' => 1,
                'status' => 'active',
                'goals' => $this->generateGoals($rug),
                'risks' => $this->generateRisks($rug),
                'interventions' => [],
                'service_requirements' => $serviceRequirements,
                'approved_at' => now()->subDays(rand(7, 30)),
                'notes' => "Created from AI scenario: {$scenarioData['title']}",
            ]);

            $this->command->info("  {$patient->user->name}: CarePlan from {$template->code} (RUG: {$rug->rug_group}) → \"{$scenarioData['title']}\"");
        }

        // Diagnostic summary
        $plansWithRequirements = CarePlan::whereNotNull('service_requirements')
            ->where('status', 'active')
            ->get()
            ->filter(fn($p) => !empty($p->service_requirements))
            ->count();
        $this->command->info("CarePlans created: {$plansWithRequirements}/10 have service_requirements populated");

        if ($plansWithRequirements < 10) {
            $this->command->error('⚠️  Some care plans are missing service_requirements!');
            $this->command->error('    Check that RUGBundleTemplatesSeeder created template services correctly.');
        }
    }

    protected function getOrCreateCareBundle(CareBundleTemplate $template): CareBundle
    {
        return CareBundle::firstOrCreate(
            ['code' => $template->code],
            [
                'name' => $template->name,
                'description' => $template->description,
                'price' => $template->weekly_cap_cents / 100 * 4, // Monthly
                'active' => true,
            ]
        );
    }

    /**
     * Extract service requirements from template based on patient's RUG flags.
     * This stores the "what care is needed" in the care plan without creating assignments.
     */
    protected function extractServiceRequirements(CareBundleTemplate $template, RUGClassification $rug): array
    {
        $flags = $rug->flags ?? [];
        $services = $template->getServicesForFlags($flags);
        $requirements = [];

        foreach ($services as $templateService) {
            $serviceType = $templateService->serviceType;
            if (!$serviceType) {
                continue;
            }

            $requirements[] = [
                'service_type_id' => $serviceType->id,
                'frequency_per_week' => $templateService->default_frequency_per_week ?? 1,
                'duration_minutes' => $templateService->default_duration_minutes ?? 60,
                'duration_weeks' => 12, // Default care plan duration
                'provider_preference' => null,
                'assignment_type' => 'weekly',
                'role_required' => $serviceType->category,
            ];
        }

        return $requirements;
    }

    protected function generateGoals(RUGClassification $rug): array
    {
        $goals = ['Maintain current level of independence'];

        if ($rug->adl_sum >= 10) {
            $goals[] = 'Provide safe ADL assistance to prevent injury';
        }
        if ($rug->hasFlag('fall_risk') || $rug->hasFlag('high_fall_risk')) {
            $goals[] = 'Reduce fall risk through environmental modifications and mobility support';
        }
        if ($rug->cps_score >= 3) {
            $goals[] = 'Provide cognitive support and structured daily routines';
        }
        if ($rug->rug_category === RUGClassification::CATEGORY_SPECIAL_REHABILITATION) {
            $goals[] = 'Achieve rehabilitation goals and maximize functional recovery';
        }
        if ($rug->rug_category === RUGClassification::CATEGORY_CLINICALLY_COMPLEX) {
            $goals[] = 'Monitor and manage complex clinical conditions';
        }

        return $goals;
    }

    protected function generateRisks(RUGClassification $rug): array
    {
        $risks = [];

        if ($rug->hasFlag('fall_risk') || $rug->hasFlag('high_fall_risk')) {
            $risks[] = 'Fall Risk';
        }
        if ($rug->hasFlag('wandering_risk')) {
            $risks[] = 'Wandering/Elopement Risk';
        }
        if ($rug->cps_score >= 3) {
            $risks[] = 'Cognitive Impairment';
        }
        if ($rug->hasFlag('high_health_instability')) {
            $risks[] = 'Health Instability';
        }
        if ($rug->hasFlag('caregiver_burden_high')) {
            $risks[] = 'Caregiver Stress';
        }

        return $risks;
    }

    /**
     * v2.3: Generate scenario metadata based on RUG classification and patient profile.
     *
     * This simulates what the AI Bundle Engine would produce, selecting an appropriate
     * scenario axis based on the patient's clinical profile.
     */
    protected function generateScenarioMetadata(RUGClassification $rug, Patient $patient): array
    {
        // Determine best-fit scenario axis based on patient profile
        $axis = $this->selectScenarioAxis($rug);
        $title = $this->getScenarioTitle($axis);

        return [
            'title' => $title,
            'axis' => $axis,
            'metadata' => [
                'scenario_id' => 'demo-' . $patient->id . '-' . $axis,
                'title' => $title,
                'axis' => $axis,
                'source' => 'category_composition_v2.3',
                'generated_at' => now()->subDays(rand(7, 30))->toIso8601String(),
                'rug_group' => $rug->rug_group,
                'rug_category' => $rug->rug_category,
                'algorithm_scores' => [
                    'personal_support' => rand(2, 5),
                    'rehabilitation' => $rug->rug_category === RUGClassification::CATEGORY_SPECIAL_REHABILITATION ? rand(3, 5) : rand(0, 2),
                    'chess_ca' => rand(1, 4),
                ],
                'triggered_caps' => $this->generateTriggeredCaps($rug),
            ],
        ];
    }

    /**
     * Select the most appropriate scenario axis based on RUG classification.
     */
    protected function selectScenarioAxis(RUGClassification $rug): string
    {
        // Priority-based selection based on RUG category and flags
        if ($rug->rug_category === RUGClassification::CATEGORY_SPECIAL_REHABILITATION) {
            return 'recovery_rehab';
        }

        if ($rug->rug_category === RUGClassification::CATEGORY_EXTENSIVE_SERVICES ||
            $rug->rug_category === RUGClassification::CATEGORY_SPECIAL_CARE) {
            return 'medical_intensive';
        }

        if ($rug->rug_category === RUGClassification::CATEGORY_CLINICALLY_COMPLEX) {
            // Clinically complex could be various axes
            $options = ['safety_stability', 'medical_intensive', 'tech_enabled'];
            return $options[array_rand($options)];
        }

        if ($rug->rug_category === RUGClassification::CATEGORY_IMPAIRED_COGNITION) {
            return 'cognitive_support';
        }

        if ($rug->rug_category === RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS) {
            // Behaviour problems could benefit from various approaches
            $options = ['cognitive_support', 'caregiver_relief', 'safety_stability'];
            return $options[array_rand($options)];
        }

        if ($rug->hasFlag('fall_risk') || $rug->hasFlag('high_fall_risk')) {
            return 'safety_stability';
        }

        if ($rug->hasFlag('caregiver_burden_high')) {
            return 'caregiver_relief';
        }

        // Default: balanced or safety-focused
        $defaults = ['balanced', 'safety_stability', 'community_integrated'];
        return $defaults[array_rand($defaults)];
    }

    /**
     * Get human-readable title for a scenario axis.
     */
    protected function getScenarioTitle(string $axis): string
    {
        return match ($axis) {
            'safety_stability' => 'Safety & Stability Focus',
            'tech_enabled' => 'Tech-Enabled Care',
            'caregiver_relief' => 'Caregiver Relief Package',
            'community_integrated' => 'Community-Integrated Care',
            'cognitive_support' => 'Cognitive Support Plan',
            'medical_intensive' => 'Medical-Intensive Care',
            'recovery_rehab' => 'Recovery & Rehabilitation',
            'balanced' => 'Balanced Care Approach',
            default => 'Personalized Care Plan',
        };
    }

    /**
     * Generate realistic triggered CAPs based on RUG profile.
     */
    protected function generateTriggeredCaps(RUGClassification $rug): array
    {
        $caps = [];

        if ($rug->hasFlag('fall_risk') || $rug->hasFlag('high_fall_risk')) {
            $caps[] = ['id' => 'falls', 'name' => 'Falls', 'level' => 'triggered'];
        }
        if ($rug->adl_sum >= 10) {
            $caps[] = ['id' => 'adl', 'name' => 'ADL/Rehabilitation', 'level' => 'triggered'];
        }
        if ($rug->cps_score >= 2) {
            $caps[] = ['id' => 'cognitive_loss', 'name' => 'Cognitive Loss', 'level' => 'triggered'];
        }
        if ($rug->hasFlag('pain_daily') || $rug->pain_score >= 2) {
            $caps[] = ['id' => 'pain', 'name' => 'Pain', 'level' => 'triggered'];
        }
        if ($rug->hasFlag('caregiver_burden_high')) {
            $caps[] = ['id' => 'informal_support', 'name' => 'Informal Support', 'level' => 'triggered'];
        }
        if ($rug->hasFlag('urinary_incontinence')) {
            $caps[] = ['id' => 'urinary_incontinence', 'name' => 'Urinary Incontinence', 'level' => 'triggered'];
        }
        if ($rug->rug_category === RUGClassification::CATEGORY_CLINICALLY_COMPLEX) {
            $caps[] = ['id' => 'cardiorespiratory', 'name' => 'Cardiorespiratory', 'level' => 'triggered'];
        }

        return $caps;
    }
}
