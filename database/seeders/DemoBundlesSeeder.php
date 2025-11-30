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

            // Create active care plan with service requirements
            // NOTE: ServiceAssignments are NOT created here - they are created by WorkforceSeeder
            // with proper scheduled dates. This maintains plan vs schedule separation.
            $carePlan = CarePlan::create([
                'patient_id' => $patient->id,
                'care_bundle_id' => $careBundle->id,
                'care_bundle_template_id' => $template->id,
                'version' => 1,
                'status' => 'active',
                'goals' => $this->generateGoals($rug),
                'risks' => $this->generateRisks($rug),
                'interventions' => [],
                'service_requirements' => $serviceRequirements,
                'approved_at' => now()->subDays(rand(7, 30)),
                'notes' => "Created from RUG template: {$template->name} ({$template->code})",
            ]);

            $this->command->info("  {$patient->user->name}: CarePlan from {$template->code} (RUG: {$rug->rug_group})");
        }

        $this->command->info('CarePlans created for all 10 active patients.');
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
}
