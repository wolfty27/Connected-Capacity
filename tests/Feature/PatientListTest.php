<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Hospital;
use App\Models\Patient;

class PatientListTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_user_can_view_patient_list()
    {
        $hospitalUser = User::create([
            'name' => 'Hospital User',
            'email' => 'hospital@test.com',
            'password' => bcrypt('secret'),
            'role' => 'hospital',
        ]);

        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
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

        Patient::create([
            'user_id' => $patientUser->id,
            'hospital_id' => $hospital->id,
            'status' => 'Available',
            'gender' => 'Female',
        ]);

        $response = $this->actingAs($hospitalUser)->get('/patients');

        $response->assertStatus(200);
        $response->assertSee('Patient Example');
    }
}
