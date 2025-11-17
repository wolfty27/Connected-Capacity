<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingsController;
use App\Models\User;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\RetirementHome;

class BookingCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_retirement_home_can_trigger_booking_creation()
    {
        Route::middleware('web')->get('/test/bookings/{patient}', [BookingsController::class, 'bookAppointment']);

        $hospitalUser = User::create([
            'name' => 'Hospital User',
            'email' => 'hospital@example.com',
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
            'name' => 'Patient One',
            'email' => 'patient@example.com',
            'password' => bcrypt('secret'),
            'role' => 'patient',
        ]);

        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'hospital_id' => $hospital->id,
            'status' => 'Inactive',
            'gender' => 'Male',
        ]);

        $retirementUser = User::create([
            'name' => 'Retirement User',
            'email' => 'retirement@example.com',
            'password' => bcrypt('secret'),
            'role' => 'retirement-home',
        ]);

        RetirementHome::create([
            'user_id' => $retirementUser->id,
            'type' => 'independent',
            'website' => 'https://retirement.test',
        ]);

        $response = $this->actingAs($retirementUser)->get('/test/bookings/' . $patient->id);

        $response->assertRedirect('/bookings');
        $this->assertTrue(session()->has('success'));

        $this->assertDatabaseHas('bookings', [
            'patient_id' => $patient->id,
            'hospital_id' => $hospital->id,
        ]);
    }
}
