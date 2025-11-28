<?php

namespace Database\Seeders;

use App\Models\RUGClassification;
use App\Models\RugServiceRecommendation;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

/**
 * RugServiceRecommendationsSeeder
 *
 * Seeds RUG/interRAI-based service recommendations for clinically indicated services.
 * These recommendations supplement the base bundle templates when clinical criteria are met.
 *
 * Based on docs/CC21_RUG_Bundle_Templates.md STEP 6:
 * - Reduced Physical Function / high ADL → Homemaking
 * - Clinically Complex → Homemaking if ADL/IADL issues, Caregiver Coaching
 * - Impaired Cognition → Activation/Social, Behavioural Supports, Caregiver Coaching
 * - Behaviour Problems → Behavioural Supports, Activation, Respite
 * - Rehab → Homemaking if ADL/IADL deficits
 *
 * @see docs/CC21_RUG_Bundle_Templates.md
 */
class RugServiceRecommendationsSeeder extends Seeder
{
    public function run(): void
    {
        // Cache service type IDs
        $serviceIds = ServiceType::pluck('id', 'code')->toArray();

        // =========================================================================
        // HOMEMAKING RECOMMENDATIONS
        // =========================================================================

        // Homemaking for high ADL patients (mobility/physical function issues)
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'service_code' => 'HMK',
            'min_freq' => 2,
            'max_freq' => 3,
            'duration' => 60,
            'trigger' => ['adl_min' => 11, 'iadl_min' => 1],
            'justification' => 'High ADL score with IADL impairment indicates need for homemaking support',
            'priority' => 70,
            'required' => false,
        ], $serviceIds);

        // Homemaking for Clinically Complex with ADL/IADL issues
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
            'service_code' => 'HMK',
            'min_freq' => 1,
            'max_freq' => 2,
            'duration' => 60,
            'trigger' => ['adl_min' => 6, 'iadl_min' => 1],
            'justification' => 'Clinically complex with ADL/IADL deficits benefits from homemaking support',
            'priority' => 65,
            'required' => false,
        ], $serviceIds);

        // Homemaking for Rehab patients with ADL/IADL deficits
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_SPECIAL_REHABILITATION,
            'service_code' => 'HMK',
            'min_freq' => 2,
            'max_freq' => 3,
            'duration' => 60,
            'trigger' => ['iadl_min' => 1],
            'justification' => 'Rehabilitation patients with IADL deficits need homemaking to support recovery',
            'priority' => 60,
            'required' => false,
        ], $serviceIds);

        // =========================================================================
        // BEHAVIOURAL SUPPORTS RECOMMENDATIONS
        // =========================================================================

        // Behaviour Problems category - always recommend BEH
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
            'service_code' => 'BEH',
            'min_freq' => 2,
            'max_freq' => 4,
            'duration' => 60,
            'trigger' => null,
            'justification' => 'Behaviour Problems category requires behavioural support interventions',
            'priority' => 90,
            'required' => true,
        ], $serviceIds);

        // Impaired Cognition with behaviour flags
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'service_code' => 'BEH',
            'min_freq' => 1,
            'max_freq' => 2,
            'duration' => 60,
            'trigger' => ['flags' => ['behaviour_problems']],
            'justification' => 'Impaired cognition with behavioural symptoms needs behavioural supports',
            'priority' => 75,
            'required' => false,
        ], $serviceIds);

        // =========================================================================
        // SOCIAL/RECREATIONAL (ACTIVATION) RECOMMENDATIONS
        // =========================================================================

        // Impaired Cognition - activation helps maintain function
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'service_code' => 'REC',
            'min_freq' => 2,
            'max_freq' => 3,
            'duration' => 60,
            'trigger' => null,
            'justification' => 'Cognitive impairment benefits from activation and social engagement',
            'priority' => 70,
            'required' => false,
        ], $serviceIds);

        // Behaviour Problems - structured activities help manage behaviours
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
            'service_code' => 'REC',
            'min_freq' => 2,
            'max_freq' => 3,
            'duration' => 60,
            'trigger' => null,
            'justification' => 'Structured activation helps manage responsive behaviours',
            'priority' => 75,
            'required' => false,
        ], $serviceIds);

        // =========================================================================
        // RESPITE RECOMMENDATIONS
        // =========================================================================

        // Behaviour Problems - caregiver respite critical
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
            'service_code' => 'RES',
            'min_freq' => 1,
            'max_freq' => 2,
            'duration' => 240, // 4 hour blocks
            'trigger' => null,
            'justification' => 'Behavioural support requires caregiver respite to prevent burnout',
            'priority' => 80,
            'required' => false,
        ], $serviceIds);

        // Impaired Cognition - respite for dementia caregiving
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'service_code' => 'RES',
            'min_freq' => 1,
            'max_freq' => 2,
            'duration' => 240,
            'trigger' => ['adl_min' => 6],
            'justification' => 'Dementia caregiving with moderate ADL needs respite support',
            'priority' => 70,
            'required' => false,
        ], $serviceIds);

        // =========================================================================
        // CAREGIVER COACHING/EDUCATION RECOMMENDATIONS
        // =========================================================================

        // Impaired Cognition - caregiver education critical
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'service_code' => 'SW',
            'min_freq' => 1,
            'max_freq' => 1,
            'duration' => 60,
            'trigger' => null,
            'justification' => 'Cognitive impairment caregivers need coaching and psychosocial support',
            'priority' => 65,
            'required' => false,
        ], $serviceIds);

        // Behaviour Problems - caregiver coaching
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
            'service_code' => 'SW',
            'min_freq' => 1,
            'max_freq' => 1,
            'duration' => 60,
            'trigger' => null,
            'justification' => 'Behavioural support requires caregiver coaching',
            'priority' => 70,
            'required' => false,
        ], $serviceIds);

        // Clinically Complex - high burden coaching
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
            'service_code' => 'SW',
            'min_freq' => 1,
            'max_freq' => 1,
            'duration' => 60,
            'trigger' => ['adl_min' => 11],
            'justification' => 'High complexity and ADL burden needs caregiver support',
            'priority' => 60,
            'required' => false,
        ], $serviceIds);

        // =========================================================================
        // OT FOR HOME SAFETY RECOMMENDATIONS
        // =========================================================================

        // Reduced Physical Function - home safety assessment
        $this->createRecommendation([
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'service_code' => 'OT',
            'min_freq' => 1,
            'max_freq' => 1,
            'duration' => 60,
            'trigger' => ['adl_min' => 9],
            'justification' => 'High ADL in reduced physical function needs OT home safety assessment',
            'priority' => 55,
            'required' => false,
        ], $serviceIds);

        $this->command->info('Seeded RUG service recommendations.');
    }

    /**
     * Create a service recommendation record.
     */
    protected function createRecommendation(array $data, array $serviceIds): void
    {
        $serviceId = $serviceIds[$data['service_code']] ?? null;

        if (!$serviceId) {
            $this->command->warn("Service type not found: {$data['service_code']}");
            return;
        }

        RugServiceRecommendation::updateOrCreate(
            [
                'rug_group' => $data['rug_group'] ?? null,
                'rug_category' => $data['rug_category'] ?? null,
                'service_type_id' => $serviceId,
            ],
            [
                'min_frequency_per_week' => $data['min_freq'] ?? 0,
                'max_frequency_per_week' => $data['max_freq'] ?? null,
                'default_duration_minutes' => $data['duration'] ?? 60,
                'trigger_conditions' => $data['trigger'] ?? null,
                'justification' => $data['justification'] ?? null,
                'clinical_notes' => $data['notes'] ?? null,
                'priority_weight' => $data['priority'] ?? 50,
                'is_required' => $data['required'] ?? false,
                'is_active' => true,
            ]
        );
    }
}
