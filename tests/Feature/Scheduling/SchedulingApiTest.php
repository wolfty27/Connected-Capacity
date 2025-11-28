<?php

namespace Tests\Feature\Scheduling;

use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulingApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ServiceProviderOrganization $spo;
    protected Patient $patient;
    protected CarePlan $carePlan;
    protected ServiceType $serviceType;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->spo = ServiceProviderOrganization::factory()->create([
            'type' => 'se_health',
        ]);

        // Create admin user
        $this->user = User::factory()->create([
            'role' => User::ROLE_SPO_ADMIN,
            'organization_id' => $this->spo->id,
        ]);

        // Create staff role
        $staffRole = StaffRole::factory()->create([
            'code' => 'PSW',
            'name' => 'Personal Support Worker',
        ]);

        // Create staff member
        $this->staff = User::factory()->create([
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $this->spo->id,
            'staff_role_id' => $staffRole->id,
            'staff_status' => User::STAFF_STATUS_ACTIVE,
        ]);

        // Create staff availability
        for ($day = 1; $day <= 5; $day++) {
            StaffAvailability::create([
                'user_id' => $this->staff->id,
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '16:00',
                'effective_from' => now()->subMonth(),
            ]);
        }

        // Create service type
        $this->serviceType = ServiceType::factory()->create([
            'code' => 'PSW',
            'name' => 'Personal Support Worker',
            'category' => 'psw',
        ]);

        // Create patient with care plan
        $patientUser = User::factory()->create(['role' => 'patient']);
        $this->patient = Patient::factory()->create([
            'user_id' => $patientUser->id,
            'status' => 'Active',
        ]);

        $this->carePlan = CarePlan::factory()->create([
            'patient_id' => $this->patient->id,
            'status' => 'active',
        ]);
    }

    public function test_get_scheduling_grid_returns_staff_and_assignments()
    {
        // Create an assignment
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->serviceType->id,
            'assigned_user_id' => $this->staff->id,
            'service_provider_organization_id' => $this->spo->id,
            'scheduled_start' => now()->startOfWeek()->addDays(1)->setTime(9, 0),
            'scheduled_end' => now()->startOfWeek()->addDays(1)->setTime(10, 0),
            'status' => ServiceAssignment::STATUS_PLANNED,
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v2/scheduling/grid?' . http_build_query([
                'start_date' => now()->startOfWeek()->toDateString(),
                'end_date' => now()->endOfWeek()->toDateString(),
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'staff' => [
                        '*' => ['id', 'name', 'role', 'employment_type', 'utilization'],
                    ],
                    'assignments' => [
                        '*' => ['id', 'staff_id', 'patient_id', 'service_type_name'],
                    ],
                    'week' => ['start', 'end'],
                ],
            ]);
    }

    public function test_get_scheduling_requirements_returns_unscheduled_care()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v2/scheduling/requirements?' . http_build_query([
                'start_date' => now()->startOfWeek()->toDateString(),
                'end_date' => now()->endOfWeek()->toDateString(),
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'summary' => [
                    'patients_with_needs',
                    'total_remaining_hours',
                    'period',
                ],
            ]);
    }

    public function test_create_assignment_succeeds_with_valid_data()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v2/scheduling/assignments', [
                'staff_id' => $this->staff->id,
                'patient_id' => $this->patient->id,
                'service_type_id' => $this->serviceType->id,
                'date' => now()->startOfWeek()->addDay()->toDateString(),
                'start_time' => '09:00',
                'duration_minutes' => 60,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'staff_id',
                    'patient_id',
                    'service_type_id',
                    'date',
                    'start_time',
                    'duration_minutes',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('service_assignments', [
            'patient_id' => $this->patient->id,
            'assigned_user_id' => $this->staff->id,
            'service_type_id' => $this->serviceType->id,
        ]);
    }

    public function test_update_assignment_succeeds()
    {
        $assignment = ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->serviceType->id,
            'assigned_user_id' => $this->staff->id,
            'service_provider_organization_id' => $this->spo->id,
            'scheduled_start' => now()->startOfWeek()->addDays(1)->setTime(9, 0),
            'scheduled_end' => now()->startOfWeek()->addDays(1)->setTime(10, 0),
            'status' => ServiceAssignment::STATUS_PLANNED,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v2/scheduling/assignments/{$assignment->id}", [
                'start_time' => '10:00',
                'duration_minutes' => 90,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.start_time', '10:00');

        $assignment->refresh();
        $this->assertEquals(90, $assignment->duration_minutes);
    }

    public function test_delete_assignment_cancels_it()
    {
        $assignment = ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->serviceType->id,
            'assigned_user_id' => $this->staff->id,
            'service_provider_organization_id' => $this->spo->id,
            'scheduled_start' => now()->startOfWeek()->addDays(1)->setTime(9, 0),
            'scheduled_end' => now()->startOfWeek()->addDays(1)->setTime(10, 0),
            'status' => ServiceAssignment::STATUS_PLANNED,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v2/scheduling/assignments/{$assignment->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Assignment cancelled successfully']);

        $assignment->refresh();
        $this->assertEquals(ServiceAssignment::STATUS_CANCELLED, $assignment->status);
    }

    public function test_get_navigation_examples_returns_sample_data()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v2/scheduling/navigation-examples');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'staff',
                    'patient',
                ],
            ]);
    }
}
