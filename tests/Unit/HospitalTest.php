<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;

class HospitalTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_relationships_work()
    {
        $user = User::create([
            'name' => 'Hospital User',
            'email' => 'hospital@test.com',
            'password' => bcrypt('secret'),
            'role' => 'hospital',
        ]);

        $hospital = Hospital::create([
            'user_id' => $user->id,
            'documents' => null,
            'website' => 'https://hospital.test',
            'calendly' => null,
        ]);

        $patientUser = User::create([
            'name' => 'Patient Example',
            'email' => 'patient@test.com',
            'password' => bcrypt('secret'),
            'role' => 'patient',
        ]);

        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'hospital_id' => $hospital->id,
            'status' => 'Available',
            'gender' => 'Female',
        ]);

        $this->assertEquals($user->id, $hospital->user->id);
        $this->assertTrue($hospital->patients->contains($patient));
    }
}
