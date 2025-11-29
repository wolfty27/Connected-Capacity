<?php

namespace Tests\Unit;

use App\Models\CarePlan;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Scheduling\SchedulingEngine;
use App\Services\Travel\FakeTravelTimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SchedulingEngineTravelTest - Tests for travel-aware scheduling.
 *
 * Verifies that:
 * - canAssignWithTravel() correctly validates travel constraints
 * - Overlapping-by-travel scenarios are blocked
 * - Realistic spacing scenarios are allowed
 * - First appointment of day is exempt from inbound travel check
 */
class SchedulingEngineTravelTest extends TestCase
{
    use RefreshDatabase;

    protected SchedulingEngine $engine;
    protected User $staff;
    protected Hospital $hospital;
    protected Patient $patient1;
    protected Patient $patient2;
    protected Patient $patient3;
    protected ServiceType $serviceType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new SchedulingEngine();

        // Use fixed travel time for predictable tests
        $this->engine->setTravelTimeService(FakeTravelTimeService::fixed(15));

        // Create staff role
        $staffRole = StaffRole::create([
            'code' => 'PSW',
            'name' => 'Personal Support Worker',
            'category' => 'direct_care',
            'is_active' => true,
        ]);

        // Create staff member
        $this->staff = User::create([
            'name' => 'Test Staff',
            'email' => 'staff@test.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_FIELD_STAFF,
            'staff_status' => User::STAFF_STATUS_ACTIVE,
            'staff_role_id' => $staffRole->id,
            'max_weekly_hours' => 40,
        ]);

        // Create staff availability (Mon-Fri 08:00-16:00)
        for ($day = 1; $day <= 5; $day++) {
            StaffAvailability::create([
                'user_id' => $this->staff->id,
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '16:00',
                'effective_from' => Carbon::now()->subMonth(),
                'is_recurring' => true,
            ]);
        }

        // Create hospital
        $hospitalUser = User::create([
            'name' => 'Test Hospital',
            'email' => 'hospital@test.com',
            'password' => bcrypt('secret'),
            'role' => 'hospital',
        ]);

        $this->hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'documents' => null,
            'website' => 'https://test-hospital.com',
        ]);

        // Create patients with different locations
        $this->patient1 = $this->createPatient('Patient 1', 43.6564, -79.3887); // Downtown
        $this->patient2 = $this->createPatient('Patient 2', 43.7123, -79.3774); // North
        $this->patient3 = $this->createPatient('Patient 3', 43.6426, -79.3871); // CN Tower area

        // Create service type
        $this->serviceType = ServiceType::create([
            'code' => 'PSW',
            'name' => 'Personal Support',
            'category' => 'personal_support',
            'default_duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    public function test_first_appointment_of_day_is_allowed()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);
        $start = $date->copy()->setTime(9, 0);
        $end = $date->copy()->setTime(10, 0);

        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $this->patient1,
            $start,
            $end,
            $this->serviceType->id
        );

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_overlapping_time_is_blocked()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);

        // Create existing assignment
        $this->createAssignment(
            $this->patient1,
            $date->copy()->setTime(10, 0),
            $date->copy()->setTime(11, 0)
        );

        // Try to schedule overlapping assignment
        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $this->patient2,
            $date->copy()->setTime(10, 30),
            $date->copy()->setTime(11, 30),
            $this->serviceType->id
        );

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('Conflicts', $result->errors[0]);
    }

    public function test_insufficient_travel_time_is_blocked()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);

        // Create existing assignment ending at 10:00
        $this->createAssignment(
            $this->patient1,
            $date->copy()->setTime(9, 0),
            $date->copy()->setTime(10, 0)
        );

        // Try to schedule next assignment starting at 10:05 (only 5 min gap)
        // With 15 min travel time + 5 min buffer, this should fail
        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $this->patient2,
            $date->copy()->setTime(10, 5),
            $date->copy()->setTime(11, 5),
            $this->serviceType->id
        );

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('Cannot start at', $result->errors[0]);
    }

    public function test_sufficient_travel_time_is_allowed()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);

        // Create existing assignment ending at 10:00
        $this->createAssignment(
            $this->patient1,
            $date->copy()->setTime(9, 0),
            $date->copy()->setTime(10, 0)
        );

        // Schedule next assignment at 10:25 (25 min gap)
        // With 15 min travel + 5 min buffer = 20 min needed, so 25 min is OK
        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $this->patient2,
            $date->copy()->setTime(10, 25),
            $date->copy()->setTime(11, 25),
            $this->serviceType->id
        );

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_travel_validation_returns_earliest_start()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);

        // Create existing assignment ending at 10:00
        $this->createAssignment(
            $this->patient1,
            $date->copy()->setTime(9, 0),
            $date->copy()->setTime(10, 0)
        );

        // Try to schedule too early
        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $this->patient2,
            $date->copy()->setTime(10, 5),
            $date->copy()->setTime(11, 5),
            $this->serviceType->id
        );

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->earliestStart);
        // Earliest should be 10:00 + buffer + travel = 10:20
        $this->assertEquals('10:20', $result->earliestStart->format('H:i'));
    }

    public function test_travel_to_next_assignment_is_validated()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);

        // Create existing assignment starting at 11:30
        $this->createAssignment(
            $this->patient2,
            $date->copy()->setTime(11, 30),
            $date->copy()->setTime(12, 30)
        );

        // Try to schedule assignment ending at 11:25 (only 5 min before next)
        // With 15 min travel + 5 min buffer needed, this should fail
        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $this->patient1,
            $date->copy()->setTime(10, 25),
            $date->copy()->setTime(11, 25),
            $this->serviceType->id
        );

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Must end by', $result->errors[0]);
    }

    public function test_patient_without_coordinates_gets_warning()
    {
        $patientNoCoords = $this->createPatient('No Coords Patient', null, null);

        $date = Carbon::now()->next(Carbon::MONDAY);
        $start = $date->copy()->setTime(9, 0);
        $end = $date->copy()->setTime(10, 0);

        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $patientNoCoords,
            $start,
            $end,
            $this->serviceType->id
        );

        // Should still be valid but with warning
        $this->assertTrue($result->isValid);
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('location unknown', $result->warnings[0]);
    }

    public function test_update_assignment_with_exclude_id()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);

        // Create assignment
        $assignment = $this->createAssignment(
            $this->patient1,
            $date->copy()->setTime(9, 0),
            $date->copy()->setTime(10, 0)
        );

        // Try to validate the same slot (should pass when excluding self)
        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $this->patient1,
            $date->copy()->setTime(9, 0),
            $date->copy()->setTime(10, 0),
            $this->serviceType->id,
            $assignment->id
        );

        $this->assertTrue($result->isValid);
    }

    public function test_get_previous_assignment()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);

        $assignment1 = $this->createAssignment(
            $this->patient1,
            $date->copy()->setTime(9, 0),
            $date->copy()->setTime(10, 0)
        );

        $assignment2 = $this->createAssignment(
            $this->patient2,
            $date->copy()->setTime(11, 0),
            $date->copy()->setTime(12, 0)
        );

        // Get previous before 11:30 (should be assignment2)
        $previous = $this->engine->getPreviousAssignment(
            $this->staff->id,
            $date->copy()->setTime(12, 30)
        );

        $this->assertNotNull($previous);
        $this->assertEquals($assignment2->id, $previous->id);
    }

    public function test_get_next_assignment()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);

        $assignment1 = $this->createAssignment(
            $this->patient1,
            $date->copy()->setTime(9, 0),
            $date->copy()->setTime(10, 0)
        );

        $assignment2 = $this->createAssignment(
            $this->patient2,
            $date->copy()->setTime(11, 0),
            $date->copy()->setTime(12, 0)
        );

        // Get next after 10:00 (should be assignment2)
        $next = $this->engine->getNextAssignment(
            $this->staff->id,
            $date->copy()->setTime(10, 0)
        );

        $this->assertNotNull($next);
        $this->assertEquals($assignment2->id, $next->id);
    }

    public function test_get_buffer_minutes_by_category()
    {
        // Nursing should have 10 min buffer
        $nursingType = ServiceType::create([
            'code' => 'NUR',
            'name' => 'Nursing',
            'category' => 'nursing',
            'is_active' => true,
        ]);

        $buffer = $this->engine->getBufferMinutes($nursingType->id);
        $this->assertEquals(10, $buffer);

        // PSW should have 5 min buffer
        $buffer = $this->engine->getBufferMinutes($this->serviceType->id);
        $this->assertEquals(5, $buffer);

        // Unknown should use default
        $buffer = $this->engine->getBufferMinutes(null);
        $this->assertEquals(5, $buffer);
    }

    // =========================================================================
    // PATIENT NON-CONCURRENCY TESTS
    // =========================================================================

    public function test_patient_concurrent_visit_is_blocked()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);

        // Create an existing assignment for patient1 with a different staff member
        $otherStaff = User::create([
            'name' => 'Other Staff',
            'email' => 'other.staff@test.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_FIELD_STAFF,
            'staff_status' => User::STAFF_STATUS_ACTIVE,
            'max_weekly_hours' => 40,
        ]);

        // Other staff has assignment with patient1 at 10:00-11:00
        ServiceAssignment::create([
            'patient_id' => $this->patient1->id,
            'assigned_user_id' => $otherStaff->id,
            'service_type_id' => $this->serviceType->id,
            'scheduled_start' => $date->copy()->setTime(10, 0),
            'scheduled_end' => $date->copy()->setTime(11, 0),
            'duration_minutes' => 60,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'source' => ServiceAssignment::SOURCE_INTERNAL,
        ]);

        // Now try to assign the main staff to patient1 at overlapping time
        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $this->patient1,
            $date->copy()->setTime(10, 30),
            $date->copy()->setTime(11, 30),
            $this->serviceType->id
        );

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('Patient conflict', $result->errors[0]);
    }

    public function test_patient_non_overlapping_visits_allowed()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);

        // Create an existing assignment for patient1 with a different staff member
        $otherStaff = User::create([
            'name' => 'Other Staff',
            'email' => 'other.staff2@test.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_FIELD_STAFF,
            'staff_status' => User::STAFF_STATUS_ACTIVE,
            'max_weekly_hours' => 40,
        ]);

        // Other staff has assignment with patient1 at 9:00-10:00
        ServiceAssignment::create([
            'patient_id' => $this->patient1->id,
            'assigned_user_id' => $otherStaff->id,
            'service_type_id' => $this->serviceType->id,
            'scheduled_start' => $date->copy()->setTime(9, 0),
            'scheduled_end' => $date->copy()->setTime(10, 0),
            'duration_minutes' => 60,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'source' => ServiceAssignment::SOURCE_INTERNAL,
        ]);

        // Assign main staff to patient1 at non-overlapping time (11:00-12:00)
        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $this->patient1,
            $date->copy()->setTime(11, 0),
            $date->copy()->setTime(12, 0),
            $this->serviceType->id
        );

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_has_patient_conflicts_method()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);
        $start = $date->copy()->setTime(10, 0);
        $end = $date->copy()->setTime(11, 0);

        // Create assignment for patient1
        $this->createAssignment($this->patient1, $start, $end);

        // Check for conflict
        $hasConflict = $this->engine->hasPatientConflicts(
            $this->patient1->id,
            $date->copy()->setTime(10, 30),
            $date->copy()->setTime(11, 30)
        );

        $this->assertTrue($hasConflict);

        // Check no conflict for different patient
        $hasNoConflict = $this->engine->hasPatientConflicts(
            $this->patient2->id,
            $date->copy()->setTime(10, 30),
            $date->copy()->setTime(11, 30)
        );

        $this->assertFalse($hasNoConflict);
    }

    public function test_get_patient_conflicts_method()
    {
        $date = Carbon::now()->next(Carbon::MONDAY);
        $start = $date->copy()->setTime(10, 0);
        $end = $date->copy()->setTime(11, 0);

        // Create assignment for patient1
        $assignment = $this->createAssignment($this->patient1, $start, $end);

        // Get conflicts
        $conflicts = $this->engine->getPatientConflicts(
            $this->patient1->id,
            $date->copy()->setTime(10, 30),
            $date->copy()->setTime(11, 30)
        );

        $this->assertCount(1, $conflicts);
        $this->assertEquals($assignment->id, $conflicts->first()->id);
    }

    protected function createPatient(string $name, ?float $lat, ?float $lng): Patient
    {
        static $counter = 0;
        $counter++;

        $patientUser = User::create([
            'name' => $name,
            'email' => "patient{$counter}@test.com",
            'password' => bcrypt('secret'),
            'role' => 'patient',
        ]);

        return Patient::create([
            'user_id' => $patientUser->id,
            'hospital_id' => $this->hospital->id,
            'status' => 'Active',
            'gender' => 'Male',
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }

    protected function createAssignment(Patient $patient, Carbon $start, Carbon $end): ServiceAssignment
    {
        return ServiceAssignment::create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $this->staff->id,
            'service_type_id' => $this->serviceType->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'duration_minutes' => $start->diffInMinutes($end),
            'status' => ServiceAssignment::STATUS_PLANNED,
            'source' => ServiceAssignment::SOURCE_INTERNAL,
        ]);
    }
}
