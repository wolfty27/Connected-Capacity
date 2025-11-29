<?php

namespace Tests\Feature;

use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Scheduling\SchedulingEngine;
use Carbon\Carbon;
use Database\Seeders\CoreDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test patient non-concurrency constraint.
 *
 * A patient cannot have multiple service providers at the same time.
 * This prevents scheduling conflicts where two staff members would
 * be assigned to the same patient simultaneously.
 */
class PatientNonConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Patient $patient;
    protected User $staff1;
    protected User $staff2;
    protected ServiceType $serviceType;
    protected CarePlan $carePlan;
    protected SchedulingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed service types
        $this->seed(CoreDataSeeder::class);

        $this->engine = new SchedulingEngine();

        // Create SPO
        $spo = ServiceProviderOrganization::create([
            'name' => 'Test SPO',
            'slug' => 'test-spo',
            'type' => 'se_health',
            'active' => true,
        ]);

        // Create patient with coordinates
        $patientUser = User::create([
            'name' => 'Test Patient',
            'email' => 'patient@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PATIENT,
        ]);

        $this->patient = Patient::create([
            'user_id' => $patientUser->id,
            'status' => 'Active',
            'lat' => 43.6532,
            'lng' => -79.3832,
        ]);

        // Create care plan
        $this->carePlan = CarePlan::create([
            'patient_id' => $this->patient->id,
            'status' => 'active',
            'start_date' => Carbon::now()->subDays(7),
        ]);

        // Create staff members
        $this->staff1 = User::create([
            'name' => 'Staff Member 1',
            'email' => 'staff1@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'staff_status' => User::STAFF_STATUS_ACTIVE,
        ]);

        $this->staff2 = User::create([
            'name' => 'Staff Member 2',
            'email' => 'staff2@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'staff_status' => User::STAFF_STATUS_ACTIVE,
        ]);

        $this->serviceType = ServiceType::where('code', 'PSW')->first();
    }

    /** @test */
    public function patient_cannot_have_overlapping_visits()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create first assignment: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->serviceType->id,
            'assigned_user_id' => $this->staff1->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Try to create overlapping assignment: 09:30-10:30
        $overlapStart = $today->copy()->addMinutes(30);
        $overlapEnd = $overlapStart->copy()->addHour();

        $hasOverlap = $this->engine->patientHasOverlap(
            $this->patient->id,
            $overlapStart,
            $overlapEnd
        );

        $this->assertTrue($hasOverlap, 'Should detect patient overlap');
    }

    /** @test */
    public function patient_can_have_non_overlapping_visits()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create first assignment: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->serviceType->id,
            'assigned_user_id' => $this->staff1->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Try to create non-overlapping assignment: 10:30-11:30 (30 min gap)
        $nextStart = $today->copy()->addMinutes(90);
        $nextEnd = $nextStart->copy()->addHour();

        $hasOverlap = $this->engine->patientHasOverlap(
            $this->patient->id,
            $nextStart,
            $nextEnd
        );

        $this->assertFalse($hasOverlap, 'Should not detect overlap for non-overlapping visits');
    }

    /** @test */
    public function can_assign_with_travel_rejects_patient_overlap()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create first assignment: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->serviceType->id,
            'assigned_user_id' => $this->staff1->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Try to assign a different staff member at overlapping time
        $overlapStart = $today->copy()->addMinutes(30);
        $overlapEnd = $overlapStart->copy()->addHour();

        $result = $this->engine->canAssignWithTravel(
            $this->staff2,
            $this->patient,
            $overlapStart,
            $overlapEnd,
            $this->serviceType->id
        );

        $this->assertFalse($result->isValid(), 'Should reject overlapping patient assignment');
        $this->assertStringContainsString(
            'Patient already has another visit',
            $result->getErrors()[0],
            'Error message should mention patient overlap'
        );
    }

    /** @test */
    public function cancelled_assignments_do_not_block_scheduling()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create cancelled assignment: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->serviceType->id,
            'assigned_user_id' => $this->staff1->id,
            'status' => ServiceAssignment::STATUS_CANCELLED,
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Try to schedule at the same time (should succeed because previous is cancelled)
        $result = $this->engine->canAssignWithTravel(
            $this->staff2,
            $this->patient,
            $today,
            $today->copy()->addHour(),
            $this->serviceType->id
        );

        $this->assertTrue(
            $result->isValid(),
            'Cancelled assignments should not block new scheduling'
        );
    }

    /** @test */
    public function exact_adjacent_times_do_not_overlap()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create first assignment: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->serviceType->id,
            'assigned_user_id' => $this->staff1->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Try to create adjacent assignment: 10:00-11:00 (starts exactly when first ends)
        $adjacentStart = $today->copy()->addHour();
        $adjacentEnd = $adjacentStart->copy()->addHour();

        $hasOverlap = $this->engine->patientHasOverlap(
            $this->patient->id,
            $adjacentStart,
            $adjacentEnd
        );

        $this->assertFalse(
            $hasOverlap,
            'Adjacent visits (no gap) should not be considered overlapping'
        );
    }
}
