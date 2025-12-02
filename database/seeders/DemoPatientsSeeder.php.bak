<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\User;
use App\Services\RegionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DemoPatientsSeeder - Creates 15 demo patients for CC2.1 architecture
 *
 * Creates exactly 15 patients:
 * - 5 in Intake Queue:
 *   - 3 READY (have InterRAI HC Assessment + RUGClassification): assessment_complete status
 *   - 2 NOT READY (no assessment yet): assessment_in_progress or triage_complete status
 * - 10 Active patients (have Assessment + RUGClassification + CarePlan)
 *
 * Patient distribution by RUG category target (for patients with assessments):
 * - Special Rehabilitation: 2 patients (RB0, RA2)
 * - Extensive Services: 2 patients (SE3, SE1)
 * - Special Care: 2 patients (SSB, SSA)
 * - Clinically Complex: 2 patients (CC0, CC0)
 * - Impaired Cognition: 2 patients (IB0, IB0)
 * - Behaviour Problems: 2 patients (BB0, BB0)
 * - Reduced Physical Function: 1 patient (PD0)
 *
 * This distribution covers all 7 major RUG categories to exercise the bundle engine.
 *
 * IMPORTANT: Readiness for bundle is determined solely by the existence of
 * InterRAI HC Assessment + RUGClassification records, NOT by queue_status alone.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class DemoPatientsSeeder extends Seeder
{
    /**
     * Realistic Toronto/GTA addresses for demo patients.
     *
     * These are real public locations (hospitals, community centres, libraries)
     * to provide realistic travel time calculations for scheduling demo.
     *
     * Format: [address, city, postal_code, lat, lng]
     */
    protected static array $torontoAddresses = [
        // Toronto Central - Healthcare District
        ['555 University Ave', 'Toronto', 'M5G 1X8', 43.6564, -79.3887],      // Princess Margaret Cancer Centre
        ['200 Elizabeth St', 'Toronto', 'M5G 2C4', 43.6591, -79.3881],        // Toronto General Hospital
        ['30 Bond St', 'Toronto', 'M5B 1W8', 43.6565, -79.3775],              // St. Michael's Hospital
        ['76 Grenville St', 'Toronto', 'M5S 1B2', 43.6639, -79.3854],         // Mount Sinai Hospital
        ['600 University Ave', 'Toronto', 'M5G 1X5', 43.6582, -79.3896],      // SickKids Hospital

        // Central East - Beaches / Leslieville
        ['2075 Bayview Ave', 'Toronto', 'M4N 3M5', 43.7123, -79.3774],        // Sunnybrook Hospital
        ['1 Danforth Ave', 'Toronto', 'M4K 1N2', 43.6767, -79.3496],          // Danforth area
        ['95 Queen St E', 'Toronto', 'M4C 1E2', 43.6723, -79.3356],           // The Beaches

        // North - North York
        ['4001 Leslie St', 'Toronto', 'M2K 1E1', 43.7557, -79.3651],          // North York General
        ['1050 Sheppard Ave W', 'Toronto', 'M3H 2T4', 43.7487, -79.4491],     // Baycrest

        // Central West - Etobicoke
        ['2111 Finch Ave W', 'Toronto', 'M9M 2W2', 43.7577, -79.5313],        // Humber River Hospital
        ['175 Galaxy Blvd', 'Toronto', 'M9W 0C9', 43.7089, -79.5741],         // Etobicoke area
        ['5 Fairview Mall Dr', 'Toronto', 'M2J 2Z1', 43.7778, -79.3472],      // Fairview Mall area

        // East - Scarborough
        ['3030 Birchmount Rd', 'Toronto', 'M1W 3W3', 43.8128, -79.3191],      // Scarborough Health Network
        ['3050 Lawrence Ave E', 'Toronto', 'M1P 2V5', 43.7524, -79.2657],     // Scarborough area
    ];

    protected RegionService $regionService;

    public function run(): void
    {
        $this->regionService = app(RegionService::class);
        $this->command->info('Creating 15 demo patients...');

        $hospital = Hospital::first();
        if (!$hospital) {
            $this->command->error('No hospital found. Please run DemoSeeder first.');
            return;
        }

        // Create 5 Intake Queue patients
        $this->createIntakeQueuePatients($hospital);

        // Create 10 Active patients
        $this->createActivePatients($hospital);

        $this->command->info('Demo patients created: 5 intake queue + 10 active = 15 total');
    }

    /**
     * 5 Intake Queue patients - mixed readiness states.
     *
     * - 3 READY: Have InterRAI HC Assessment + RUGClassification
     *   - Queue status: assessment_complete
     *   - Will be seeded with assessment by DemoAssessmentsSeeder
     *
     * - 2 NOT READY: No assessment yet
     *   - Queue status: triage_complete or assessment_in_progress
     *   - Will NOT be seeded with assessment
     *
     * Covering: Clinically Complex, Impaired Cognition, Behaviour Problems, Special Care, Reduced Physical Function
     */
    protected function createIntakeQueuePatients(Hospital $hospital): void
    {
        $this->command->info('  Creating 5 intake queue patients (3 ready, 2 pending)...');

        $patients = [
            // === READY PATIENTS (3) - Will have InterRAI HC Assessment + RUGClassification ===

            // Queue Patient 1: Clinically Complex (CC0) - READY
            [
                'name' => 'Eleanor Mitchell',
                'email' => 'eleanor.mitchell@demo.cc',
                'gender' => 'Female',
                'dob' => '1943-08-17',
                'ohip' => 'DEMO-Q01-001',
                'target_rug' => 'CC0', // Clinically Complex, high ADL
                'ready' => true,       // Will have assessment seeded
            ],
            // Queue Patient 2: Impaired Cognition (IB0) - READY
            [
                'name' => 'Harold Peterson',
                'email' => 'harold.peterson@demo.cc',
                'gender' => 'Male',
                'dob' => '1938-03-25',
                'ohip' => 'DEMO-Q02-002',
                'target_rug' => 'IB0', // Impaired Cognition
                'ready' => true,       // Will have assessment seeded
            ],
            // Queue Patient 3: Behaviour Problems (BB0) - READY
            [
                'name' => 'Dorothy Flanagan',
                'email' => 'dorothy.flanagan@demo.cc',
                'gender' => 'Female',
                'dob' => '1945-11-02',
                'ohip' => 'DEMO-Q03-003',
                'target_rug' => 'BB0', // Behaviour Problems
                'ready' => true,       // Will have assessment seeded
            ],

            // === NOT READY PATIENTS (2) - No assessment yet, InterRAI HC Assessment Pending ===

            // Queue Patient 4: Special Care (SSA) - NOT READY (assessment in progress)
            [
                'name' => 'Raymond Clarke',
                'email' => 'raymond.clarke@demo.cc',
                'gender' => 'Male',
                'dob' => '1940-06-14',
                'ohip' => 'DEMO-Q04-004',
                'target_rug' => null,   // No assessment yet
                'ready' => false,       // Will NOT have assessment seeded
            ],
            // Queue Patient 5: Reduced Physical Function (PD0) - NOT READY (triage complete, awaiting assessment)
            [
                'name' => 'Beatrice Wong',
                'email' => 'beatrice.wong@demo.cc',
                'gender' => 'Female',
                'dob' => '1948-09-30',
                'ohip' => 'DEMO-Q05-005',
                'target_rug' => null,   // No assessment yet
                'ready' => false,       // Will NOT have assessment seeded
            ],
        ];

        foreach ($patients as $index => $data) {
            $isReady = $data['ready'];
            $patient = $this->createPatient($hospital, $data, 'Pending', true, !$isReady, $index);

            if ($isReady) {
                // READY patient: has InterRAI HC Assessment + RUGClassification
                // Queue status: assessment_complete
                PatientQueue::create([
                    'patient_id' => $patient->id,
                    'queue_status' => 'assessment_complete',
                    'priority' => $index + 1,
                    'entered_queue_at' => now()->subDays(rand(14, 28)),
                    'triage_completed_at' => now()->subDays(rand(10, 20)),
                    'assessment_completed_at' => now()->subDays(rand(1, 5)),
                ]);
                $this->command->info("    [Q{$index}] {$data['name']} -> READY (assessment_complete), target RUG: {$data['target_rug']}");
            } else {
                // NOT READY patient: no InterRAI HC Assessment yet
                // Alternate between 'triage_complete' and 'assessment_in_progress' for variety
                $status = ($index % 2 === 0) ? 'assessment_in_progress' : 'triage_complete';
                PatientQueue::create([
                    'patient_id' => $patient->id,
                    'queue_status' => $status,
                    'priority' => $index + 1,
                    'entered_queue_at' => now()->subDays(rand(7, 14)),
                    'triage_completed_at' => ($status === 'triage_complete') ? now()->subDays(rand(1, 3)) : now()->subDays(rand(3, 7)),
                    'assessment_completed_at' => null, // No assessment completed
                ]);
                $statusLabel = ($status === 'triage_complete') ? 'InterRAI HC Assessment Pending' : 'InterRAI HC Assessment In Progress';
                $this->command->info("    [Q{$index}] {$data['name']} -> NOT READY ({$statusLabel})");
            }
        }
    }

    /**
     * 10 Active patients - have InterRAI, RUG, and will get care plans.
     * Diversified across all 7 RUG categories with varying ADL levels.
     *
     * Distribution shows full range of care intensity:
     * - High ADL/complexity: RB0, SE3, SSB, CC0, IB0, BB0, PD0
     * - Moderate ADL: RA2, SE2, CB0, IA2, BA2, PC0
     * - Lower ADL: RA1, SE1, CA1, IA1, BA1, PA1, PB0
     */
    protected function createActivePatients(Hospital $hospital): void
    {
        $this->command->info('  Creating 10 active patients...');

        $patients = [
            // Active Patient 1: Special Rehabilitation (RB0) - Post-stroke, high ADL needs
            [
                'name' => 'Margaret Thompson',
                'email' => 'margaret.thompson@demo.cc',
                'gender' => 'Female',
                'dob' => '1955-03-15',
                'ohip' => 'DEMO-A01-101',
                'target_rug' => 'RB0', // Special Rehabilitation - HIGH ADL (11-18)
            ],
            // Active Patient 2: Special Rehabilitation (RA2) - Post-fracture, moderate ADL + high IADL
            [
                'name' => 'Robert Chen',
                'email' => 'robert.chen@demo.cc',
                'gender' => 'Male',
                'dob' => '1946-07-22',
                'ohip' => 'DEMO-A02-102',
                'target_rug' => 'RA2', // Special Rehabilitation - lower ADL + higher IADL
            ],
            // Active Patient 3: Extensive Services (SE3) - Ventilator dependent
            [
                'name' => 'William Foster',
                'email' => 'william.foster@demo.cc',
                'gender' => 'Male',
                'dob' => '1937-11-28',
                'ohip' => 'DEMO-A03-103',
                'target_rug' => 'SE3', // Extensive Services - MAX intensity
            ],
            // Active Patient 4: Extensive Services (SE1) - Dialysis
            [
                'name' => 'Patricia Valdez',
                'email' => 'patricia.valdez@demo.cc',
                'gender' => 'Female',
                'dob' => '1942-05-20',
                'ohip' => 'DEMO-A04-104',
                'target_rug' => 'SE1', // Extensive Services - lower complexity
            ],
            // Active Patient 5: Special Care (SSB) - MS with very high ADL needs
            [
                'name' => 'James O\'Brien',
                'email' => 'james.obrien@demo.cc',
                'gender' => 'Male',
                'dob' => '1947-02-28',
                'ohip' => 'DEMO-A05-105',
                'target_rug' => 'SSB', // Special Care - HIGH ADL (14-18)
            ],
            // Active Patient 6: Special Care (SSA) - Pressure ulcer + swallowing
            [
                'name' => 'Helen Kowalski',
                'email' => 'helen.kowalski@demo.cc',
                'gender' => 'Female',
                'dob' => '1950-12-25',
                'ohip' => 'DEMO-A06-106',
                'target_rug' => 'SSA', // Special Care - lower ADL
            ],
            // Active Patient 7: Clinically Complex (CC0) - CHF + COPD, high ADL
            [
                'name' => 'Frank Morrison',
                'email' => 'frank.morrison@demo.cc',
                'gender' => 'Male',
                'dob' => '1941-01-14',
                'ohip' => 'DEMO-A07-107',
                'target_rug' => 'CC0', // Clinically Complex - HIGH ADL (11-18)
            ],
            // Active Patient 8: Impaired Cognition (IB0) - Moderate dementia
            [
                'name' => 'Grace Nakamura',
                'email' => 'grace.nakamura@demo.cc',
                'gender' => 'Female',
                'dob' => '1952-04-18',
                'ohip' => 'DEMO-A08-108',
                'target_rug' => 'IB0', // Impaired Cognition - moderate ADL
            ],
            // Active Patient 9: Behaviour Problems (BB0) - Responsive behaviours
            [
                'name' => 'Albert Singh',
                'email' => 'albert.singh@demo.cc',
                'gender' => 'Male',
                'dob' => '1958-08-05',
                'ohip' => 'DEMO-A09-109',
                'target_rug' => 'BB0', // Behaviour Problems - moderate ADL
            ],
            // Active Patient 10: Reduced Physical Function (PD0) - High ADL, no other triggers
            [
                'name' => 'Catherine Dubois',
                'email' => 'catherine.dubois@demo.cc',
                'gender' => 'Female',
                'dob' => '1949-10-12',
                'ohip' => 'DEMO-A10-110',
                'target_rug' => 'PD0', // Reduced Physical Function - high ADL fallback
            ],
        ];

        foreach ($patients as $index => $data) {
            // Start address index at 5 to give active patients different addresses than queue patients
            $patient = $this->createPatient($hospital, $data, 'Active', false, false, $index + 5);

            // Create transitioned queue entry
            PatientQueue::create([
                'patient_id' => $patient->id,
                'queue_status' => 'transitioned',
                'priority' => 5,
                'entered_queue_at' => now()->subDays(rand(30, 60)),
                'triage_completed_at' => now()->subDays(rand(25, 55)),
                'assessment_completed_at' => now()->subDays(rand(20, 50)),
                'bundle_started_at' => now()->subDays(rand(15, 45)),
                'bundle_completed_at' => now()->subDays(rand(10, 40)),
                'transitioned_at' => now()->subDays(rand(7, 30)),
            ]);

            $this->command->info("    [A{$index}] {$data['name']} -> target RUG: {$data['target_rug']}");
        }
    }

    protected function createPatient(Hospital $hospital, array $data, string $status, bool $isInQueue, bool $assessmentPending = false, int $addressIndex = 0): Patient
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);

        // Get address from static array (cycling through if needed)
        $address = self::$torontoAddresses[$addressIndex % count(self::$torontoAddresses)];

        $patient = Patient::create([
            'user_id' => $user->id,
            'hospital_id' => $hospital->id,
            'date_of_birth' => $data['dob'],
            'gender' => $data['gender'],
            'ohip' => $data['ohip'],
            'status' => $status,
            'is_in_queue' => $isInQueue,
            // Address fields for travel time calculations
            'address' => $address[0],
            'city' => $address[1],
            'postal_code' => $address[2],
            'lat' => $address[3],
            'lng' => $address[4],
            // interrai_status reflects whether assessment is completed or pending
            'interrai_status' => $assessmentPending ? 'pending' : 'completed',
            'activated_at' => $status === 'Active' ? now()->subDays(rand(7, 30)) : null,
        ]);

        // Auto-assign region based on postal code FSA
        $this->regionService->assignRegion($patient);

        return $patient;
    }

    /**
     * Get the target RUG group for a patient by OHIP number.
     * Used by DemoAssessmentsSeeder to generate appropriate assessments.
     *
     * IMPORTANT: Only returns patients that SHOULD have assessments:
     * - 3 READY queue patients (have assessment + RUG)
     * - 10 Active patients (have assessment + RUG + care plan)
     *
     * Does NOT include the 2 NOT READY queue patients (Q04, Q05) who are
     * awaiting InterRAI HC assessment.
     *
     * Distribution covers all 7 major RUG categories:
     * - Special Rehabilitation: RB0, RA2
     * - Extensive Services: SE3, SE1
     * - Special Care: SSB, SSA
     * - Clinically Complex: CC0 (x3)
     * - Impaired Cognition: IB0 (x3)
     * - Behaviour Problems: BB0 (x3)
     * - Reduced Physical Function: PD0
     */
    public static function getTargetRugGroups(): array
    {
        return [
            // Queue patients - READY only (3 of 5)
            // These will have InterRAI HC Assessment + RUGClassification
            'DEMO-Q01-001' => 'CC0',  // Clinically Complex - high ADL
            'DEMO-Q02-002' => 'IB0',  // Impaired Cognition - moderate ADL
            'DEMO-Q03-003' => 'BB0',  // Behaviour Problems - moderate ADL
            // NOTE: DEMO-Q04-004 and DEMO-Q05-005 are NOT included
            //       They are NOT READY (no assessment yet)

            // Active patients (with care plans) - diversified across all RUG categories
            'DEMO-A01-101' => 'RB0',  // Special Rehabilitation - high ADL
            'DEMO-A02-102' => 'RA2',  // Special Rehabilitation - lower ADL + higher IADL
            'DEMO-A03-103' => 'SE3',  // Extensive Services - highest complexity
            'DEMO-A04-104' => 'SE1',  // Extensive Services - lower complexity
            'DEMO-A05-105' => 'SSB',  // Special Care - high ADL
            'DEMO-A06-106' => 'SSA',  // Special Care - lower ADL
            'DEMO-A07-107' => 'CC0',  // Clinically Complex - high ADL
            'DEMO-A08-108' => 'IB0',  // Impaired Cognition - moderate ADL
            'DEMO-A09-109' => 'BB0',  // Behaviour Problems - moderate ADL
            'DEMO-A10-110' => 'PD0',  // Reduced Physical Function - high ADL fallback
        ];
    }

    /**
     * Get patients that should NOT have assessments (NOT READY queue patients).
     */
    public static function getNotReadyPatients(): array
    {
        return [
            'DEMO-Q04-004', // Raymond Clarke - assessment in progress
            'DEMO-Q05-005', // Beatrice Wong - triage complete, awaiting assessment
        ];
    }
}
