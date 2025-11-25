<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MasterUserSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'master@connectedcapacity.com'],
            [
                'name' => 'Master Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_MASTER,
                'organization_id' => null,
            ]
        );
    }
}
