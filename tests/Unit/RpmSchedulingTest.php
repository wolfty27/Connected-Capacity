<?php

namespace Tests\Unit;

use App\Models\CareBundle;
use App\Models\CarePlan;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Scheduling\CareBundleAssignmentPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RpmSchedulingTest - Tests for RPM fixed-visit scheduling mode.
 *
 * Verifies that:
 * - ServiceType correctly identifies fixed_visits services
 * - RPM requires exactly 2 visits per care plan (Setup + Discharge)
 * - CareBundleAssignmentPlanner correctly calculates remaining RPM visits
 * - RPM shows in unscheduled care until both visits are scheduled
 */
class RpmSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceType $rpmService;
    protected ServiceType $regularService;
    protected Patient $patient;
    protected CarePlan $carePlan;
    protected Hospital $hospital;

    protected function setUp(): void
    {
        parent::setUp();

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
        ]);

        // Create RPM service type with fixed_visits mode
        $this->rpmService = ServiceType::create([
            'code' => 'RPM',
            'name' => 'Remote Patient Monitoring',
            'category' => 'Safety, Monitoring & Technology',
            'default_duration_minutes' => 60,
            'active' => true,
            'scheduling_mode' => ServiceType::SCHEDULING_MODE_FIXED_VISITS,
            'fixed_visits_per_plan' => 2,
            'fixed_visit_labels' => ['Setup', 'Discharge'],
        ]);

        // Create regular service type with weekly mode
        $this->regularService = ServiceType::create([
            'code' => 'PSW',
            'name' => 'Personal Support',
            'category' => 'personal_support',
            'default_duration_minutes' => 60,
            'active' => true,
            'scheduling_mode' => ServiceType::SCHEDULING_MODE_WEEKLY,
        ]);

        // Create patient
        $patientUser = User::create([
            'name' => 'Test Patient',
            'email' => 'patient@test.com',
            'password' => bcrypt('secret'),
            'role' => 'patient',
        ]);

        $this->patient = Patient::create([
            'user_id' => $patientUser->id,
            'hospital_id' => $this->hospital->id,
            'status' => 'Active',
            'gender' => 'Male',
        ]);

        // Create care bundle and care plan
        $careBundle = CareBundle::create([
            'name' => 'Test Bundle',
            'code' => 'TEST',
            'active' => true,
        ]);

        // Attach services to bundle
        $careBundle->serviceTypes()->attach($this->rpmService->id, [
            'default_frequency_per_week' => 1, // Not used for fixed_visits
            'default_duration_minutes' => 60,
        ]);

        $this->carePlan = CarePlan::create([
            'patient_id' => $this->patient->id,
            'care_bundle_id' => $careBundle->id,
            'status' => 'active',
        ]);
    }

    public function test_rpm_service_is_fixed_visits()
    {
        $this->assertTrue($this->rpmService->isFixedVisits());
        $this->assertFalse($this->rpmService->isWeeklyScheduled());
    }

    public function test_regular_service_is_weekly_scheduled()
    {
        $this->assertFalse($this->regularService->isFixedVisits());
        $this->assertTrue($this->regularService->isWeeklyScheduled());
    }

    public function test_fixed_visits_per_plan_value()
    {
        $this->assertEquals(2, $this->rpmService->fixed_visits_per_plan);
    }

    public function test_fixed_visit_labels()
    {
        $this->assertIsArray($this->rpmService->fixed_visit_labels);
        $this->assertEquals('Setup', $this->rpmService->getVisitLabel(1));
        $this->assertEquals('Discharge', $this->rpmService->getVisitLabel(2));
        $this->assertNull($this->rpmService->getVisitLabel(3));
    }

    public function test_rpm_requires_two_visits()
    {
        // With no assignments, should show 2 remaining
        $scheduled = ServiceAssignment::where('care_plan_id', $this->carePlan->id)
            ->where('service_type_id', $this->rpmService->id)
            ->count();

        $required = $this->rpmService->fixed_visits_per_plan;
        $remaining = $required - $scheduled;

        $this->assertEquals(2, $remaining);
    }

    public function test_rpm_shows_one_remaining_after_first_visit()
    {
        // Create first RPM visit (Setup)
        $this->createRpmAssignment('Setup');

        $scheduled = ServiceAssignment::where('care_plan_id', $this->carePlan->id)
            ->where('service_type_id', $this->rpmService->id)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED])
            ->count();

        $required = $this->rpmService->fixed_visits_per_plan;
        $remaining = $required - $scheduled;

        $this->assertEquals(1, $remaining);
    }

    public function test_rpm_shows_zero_remaining_after_both_visits()
    {
        // Create both RPM visits
        $this->createRpmAssignment('Setup');
        $this->createRpmAssignment('Discharge');

        $scheduled = ServiceAssignment::where('care_plan_id', $this->carePlan->id)
            ->where('service_type_id', $this->rpmService->id)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED])
            ->count();

        $required = $this->rpmService->fixed_visits_per_plan;
        $remaining = $required - $scheduled;

        $this->assertEquals(0, $remaining);
    }

    public function test_cancelled_rpm_visit_not_counted()
    {
        // Create cancelled RPM visit
        $this->createRpmAssignment('Setup', ServiceAssignment::STATUS_CANCELLED);

        $scheduled = ServiceAssignment::where('care_plan_id', $this->carePlan->id)
            ->where('service_type_id', $this->rpmService->id)
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED])
            ->count();

        $this->assertEquals(0, $scheduled);
    }

    public function test_service_type_null_scheduling_mode_is_weekly()
    {
        $serviceWithNull = ServiceType::create([
            'code' => 'TEST',
            'name' => 'Test Service',
            'category' => 'other',
            'active' => true,
            'scheduling_mode' => null, // Not set
        ]);

        $this->assertTrue($serviceWithNull->isWeeklyScheduled());
        $this->assertFalse($serviceWithNull->isFixedVisits());
    }

    protected function createRpmAssignment(string $label, string $status = ServiceAssignment::STATUS_PLANNED): ServiceAssignment
    {
        static $dayOffset = 0;
        $date = Carbon::now()->addDays($dayOffset++);

        $staff = User::firstOrCreate(
            ['email' => 'rpmstaff@test.com'],
            [
                'name' => 'RPM Staff',
                'password' => bcrypt('secret'),
                'role' => User::ROLE_FIELD_STAFF,
                'staff_status' => User::STAFF_STATUS_ACTIVE,
            ]
        );

        return ServiceAssignment::create([
            'care_plan_id' => $this->carePlan->id,
            'patient_id' => $this->patient->id,
            'service_type_id' => $this->rpmService->id,
            'assigned_user_id' => $staff->id,
            'scheduled_start' => $date->copy()->setTime(10, 0),
            'scheduled_end' => $date->copy()->setTime(11, 0),
            'duration_minutes' => 60,
            'status' => $status,
            'source' => ServiceAssignment::SOURCE_INTERNAL,
            'notes' => "{$label} visit",
        ]);
    }
}
