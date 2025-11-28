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
     * Covering all 7 RUG categories.
     */
    protected function createActivePatients(Hospital $hospital): void
    {
        $this->command->info('  Creating 10 active patients...');

        $patients = [
            // Active Patient 1: Special Rehabilitation (RA1) - Post hip fracture
            [
                'name' => 'Margaret Thompson',
                'email' => 'margaret.thompson@demo.cc',
                'gender' => 'Female',
                'dob' => '1955-03-15',
                'ohip' => 'DEMO-A01-101',
                'target_rug' => 'RA1', // Special Rehabilitation
            ],
            // Active Patient 2: Extensive Services (SE1) - Dialysis
            [
                'name' => 'Robert Chen',
                'email' => 'robert.chen@demo.cc',
                'gender' => 'Male',
                'dob' => '1946-07-22',
                'ohip' => 'DEMO-A02-102',
                'target_rug' => 'SE1', // Extensive Services
            ],
            // Active Patient 3: Extensive Services (SE3) - Ventilator
            [
                'name' => 'William Foster',
                'email' => 'william.foster@demo.cc',
                'gender' => 'Male',
                'dob' => '1937-11-28',
                'ohip' => 'DEMO-A03-103',
                'target_rug' => 'SE3', // Extensive Services
            ],
            // Active Patient 4: Special Care (SSB) - MS with high ADL needs
            [
                'name' => 'Patricia Valdez',
                'email' => 'patricia.valdez@demo.cc',
                'gender' => 'Female',
                'dob' => '1942-05-20',
                'ohip' => 'DEMO-A04-104',
                'target_rug' => 'SSB', // Special Care
            ],
            // Active Patient 5: Clinically Complex (CC0) - COPD + multiple conditions
            [
                'name' => 'James O\'Brien',
                'email' => 'james.obrien@demo.cc',
                'gender' => 'Male',
                'dob' => '1947-02-28',
                'ohip' => 'DEMO-A05-105',
                'target_rug' => 'CC0', // Clinically Complex
            ],
            // Active Patient 6: Clinically Complex (CA1) - Diabetes management
            [
                'name' => 'Helen Kowalski',
                'email' => 'helen.kowalski@demo.cc',
                'gender' => 'Female',
                'dob' => '1950-12-25',
                'ohip' => 'DEMO-A06-106',
                'target_rug' => 'CA1', // Clinically Complex
            ],
            // Active Patient 7: Impaired Cognition (IA1) - Early Alzheimer's
            [
                'name' => 'Frank Morrison',
                'email' => 'frank.morrison@demo.cc',
                'gender' => 'Male',
                'dob' => '1941-01-14',
                'ohip' => 'DEMO-A07-107',
                'target_rug' => 'IA1', // Impaired Cognition
            ],
            // Active Patient 8: Behaviour Problems (BA1) - Bipolar with ADL needs
            [
                'name' => 'Grace Nakamura',
                'email' => 'grace.nakamura@demo.cc',
                'gender' => 'Female',
                'dob' => '1952-04-18',
                'ohip' => 'DEMO-A08-108',
                'target_rug' => 'BA1', // Behaviour Problems
            ],
            // Active Patient 9: Reduced Physical Function (PA1) - Mild mobility issues
            [
                'name' => 'Albert Singh',
                'email' => 'albert.singh@demo.cc',
                'gender' => 'Male',
                'dob' => '1958-08-05',
                'ohip' => 'DEMO-A09-109',
                'target_rug' => 'PA1', // Reduced Physical Function
            ],
            // Active Patient 10: Reduced Physical Function (PB0) - Moderate ADL support
            [
                'name' => 'Catherine Dubois',
                'email' => 'catherine.dubois@demo.cc',
                'gender' => 'Female',
                'dob' => '1949-10-12',
                'ohip' => 'DEMO-A10-110',
                'target_rug' => 'PB0', // Reduced Physical Function
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
     */
    public static function getTargetRugGroups(): array
    {
        return [
            // Queue patients
            'DEMO-Q01-001' => 'CB0',
            'DEMO-Q02-002' => 'IB0',
            'DEMO-Q03-003' => 'BB0',
            'DEMO-Q04-004' => 'SSA',
            'DEMO-Q05-005' => 'PD0',
            // Active patients
            'DEMO-A01-101' => 'RA1',
            'DEMO-A02-102' => 'SE1',
            'DEMO-A03-103' => 'SE3',
            'DEMO-A04-104' => 'SSB',
            'DEMO-A05-105' => 'CC0',
            'DEMO-A06-106' => 'CA1',
            'DEMO-A07-107' => 'IA1',
            'DEMO-A08-108' => 'BA1',
            'DEMO-A09-109' => 'PA1',
            'DEMO-A10-110' => 'PB0',
        ];
    }
}
