<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DemoPatientsSeeder - Creates 15 demo patients for CC2.1 architecture
 *
 * Creates exactly 15 patients:
 * - 5 in Intake Queue (ready for bundle building, no care plan yet)
 * - 10 Active patients (will have care plans added by DemoBundlesSeeder)
 *
 * Patient distribution by RUG category target:
 * - Reduced Physical Function: 3 patients (PA1, PB0, PD0)
 * - Behaviour Problems: 2 patients (BA1, BB0)
 * - Impaired Cognition: 2 patients (IA1, IB0)
 * - Clinically Complex: 3 patients (CA1, CB0, CC0)
 * - Special Care: 2 patients (SSA, SSB)
 * - Extensive Services: 2 patients (SE1, SE3)
 * - Special Rehabilitation: 1 patient (RA1)
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class DemoPatientsSeeder extends Seeder
{
    public function run(): void
    {
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
     * 5 Intake Queue patients - have InterRAI but no care plan yet.
     * Covering: Clinically Complex, Impaired Cognition, Behaviour Problems, Special Care, Reduced Physical Function
     */
    protected function createIntakeQueuePatients(Hospital $hospital): void
    {
        $this->command->info('  Creating 5 intake queue patients...');

        $patients = [
            // Queue Patient 1: Clinically Complex (CB0)
            [
                'name' => 'Eleanor Mitchell',
                'email' => 'eleanor.mitchell@demo.cc',
                'gender' => 'Female',
                'dob' => '1943-08-17',
                'ohip' => 'DEMO-Q01-001',
                'target_rug' => 'CB0', // Clinically Complex
            ],
            // Queue Patient 2: Impaired Cognition (IB0)
            [
                'name' => 'Harold Peterson',
                'email' => 'harold.peterson@demo.cc',
                'gender' => 'Male',
                'dob' => '1938-03-25',
                'ohip' => 'DEMO-Q02-002',
                'target_rug' => 'IB0', // Impaired Cognition
            ],
            // Queue Patient 3: Behaviour Problems (BB0)
            [
                'name' => 'Dorothy Flanagan',
                'email' => 'dorothy.flanagan@demo.cc',
                'gender' => 'Female',
                'dob' => '1945-11-02',
                'ohip' => 'DEMO-Q03-003',
                'target_rug' => 'BB0', // Behaviour Problems
            ],
            // Queue Patient 4: Special Care (SSA)
            [
                'name' => 'Raymond Clarke',
                'email' => 'raymond.clarke@demo.cc',
                'gender' => 'Male',
                'dob' => '1940-06-14',
                'ohip' => 'DEMO-Q04-004',
                'target_rug' => 'SSA', // Special Care
            ],
            // Queue Patient 5: Reduced Physical Function (PD0)
            [
                'name' => 'Beatrice Wong',
                'email' => 'beatrice.wong@demo.cc',
                'gender' => 'Female',
                'dob' => '1948-09-30',
                'ohip' => 'DEMO-Q05-005',
                'target_rug' => 'PD0', // Reduced Physical Function
            ],
        ];

        foreach ($patients as $index => $data) {
            $patient = $this->createPatient($hospital, $data, 'Pending', true);

            // Create queue entry - TNP complete, ready for bundle building
            PatientQueue::create([
                'patient_id' => $patient->id,
                'queue_status' => 'tnp_complete',
                'priority' => $index + 1,
                'entered_queue_at' => now()->subDays(rand(7, 21)),
                'triage_completed_at' => now()->subDays(rand(5, 14)),
                'tnp_completed_at' => now()->subDays(rand(1, 5)),
            ]);

            $this->command->info("    [Q{$index}] {$data['name']} -> target RUG: {$data['target_rug']}");
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
            // Active Patient 2: Extensive Services (SE2) - IV therapy + moderate ADL
            [
                'name' => 'Robert Chen',
                'email' => 'robert.chen@demo.cc',
                'gender' => 'Male',
                'dob' => '1946-07-22',
                'ohip' => 'DEMO-A02-102',
                'target_rug' => 'SE2', // Extensive Services - moderate complexity
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
            // Active Patient 4: Special Care (SSB) - MS with very high ADL needs
            [
                'name' => 'Patricia Valdez',
                'email' => 'patricia.valdez@demo.cc',
                'gender' => 'Female',
                'dob' => '1942-05-20',
                'ohip' => 'DEMO-A04-104',
                'target_rug' => 'SSB', // Special Care - HIGH ADL (14-18)
            ],
            // Active Patient 5: Clinically Complex (CC0) - CHF + COPD, high ADL
            [
                'name' => 'James O\'Brien',
                'email' => 'james.obrien@demo.cc',
                'gender' => 'Male',
                'dob' => '1947-02-28',
                'ohip' => 'DEMO-A05-105',
                'target_rug' => 'CC0', // Clinically Complex - HIGH ADL (11-18)
            ],
            // Active Patient 6: Clinically Complex (CA2) - Diabetes + wounds, moderate ADL
            [
                'name' => 'Helen Kowalski',
                'email' => 'helen.kowalski@demo.cc',
                'gender' => 'Female',
                'dob' => '1950-12-25',
                'ohip' => 'DEMO-A06-106',
                'target_rug' => 'CA2', // Clinically Complex - lower ADL, higher IADL
            ],
            // Active Patient 7: Impaired Cognition (IA2) - Moderate dementia, IADL needs
            [
                'name' => 'Frank Morrison',
                'email' => 'frank.morrison@demo.cc',
                'gender' => 'Male',
                'dob' => '1941-01-14',
                'ohip' => 'DEMO-A07-107',
                'target_rug' => 'IA2', // Impaired Cognition - lower ADL, higher IADL
            ],
            // Active Patient 8: Behaviour Problems (BA2) - Anxiety/agitation, IADL needs
            [
                'name' => 'Grace Nakamura',
                'email' => 'grace.nakamura@demo.cc',
                'gender' => 'Female',
                'dob' => '1952-04-18',
                'ohip' => 'DEMO-A08-108',
                'target_rug' => 'BA2', // Behaviour Problems - lower ADL, higher IADL
            ],
            // Active Patient 9: Reduced Physical Function (PC0) - Mod-high ADL (9-10)
            [
                'name' => 'Albert Singh',
                'email' => 'albert.singh@demo.cc',
                'gender' => 'Male',
                'dob' => '1958-08-05',
                'ohip' => 'DEMO-A09-109',
                'target_rug' => 'PC0', // Reduced Physical Function - ADL 9-10
            ],
            // Active Patient 10: Reduced Physical Function (PA2) - Low ADL, IADL needs
            [
                'name' => 'Catherine Dubois',
                'email' => 'catherine.dubois@demo.cc',
                'gender' => 'Female',
                'dob' => '1949-10-12',
                'ohip' => 'DEMO-A10-110',
                'target_rug' => 'PA2', // Reduced Physical Function - low ADL, higher IADL
            ],
        ];

        foreach ($patients as $index => $data) {
            $patient = $this->createPatient($hospital, $data, 'Active', false);

            // Create transitioned queue entry
            PatientQueue::create([
                'patient_id' => $patient->id,
                'queue_status' => 'transitioned',
                'priority' => 5,
                'entered_queue_at' => now()->subDays(rand(30, 60)),
                'triage_completed_at' => now()->subDays(rand(25, 55)),
                'tnp_completed_at' => now()->subDays(rand(20, 50)),
                'bundle_started_at' => now()->subDays(rand(15, 45)),
                'bundle_completed_at' => now()->subDays(rand(10, 40)),
                'transitioned_at' => now()->subDays(rand(7, 30)),
            ]);

            $this->command->info("    [A{$index}] {$data['name']} -> target RUG: {$data['target_rug']}");
        }
    }

    protected function createPatient(Hospital $hospital, array $data, string $status, bool $isInQueue): Patient
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);

        return Patient::create([
            'user_id' => $user->id,
            'hospital_id' => $hospital->id,
            'date_of_birth' => $data['dob'],
            'gender' => $data['gender'],
            'ohip' => $data['ohip'],
            'status' => $status,
            'is_in_queue' => $isInQueue,
            'interrai_status' => 'completed',
            'activated_at' => $status === 'Active' ? now()->subDays(rand(7, 30)) : null,
        ]);
    }

    /**
     * Get the target RUG group for a patient by OHIP number.
     * Used by DemoAssessmentsSeeder to generate appropriate assessments.
     *
     * Diversified distribution showing full range of RUG groups:
     * - Queue (5): CB0, IB0, BB0, SSA, PD0 (mix of moderate-high complexity)
     * - Active (10): RB0, SE2, SE3, SSB, CC0, CA2, IA2, BA2, PC0, PA2 (full spectrum)
     */
    public static function getTargetRugGroups(): array
    {
        return [
            // Queue patients (pending bundle selection)
            'DEMO-Q01-001' => 'CB0',  // Clinically Complex - moderate ADL
            'DEMO-Q02-002' => 'IB0',  // Impaired Cognition - moderate ADL
            'DEMO-Q03-003' => 'BB0',  // Behaviour Problems - moderate ADL
            'DEMO-Q04-004' => 'SSA',  // Special Care - lower ADL
            'DEMO-Q05-005' => 'PD0',  // Reduced Physical Function - HIGH ADL
            // Active patients (with care plans) - diversified across ADL spectrum
            'DEMO-A01-101' => 'RB0',  // Special Rehabilitation - HIGH ADL (11-18)
            'DEMO-A02-102' => 'SE2',  // Extensive Services - moderate complexity
            'DEMO-A03-103' => 'SE3',  // Extensive Services - MAX intensity
            'DEMO-A04-104' => 'SSB',  // Special Care - HIGH ADL (14-18)
            'DEMO-A05-105' => 'CC0',  // Clinically Complex - HIGH ADL (11-18)
            'DEMO-A06-106' => 'CA2',  // Clinically Complex - lower ADL, higher IADL
            'DEMO-A07-107' => 'IA2',  // Impaired Cognition - lower ADL, higher IADL
            'DEMO-A08-108' => 'BA2',  // Behaviour Problems - lower ADL, higher IADL
            'DEMO-A09-109' => 'PC0',  // Reduced Physical Function - ADL 9-10
            'DEMO-A10-110' => 'PA2',  // Reduced Physical Function - low ADL, IADL needs
        ];
    }
}
