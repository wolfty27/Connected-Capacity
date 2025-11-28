<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\RUGClassification;
use App\Models\User;
use App\Services\RUGClassificationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * RugDemoSeeder - Creates 15 demo patients with InterRAI assessments and RUG classifications
 *
 * This seeder creates a realistic set of patients covering all 7 RUG-III/HC categories:
 * - Special Rehabilitation (1 patient)
 * - Extensive Services (2 patients)
 * - Special Care (2 patients)
 * - Clinically Complex (3 patients)
 * - Impaired Cognition (2 patients)
 * - Behaviour Problems (2 patients)
 * - Reduced Physical Function (3 patients)
 *
 * Each patient has:
 * - User account
 * - Patient record linked to hospital
 * - InterRAI HC assessment with appropriate scores
 * - RUG classification derived from assessment
 * - Queue entry (TNP complete, ready for bundle building)
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 * @see docs/CC21_RUG_Bundle_Templates.md
 */
class RugDemoSeeder extends Seeder
{
    protected RUGClassificationService $rugService;

    public function __construct()
    {
        $this->rugService = new RUGClassificationService();
    }

    public function run(): void
    {
        $this->command->info('Seeding RUG Demo Patients with InterRAI assessments...');

        $hospital = Hospital::first();
        if (!$hospital) {
            $this->command->error('No hospital found. Please run DemoSeeder first.');
            return;
        }

        $demoPatients = $this->getDemoPatientProfiles();

        foreach ($demoPatients as $index => $profile) {
            $patient = $this->createPatientWithAssessment($hospital, $profile, $index + 1);
            $this->command->info("  [{$index}/15] Created: {$profile['name']} -> RUG: {$profile['expected_rug']} ({$profile['category']})");
        }

        $this->command->info('RUG Demo Patients seeded successfully!');
        $this->command->info('');
        $this->command->info('Summary by RUG Category:');
        $this->printSummary();
    }

    protected function getDemoPatientProfiles(): array
    {
        return [
            // ============================================
            // REDUCED PHYSICAL FUNCTION (PA1, PB0, PD0)
            // ============================================
            [
                'name' => 'Alice Patterson',
                'email' => 'alice.patterson.rug@example.com',
                'gender' => 'Female',
                'dob' => '1955-03-12',
                'ohip' => 'RUG1-001-001',
                'category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
                'expected_rug' => 'PA1',
                'assessment' => [
                    'maple_score' => '2',
                    'adl_hierarchy' => 1,
                    'iadl_difficulty' => 2,
                    'cognitive_performance_scale' => 0,
                    'chess_score' => 1,
                    'depression_rating_scale' => 1,
                    'pain_scale' => 1,
                    'falls_in_last_90_days' => false,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'M17.9', // Osteoarthritis
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 2, 'adl_hygiene' => 1, 'adl_locomotion' => 1, 'adl_eating' => 0,
                    'iadl_meal_prep' => 2, 'iadl_housework' => 3, 'iadl_shopping' => 3,
                ]),
            ],
            [
                'name' => 'Bernard Collins',
                'email' => 'bernard.collins.rug@example.com',
                'gender' => 'Male',
                'dob' => '1948-07-22',
                'ohip' => 'RUG1-002-002',
                'category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
                'expected_rug' => 'PB0',
                'assessment' => [
                    'maple_score' => '3',
                    'adl_hierarchy' => 2,
                    'iadl_difficulty' => 4,
                    'cognitive_performance_scale' => 1,
                    'chess_score' => 1,
                    'depression_rating_scale' => 2,
                    'pain_scale' => 2,
                    'falls_in_last_90_days' => true,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'I10', // Hypertension
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 3, 'adl_hygiene' => 2, 'adl_locomotion' => 2, 'adl_eating' => 1,
                    'iadl_meal_prep' => 4, 'iadl_housework' => 4, 'iadl_shopping' => 5,
                ]),
            ],
            [
                'name' => 'Carol Dawson',
                'email' => 'carol.dawson.rug@example.com',
                'gender' => 'Female',
                'dob' => '1940-11-05',
                'ohip' => 'RUG1-003-003',
                'category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
                'expected_rug' => 'PD0',
                'assessment' => [
                    'maple_score' => '4',
                    'adl_hierarchy' => 4,
                    'iadl_difficulty' => 6,
                    'cognitive_performance_scale' => 1,
                    'chess_score' => 2,
                    'depression_rating_scale' => 2,
                    'pain_scale' => 2,
                    'falls_in_last_90_days' => true,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'G20', // Parkinson's
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 5, 'adl_hygiene' => 4, 'adl_locomotion' => 4, 'adl_eating' => 2,
                    'iadl_meal_prep' => 6, 'iadl_housework' => 6, 'iadl_shopping' => 6,
                ]),
            ],

            // ============================================
            // BEHAVIOUR PROBLEMS (BA1, BB0)
            // ============================================
            [
                'name' => 'Daniel Evans',
                'email' => 'daniel.evans.rug@example.com',
                'gender' => 'Male',
                'dob' => '1952-04-18',
                'ohip' => 'RUG2-004-004',
                'category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
                'expected_rug' => 'BA1',
                'assessment' => [
                    'maple_score' => '4',
                    'adl_hierarchy' => 1,
                    'iadl_difficulty' => 3,
                    'cognitive_performance_scale' => 2,
                    'chess_score' => 1,
                    'depression_rating_scale' => 3,
                    'pain_scale' => 1,
                    'falls_in_last_90_days' => false,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'F31.9', // Bipolar disorder
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 2, 'adl_hygiene' => 1, 'adl_locomotion' => 1, 'adl_eating' => 0,
                    'behaviour_verbal' => 2, 'behaviour_physical' => 1, 'behaviour_socially_inappropriate' => 1,
                ]),
            ],
            [
                'name' => 'Eleanor Foster',
                'email' => 'eleanor.foster.rug@example.com',
                'gender' => 'Female',
                'dob' => '1945-09-30',
                'ohip' => 'RUG2-005-005',
                'category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
                'expected_rug' => 'BB0',
                'assessment' => [
                    'maple_score' => '4',
                    'adl_hierarchy' => 3,
                    'iadl_difficulty' => 5,
                    'cognitive_performance_scale' => 3,
                    'chess_score' => 2,
                    'depression_rating_scale' => 4,
                    'pain_scale' => 1,
                    'falls_in_last_90_days' => true,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'F03.90', // Dementia with behavioral disturbance
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 4, 'adl_hygiene' => 3, 'adl_locomotion' => 3, 'adl_eating' => 1,
                    'behaviour_verbal' => 2, 'behaviour_physical' => 2, 'behaviour_wandering' => 1, 'behaviour_resists_care' => 2,
                ]),
            ],

            // ============================================
            // IMPAIRED COGNITION (IA1, IB0)
            // ============================================
            [
                'name' => 'Frank Gregory',
                'email' => 'frank.gregory.rug@example.com',
                'gender' => 'Male',
                'dob' => '1938-01-14',
                'ohip' => 'RUG3-006-006',
                'category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
                'expected_rug' => 'IA1',
                'assessment' => [
                    'maple_score' => '4',
                    'adl_hierarchy' => 1,
                    'iadl_difficulty' => 4,
                    'cognitive_performance_scale' => 4,
                    'chess_score' => 1,
                    'depression_rating_scale' => 2,
                    'pain_scale' => 0,
                    'falls_in_last_90_days' => false,
                    'wandering_flag' => true,
                    'primary_diagnosis_icd10' => 'G30.9', // Alzheimer's
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 2, 'adl_hygiene' => 1, 'adl_locomotion' => 1, 'adl_eating' => 0,
                    'cps_decision_making' => 4, 'cps_short_term_memory' => 1, 'cps_communication' => 2,
                ]),
            ],
            [
                'name' => 'Grace Hamilton',
                'email' => 'grace.hamilton.rug@example.com',
                'gender' => 'Female',
                'dob' => '1935-06-08',
                'ohip' => 'RUG3-007-007',
                'category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
                'expected_rug' => 'IB0',
                'assessment' => [
                    'maple_score' => '5',
                    'adl_hierarchy' => 3,
                    'iadl_difficulty' => 6,
                    'cognitive_performance_scale' => 5,
                    'chess_score' => 2,
                    'depression_rating_scale' => 3,
                    'pain_scale' => 1,
                    'falls_in_last_90_days' => true,
                    'wandering_flag' => true,
                    'primary_diagnosis_icd10' => 'G30.1', // Alzheimer's late onset
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 4, 'adl_hygiene' => 3, 'adl_locomotion' => 3, 'adl_eating' => 2,
                    'cps_decision_making' => 5, 'cps_short_term_memory' => 1, 'cps_communication' => 3,
                ]),
            ],

            // ============================================
            // CLINICALLY COMPLEX (CA1, CB0, CC0)
            // ============================================
            [
                'name' => 'Henry Irving',
                'email' => 'henry.irving.rug@example.com',
                'gender' => 'Male',
                'dob' => '1950-12-25',
                'ohip' => 'RUG4-008-008',
                'category' => RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
                'expected_rug' => 'CA1',
                'assessment' => [
                    'maple_score' => '3',
                    'adl_hierarchy' => 1,
                    'iadl_difficulty' => 3,
                    'cognitive_performance_scale' => 1,
                    'chess_score' => 3,
                    'depression_rating_scale' => 2,
                    'pain_scale' => 2,
                    'falls_in_last_90_days' => false,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'E11.65', // Type 2 diabetes with hyperglycemia
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 2, 'adl_hygiene' => 1, 'adl_locomotion' => 1, 'adl_eating' => 0,
                    'clinical_diabetes' => 1, 'clinical_dialysis' => 0, 'clinical_oxygen' => 0, 'clinical_wound' => 1,
                ]),
            ],
            [
                'name' => 'Irene Jackson',
                'email' => 'irene.jackson.rug@example.com',
                'gender' => 'Female',
                'dob' => '1943-08-17',
                'ohip' => 'RUG4-009-009',
                'category' => RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
                'expected_rug' => 'CB0',
                'assessment' => [
                    'maple_score' => '4',
                    'adl_hierarchy' => 3,
                    'iadl_difficulty' => 5,
                    'cognitive_performance_scale' => 2,
                    'chess_score' => 3,
                    'depression_rating_scale' => 2,
                    'pain_scale' => 3,
                    'falls_in_last_90_days' => true,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'I50.9', // Heart failure
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 4, 'adl_hygiene' => 3, 'adl_locomotion' => 3, 'adl_eating' => 1,
                    'clinical_diabetes' => 0, 'clinical_chf' => 1, 'clinical_oxygen' => 1, 'clinical_wound' => 1,
                ]),
            ],
            [
                'name' => 'James Kennedy',
                'email' => 'james.kennedy.rug@example.com',
                'gender' => 'Male',
                'dob' => '1947-02-28',
                'ohip' => 'RUG4-010-010',
                'category' => RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
                'expected_rug' => 'CC0',
                'assessment' => [
                    'maple_score' => '5',
                    'adl_hierarchy' => 4,
                    'iadl_difficulty' => 6,
                    'cognitive_performance_scale' => 2,
                    'chess_score' => 4,
                    'depression_rating_scale' => 3,
                    'pain_scale' => 3,
                    'falls_in_last_90_days' => true,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'J44.1', // COPD with acute exacerbation
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 5, 'adl_hygiene' => 4, 'adl_locomotion' => 4, 'adl_eating' => 2,
                    'clinical_copd' => 1, 'clinical_oxygen' => 1, 'clinical_pneumonia' => 1, 'clinical_wound' => 1,
                ]),
            ],

            // ============================================
            // SPECIAL CARE (SSA, SSB)
            // ============================================
            [
                'name' => 'Karen Lewis',
                'email' => 'karen.lewis.rug@example.com',
                'gender' => 'Female',
                'dob' => '1942-05-20',
                'ohip' => 'RUG5-011-011',
                'category' => RUGClassification::CATEGORY_SPECIAL_CARE,
                'expected_rug' => 'SSA',
                'assessment' => [
                    'maple_score' => '5',
                    'adl_hierarchy' => 4,
                    'iadl_difficulty' => 6,
                    'cognitive_performance_scale' => 3,
                    'chess_score' => 3,
                    'depression_rating_scale' => 3,
                    'pain_scale' => 3,
                    'falls_in_last_90_days' => true,
                    'wandering_flag' => true,
                    'primary_diagnosis_icd10' => 'G30.9', // Alzheimer's with complications
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 5, 'adl_hygiene' => 4, 'adl_locomotion' => 4, 'adl_eating' => 3,
                    'special_ms' => 0, 'special_quadriplegia' => 0, 'special_burns' => 0, 'special_coma' => 0, 'special_septicemia' => 0,
                    'clinical_wound' => 1, 'clinical_tube_feeding' => 1,
                ]),
            ],
            [
                'name' => 'Lawrence Morris',
                'email' => 'lawrence.morris.rug@example.com',
                'gender' => 'Male',
                'dob' => '1939-10-03',
                'ohip' => 'RUG5-012-012',
                'category' => RUGClassification::CATEGORY_SPECIAL_CARE,
                'expected_rug' => 'SSB',
                'assessment' => [
                    'maple_score' => '5',
                    'adl_hierarchy' => 5,
                    'iadl_difficulty' => 6,
                    'cognitive_performance_scale' => 4,
                    'chess_score' => 4,
                    'depression_rating_scale' => 4,
                    'pain_scale' => 3,
                    'falls_in_last_90_days' => true,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'G35', // Multiple sclerosis
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 6, 'adl_hygiene' => 5, 'adl_locomotion' => 5, 'adl_eating' => 4,
                    'special_ms' => 1, 'special_quadriplegia' => 0, 'special_burns' => 0, 'special_coma' => 0,
                    'clinical_wound' => 1, 'clinical_tube_feeding' => 1, 'clinical_oxygen' => 1,
                ]),
            ],

            // ============================================
            // EXTENSIVE SERVICES (SE1, SE3)
            // ============================================
            [
                'name' => 'Mary Nelson',
                'email' => 'mary.nelson.rug@example.com',
                'gender' => 'Female',
                'dob' => '1946-03-15',
                'ohip' => 'RUG6-013-013',
                'category' => RUGClassification::CATEGORY_EXTENSIVE_SERVICES,
                'expected_rug' => 'SE1',
                'assessment' => [
                    'maple_score' => '5',
                    'adl_hierarchy' => 4,
                    'iadl_difficulty' => 6,
                    'cognitive_performance_scale' => 2,
                    'chess_score' => 4,
                    'depression_rating_scale' => 2,
                    'pain_scale' => 2,
                    'falls_in_last_90_days' => false,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'N18.6', // End stage renal disease
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 5, 'adl_hygiene' => 4, 'adl_locomotion' => 4, 'adl_eating' => 2,
                    'extensive_iv' => 0, 'extensive_ventilator' => 0, 'extensive_trach' => 0, 'extensive_dialysis' => 1,
                ]),
            ],
            [
                'name' => 'Nathan Oliver',
                'email' => 'nathan.oliver.rug@example.com',
                'gender' => 'Male',
                'dob' => '1937-11-28',
                'ohip' => 'RUG6-014-014',
                'category' => RUGClassification::CATEGORY_EXTENSIVE_SERVICES,
                'expected_rug' => 'SE3',
                'assessment' => [
                    'maple_score' => '5',
                    'adl_hierarchy' => 6,
                    'iadl_difficulty' => 6,
                    'cognitive_performance_scale' => 4,
                    'chess_score' => 5,
                    'depression_rating_scale' => 3,
                    'pain_scale' => 3,
                    'falls_in_last_90_days' => false,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'J96.11', // Chronic respiratory failure
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 6, 'adl_hygiene' => 6, 'adl_locomotion' => 6, 'adl_eating' => 5,
                    'extensive_iv' => 1, 'extensive_ventilator' => 1, 'extensive_trach' => 1, 'extensive_dialysis' => 0,
                ]),
            ],

            // ============================================
            // SPECIAL REHABILITATION (RA1)
            // ============================================
            [
                'name' => 'Olivia Parker',
                'email' => 'olivia.parker.rug@example.com',
                'gender' => 'Female',
                'dob' => '1958-07-09',
                'ohip' => 'RUG7-015-015',
                'category' => RUGClassification::CATEGORY_SPECIAL_REHABILITATION,
                'expected_rug' => 'RA1',
                'assessment' => [
                    'maple_score' => '4',
                    'adl_hierarchy' => 3,
                    'iadl_difficulty' => 5,
                    'cognitive_performance_scale' => 1,
                    'chess_score' => 2,
                    'depression_rating_scale' => 2,
                    'pain_scale' => 2,
                    'falls_in_last_90_days' => true,
                    'wandering_flag' => false,
                    'primary_diagnosis_icd10' => 'S72.001A', // Hip fracture
                ],
                'raw_items' => $this->buildRawItems([
                    'adl_bathing' => 4, 'adl_hygiene' => 3, 'adl_locomotion' => 4, 'adl_eating' => 1,
                    'therapy_pt' => 150, 'therapy_ot' => 90, 'therapy_slp' => 0,
                    // Total 240 minutes = RA1 (150-299 min)
                ]),
            ],
        ];
    }

    protected function createPatientWithAssessment(Hospital $hospital, array $profile, int $index): Patient
    {
        // Create user
        $user = User::create([
            'name' => $profile['name'],
            'email' => $profile['email'],
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);

        // Create patient
        $patient = Patient::create([
            'user_id' => $user->id,
            'hospital_id' => $hospital->id,
            'date_of_birth' => $profile['dob'],
            'gender' => $profile['gender'],
            'ohip' => $profile['ohip'],
            'status' => 'Pending',
            'is_in_queue' => true,
            'interrai_status' => 'completed',
        ]);

        // Create InterRAI assessment
        $assessment = InterraiAssessment::create([
            'patient_id' => $patient->id,
            'assessment_type' => 'hc',
            'assessment_date' => now()->subDays(rand(5, 30)),
            'source' => 'ohah_provided',
            'workflow_status' => 'completed',
            'is_current' => true,
            'version' => 1,
            'maple_score' => $profile['assessment']['maple_score'],
            'adl_hierarchy' => $profile['assessment']['adl_hierarchy'],
            'iadl_difficulty' => $profile['assessment']['iadl_difficulty'],
            'cognitive_performance_scale' => $profile['assessment']['cognitive_performance_scale'],
            'chess_score' => $profile['assessment']['chess_score'],
            'depression_rating_scale' => $profile['assessment']['depression_rating_scale'],
            'pain_scale' => $profile['assessment']['pain_scale'],
            'falls_in_last_90_days' => $profile['assessment']['falls_in_last_90_days'],
            'wandering_flag' => $profile['assessment']['wandering_flag'],
            'primary_diagnosis_icd10' => $profile['assessment']['primary_diagnosis_icd10'],
            'raw_items' => $profile['raw_items'],
            'iar_upload_status' => 'uploaded',
            'chris_sync_status' => 'synced',
        ]);

        // Generate RUG classification using the service
        $rugClassification = $this->rugService->classify($assessment);

        // Update patient with maple score
        $patient->update([
            'maple_score' => $profile['assessment']['maple_score'],
            'rai_cha_score' => $profile['assessment']['adl_hierarchy'],
        ]);

        // Create queue entry (TNP complete - ready for bundle building)
        PatientQueue::create([
            'patient_id' => $patient->id,
            'queue_status' => 'tnp_complete',
            'priority' => $this->calculatePriority($rugClassification),
            'entered_queue_at' => now()->subDays(rand(7, 21)),
            'triage_completed_at' => now()->subDays(rand(5, 14)),
            'tnp_completed_at' => now()->subDays(rand(1, 7)),
        ]);

        return $patient;
    }

    protected function calculatePriority(RUGClassification $rug): int
    {
        // Higher RUG rank = higher priority (lower number)
        $rank = $rug->numeric_rank;

        return match (true) {
            $rank >= 20 => 1, // SE/RB - highest priority
            $rank >= 15 => 2, // SSB/SSA/CC - high priority
            $rank >= 9 => 3,  // CB/CA/IB/IA - medium-high
            $rank >= 6 => 4,  // BB/BA - medium
            default => 5,     // PD/PC/PB/PA - standard
        };
    }

    protected function buildRawItems(array $items): array
    {
        // Build a comprehensive raw_items structure for the assessment
        $defaults = [
            // ADL items (Section G)
            'adl_bathing' => 0,
            'adl_hygiene' => 0,
            'adl_locomotion' => 0,
            'adl_eating' => 0,
            'adl_toilet_use' => 0,
            'adl_bed_mobility' => 0,
            'adl_transfer' => 0,
            'adl_dressing_upper' => 0,
            'adl_dressing_lower' => 0,

            // IADL items
            'iadl_meal_prep' => 0,
            'iadl_housework' => 0,
            'iadl_shopping' => 0,
            'iadl_medications' => 0,
            'iadl_phone' => 0,
            'iadl_finances' => 0,
            'iadl_transportation' => 0,

            // Cognition items (Section C)
            'cps_decision_making' => 0,
            'cps_short_term_memory' => 0,
            'cps_communication' => 0,

            // Behaviour items (Section E)
            'behaviour_verbal' => 0,
            'behaviour_physical' => 0,
            'behaviour_wandering' => 0,
            'behaviour_resists_care' => 0,
            'behaviour_socially_inappropriate' => 0,

            // Clinical conditions
            'clinical_diabetes' => 0,
            'clinical_chf' => 0,
            'clinical_copd' => 0,
            'clinical_pneumonia' => 0,
            'clinical_wound' => 0,
            'clinical_tube_feeding' => 0,
            'clinical_oxygen' => 0,
            'clinical_dialysis' => 0,

            // Special conditions
            'special_ms' => 0,
            'special_quadriplegia' => 0,
            'special_burns' => 0,
            'special_coma' => 0,
            'special_septicemia' => 0,

            // Extensive services
            'extensive_iv' => 0,
            'extensive_ventilator' => 0,
            'extensive_trach' => 0,
            'extensive_dialysis' => 0,

            // Therapy minutes
            'therapy_pt' => 0,
            'therapy_ot' => 0,
            'therapy_slp' => 0,
            'therapy_nursing_rehab' => 0,
        ];

        return array_merge($defaults, $items);
    }

    protected function printSummary(): void
    {
        $categories = RUGClassification::select('rug_category')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw("GROUP_CONCAT(rug_group SEPARATOR ', ') as groups")
            ->groupBy('rug_category')
            ->orderByDesc('count')
            ->get();

        foreach ($categories as $category) {
            $this->command->info("  - {$category->rug_category}: {$category->count} patients ({$category->groups})");
        }
    }
}
