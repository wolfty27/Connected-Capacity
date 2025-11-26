<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_patient_returns_correct_structure()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $patientUser = User::factory()->create(['name' => 'John Doe']);
        
        // Patient factory might not have ohip in definition if migration was recent, so we explicitly set it.
        // Also ensuring we have a valid hospital if the factory requires it (it does).
        $patient = Patient::factory()->create([
            'user_id' => $patientUser->id,
            'date_of_birth' => '1980-01-01',
            'ohip' => '1234567890',
            'status' => 'Active'
        ]);

        $response = $this->actingAs($user)->getJson('/api/patients/' . $patient->id);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $patient->id,
                    'user' => [
                        'name' => 'John Doe',
                    ],
                    'date_of_birth' => '1980-01-01',
                    'ohip' => '1234567890',
                    'status' => 'Active'
                ]
            ]);
            
        // Assert keys explicitly to ensure they exist
        $response->assertJsonStructure([
            'data' => [
                'id',
                'user' => ['name', 'email', 'phone'],
                'date_of_birth',
                'ohip',
                'diagnosis',
                'status'
            ]
        ]);
    }
}
