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
 * Test service type spacing rules.
 *
 * Services with min_gap_between_visits_minutes require a minimum time
 * gap between consecutive visits. This prevents bunching multiple visits
 * of the same service type too close together.
 *
 * Example: PSW visits require 120 min gap to ensure visits are spread
 * throughout the day (morning, noon, evening) instead of all in the morning.
 */
class SpacingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected Patient $patient;
    protected User $staff;
    protected ServiceType $pswServiceType;
    protected ServiceType $nursingServiceType;
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

        // Create staff member
        $this->staff = User::create([
            'name' => 'Test Staff',
            'email' => 'staff@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'staff_status' => User::STAFF_STATUS_ACTIVE,
        ]);

        $this->pswServiceType = ServiceType::where('code', 'PSW')->first();
        $this->nursingServiceType = ServiceType::where('code', 'NUR')->first();
    }

    /** @test */
    public function psw_visits_require_120_minute_gap()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create first PSW visit: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->pswServiceType->id,
            'assigned_user_id' => $this->staff->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Try to schedule second PSW visit at 09:30 (only 30 min gap, needs 120)
        $tooEarlyStart = $today->copy()->addMinutes(90); // 10:30 (30 min after first ended)
        $spacingError = $this->engine->checkSpacingRule(
            $this->patient->id,
            $this->pswServiceType->id,
            $tooEarlyStart
        );

        $this->assertNotNull($spacingError, 'Should detect spacing violation for PSW visits');
        $this->assertStringContainsString('120', $spacingError, 'Error should mention 120 min gap requirement');
    }

    /** @test */
    public function psw_visits_can_be_scheduled_with_sufficient_gap()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create first PSW visit: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->pswServiceType->id,
            'assigned_user_id' => $this->staff->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Schedule second PSW visit at 12:00 (120 min after first ended)
        $validStart = $today->copy()->addHours(3); // 12:00 (120 min gap)
        $spacingError = $this->engine->checkSpacingRule(
            $this->patient->id,
            $this->pswServiceType->id,
            $validStart
        );

        $this->assertNull($spacingError, 'Should allow PSW visit with sufficient gap (120 min)');
    }

    /** @test */
    public function nursing_visits_require_60_minute_gap()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create first nursing visit: 09:00-09:45
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->nursingServiceType->id,
            'assigned_user_id' => $this->staff->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addMinutes(45),
            'duration_minutes' => 45,
        ]);

        // Try to schedule second nursing visit at 10:15 (30 min gap, needs 60)
        $tooEarlyStart = $today->copy()->addMinutes(75); // 10:15
        $spacingError = $this->engine->checkSpacingRule(
            $this->patient->id,
            $this->nursingServiceType->id,
            $tooEarlyStart
        );

        $this->assertNotNull($spacingError, 'Should detect spacing violation for nursing visits');
        $this->assertStringContainsString('60', $spacingError, 'Error should mention 60 min gap requirement');
    }

    /** @test */
    public function can_assign_with_travel_enforces_spacing_rules()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create first PSW visit: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->pswServiceType->id,
            'assigned_user_id' => $this->staff->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Try to assign second PSW visit too soon (10:30, only 30 min gap)
        $tooEarlyStart = $today->copy()->addMinutes(90);
        $tooEarlyEnd = $tooEarlyStart->copy()->addHour();

        $result = $this->engine->canAssignWithTravel(
            $this->staff,
            $this->patient,
            $tooEarlyStart,
            $tooEarlyEnd,
            $this->pswServiceType->id
        );

        $this->assertFalse($result->isValid(), 'Should reject PSW visit with insufficient gap');
        $this->assertStringContainsString(
            'Spacing rule violated',
            $result->getErrors()[0],
            'Error message should mention spacing rule violation'
        );
    }

    /** @test */
    public function spacing_rules_only_apply_to_same_service_type()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create PSW visit: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->pswServiceType->id,
            'assigned_user_id' => $this->staff->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Schedule nursing visit immediately after PSW (10:00-10:45)
        // This should be allowed because spacing rules are per service type
        $nursingStart = $today->copy()->addHour(); // 10:00
        $spacingError = $this->engine->checkSpacingRule(
            $this->patient->id,
            $this->nursingServiceType->id,
            $nursingStart
        );

        $this->assertNull(
            $spacingError,
            'Spacing rules should not apply across different service types'
        );
    }

    /** @test */
    public function service_without_spacing_rule_can_be_scheduled_back_to_back()
    {
        // Find a service type without min_gap (e.g., MEAL Delivery)
        $mealService = ServiceType::where('code', 'MEAL')->first();

        $today = Carbon::today()->setTime(12, 0);

        // Create first meal delivery: 12:00-12:30
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $mealService->id,
            'assigned_user_id' => $this->staff->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addMinutes(30),
            'duration_minutes' => 30,
        ]);

        // Try to schedule another meal delivery immediately after (12:30)
        $nextStart = $today->copy()->addMinutes(30);
        $spacingError = $this->engine->checkSpacingRule(
            $this->patient->id,
            $mealService->id,
            $nextStart
        );

        $this->assertNull(
            $spacingError,
            'Services without min_gap should allow back-to-back scheduling'
        );
    }

    /** @test */
    public function spacing_rule_considers_only_completed_or_planned_visits()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create cancelled PSW visit: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->pswServiceType->id,
            'assigned_user_id' => $this->staff->id,
            'status' => ServiceAssignment::STATUS_CANCELLED,
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Schedule new PSW visit at 10:30 (should succeed because previous is cancelled)
        $nextStart = $today->copy()->addMinutes(90);
        $spacingError = $this->engine->checkSpacingRule(
            $this->patient->id,
            $this->pswServiceType->id,
            $nextStart
        );

        $this->assertNull(
            $spacingError,
            'Cancelled visits should not affect spacing rule validation'
        );
    }

    /** @test */
    public function validate_assignment_enforces_spacing_rules()
    {
        $today = Carbon::today()->setTime(9, 0);

        // Create first PSW visit: 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->pswServiceType->id,
            'assigned_user_id' => $this->staff->id,
            'status' => 'planned',
            'scheduled_start' => $today,
            'scheduled_end' => $today->copy()->addHour(),
            'duration_minutes' => 60,
        ]);

        // Create a new PSW assignment too soon (10:30 = 30 min gap, needs 120 min)
        $tooSoonAssignment = new ServiceAssignment([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->pswServiceType->id,
            'assigned_user_id' => $this->staff->id,
            'scheduled_start' => $today->copy()->addMinutes(90),
            'scheduled_end' => $today->copy()->addMinutes(150),
            'duration_minutes' => 60,
        ]);

        // Eager load required relationships
        $tooSoonAssignment->setRelation('assignedUser', $this->staff);
        $tooSoonAssignment->setRelation('serviceType', $this->pswServiceType);

        $validation = $this->engine->validateAssignment($tooSoonAssignment);

        $this->assertFalse($validation->isValid, 'validateAssignment should reject spacing violation');
        $this->assertNotEmpty($validation->errors, 'Should have error messages');
        $this->assertStringContainsString(
            'Spacing rule violated',
            $validation->errors[0],
            'Error should mention spacing rule'
        );
    }
}
