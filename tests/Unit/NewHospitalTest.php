<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\NewHospital;
use App\Models\Hospital;
use App\Models\User;

class NewHospitalTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_hospital_is_alias_of_hospital_model()
    {
        $user = User::create([
            'name' => 'Alias Hospital User',
            'email' => 'alias@test.com',
            'password' => bcrypt('secret'),
            'role' => 'hospital',
        ]);

        $newHospital = NewHospital::create([
            'user_id' => $user->id,
            'documents' => null,
            'website' => 'https://alias-hospital.test',
            'calendly' => null,
        ]);

        $this->assertEquals($newHospital->id, Hospital::first()->id);
        $this->assertEquals($user->id, $newHospital->user->id);
    }
}
