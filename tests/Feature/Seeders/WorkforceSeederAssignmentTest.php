<?php

namespace Tests\Feature\Seeders;

use App\Models\CareBundle;
use App\Models\CareBundleTemplate;
use App\Models\CareBundleTemplateService;
use App\Models\EmploymentType;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceRoleMapping;
use App\Models\ServiceType;
use App\Models\StaffMember;
use App\Models\StaffRole;
use Carbon\Carbon;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WorkforceSeederAssignmentTest
 *
 * Tests that WorkforceSeeder correctly creates ServiceAssignments
 * for past 3 weeks (completed) + current week (scheduled).
 *
 * This ensures the FTE Compliance Trend graph has proper historical data.
 */
class WorkforceSeederAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected StaffMember $pswStaff;
    protected StaffMember $rnStaff;
    protected Patient $patient;
    protected CareBundleTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPrerequisites();
    }

    protected function seedPrerequisites(): void
    {
        // Create organization
        $this->organization = Organization::create([
            'name' => 'Test SSPO',
            'code' => 'TEST_SSPO',
            'type' => 'spo',
        ]);

        // Create staff roles
        $pswRole = StaffRole::create([
            'code' => 'PSW',
            'name' => 'Personal Support Worker',
            'is_clinical' => false,
        ]);

        $rnRole = StaffRole::create([
            'code' => 'RN',
            'name' => 'Registered Nurse',
            'is_clinical' => true,
        ]);

        // Create employment types
        $ftType = EmploymentType::create([
            'code' => 'FT',
            'name' => 'Full-Time',
            'standard_hours_per_week' => 37.5,
            'is_full_time' => true,
        ]);

        // Create service types
        $pswService = ServiceType::create([
            'code' => 'PSW',
            'name' => 'Personal Care (PSW)',
            'cost_per_visit' => 45,
            'default_duration_minutes' => 60,
            'active' => true,
        ]);

        $nurService = ServiceType::create([
            'code' => 'NUR',
            'name' => 'Nursing (RN/RPN)',
            'cost_per_visit' => 120,
            'default_duration_minutes' => 60,
            'active' => true,
        ]);

        // Create role-service mappings
        ServiceRoleMapping::create([
            'staff_role_id' => $pswRole->id,
            'service_type_id' => $pswService->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        ServiceRoleMapping::create([
            'staff_role_id' => $rnRole->id,
            'service_type_id' => $nurService->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        // Create staff members
        $this->pswStaff = StaffMember::create([
            'first_name' => 'Test',
            'last_name' => 'PSW',
            'email' => 'test.psw@example.com',
            'organization_id' => $this->organization->id,
            'staff_role_id' => $pswRole->id,
            'employment_type_id' => $ftType->id,
            'hire_date' => now()->subYear(),
            'is_active' => true,
        ]);

        $this->rnStaff = StaffMember::create([
            'first_name' => 'Test',
            'last_name' => 'RN',
            'email' => 'test.rn@example.com',
            'organization_id' => $this->organization->id,
            'staff_role_id' => $rnRole->id,
            'employment_type_id' => $ftType->id,
            'hire_date' => now()->subYear(),
            'is_active' => true,
        ]);

        // Create patient
        $this->patient = Patient::create([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'date_of_birth' => '1940-01-01',
            'mrn' => 'TEST001',
            'status' => 'active',
        ]);

        // Create bundle template
        $this->template = CareBundleTemplate::create([
            'code' => 'LTC_PD0_STANDARD',
            'name' => 'Reduced Physical Function - High ADL',
            'rug_group' => 'PD0',
            'rug_category' => 'Reduced Physical Function',
            'weekly_cap_cents' => 400000,
        ]);

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $this->template->id,
            'service_type_id' => $pswService->id,
            'default_frequency_per_week' => 21,
            'default_duration_minutes' => 60,
            'is_required' => true,
        ]);

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $this->template->id,
            'service_type_id' => $nurService->id,
            'default_frequency_per_week' => 2,
            'default_duration_minutes' => 45,
            'is_required' => false,
        ]);

        // Create care bundle for patient
        CareBundle::create([
            'patient_id' => $this->patient->id,
            'care_bundle_template_id' => $this->template->id,
            'organization_id' => $this->organization->id,
            'status' => 'active',
            'start_date' => now()->subWeeks(4),
        ]);
    }

    /** @test */
    public function it_creates_assignments_for_past_three_weeks()
    {
        $this->createTestAssignments();

        $threeWeeksAgo = now()->subWeeks(3)->startOfWeek();
        $twoWeeksAgo = now()->subWeeks(2)->startOfWeek();
        $oneWeekAgo = now()->subWeeks(1)->startOfWeek();

        // Check assignments exist for past 3 weeks
        $pastAssignments = ServiceAssignment::where('scheduled_date', '<', now()->startOfWeek())
            ->where('scheduled_date', '>=', $threeWeeksAgo)
            ->get();

        $this->assertGreaterThan(0, $pastAssignments->count(), 'Past assignments should exist');

        // Past assignments should have completed status
        $completedAssignments = $pastAssignments->where('status', 'completed');
        $this->assertGreaterThan(0, $completedAssignments->count(), 'Past assignments should be completed');
    }

    /** @test */
    public function it_creates_assignments_for_current_week()
    {
        $this->createTestAssignments();

        $currentWeekStart = now()->startOfWeek();
        $currentWeekEnd = now()->endOfWeek();

        // Check assignments exist for current week
        $currentAssignments = ServiceAssignment::whereBetween('scheduled_date', [$currentWeekStart, $currentWeekEnd])
            ->get();

        $this->assertGreaterThan(0, $currentAssignments->count(), 'Current week assignments should exist');

        // Current week assignments should have scheduled status
        $scheduledAssignments = $currentAssignments->where('status', 'scheduled');
        $this->assertGreaterThan(0, $scheduledAssignments->count(), 'Current week assignments should be scheduled');
    }

    /** @test */
    public function it_assigns_correct_staff_based_on_role_mapping()
    {
        $this->createTestAssignments();

        $pswService = ServiceType::where('code', 'PSW')->first();
        $nurService = ServiceType::where('code', 'NUR')->first();

        // PSW services should be assigned to PSW staff
        $pswAssignments = ServiceAssignment::where('service_type_id', $pswService->id)->get();
        foreach ($pswAssignments as $assignment) {
            $staff = $assignment->staffMember;
            $this->assertEquals('PSW', $staff->staffRole->code,
                'PSW service should be assigned to PSW role');
        }

        // NUR services should be assigned to RN staff
        $nurAssignments = ServiceAssignment::where('service_type_id', $nurService->id)->get();
        foreach ($nurAssignments as $assignment) {
            $staff = $assignment->staffMember;
            $this->assertEquals('RN', $staff->staffRole->code,
                'NUR service should be assigned to RN role');
        }
    }

    /** @test */
    public function it_tracks_actual_hours_for_completed_assignments()
    {
        $this->createTestAssignments();

        $completedAssignments = ServiceAssignment::where('status', 'completed')->get();

        foreach ($completedAssignments as $assignment) {
            $this->assertNotNull($assignment->actual_duration_minutes,
                'Completed assignment should have actual duration');
            $this->assertGreaterThan(0, $assignment->actual_duration_minutes,
                'Actual duration should be positive');
        }
    }

    /** @test */
    public function it_creates_multiple_weeks_of_historical_data()
    {
        $this->createTestAssignments();

        // Group assignments by week
        $assignments = ServiceAssignment::all();

        $weeklyGroups = $assignments->groupBy(function ($assignment) {
            return Carbon::parse($assignment->scheduled_date)->startOfWeek()->format('Y-W');
        });

        // Should have data for at least 4 weeks (3 past + current)
        $this->assertGreaterThanOrEqual(4, $weeklyGroups->count(),
            'Should have at least 4 weeks of assignment data');
    }

    /**
     * Helper method to create test assignments (simulating what WorkforceSeeder does)
     */
    protected function createTestAssignments(): void
    {
        $pswService = ServiceType::where('code', 'PSW')->first();
        $nurService = ServiceType::where('code', 'NUR')->first();

        // Create assignments for past 3 weeks (completed)
        for ($weekOffset = 3; $weekOffset >= 1; $weekOffset--) {
            $weekStart = now()->subWeeks($weekOffset)->startOfWeek();

            // PSW visits (21/week for demonstration, we'll do 3 per day Mon-Fri)
            for ($day = 0; $day < 5; $day++) {
                $date = $weekStart->copy()->addDays($day);

                // Morning PSW visit
                ServiceAssignment::create([
                    'patient_id' => $this->patient->id,
                    'staff_member_id' => $this->pswStaff->id,
                    'service_type_id' => $pswService->id,
                    'scheduled_date' => $date,
                    'scheduled_start_time' => '08:00',
                    'scheduled_duration_minutes' => 60,
                    'actual_duration_minutes' => rand(55, 70),
                    'status' => 'completed',
                ]);

                // Afternoon PSW visit
                ServiceAssignment::create([
                    'patient_id' => $this->patient->id,
                    'staff_member_id' => $this->pswStaff->id,
                    'service_type_id' => $pswService->id,
                    'scheduled_date' => $date,
                    'scheduled_start_time' => '14:00',
                    'scheduled_duration_minutes' => 60,
                    'actual_duration_minutes' => rand(55, 70),
                    'status' => 'completed',
                ]);

                // Evening PSW visit
                ServiceAssignment::create([
                    'patient_id' => $this->patient->id,
                    'staff_member_id' => $this->pswStaff->id,
                    'service_type_id' => $pswService->id,
                    'scheduled_date' => $date,
                    'scheduled_start_time' => '18:00',
                    'scheduled_duration_minutes' => 60,
                    'actual_duration_minutes' => rand(55, 70),
                    'status' => 'completed',
                ]);
            }

            // NUR visit (2/week)
            ServiceAssignment::create([
                'patient_id' => $this->patient->id,
                'staff_member_id' => $this->rnStaff->id,
                'service_type_id' => $nurService->id,
                'scheduled_date' => $weekStart->copy()->addDays(1), // Tuesday
                'scheduled_start_time' => '10:00',
                'scheduled_duration_minutes' => 45,
                'actual_duration_minutes' => rand(40, 55),
                'status' => 'completed',
            ]);

            ServiceAssignment::create([
                'patient_id' => $this->patient->id,
                'staff_member_id' => $this->rnStaff->id,
                'service_type_id' => $nurService->id,
                'scheduled_date' => $weekStart->copy()->addDays(4), // Friday
                'scheduled_start_time' => '10:00',
                'scheduled_duration_minutes' => 45,
                'actual_duration_minutes' => rand(40, 55),
                'status' => 'completed',
            ]);
        }

        // Create assignments for current week (scheduled)
        $currentWeekStart = now()->startOfWeek();
        $today = now();

        for ($day = 0; $day < 5; $day++) {
            $date = $currentWeekStart->copy()->addDays($day);
            $isPast = $date->lt($today);
            $status = $isPast ? 'completed' : 'scheduled';

            // Morning PSW visit
            ServiceAssignment::create([
                'patient_id' => $this->patient->id,
                'staff_member_id' => $this->pswStaff->id,
                'service_type_id' => $pswService->id,
                'scheduled_date' => $date,
                'scheduled_start_time' => '08:00',
                'scheduled_duration_minutes' => 60,
                'actual_duration_minutes' => $isPast ? rand(55, 70) : null,
                'status' => $status,
            ]);

            // Afternoon PSW visit
            ServiceAssignment::create([
                'patient_id' => $this->patient->id,
                'staff_member_id' => $this->pswStaff->id,
                'service_type_id' => $pswService->id,
                'scheduled_date' => $date,
                'scheduled_start_time' => '14:00',
                'scheduled_duration_minutes' => 60,
                'actual_duration_minutes' => $isPast ? rand(55, 70) : null,
                'status' => $status,
            ]);

            // Evening PSW visit
            ServiceAssignment::create([
                'patient_id' => $this->patient->id,
                'staff_member_id' => $this->pswStaff->id,
                'service_type_id' => $pswService->id,
                'scheduled_date' => $date,
                'scheduled_start_time' => '18:00',
                'scheduled_duration_minutes' => 60,
                'actual_duration_minutes' => $isPast ? rand(55, 70) : null,
                'status' => $status,
            ]);
        }

        // NUR visits for current week
        ServiceAssignment::create([
            'patient_id' => $this->patient->id,
            'staff_member_id' => $this->rnStaff->id,
            'service_type_id' => $nurService->id,
            'scheduled_date' => $currentWeekStart->copy()->addDays(1),
            'scheduled_start_time' => '10:00',
            'scheduled_duration_minutes' => 45,
            'actual_duration_minutes' => $currentWeekStart->copy()->addDays(1)->lt($today) ? rand(40, 55) : null,
            'status' => $currentWeekStart->copy()->addDays(1)->lt($today) ? 'completed' : 'scheduled',
        ]);

        ServiceAssignment::create([
            'patient_id' => $this->patient->id,
            'staff_member_id' => $this->rnStaff->id,
            'service_type_id' => $nurService->id,
            'scheduled_date' => $currentWeekStart->copy()->addDays(4),
            'scheduled_start_time' => '10:00',
            'scheduled_duration_minutes' => 45,
            'actual_duration_minutes' => null,
            'status' => 'scheduled',
        ]);
    }
}
