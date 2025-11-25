<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ServiceProviderOrganization;
use App\Models\NewHospital;
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
        NewHospital::create(['user_id' => $hospitalUser->id]);

        // 4. Field Staff
        User::updateOrCreate(['email' => 'field@example.com'], [
            'name' => 'Field Staff',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
        ]);
    }
}
