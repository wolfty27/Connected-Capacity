<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ServiceProviderOrganization;
use App\Models\Patient;
use App\Models\CareAssignment;
use App\Models\ServiceType;
use App\Models\TransitionNeedsProfile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class ConnectedCapacitySeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        // 1. Ensure SPO exists
        $spo = ServiceProviderOrganization::firstOrCreate(
            ['slug' => 'demo-spo'],
            [
                'name' => 'Demo SPO Health', 
                'type' => 'se_health',
                'active' => true,
                'capabilities' => ['dementia', 'clinical', 'technology']
            ]
        );

        // 2. Create SSPOs (Partner Organizations)
        $sspo1 = ServiceProviderOrganization::firstOrCreate(
            ['slug' => 'partner-mental-health'],
            [
                'name' => 'Mindfulness Care Partners', 
                'type' => 'partner',
                'active' => true,
                'capabilities' => ['mental_health', 'community']
            ]
        );

        $sspo2 = ServiceProviderOrganization::firstOrCreate(
            ['slug' => 'partner-rehab'],
            [
                'name' => 'Active Rehab Solutions', 
                'type' => 'partner',
                'active' => true,
                'capabilities' => ['clinical', 'dementia']
            ]
        );

        // 3. Create Users for SSPOs
        $this->createOrgUser($sspo1, 'sspo1@example.com', 'SSPO Admin 1', User::ROLE_SSPO_ADMIN);
        $this->createOrgUser($sspo2, 'sspo2@example.com', 'SSPO Admin 2', User::ROLE_SSPO_ADMIN);

        // 4. Create Service Types
        $nursing = ServiceType::firstOrCreate(['code' => 'NURSING'], ['name' => 'Nursing Visit', 'category' => 'Clinical', 'default_duration_minutes' => 60, 'active' => true]);
        $psw = ServiceType::firstOrCreate(['code' => 'PSW'], ['name' => 'Personal Support', 'category' => 'Support', 'default_duration_minutes' => 60, 'active' => true]);
        $pt = ServiceType::firstOrCreate(['code' => 'PT'], ['name' => 'Physiotherapy', 'category' => 'Rehab', 'default_duration_minutes' => 45, 'active' => true]);

        // 5. Create Patients with various statuses and assignments
        $fieldStaff = User::where('email', 'field@example.com')->first();
        if (!$fieldStaff) {
             $fieldStaff = User::create([
                'name' => 'Field Staff',
                'email' => 'field@example.com',
                'password' => Hash::make('password'),
                'role' => User::ROLE_FIELD_STAFF,
                'organization_id' => $spo->id,
                'organization_role' => 'FIELD_STAFF'
            ]);
        }

        $statuses = ['Available', 'Placement Made', 'Inactive', 'Application Progress'];
        
        for ($i = 1; $i <= 15; $i++) {
            $gender = $faker->randomElement(['Male', 'Female']);
            $status = $faker->randomElement($statuses);
            
            // Create Patient User
            $patientUser = User::create([
                'name' => $faker->name($gender),
                'email' => $faker->unique()->safeEmail,
                'password' => Hash::make('password'),
                'role' => 'patient', // assuming this role exists or is just a user record
                'phone_number' => $faker->phoneNumber,
                'organization_id' => $spo->id // Primary org managing them
            ]);

            $hospital = \App\Models\Hospital::first(); // Ensure we have a hospital ID
            $patient = Patient::create([
                'user_id' => $patientUser->id,
                'hospital_id' => $hospital ? $hospital->id : 1, // Fallback to 1 if none found
                'date_of_birth' => $faker->date('Y-m-d', '-60 years'),
                'gender' => $gender,
                'status' => $status,
                'risk_flags' => $faker->randomElements(['Fall Risk', 'Diabetic', 'Social Isolation'], $faker->numberBetween(0, 2)),
                'primary_coordinator_id' => 6, // SPO Admin ID usually
            ]);

            // Create TNP
            TransitionNeedsProfile::create([
                'patient_id' => $patient->id,
                'clinical_flags' => $patient->risk_flags,
                'narrative_summary' => "Patient is a " . Carbon::parse($patient->date_of_birth)->age . " year old " . $gender . " presenting with mobility issues. " . $faker->paragraph,
                'status' => $faker->randomElement(['draft', 'completed']),
                'ai_summary_status' => $faker->randomElement(['pending', 'completed']),
                'ai_summary_text' => "AI generated summary of clinical needs..."
            ]);

            // Create Care Assignments (Visits)
            if ($status === 'Placement Made' || $status === 'Application Progress') {
                CareAssignment::create([
                    'patient_id' => $patient->id,
                    'assigned_user_id' => $fieldStaff->id,
                    'service_provider_organization_id' => $spo->id,
                    'status' => $faker->randomElement(['pending', 'in_progress', 'completed']),
                    'start_date' => Carbon::today()->addHours($faker->numberBetween(8, 16)),
                    'end_date' => Carbon::today()->addHours($faker->numberBetween(17, 18)),
                ]);

                // Add a future assignment
                CareAssignment::create([
                    'patient_id' => $patient->id,
                    'assigned_user_id' => $fieldStaff->id,
                    'service_provider_organization_id' => $spo->id,
                    'status' => 'pending',
                    'start_date' => Carbon::tomorrow()->addHours(9),
                ]);
            }
        }
    }

    private function createOrgUser($org, $email, $name, $role) {
        User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make('password'),
            'role' => $role,
            'organization_id' => $org->id,
            'organization_role' => $role,
        ]);
    }
}
