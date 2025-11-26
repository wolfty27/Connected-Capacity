<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ServiceProviderOrganization;
use App\Models\Hospital;
use App\Models\RetirementHome;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run()
    {
        // 1. Admin
        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'System Admin',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        // 2. SPO Admin & Organization (SE Health)
        $spo = ServiceProviderOrganization::firstOrCreate(
            ['slug' => 'se-health'],
            ['name' => 'SE Health', 'active' => true]
        );
        User::updateOrCreate(['email' => 'admin@sehc.com'], [
            'name' => 'SE Health Admin',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SPO_ADMIN,
            'organization_id' => $spo->id,
            'organization_role' => User::ROLE_SPO_ADMIN,
        ]);

        // 3. Hospital User
        $hospitalUser = User::updateOrCreate(['email' => 'hospital@example.com'], [
            'name' => 'Hospital Staff',
            'password' => Hash::make('password'),
            'role' => User::ROLE_HOSPITAL,
        ]);
        Hospital::firstOrCreate(['user_id' => $hospitalUser->id]);

        // 4. SPO Coordinator
        User::updateOrCreate(['email' => 'coordinator@sehc.com'], [
            'name' => 'Sarah Mitchell',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SPO_COORDINATOR,
            'organization_id' => $spo->id,
            'organization_role' => 'Care Coordinator',
            'employment_type' => 'full_time',
            'max_weekly_hours' => 40,
            'staff_status' => 'active',
        ]);

        // 5. Field Staff - Full Time
        User::updateOrCreate(['email' => 'maria.santos@sehc.com'], [
            'name' => 'Maria Santos',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'RN',
            'employment_type' => 'full_time',
            'max_weekly_hours' => 40,
            'staff_status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'james.chen@sehc.com'], [
            'name' => 'James Chen',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'PSW',
            'employment_type' => 'full_time',
            'max_weekly_hours' => 40,
            'staff_status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'aisha.patel@sehc.com'], [
            'name' => 'Aisha Patel',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'OT',
            'employment_type' => 'full_time',
            'max_weekly_hours' => 40,
            'staff_status' => 'active',
        ]);

        // 6. Field Staff - Part Time
        User::updateOrCreate(['email' => 'david.wilson@sehc.com'], [
            'name' => 'David Wilson',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'RPN',
            'employment_type' => 'part_time',
            'max_weekly_hours' => 24,
            'staff_status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'lisa.nguyen@sehc.com'], [
            'name' => 'Lisa Nguyen',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'PSW',
            'employment_type' => 'part_time',
            'max_weekly_hours' => 20,
            'staff_status' => 'active',
        ]);

        // 7. Field Staff - Casual
        User::updateOrCreate(['email' => 'michael.brown@sehc.com'], [
            'name' => 'Michael Brown',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'PSW',
            'employment_type' => 'casual',
            'max_weekly_hours' => 16,
            'staff_status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'emma.taylor@sehc.com'], [
            'name' => 'Emma Taylor',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'RN',
            'employment_type' => 'casual',
            'max_weekly_hours' => 12,
            'staff_status' => 'active',
        ]);

        // 8. Staff on leave
        User::updateOrCreate(['email' => 'robert.lee@sehc.com'], [
            'name' => 'Robert Lee',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'PT',
            'employment_type' => 'full_time',
            'max_weekly_hours' => 40,
            'staff_status' => 'on_leave',
        ]);
    }
}
