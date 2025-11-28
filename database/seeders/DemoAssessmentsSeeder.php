<?php

namespace Database\Seeders;

use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Services\RUGClassificationService;
use Illuminate\Database\Seeder;

/**
 * DemoAssessmentsSeeder - Creates InterRAI HC assessments and RUG classifications
 *
 * For each of the 15 demo patients created by DemoPatientsSeeder:
 * - Creates a realistic InterRAI HC assessment with appropriate scores
 * - Generates RUG classification using RUGClassificationService
 *
 * Assessment profiles are designed to produce specific RUG groups.
 *
 * @see docs/CC21_RUG_Algorithm_Pseudocode.md
 */
class DemoAssessmentsSeeder extends Seeder
{
    protected RUGClassificationService $rugService;

    public function __construct()
    {
        $this->rugService = new RUGClassificationService();
    }

    public function run(): void
    {
        $this->command->info('Creating InterRAI assessments and RUG classifications...');

        $targetRugGroups = DemoPatientsSeeder::getTargetRugGroups();
        $patients = Patient::whereIn('ohip', array_keys($targetRugGroups))->get();

        if ($patients->isEmpty()) {
            $this->command->error('No demo patients found. Run DemoPatientsSeeder first.');
            return;
        }

        foreach ($patients as $patient) {
            $targetRug = $targetRugGroups[$patient->ohip] ?? 'PA1';
            $profile = $this->getAssessmentProfile($targetRug);

            // Create InterRAI assessment
            $assessment = InterraiAssessment::create([
                'patient_id' => $patient->id,
                'assessment_type' => 'hc',
                'assessment_date' => now()->subDays(rand(5, 30)),
                'source' => 'ohah_provided',
                'workflow_status' => 'completed',
                'is_current' => true,
                'version' => 1,
                'maple_score' => $profile['maple_score'],
                'adl_hierarchy' => $profile['adl_hierarchy'],
                'iadl_difficulty' => $profile['iadl_difficulty'],
                'cognitive_performance_scale' => $profile['cps'],
                'chess_score' => $profile['chess'],
                'depression_rating_scale' => $profile['drs'],
                'pain_scale' => $profile['pain'],
                'falls_in_last_90_days' => $profile['falls'],
                'wandering_flag' => $profile['wandering'],
                'primary_diagnosis_icd10' => $profile['diagnosis'],
                'raw_items' => $profile['raw_items'],
                'iar_upload_status' => 'uploaded',
                'chris_sync_status' => 'synced',
            ]);

            // Generate RUG classification
            $rug = $this->rugService->classify($assessment);

            // Update patient with scores
            $patient->update([
                'maple_score' => $profile['maple_score'],
                'rai_cha_score' => $profile['adl_hierarchy'],
            ]);

            $this->command->info("  {$patient->user->name}: InterRAI -> RUG {$rug->rug_group} ({$rug->rug_category})");
        }

        $this->command->info('Assessments and RUG classifications created for all 15 patients.');
    }

    /**
     * Get assessment profile designed to produce a specific RUG group.
     *
     * RUG groups by category and ADL level:
     * - Special Rehabilitation: RB0 (ADL 11-18), RA2 (ADL 4-10, IADL 2+), RA1 (ADL 4-10, IADL 0-1)
     * - Extensive Services: SE3 (3+ extensive), SE2 (2 extensive), SE1 (1 extensive)
     * - Special Care: SSB (ADL 14-18), SSA (ADL 4-13)
     * - Clinically Complex: CC0 (ADL 11-18), CB0 (ADL 6-10), CA2 (ADL 4-5, IADL 1+), CA1 (ADL 4-5, IADL 0)
     * - Impaired Cognition: IB0 (ADL 6-10), IA2 (ADL 4-5, IADL 1+), IA1 (ADL 4-5, IADL 0)
     * - Behaviour Problems: BB0 (ADL 6-10), BA2 (ADL 4-5, IADL 1+), BA1 (ADL 4-5, IADL 0)
     * - Reduced Physical Function: PD0 (ADL 11+), PC0 (ADL 9-10), PB0 (ADL 6-8), PA2 (ADL 4-5, IADL 1+), PA1 (ADL 4-5, IADL 0)
     */
    protected function getAssessmentProfile(string $targetRug): array
    {
        return match ($targetRug) {
            // Special Rehabilitation - HIGH ADL (11-18), high therapy
            'RB0' => [
                'maple_score' => '5',
                'adl_hierarchy' => 5,
                'iadl_difficulty' => 6,
                'cps' => 2,
                'chess' => 3,
                'drs' => 3,
                'pain' => 3,
                'falls' => true,
                'wandering' => false,
                'diagnosis' => 'I63.9', // Stroke
                'raw_items' => $this->buildRawItems([
                    'therapy_pt' => 180, 'therapy_ot' => 120, 'therapy_slp' => 60,
                    'adl_bathing' => 5, 'adl_locomotion' => 5, 'adl_eating' => 3,
                ]),
            ],

            // Special Rehabilitation - lower ADL (4-10), IADL 2+
            'RA2' => [
                'maple_score' => '4',
                'adl_hierarchy' => 3,
                'iadl_difficulty' => 5,
                'cps' => 1,
                'chess' => 2,
                'drs' => 2,
                'pain' => 2,
                'falls' => true,
                'wandering' => false,
                'diagnosis' => 'S72.001A', // Hip fracture
                'raw_items' => $this->buildRawItems([
                    'therapy_pt' => 150, 'therapy_ot' => 90, 'therapy_slp' => 0,
                    'adl_bathing' => 4, 'adl_locomotion' => 3, 'adl_eating' => 1,
                ]),
            ],

            // Special Rehabilitation - lower ADL, lower IADL
            'RA1' => [
                'maple_score' => '4',
                'adl_hierarchy' => 2,
                'iadl_difficulty' => 3,
                'cps' => 1,
                'chess' => 2,
                'drs' => 2,
                'pain' => 2,
                'falls' => true,
                'wandering' => false,
                'diagnosis' => 'S72.001A', // Hip fracture
                'raw_items' => $this->buildRawItems([
                    'therapy_pt' => 150, 'therapy_ot' => 90, 'therapy_slp' => 0,
                    'adl_bathing' => 3, 'adl_locomotion' => 2, 'adl_eating' => 0,
                ]),
            ],

            // Extensive Services - 2 extensive therapies (moderate)
            'SE2' => [
                'maple_score' => '5',
                'adl_hierarchy' => 5,
                'iadl_difficulty' => 6,
                'cps' => 3,
                'chess' => 4,
                'drs' => 2,
                'pain' => 3,
                'falls' => false,
                'wandering' => false,
                'diagnosis' => 'N18.6', // ESRD
                'raw_items' => $this->buildRawItems([
                    'extensive_dialysis' => 1, 'extensive_iv' => 1,
                    'adl_bathing' => 5, 'adl_locomotion' => 5, 'adl_eating' => 3,
                ]),
            ],

            // Extensive Services - dialysis only
            'SE1' => [
                'maple_score' => '5',
                'adl_hierarchy' => 4,
                'iadl_difficulty' => 6,
                'cps' => 2,
                'chess' => 4,
                'drs' => 2,
                'pain' => 2,
                'falls' => false,
                'wandering' => false,
                'diagnosis' => 'N18.6', // ESRD
                'raw_items' => $this->buildRawItems([
                    'extensive_dialysis' => 1,
                    'adl_bathing' => 5, 'adl_locomotion' => 4, 'adl_eating' => 2,
                ]),
            ],

            // Extensive Services - ventilator/trach
            'SE3' => [
                'maple_score' => '5',
                'adl_hierarchy' => 6,
                'iadl_difficulty' => 6,
                'cps' => 4,
                'chess' => 5,
                'drs' => 3,
                'pain' => 3,
                'falls' => false,
                'wandering' => false,
                'diagnosis' => 'J96.11', // Chronic respiratory failure
                'raw_items' => $this->buildRawItems([
                    'extensive_ventilator' => 1, 'extensive_trach' => 1, 'extensive_iv' => 1,
                    'adl_bathing' => 6, 'adl_locomotion' => 6, 'adl_eating' => 5,
                ]),
            ],

            // Special Care - lower ADL
            'SSA' => [
                'maple_score' => '5',
                'adl_hierarchy' => 4,
                'iadl_difficulty' => 6,
                'cps' => 3,
                'chess' => 3,
                'drs' => 3,
                'pain' => 3,
                'falls' => true,
                'wandering' => true,
                'diagnosis' => 'G30.9', // Alzheimer's
                'raw_items' => $this->buildRawItems([
                    'clinical_wound' => 1, 'clinical_tube_feeding' => 1,
                    'adl_bathing' => 5, 'adl_locomotion' => 4, 'adl_eating' => 3,
                ]),
            ],

            // Special Care - higher ADL
            'SSB' => [
                'maple_score' => '5',
                'adl_hierarchy' => 5,
                'iadl_difficulty' => 6,
                'cps' => 4,
                'chess' => 4,
                'drs' => 4,
                'pain' => 3,
                'falls' => true,
                'wandering' => false,
                'diagnosis' => 'G35', // MS
                'raw_items' => $this->buildRawItems([
                    'special_ms' => 1, 'clinical_wound' => 1, 'clinical_tube_feeding' => 1, 'clinical_oxygen' => 1,
                    'adl_bathing' => 6, 'adl_locomotion' => 5, 'adl_eating' => 4,
                ]),
            ],

            // Clinically Complex - highest ADL
            'CC0' => [
                'maple_score' => '5',
                'adl_hierarchy' => 4,
                'iadl_difficulty' => 6,
                'cps' => 2,
                'chess' => 4,
                'drs' => 3,
                'pain' => 3,
                'falls' => true,
                'wandering' => false,
                'diagnosis' => 'J44.1', // COPD exacerbation
                'raw_items' => $this->buildRawItems([
                    'clinical_copd' => 1, 'clinical_oxygen' => 1, 'clinical_pneumonia' => 1, 'clinical_wound' => 1,
                    'adl_bathing' => 5, 'adl_locomotion' => 4, 'adl_eating' => 2,
                ]),
            ],

            // Clinically Complex - mid ADL
            'CB0' => [
                'maple_score' => '4',
                'adl_hierarchy' => 3,
                'iadl_difficulty' => 5,
                'cps' => 2,
                'chess' => 3,
                'drs' => 2,
                'pain' => 3,
                'falls' => true,
                'wandering' => false,
                'diagnosis' => 'I50.9', // Heart failure
                'raw_items' => $this->buildRawItems([
                    'clinical_chf' => 1, 'clinical_oxygen' => 1, 'clinical_wound' => 1,
                    'adl_bathing' => 4, 'adl_locomotion' => 3, 'adl_eating' => 1,
                ]),
            ],

            // Clinically Complex - lower ADL, higher IADL
            'CA2' => [
                'maple_score' => '3',
                'adl_hierarchy' => 2,
                'iadl_difficulty' => 5,
                'cps' => 1,
                'chess' => 3,
                'drs' => 2,
                'pain' => 2,
                'falls' => false,
                'wandering' => false,
                'diagnosis' => 'E11.65', // Type 2 diabetes
                'raw_items' => $this->buildRawItems([
                    'clinical_diabetes' => 1, 'clinical_wound' => 1,
                    'adl_bathing' => 2, 'adl_locomotion' => 2, 'adl_eating' => 1,
                ]),
            ],

            // Clinically Complex - lower ADL, lower IADL
            'CA1' => [
                'maple_score' => '3',
                'adl_hierarchy' => 1,
                'iadl_difficulty' => 2,
                'cps' => 1,
                'chess' => 3,
                'drs' => 2,
                'pain' => 2,
                'falls' => false,
                'wandering' => false,
                'diagnosis' => 'E11.65', // Type 2 diabetes
                'raw_items' => $this->buildRawItems([
                    'clinical_diabetes' => 1, 'clinical_wound' => 1,
                    'adl_bathing' => 2, 'adl_locomotion' => 1, 'adl_eating' => 0,
                ]),
            ],

            // Impaired Cognition - higher ADL
            'IB0' => [
                'maple_score' => '5',
                'adl_hierarchy' => 3,
                'iadl_difficulty' => 6,
                'cps' => 5,
                'chess' => 2,
                'drs' => 3,
                'pain' => 1,
                'falls' => true,
                'wandering' => true,
                'diagnosis' => 'G30.1', // Alzheimer's late onset
                'raw_items' => $this->buildRawItems([
                    'cps_decision_making' => 5, 'cps_short_term_memory' => 1, 'cps_communication' => 3,
                    'adl_bathing' => 4, 'adl_locomotion' => 3, 'adl_eating' => 2,
                ]),
            ],

            // Impaired Cognition - lower ADL, higher IADL
            'IA2' => [
                'maple_score' => '4',
                'adl_hierarchy' => 2,
                'iadl_difficulty' => 5,
                'cps' => 4,
                'chess' => 1,
                'drs' => 2,
                'pain' => 0,
                'falls' => true,
                'wandering' => true,
                'diagnosis' => 'G30.9', // Alzheimer's
                'raw_items' => $this->buildRawItems([
                    'cps_decision_making' => 4, 'cps_short_term_memory' => 1, 'cps_communication' => 2,
                    'adl_bathing' => 2, 'adl_locomotion' => 2, 'adl_eating' => 1,
                ]),
            ],

            // Impaired Cognition - lower ADL, lower IADL
            'IA1' => [
                'maple_score' => '4',
                'adl_hierarchy' => 1,
                'iadl_difficulty' => 2,
                'cps' => 4,
                'chess' => 1,
                'drs' => 2,
                'pain' => 0,
                'falls' => false,
                'wandering' => true,
                'diagnosis' => 'G30.9', // Alzheimer's
                'raw_items' => $this->buildRawItems([
                    'cps_decision_making' => 4, 'cps_short_term_memory' => 1, 'cps_communication' => 2,
                    'adl_bathing' => 2, 'adl_locomotion' => 1, 'adl_eating' => 0,
                ]),
            ],

            // Behaviour Problems - higher ADL
            'BB0' => [
                'maple_score' => '4',
                'adl_hierarchy' => 3,
                'iadl_difficulty' => 5,
                'cps' => 3,
                'chess' => 2,
                'drs' => 4,
                'pain' => 1,
                'falls' => true,
                'wandering' => false,
                'diagnosis' => 'F03.90', // Dementia with behavioral
                'raw_items' => $this->buildRawItems([
                    'behaviour_verbal' => 2, 'behaviour_physical' => 2, 'behaviour_wandering' => 1, 'behaviour_resists_care' => 2,
                    'adl_bathing' => 4, 'adl_locomotion' => 3, 'adl_eating' => 1,
                ]),
            ],

            // Behaviour Problems - lower ADL, higher IADL
            'BA2' => [
                'maple_score' => '4',
                'adl_hierarchy' => 2,
                'iadl_difficulty' => 5,
                'cps' => 2,
                'chess' => 1,
                'drs' => 3,
                'pain' => 1,
                'falls' => false,
                'wandering' => false,
                'diagnosis' => 'F41.1', // Generalized anxiety
                'raw_items' => $this->buildRawItems([
                    'behaviour_verbal' => 2, 'behaviour_physical' => 1, 'behaviour_socially_inappropriate' => 1,
                    'adl_bathing' => 2, 'adl_locomotion' => 2, 'adl_eating' => 1,
                ]),
            ],

            // Behaviour Problems - lower ADL, lower IADL
            'BA1' => [
                'maple_score' => '4',
                'adl_hierarchy' => 1,
                'iadl_difficulty' => 2,
                'cps' => 2,
                'chess' => 1,
                'drs' => 3,
                'pain' => 1,
                'falls' => false,
                'wandering' => false,
                'diagnosis' => 'F31.9', // Bipolar
                'raw_items' => $this->buildRawItems([
                    'behaviour_verbal' => 2, 'behaviour_physical' => 1, 'behaviour_socially_inappropriate' => 1,
                    'adl_bathing' => 2, 'adl_locomotion' => 1, 'adl_eating' => 0,
                ]),
            ],

            // Reduced Physical Function - highest ADL
            'PD0' => [
                'maple_score' => '4',
                'adl_hierarchy' => 4,
                'iadl_difficulty' => 6,
                'cps' => 1,
                'chess' => 2,
                'drs' => 2,
                'pain' => 2,
                'falls' => true,
                'wandering' => false,
                'diagnosis' => 'G20', // Parkinson's
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 5, 'adl_locomotion' => 4, 'adl_eating' => 2,
                ]),
            ],

            // Reduced Physical Function - ADL 9-10
            'PC0' => [
                'maple_score' => '4',
                'adl_hierarchy' => 4,
                'iadl_difficulty' => 5,
                'cps' => 1,
                'chess' => 2,
                'drs' => 2,
                'pain' => 2,
                'falls' => true,
                'wandering' => false,
                'diagnosis' => 'G20', // Parkinson's
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 4, 'adl_locomotion' => 3, 'adl_eating' => 2,
                ]),
            ],

            // Reduced Physical Function - ADL 6-8
            'PB0' => [
                'maple_score' => '3',
                'adl_hierarchy' => 3,
                'iadl_difficulty' => 4,
                'cps' => 1,
                'chess' => 1,
                'drs' => 2,
                'pain' => 2,
                'falls' => true,
                'wandering' => false,
                'diagnosis' => 'I10', // Hypertension
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 3, 'adl_locomotion' => 3, 'adl_eating' => 1,
                ]),
            ],

            // Reduced Physical Function - low ADL (4-5), higher IADL (1+)
            'PA2' => [
                'maple_score' => '3',
                'adl_hierarchy' => 2,
                'iadl_difficulty' => 5,
                'cps' => 0,
                'chess' => 1,
                'drs' => 1,
                'pain' => 1,
                'falls' => false,
                'wandering' => false,
                'diagnosis' => 'M17.9', // Osteoarthritis
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 2, 'adl_locomotion' => 2, 'adl_eating' => 1,
                ]),
            ],

            // Reduced Physical Function - lowest ADL (4-5), low IADL (0) - PA1 (default)
            default => [
                'maple_score' => '2',
                'adl_hierarchy' => 1,
                'iadl_difficulty' => 2,
                'cps' => 0,
                'chess' => 1,
                'drs' => 1,
                'pain' => 1,
                'falls' => false,
                'wandering' => false,
                'diagnosis' => 'M17.9', // Osteoarthritis
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 2, 'adl_locomotion' => 1, 'adl_eating' => 0,
                ]),
            ],
        };
    }

    protected function buildRawItems(array $items): array
    {
        $defaults = [
            // ADL items
            'adl_bathing' => 0, 'adl_hygiene' => 0, 'adl_locomotion' => 0, 'adl_eating' => 0,
            'adl_toilet_use' => 0, 'adl_bed_mobility' => 0, 'adl_transfer' => 0,
            // IADL items
            'iadl_meal_prep' => 0, 'iadl_housework' => 0, 'iadl_shopping' => 0, 'iadl_medications' => 0,
            // Cognition
            'cps_decision_making' => 0, 'cps_short_term_memory' => 0, 'cps_communication' => 0,
            // Behaviour
            'behaviour_verbal' => 0, 'behaviour_physical' => 0, 'behaviour_wandering' => 0,
            'behaviour_resists_care' => 0, 'behaviour_socially_inappropriate' => 0,
            // Clinical conditions
            'clinical_diabetes' => 0, 'clinical_chf' => 0, 'clinical_copd' => 0, 'clinical_pneumonia' => 0,
            'clinical_wound' => 0, 'clinical_tube_feeding' => 0, 'clinical_oxygen' => 0, 'clinical_dialysis' => 0,
            // Special conditions
            'special_ms' => 0, 'special_quadriplegia' => 0, 'special_burns' => 0, 'special_coma' => 0,
            // Extensive services
            'extensive_iv' => 0, 'extensive_ventilator' => 0, 'extensive_trach' => 0, 'extensive_dialysis' => 0,
            // Therapy
            'therapy_pt' => 0, 'therapy_ot' => 0, 'therapy_slp' => 0,
        ];

        return array_merge($defaults, $items);
    }
}
