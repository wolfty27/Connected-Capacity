<?php

namespace Tests\Feature;

use App\DTOs\RequiredAssignmentDTO;
use App\DTOs\UnscheduledServiceDTO;
use App\Models\CareBundleService;
use App\Models\CareBundleTemplate;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Services\Scheduling\CareBundleAssignmentPlanner;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Tests for bundles.unscheduled_care_correctness feature.
 *
 * Acceptance criteria:
 * - CareBundleAssignmentPlanner computes required/scheduled/remaining units.
 * - Unscheduled Care panel shows patients with remaining units > 0.
 * - RPM handled per fixed_visit logic.
 */
class UnscheduledCareTest extends TestCase
{
    private CareBundleAssignmentPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new CareBundleAssignmentPlanner();
    }

    /**
     * Test: UnscheduledServiceDTO calculates remaining correctly
     */
    public function test_unscheduled_service_dto_calculates_remaining(): void
    {
        $dto = new UnscheduledServiceDTO(
            serviceTypeId: 1,
            serviceTypeName: 'PSW',
            category: 'personal_care',
            color: '#10b981',
            required: 21, // 21 hours/week required
            scheduled: 14, // 14 hours scheduled
            unitType: 'hours'
        );

        $this->assertEquals(7, $dto->getRemaining(), 'Remaining should be 21 - 14 = 7');
        $this->assertTrue($dto->hasUnscheduledNeeds());
        $this->assertEqualsWithDelta(66.67, $dto->getCompletionPercentage(), 0.1);
    }

    /**
     * Test: DTO with all care scheduled shows no needs
     */
    public function test_dto_with_all_care_scheduled_shows_no_needs(): void
    {
        $dto = new UnscheduledServiceDTO(
            serviceTypeId: 1,
            serviceTypeName: 'PSW',
            category: 'personal_care',
            color: '#10b981',
            required: 21,
            scheduled: 21, // Fully scheduled
            unitType: 'hours'
        );

        $this->assertEquals(0, $dto->getRemaining());
        $this->assertFalse($dto->hasUnscheduledNeeds());
        $this->assertEquals(100, $dto->getCompletionPercentage());
    }

    /**
     * Test: DTO remaining cannot be negative (over-scheduled is OK)
     */
    public function test_dto_remaining_cannot_be_negative(): void
    {
        $dto = new UnscheduledServiceDTO(
            serviceTypeId: 1,
            serviceTypeName: 'PSW',
            category: 'personal_care',
            color: '#10b981',
            required: 21,
            scheduled: 25, // More than required
            unitType: 'hours'
        );

        $this->assertEquals(0, $dto->getRemaining(), 'Remaining should not go negative');
        $this->assertFalse($dto->hasUnscheduledNeeds());
    }

    /**
     * Test: RequiredAssignmentDTO aggregates multiple services
     */
    public function test_required_assignment_dto_aggregates_services(): void
    {
        $services = [
            new UnscheduledServiceDTO(1, 'PSW', 'personal_care', '#10b981', 21, 14, 'hours'),
            new UnscheduledServiceDTO(2, 'PT', 'therapy', '#6366f1', 3, 2, 'hours'),
            new UnscheduledServiceDTO(3, 'RPM', 'monitoring', '#0ea5e9', 2, 1, 'visits'),
        ];

        $dto = new RequiredAssignmentDTO(
            patientId: 1,
            patientName: 'Test Patient',
            rugCategory: 'RB0',
            riskFlags: ['high_fall_risk'],
            services: $services,
            carePlanId: 100,
            careBundleTemplateId: 1
        );

        $this->assertTrue($dto->hasUnscheduledNeeds());
        $this->assertEquals(8, $dto->getTotalRemainingHours()); // 7 + 1
        $this->assertEquals(1, $dto->getTotalRemainingVisits());
        $this->assertCount(3, $dto->getServicesWithNeeds());
    }

    /**
     * Test: RPM uses fixed visits logic (2 visits per care plan)
     */
    public function test_rpm_uses_fixed_visits_logic(): void
    {
        // RPM service type configuration
        $rpmType = new ServiceType([
            'id' => 5,
            'code' => 'RPM',
            'name' => 'Remote Patient Monitoring',
            'scheduling_mode' => ServiceType::SCHEDULING_MODE_FIXED_VISITS,
            'fixed_visits_per_plan' => 2,
            'fixed_visit_labels' => ['Setup', 'Discharge'],
        ]);

        $this->assertTrue($rpmType->isFixedVisits());
        $this->assertEquals(2, $rpmType->fixed_visits_per_plan);
        $this->assertEquals('Setup', $rpmType->getVisitLabel(1));
        $this->assertEquals('Discharge', $rpmType->getVisitLabel(2));
    }

    /**
     * Test: RPM shows remaining visits until both are scheduled
     */
    public function test_rpm_shows_remaining_until_both_scheduled(): void
    {
        // Only Setup visit scheduled
        $dto = new UnscheduledServiceDTO(
            serviceTypeId: 5,
            serviceTypeName: 'RPM',
            category: 'monitoring',
            color: '#0ea5e9',
            required: 2, // 2 visits required
            scheduled: 1, // 1 visit (Setup) scheduled
            unitType: 'visits'
        );

        $this->assertEquals(1, $dto->getRemaining());
        $this->assertTrue($dto->hasUnscheduledNeeds());

        // Both visits scheduled
        $dtoComplete = new UnscheduledServiceDTO(
            serviceTypeId: 5,
            serviceTypeName: 'RPM',
            category: 'monitoring',
            color: '#0ea5e9',
            required: 2,
            scheduled: 2, // Both Setup and Discharge scheduled
            unitType: 'visits'
        );

        $this->assertEquals(0, $dtoComplete->getRemaining());
        $this->assertFalse($dtoComplete->hasUnscheduledNeeds());
    }

    /**
     * Test: Priority level based on risk flags
     */
    public function test_priority_level_based_on_risk_flags(): void
    {
        // High priority: has high-risk flags
        $highPriority = new RequiredAssignmentDTO(
            patientId: 1,
            patientName: 'High Risk Patient',
            rugCategory: 'RB0',
            riskFlags: ['high_fall_risk', 'clinical_instability'],
            services: [],
            carePlanId: 1,
            careBundleTemplateId: 1
        );

        $this->assertEquals(1, $highPriority->getPriorityLevel());

        // Low priority: no risk flags, low remaining hours
        $lowPriority = new RequiredAssignmentDTO(
            patientId: 2,
            patientName: 'Low Risk Patient',
            rugCategory: 'PA1',
            riskFlags: [],
            services: [
                new UnscheduledServiceDTO(1, 'PSW', 'personal_care', '#10b981', 10, 8, 'hours'),
            ],
            carePlanId: 2,
            careBundleTemplateId: 2
        );

        $this->assertEquals(3, $lowPriority->getPriorityLevel());
    }

    /**
     * Test: Medium priority for significant unscheduled care
     */
    public function test_medium_priority_for_significant_unscheduled_care(): void
    {
        $mediumPriority = new RequiredAssignmentDTO(
            patientId: 3,
            patientName: 'Medium Priority Patient',
            rugCategory: 'CC0',
            riskFlags: [], // No high-risk flags
            services: [
                new UnscheduledServiceDTO(1, 'PSW', 'personal_care', '#10b981', 21, 10, 'hours'),
            ],
            carePlanId: 3,
            careBundleTemplateId: 3
        );

        // 11 hours remaining >= 10 threshold
        $this->assertEquals(11, $mediumPriority->getTotalRemainingHours());
        $this->assertEquals(2, $mediumPriority->getPriorityLevel());
    }

    /**
     * Test: DTO toArray includes all required fields
     */
    public function test_dto_to_array_includes_required_fields(): void
    {
        $service = new UnscheduledServiceDTO(
            serviceTypeId: 1,
            serviceTypeName: 'PSW',
            category: 'personal_care',
            color: '#10b981',
            required: 21,
            scheduled: 14,
            unitType: 'hours'
        );

        $array = $service->toArray();

        $this->assertArrayHasKey('service_type_id', $array);
        $this->assertArrayHasKey('service_type_name', $array);
        $this->assertArrayHasKey('required', $array);
        $this->assertArrayHasKey('scheduled', $array);
        $this->assertArrayHasKey('remaining', $array);
        $this->assertArrayHasKey('unit_type', $array);
        $this->assertArrayHasKey('completion_percentage', $array);
    }

    /**
     * Test: RequiredAssignmentDTO toArray structure
     */
    public function test_required_assignment_dto_to_array_structure(): void
    {
        $dto = new RequiredAssignmentDTO(
            patientId: 1,
            patientName: 'Test Patient',
            rugCategory: 'RB0',
            riskFlags: ['high_fall_risk'],
            services: [
                new UnscheduledServiceDTO(1, 'PSW', 'personal_care', '#10b981', 21, 14, 'hours'),
            ],
            carePlanId: 100,
            careBundleTemplateId: 1
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('patient_id', $array);
        $this->assertArrayHasKey('patient_name', $array);
        $this->assertArrayHasKey('rug_category', $array);
        $this->assertArrayHasKey('risk_flags', $array);
        $this->assertArrayHasKey('services', $array);
        $this->assertArrayHasKey('total_remaining_hours', $array);
        $this->assertArrayHasKey('total_remaining_visits', $array);
        $this->assertArrayHasKey('priority_level', $array);
        $this->assertArrayHasKey('has_unscheduled_needs', $array);
    }

    /**
     * Test: Patient with PSW 21h/week but only 14h scheduled appears in Unscheduled Care
     */
    public function test_patient_with_partial_scheduling_appears_in_unscheduled(): void
    {
        $services = [
            new UnscheduledServiceDTO(1, 'PSW', 'personal_care', '#10b981', 21, 14, 'hours'),
        ];

        $dto = new RequiredAssignmentDTO(
            patientId: 1,
            patientName: 'Test Patient',
            rugCategory: 'RB0',
            riskFlags: [],
            services: $services,
            carePlanId: 1,
            careBundleTemplateId: 1
        );

        $this->assertTrue(
            $dto->hasUnscheduledNeeds(),
            'Patient with 14/21 hours should show as having unscheduled needs'
        );
        $this->assertEquals(7, $dto->getTotalRemainingHours());
    }

    /**
     * Test: Patient with all care scheduled does not appear in Unscheduled Care
     */
    public function test_patient_with_full_scheduling_does_not_appear(): void
    {
        $services = [
            new UnscheduledServiceDTO(1, 'PSW', 'personal_care', '#10b981', 21, 21, 'hours'),
            new UnscheduledServiceDTO(2, 'PT', 'therapy', '#6366f1', 3, 3, 'hours'),
        ];

        $dto = new RequiredAssignmentDTO(
            patientId: 1,
            patientName: 'Fully Scheduled Patient',
            rugCategory: 'PA1',
            riskFlags: [],
            services: $services,
            carePlanId: 1,
            careBundleTemplateId: 1
        );

        $this->assertFalse(
            $dto->hasUnscheduledNeeds(),
            'Patient with all care scheduled should not show unscheduled needs'
        );
    }
}
