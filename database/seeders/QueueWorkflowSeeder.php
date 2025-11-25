<?php

namespace Database\Seeders;

use App\Models\CareBundle;
use App\Models\CarePlan;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Models\TransitionNeedsProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * QueueWorkflowSeeder - Creates test patients for the queue workflow
 *
 * This seeder creates a realistic set of patients at various stages:
 * - Active patients (transitioned from queue, have active care plans)
 * - Patients in queue at different workflow stages
 * - Patients ready for bundle building (TNP complete)
 */
class QueueWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Queue Workflow Test Data...');

        // Get or create a hospital (name is on the User, not Hospital table)
        $hospital = Hospital::first();
        if (!$hospital) {
            $hospitalUser = User::firstOrCreate(
                ['email' => 'hospital@example.com'],
                [
                    'name' => 'General Hospital',
                    'password' => Hash::make('password'),
                    'role' => 'hospital',
                ]
            );
            $hospital = Hospital::create([
                'user_id' => $hospitalUser->id,
            ]);
        }

        // Seed active patients (already transitioned from queue)
        $this->seedActivePatients($hospital);

        // Seed patients in queue at various stages
        $this->seedQueuePatients($hospital);

        $this->command->info('Queue Workflow Test Data seeded successfully!');
    }

    protected function seedActivePatients(Hospital $hospital): void
    {
        $this->command->info('Creating active patients...');

        $activePatients = [
            [
                'name' => 'Margaret Thompson',
                'email' => 'margaret.thompson@example.com',
                'gender' => 'Female',
                'dob' => '1945-03-15',
                'ohip' => '1234-567-890',
                'clinical_flags' => ['Wound Care', 'Diabetes Management'],
                'bundle_code' => 'COMPLEX',
            ],
            [
                'name' => 'Robert Chen',
                'email' => 'robert.chen@example.com',
                'gender' => 'Male',
                'dob' => '1952-08-22',
                'ohip' => '2345-678-901',
                'clinical_flags' => ['Cognitive Impairment', 'Fall Risk'],
                'bundle_code' => 'DEM-SUP',
            ],
            [
                'name' => 'Dorothy Williams',
                'email' => 'dorothy.williams@example.com',
                'gender' => 'Female',
                'dob' => '1938-11-30',
                'ohip' => '3456-789-012',
                'clinical_flags' => ['Palliative', 'Pain Management'],
                'bundle_code' => 'PALLIATIVE',
            ],
            [
                'name' => 'James Miller',
                'email' => 'james.miller@example.com',
                'gender' => 'Male',
                'dob' => '1960-05-10',
                'ohip' => '4567-890-123',
                'clinical_flags' => ['Post-Surgical', 'Wound Care'],
                'bundle_code' => 'COMPLEX',
            ],
            [
                'name' => 'Helen Brown',
                'email' => 'helen.brown@example.com',
                'gender' => 'Female',
                'dob' => '1948-07-25',
                'ohip' => '5678-901-234',
                'clinical_flags' => ['Diabetes', 'Mobility Issues'],
                'bundle_code' => 'STD-MED',
            ],
        ];

        foreach ($activePatients as $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'role' => 'patient',
            ]);

            $patient = Patient::create([
                'user_id' => $user->id,
                'hospital_id' => $hospital->id,
                'date_of_birth' => $data['dob'],
                'gender' => $data['gender'],
                'ohip' => $data['ohip'],
                'status' => 'Active',
                'is_in_queue' => false,
                'activated_at' => now()->subDays(rand(7, 30)),
            ]);

            // Create TNP
            $tnp = TransitionNeedsProfile::create([
                'patient_id' => $patient->id,
                'clinical_flags' => $data['clinical_flags'],
                'status' => 'completed',
                'narrative_summary' => "Patient requires care support for: " . implode(', ', $data['clinical_flags']),
            ]);

            // Create queue entry (transitioned)
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

            // Create care plan
            $bundle = CareBundle::where('code', $data['bundle_code'])->first();
            if ($bundle) {
                $carePlan = CarePlan::create([
                    'patient_id' => $patient->id,
                    'care_bundle_id' => $bundle->id,
                    'version' => 1,
                    'status' => 'active',
                    'goals' => ['Improve quality of life', 'Maintain independence'],
                    'risks' => $data['clinical_flags'],
                    'interventions' => [],
                    'approved_at' => now()->subDays(rand(7, 30)),
                ]);

                // Create some service assignments
                $serviceTypes = ServiceType::take(3)->get();
                foreach ($serviceTypes as $serviceType) {
                    ServiceAssignment::create([
                        'care_plan_id' => $carePlan->id,
                        'patient_id' => $patient->id,
                        'service_type_id' => $serviceType->id,
                        'status' => 'active',
                        'frequency_rule' => '2x per week',
                    ]);
                }
            }

            $this->command->info("  Created active patient: {$data['name']}");
        }
    }

    protected function seedQueuePatients(Hospital $hospital): void
    {
        $this->command->info('Creating patients in queue...');

        $queuePatients = [
            // TNP Complete - Ready for bundle
            [
                'name' => 'Patricia Davis',
                'email' => 'patricia.davis@example.com',
                'gender' => 'Female',
                'dob' => '1955-04-18',
                'ohip' => '6789-012-345',
                'queue_status' => 'tnp_complete',
                'clinical_flags' => ['Respiratory Issues', 'Medication Management'],
                'priority' => 2,
            ],
            [
                'name' => 'Michael Wilson',
                'email' => 'michael.wilson@example.com',
                'gender' => 'Male',
                'dob' => '1942-09-05',
                'ohip' => '7890-123-456',
                'queue_status' => 'tnp_complete',
                'clinical_flags' => ['Cognitive Decline', 'Wandering Risk'],
                'priority' => 1,
            ],
            [
                'name' => 'Susan Anderson',
                'email' => 'susan.anderson@example.com',
                'gender' => 'Female',
                'dob' => '1950-12-12',
                'ohip' => '8901-234-567',
                'queue_status' => 'tnp_complete',
                'clinical_flags' => ['Chronic Pain', 'Depression'],
                'priority' => 3,
            ],

            // TNP In Progress
            [
                'name' => 'William Taylor',
                'email' => 'william.taylor@example.com',
                'gender' => 'Male',
                'dob' => '1958-02-28',
                'ohip' => '9012-345-678',
                'queue_status' => 'tnp_in_progress',
                'clinical_flags' => [],
                'priority' => 4,
            ],
            [
                'name' => 'Elizabeth Moore',
                'email' => 'elizabeth.moore@example.com',
                'gender' => 'Female',
                'dob' => '1947-06-20',
                'ohip' => '0123-456-789',
                'queue_status' => 'tnp_in_progress',
                'clinical_flags' => [],
                'priority' => 5,
            ],

            // Triage Complete
            [
                'name' => 'Richard Jackson',
                'email' => 'richard.jackson@example.com',
                'gender' => 'Male',
                'dob' => '1965-01-15',
                'ohip' => '1111-222-333',
                'queue_status' => 'triage_complete',
                'clinical_flags' => [],
                'priority' => 6,
            ],

            // Triage In Progress
            [
                'name' => 'Barbara White',
                'email' => 'barbara.white@example.com',
                'gender' => 'Female',
                'dob' => '1953-08-08',
                'ohip' => '2222-333-444',
                'queue_status' => 'triage_in_progress',
                'clinical_flags' => [],
                'priority' => 7,
            ],

            // Pending Intake
            [
                'name' => 'Charles Harris',
                'email' => 'charles.harris@example.com',
                'gender' => 'Male',
                'dob' => '1970-03-22',
                'ohip' => '3333-444-555',
                'queue_status' => 'pending_intake',
                'clinical_flags' => [],
                'priority' => 8,
            ],
            [
                'name' => 'Nancy Martin',
                'email' => 'nancy.martin@example.com',
                'gender' => 'Female',
                'dob' => '1962-11-11',
                'ohip' => '4444-555-666',
                'queue_status' => 'pending_intake',
                'clinical_flags' => [],
                'priority' => 9,
            ],

            // Bundle Building (in progress)
            [
                'name' => 'Thomas Garcia',
                'email' => 'thomas.garcia@example.com',
                'gender' => 'Male',
                'dob' => '1944-07-04',
                'ohip' => '5555-666-777',
                'queue_status' => 'bundle_building',
                'clinical_flags' => ['Heart Failure', 'COPD'],
                'priority' => 2,
            ],
        ];

        foreach ($queuePatients as $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'role' => 'patient',
            ]);

            $patient = Patient::create([
                'user_id' => $user->id,
                'hospital_id' => $hospital->id,
                'date_of_birth' => $data['dob'],
                'gender' => $data['gender'],
                'ohip' => $data['ohip'],
                'status' => 'Pending',
                'is_in_queue' => true,
            ]);

            // Create TNP for patients past triage
            if (in_array($data['queue_status'], ['tnp_in_progress', 'tnp_complete', 'bundle_building', 'bundle_review', 'bundle_approved'])) {
                $tnpStatus = $data['queue_status'] === 'tnp_in_progress' ? 'draft' : 'completed';
                TransitionNeedsProfile::create([
                    'patient_id' => $patient->id,
                    'clinical_flags' => $data['clinical_flags'],
                    'status' => $tnpStatus,
                    'narrative_summary' => count($data['clinical_flags']) > 0
                        ? "Patient requires care support for: " . implode(', ', $data['clinical_flags'])
                        : null,
                ]);
            }

            // Create queue entry
            $queueEntry = PatientQueue::create([
                'patient_id' => $patient->id,
                'queue_status' => $data['queue_status'],
                'priority' => $data['priority'],
                'entered_queue_at' => now()->subDays(rand(1, 14)),
            ]);

            // Set timestamps based on status progression
            $this->setQueueTimestamps($queueEntry, $data['queue_status']);

            $this->command->info("  Created queue patient: {$data['name']} ({$data['queue_status']})");
        }
    }

    protected function setQueueTimestamps(PatientQueue $queue, string $status): void
    {
        $progressions = [
            'pending_intake' => [],
            'triage_in_progress' => [],
            'triage_complete' => ['triage_completed_at'],
            'tnp_in_progress' => ['triage_completed_at'],
            'tnp_complete' => ['triage_completed_at', 'tnp_completed_at'],
            'bundle_building' => ['triage_completed_at', 'tnp_completed_at', 'bundle_started_at'],
            'bundle_review' => ['triage_completed_at', 'tnp_completed_at', 'bundle_started_at'],
            'bundle_approved' => ['triage_completed_at', 'tnp_completed_at', 'bundle_started_at', 'bundle_completed_at'],
        ];

        $timestamps = $progressions[$status] ?? [];

        foreach ($timestamps as $index => $field) {
            $queue->$field = now()->subDays(count($timestamps) - $index);
        }

        $queue->save();
    }
}
