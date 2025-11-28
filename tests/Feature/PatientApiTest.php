<?php

namespace Tests\Feature;

use App\Models\Hospital;
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

    /**
     * Test that store patient defaults hospital_id when not provided.
     */
    public function test_store_patient_defaults_hospital_id()
    {
        // Create a hospital that will be used as the default
        $hospital = Hospital::factory()->create();

        // Create an admin user (no specific hospital association)
        $adminUser = User::factory()->create(['role' => 'admin']);

        // Create patient without providing hospital_id
        $response = $this->actingAs($adminUser)->postJson('/api/patients', [
            'name' => 'Test Patient',
            'email' => 'testpatient@example.com',
            'gender' => 'Male',
            'date_of_birth' => '1990-05-15',
            'ohip' => '1234567890AB',
            'add_to_queue' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'status', 'is_in_queue']
            ]);

        // Verify patient was created with a non-null hospital_id
        $patientId = $response->json('data.id');
        $patient = Patient::find($patientId);

        $this->assertNotNull($patient);
        $this->assertNotNull($patient->hospital_id);
        $this->assertEquals($hospital->id, $patient->hospital_id);
    }

    /**
     * Test that store patient uses provided hospital_id when given.
     */
    public function test_store_patient_uses_provided_hospital_id()
    {
        // Create multiple hospitals
        $hospital1 = Hospital::factory()->create();
        $hospital2 = Hospital::factory()->create();

        $adminUser = User::factory()->create(['role' => 'admin']);

        // Create patient with specific hospital_id
        $response = $this->actingAs($adminUser)->postJson('/api/patients', [
            'name' => 'Test Patient 2',
            'email' => 'testpatient2@example.com',
            'gender' => 'Female',
            'hospital_id' => $hospital2->id,
        ]);

        $response->assertStatus(201);

        $patientId = $response->json('data.id');
        $patient = Patient::find($patientId);

        $this->assertNotNull($patient);
        $this->assertEquals($hospital2->id, $patient->hospital_id);
    }

    /**
     * Test that hospital role user's hospital is used as default.
     */
    public function test_store_patient_uses_hospital_users_hospital()
    {
        // Create a hospital with a user
        $hospitalUser = User::factory()->create(['role' => 'hospital']);
        $hospital = Hospital::factory()->create(['user_id' => $hospitalUser->id]);

        // Also create another hospital to ensure it doesn't pick the first one
        Hospital::factory()->create();

        // Create patient as hospital user without providing hospital_id
        $response = $this->actingAs($hospitalUser)->postJson('/api/patients', [
            'name' => 'Hospital User Patient',
            'email' => 'hospitalpatient@example.com',
            'gender' => 'Male',
        ]);

        $response->assertStatus(201);

        $patientId = $response->json('data.id');
        $patient = Patient::find($patientId);

        $this->assertNotNull($patient);
        $this->assertEquals($hospital->id, $patient->hospital_id);
    }

    /**
     * Test that store fails gracefully when no hospitals exist.
     */
    public function test_store_patient_fails_without_hospitals()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);

        // No hospitals exist in the database
        $response = $this->actingAs($adminUser)->postJson('/api/patients', [
            'name' => 'No Hospital Patient',
            'email' => 'nohospital@example.com',
            'gender' => 'Male',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'No hospital available. Please create a hospital first or specify a hospital_id.',
            ]);
    }
}
