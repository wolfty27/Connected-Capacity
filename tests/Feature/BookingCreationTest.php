<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingsController;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\RetirementHome;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BookingCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_retirement_home_can_trigger_booking_creation()
    {
        $this->withoutExceptionHandling();
        // Temporary route for the test to hit the booking controller action
        Route::middleware('web')->get('/test/bookings/{patient}', [BookingsController::class, 'bookAppointment']);

        $hospitalUser = User::factory()->create([
            'role' => 'hospital',
        ]);

        $hospital = Hospital::factory()->create([
            'user_id' => $hospitalUser->id,
        ]);

        $retirementUser = User::factory()->create([
            'role' => 'retirement-home',
        ]);

        $patient = Patient::factory()->create([
            'hospital_id' => $hospital->id,
        ]);

        RetirementHome::create([
            'user_id' => $retirementUser->id,
            'type' => 'independent',
            'website' => 'https://retirement.test',
        ]);

        $response = $this->actingAs($retirementUser)->get('/test/bookings/' . $patient->id);

        // Assert we redirect to the bookings page
        $response->assertRedirect('/bookings');

        // Assert the booking exists in the database
        $this->assertDatabaseHas('bookings', [
            'patient_id' => $patient->id,
            'hospital_id' => $hospital->id,
        ]);
    }
}
