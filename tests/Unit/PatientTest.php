<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Hospital;
use App\Models\Patient;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_casts_and_relationships()
    {
        $hospitalUser = User::create([
            'name' => 'Hospital User',
            'email' => 'hospital-rel@test.com',
            'password' => bcrypt('secret'),
            'role' => 'hospital',
        ]);

        $hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'documents' => null,
            'website' => 'https://rel-hospital.test',
            'calendly' => null,
        ]);

        $patientUser = User::create([
            'name' => 'Patient Rel',
            'email' => 'patient-rel@test.com',
            'password' => bcrypt('secret'),
            'role' => 'patient',
        ]);

        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'hospital_id' => $hospital->id,
            'status' => 'Active',
            'gender' => 'Male',
        ]);

        $this->assertSame('Active', $patient->fresh()->status);
        $this->assertEquals($hospital->id, $patient->hospital->id);
    }
}
