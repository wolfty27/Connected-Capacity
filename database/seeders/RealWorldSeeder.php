<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Models\CareBundle;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\Hospital;
use App\Models\TransitionNeedsProfile;
use Illuminate\Support\Facades\Hash;

class RealWorldSeeder extends Seeder
{
    public function run()
    {
        // 1. Create Organizations
        $seHealth = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'se-health'],
            [
                'name' => 'SE Health',
                'type' => 'se_health',
                'capabilities' => ['Nursing', 'PSW', 'Home Care', 'Wound Care'],
                'active' => true
            ]
        );

        $alexisLodge = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'alexis-lodge'],
            [
                'name' => 'Alexis Lodge',
                'type' => 'partner',
                'capabilities' => ['Dementia Care', 'Patient Home Operator'],
                'active' => true
            ]
        );

        $wellhaus = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'wellhaus'],
            [
                'name' => 'Wellhaus Technology',
                'type' => 'partner',
                'capabilities' => ['Digital Health Solutions'],
                'active' => true
            ]
        );

        $reconnect = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'reconnect'],
            [
                'name' => 'Reconnect Health Services',
                'type' => 'partner',
                'capabilities' => ['Mental Health & Addiction Treatment'],
                'active' => true
            ]
        );

        $graceHospital = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'grace-hospital'],
            [
                'name' => 'Salvation Army Grace Hospital',
                'type' => 'partner',
                'capabilities' => ['Remote Monitoring', 'Post-Acute Care', 'LTC HHR'],
                'active' => true
            ]
        );

        $bridgingFutures = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'bridging-futures'],
            [
                'name' => 'Bridging Futures Canada',
                'type' => 'partner',
                'capabilities' => ['IEHP', 'Youth & Intergenerational Programs'],
                'active' => true
            ]
        );

        $aspira = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'aspira'],
            [
                'name' => 'Aspira Talent',
                'type' => 'partner',
                'capabilities' => ['Health Human Resources Recruitment'],
                'active' => true
            ]
        );

        // 2. Create Users (Admins for these Orgs)
        $seAdmin = User::updateOrCreate(['email' => 'admin@sehc.com'], [
            'name' => 'SE Health Admin',
            'password' => Hash::make('password'),
            'role' => 'SPO_ADMIN',
            'organization_id' => $seHealth->id,
        ]);

        User::updateOrCreate(['email' => 'president@alexislodge.com'], [
            'name' => 'Alexis Lodge President',
            'password' => Hash::make('password'),
            'role' => 'SSPO_ADMIN',
            'organization_id' => $alexisLodge->id,
        ]);

        User::updateOrCreate(['email' => 'admin@wellhaus.com'], [
            'name' => 'Wellhaus Admin',
            'password' => Hash::make('password'),
            'role' => 'SSPO_ADMIN',
            'organization_id' => $wellhaus->id,
        ]);

        $hospitalUser = User::updateOrCreate(['email' => 'hospital.admin@example.com'], [
            'name' => 'Scarborough General Admin',
            'password' => Hash::make('password'),
            'role' => 'hospital',
        ]);
        $hospital = Hospital::firstOrCreate(
            ['user_id' => $hospitalUser->id]
        );

        // 3. Create Service Types & Bundles
        $nursing = ServiceType::firstOrCreate(['code' => 'NURSING'], ['name' => 'Nursing (RN/RPN)', 'category' => 'Clinical', 'default_duration_minutes' => 60]);
        $psw = ServiceType::firstOrCreate(['code' => 'PSW'], ['name' => 'Personal Support', 'category' => 'Support', 'default_duration_minutes' => 60]);
        $dementia = ServiceType::firstOrCreate(['code' => 'DEMENTIA'], ['name' => 'Dementia & Memory Support', 'category' => 'Specialized', 'default_duration_minutes' => 120]);
        $rehab = ServiceType::firstOrCreate(['code' => 'REHAB'], ['name' => 'Rehabilitation (PT/OT)', 'category' => 'Clinical', 'default_duration_minutes' => 45]);
        $mentalHealth = ServiceType::firstOrCreate(['code' => 'MH'], ['name' => 'Mental Health & Addiction', 'category' => 'Specialized', 'default_duration_minutes' => 60]);
        $youth = ServiceType::firstOrCreate(['code' => 'YOUTH'], ['name' => 'Intergenerational Program', 'category' => 'Social', 'default_duration_minutes' => 90]);
        $digital = ServiceType::firstOrCreate(['code' => 'DIGITAL'], ['name' => 'Digital Health Solutions', 'category' => 'Digital', 'default_duration_minutes' => 0]);
        $rpm = ServiceType::firstOrCreate(['code' => 'RPM'], ['name' => 'Remote Monitoring (RPM)', 'category' => 'Digital', 'default_duration_minutes' => 0]);

        $standardBundle = CareBundle::firstOrCreate(['code' => 'STD-MED'], ['name' => 'Standard Medical/Surgical', 'active' => true]);
        $dementiaBundle = CareBundle::firstOrCreate(['code' => 'DEM-SUP'], ['name' => 'Dementia & Memory Support', 'active' => true]);
        $complexBundle = CareBundle::firstOrCreate(['code' => 'COMPLEX'], ['name' => 'Complex Integrated Care', 'active' => true]);

        // 4. Scenario A: Team Bundle (Eleanor Rigby)
        $eleanorUser = User::updateOrCreate(['email' => 'eleanor@example.com'], [
            'name' => 'Eleanor Rigby',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        $eleanor = Patient::updateOrCreate(['user_id' => $eleanorUser->id], [
            'date_of_birth' => '1945-01-01',
            'status' => 'active',
            'hospital_id' => $hospital->id,
            'primary_coordinator_id' => $seAdmin->id,
            'ohip' => '1234567890'
        ]);
        TransitionNeedsProfile::updateOrCreate(
            ['patient_id' => $eleanor->id],
            [
                'clinical_flags' => ['Cognitive Impairment', 'Fall Risk'],
                'narrative_summary' => 'Patient exhibiting signs of advanced dementia. Requires secure environment and 24/7 support.',
                'status' => 'completed',
                'ai_summary_status' => 'completed',
                'ai_summary_text' => 'Gemini Analysis: High risk for wandering. Recommend immediate placement in memory care bundle.'
            ]
        );
        $eleanorPlan = CarePlan::updateOrCreate(
            ['patient_id' => $eleanor->id],
            ['care_bundle_id' => $dementiaBundle->id, 'status' => 'active', 'version' => 1]
        );

        ServiceAssignment::updateOrCreate(
            ['patient_id' => $eleanor->id, 'service_type_id' => $nursing->id],
            ['care_plan_id' => $eleanorPlan->id, 'service_provider_organization_id' => $seHealth->id, 'status' => 'in_progress', 'frequency_rule' => 'Daily', 'notes' => 'Medication management']
        );
        ServiceAssignment::updateOrCreate(
            ['patient_id' => $eleanor->id, 'service_type_id' => $dementia->id],
            ['care_plan_id' => $eleanorPlan->id, 'service_provider_organization_id' => $alexisLodge->id, 'status' => 'in_progress', 'frequency_rule' => 'Residential', 'notes' => 'Full memory care support']
        );

        // 5. Scenario B: Solo Bundle (Paul McCartney)
        $paulUser = User::updateOrCreate(['email' => 'paul@example.com'], [
            'name' => 'Paul McCartney',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        $paul = Patient::updateOrCreate(['user_id' => $paulUser->id], [
            'date_of_birth' => '1942-06-18',
            'status' => 'active',
            'hospital_id' => $hospital->id,
            'primary_coordinator_id' => $seAdmin->id,
            'ohip' => '0987654321'
        ]);
        TransitionNeedsProfile::updateOrCreate(
            ['patient_id' => $paul->id],
            [
                'clinical_flags' => ['Wound Care', 'Mobility'],
                'narrative_summary' => 'Post-operative recovery from hip replacement. Surgical wound requires dressing changes.',
                'status' => 'completed',
                'ai_summary_status' => 'completed',
                'ai_summary_text' => 'Gemini Analysis: Wound healing normally. Mobility improving. Standard post-op protocol.'
            ]
        );
        $paulPlan = CarePlan::updateOrCreate(
            ['patient_id' => $paul->id],
            ['care_bundle_id' => $standardBundle->id, 'status' => 'active', 'version' => 1]
        );

        ServiceAssignment::updateOrCreate(
            ['patient_id' => $paul->id, 'service_type_id' => $nursing->id],
            ['care_plan_id' => $paulPlan->id, 'service_provider_organization_id' => $seHealth->id, 'status' => 'in_progress', 'frequency_rule' => '2x / Week', 'notes' => 'Wound care']
        );
        ServiceAssignment::updateOrCreate(
            ['patient_id' => $paul->id, 'service_type_id' => $psw->id],
            ['care_plan_id' => $paulPlan->id, 'service_provider_organization_id' => $seHealth->id, 'status' => 'in_progress', 'frequency_rule' => 'Daily', 'notes' => 'ADL assistance']
        );

        // 6. Scenario C: Complex Multi-Partner (Sgt. Pepper)
        $pepperUser = User::updateOrCreate(['email' => 'pepper@example.com'], [
            'name' => 'Sgt. Pepper',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        $pepper = Patient::updateOrCreate(['user_id' => $pepperUser->id], [
            'date_of_birth' => '1939-09-01',
            'status' => 'active',
            'hospital_id' => $hospital->id,
            'primary_coordinator_id' => $seAdmin->id,
            'ohip' => '5555555555'
        ]);
        TransitionNeedsProfile::updateOrCreate(
            ['patient_id' => $pepper->id],
            [
                'clinical_flags' => ['Multiple Comorbidities', 'Mental Health', 'Social Isolation'],
                'narrative_summary' => 'Complex case with history of falls, depression, and diabetes. Requires coordinated multi-disciplinary care.',
                'status' => 'completed',
                'ai_summary_status' => 'completed',
                'ai_summary_text' => 'Gemini Analysis: High risk of readmission. Multi-partner bundle required for holistic support.'
            ]
        );
        $pepperPlan = CarePlan::updateOrCreate(
            ['patient_id' => $pepper->id],
            ['care_bundle_id' => $complexBundle->id, 'status' => 'active', 'version' => 1]
        );

        ServiceAssignment::updateOrCreate(
            ['patient_id' => $pepper->id, 'service_type_id' => $nursing->id],
            ['care_plan_id' => $pepperPlan->id, 'service_provider_organization_id' => $seHealth->id, 'status' => 'in_progress', 'frequency_rule' => 'Daily', 'notes' => 'Complex wound care']
        );
        ServiceAssignment::updateOrCreate(
            ['patient_id' => $pepper->id, 'service_type_id' => $dementia->id],
            ['care_plan_id' => $pepperPlan->id, 'service_provider_organization_id' => $alexisLodge->id, 'status' => 'in_progress', 'frequency_rule' => 'Day Program', 'notes' => 'Adult Day Program attendance']
        );
        ServiceAssignment::updateOrCreate(
            ['patient_id' => $pepper->id, 'service_type_id' => $rpm->id],
            ['care_plan_id' => $pepperPlan->id, 'service_provider_organization_id' => $graceHospital->id, 'status' => 'in_progress', 'frequency_rule' => 'Continuous', 'notes' => 'Vitals monitoring']
        );
        ServiceAssignment::updateOrCreate(
            ['patient_id' => $pepper->id, 'service_type_id' => $mentalHealth->id],
            ['care_plan_id' => $pepperPlan->id, 'service_provider_organization_id' => $reconnect->id, 'status' => 'in_progress', 'frequency_rule' => 'Weekly', 'notes' => 'Addiction counseling']
        );
        ServiceAssignment::updateOrCreate(
            ['patient_id' => $pepper->id, 'service_type_id' => $youth->id],
            ['care_plan_id' => $pepperPlan->id, 'service_provider_organization_id' => $bridgingFutures->id, 'status' => 'in_progress', 'frequency_rule' => 'Bi-weekly', 'notes' => 'Youth engagement program']
        );
        // ... Existing assignments for Pepper ...
        ServiceAssignment::updateOrCreate(
            ['patient_id' => $pepper->id, 'service_type_id' => $digital->id],
            ['care_plan_id' => $pepperPlan->id, 'service_provider_organization_id' => $wellhaus->id, 'status' => 'in_progress', 'frequency_rule' => 'Setup', 'notes' => 'Digital platform access']
        );

        // 7. Pending Referrals (Intake Queue)
        $pending1User = User::updateOrCreate(['email' => 'pending1@example.com'], [
            'name' => 'John Doe',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        Patient::updateOrCreate(['user_id' => $pending1User->id], [
            'date_of_birth' => '1950-01-01',
            'status' => 'referral_received',
            'hospital_id' => $hospital->id,
            'ohip' => '1112223334'
        ]);

        $pending2User = User::updateOrCreate(['email' => 'pending2@example.com'], [
            'name' => 'Jane Roe',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        Patient::updateOrCreate(['user_id' => $pending2User->id], [
            'date_of_birth' => '1960-05-12',
            'status' => 'referral_received',
            'hospital_id' => $hospital->id,
            'ohip' => '2223334445'
        ]);

        $pending3User = User::updateOrCreate(['email' => 'pending3@example.com'], [
            'name' => 'Bob Smith',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        Patient::updateOrCreate(['user_id' => $pending3User->id], [
            'date_of_birth' => '1955-11-30',
            'status' => 'referral_received',
            'hospital_id' => $hospital->id,
            'ohip' => '3334445556'
        ]);
    }
}