<?php

namespace Tests\Unit\Services;

use App\Models\EmploymentType;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\CareOps\FteComplianceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for FteComplianceService
 *
 * Verifies FTE ratio calculation per RFP Q&A:
 * FTE ratio = [Number of active full-time direct staff ÷ Number of active direct staff] × 100%
 *
 * Key requirements tested:
 * - Headcount-based ratio (not hours-based)
 * - SSPO staff excluded from ratio calculation
 * - 80% compliance target
 * - HHR complement breakdown by role
 * - Staff satisfaction metrics
 */
class FteComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FteComplianceService $service;
    protected ServiceProviderOrganization $spo;
    protected EmploymentType $ftType;
    protected EmploymentType $ptType;
    protected EmploymentType $casualType;
    protected EmploymentType $sspoType;
    protected StaffRole $rnRole;
    protected StaffRole $pswRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FteComplianceService();

        // Create SPO organization
        $this->spo = ServiceProviderOrganization::factory()->create([
            'name' => 'Test SPO',
            'type' => 'se_health',
        ]);

        // Create employment types
        $this->ftType = EmploymentType::create([
            'code' => 'FT',
            'name' => 'Full-Time',
            'standard_hours_per_week' => 40,
            'is_direct_staff' => true,
            'is_full_time' => true,
            'counts_for_capacity' => true,
            'badge_color' => 'green',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->ptType = EmploymentType::create([
            'code' => 'PT',
            'name' => 'Part-Time',
            'standard_hours_per_week' => 24,
            'is_direct_staff' => true,
            'is_full_time' => false,
            'counts_for_capacity' => true,
            'badge_color' => 'blue',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->casualType = EmploymentType::create([
            'code' => 'CASUAL',
            'name' => 'Casual',
            'standard_hours_per_week' => 16,
            'is_direct_staff' => true,
            'is_full_time' => false,
            'counts_for_capacity' => true,
            'badge_color' => 'orange',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $this->sspoType = EmploymentType::create([
            'code' => 'SSPO',
            'name' => 'SSPO Contract',
            'standard_hours_per_week' => null,
            'is_direct_staff' => false,  // Excluded from FTE ratio
            'is_full_time' => false,
            'counts_for_capacity' => true,
            'badge_color' => 'purple',
            'sort_order' => 4,
            'is_active' => true,
        ]);

        // Create staff roles
        $this->rnRole = StaffRole::create([
            'code' => 'RN',
            'name' => 'Registered Nurse',
            'category' => 'nursing',
            'is_regulated' => true,
            'counts_for_fte' => true,
            'badge_color' => 'blue',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->pswRole = StaffRole::create([
            'code' => 'PSW',
            'name' => 'Personal Support Worker',
            'category' => 'personal_support',
            'is_regulated' => false,
            'counts_for_fte' => true,
            'badge_color' => 'green',
            'sort_order' => 2,
            'is_active' => true,
        ]);
    }

    /**
     * Test FTE ratio calculation with 80% full-time staff (compliant).
     */
    public function test_fte_ratio_80_percent_is_compliant(): void
    {
        // Create 8 FT + 2 PT = 80% FTE ratio
        $this->createStaff(8, $this->ftType);
        $this->createStaff(2, $this->ptType);

        $snapshot = $this->service->calculateSnapshot($this->spo->id);

        $this->assertEquals(80.0, $snapshot['fte_ratio']);
        $this->assertTrue($snapshot['is_compliant']);
        $this->assertEquals(FteComplianceService::BAND_GREEN, $snapshot['band']);
    }

    /**
     * Test FTE ratio calculation with 75% full-time staff (at risk).
     */
    public function test_fte_ratio_75_percent_is_at_risk(): void
    {
        // Create 3 FT + 1 PT = 75% FTE ratio
        $this->createStaff(3, $this->ftType);
        $this->createStaff(1, $this->ptType);

        $snapshot = $this->service->calculateSnapshot($this->spo->id);

        $this->assertEquals(75.0, $snapshot['fte_ratio']);
        $this->assertFalse($snapshot['is_compliant']);
        $this->assertEquals(FteComplianceService::BAND_YELLOW, $snapshot['band']);
    }

    /**
     * Test FTE ratio calculation with 60% full-time staff (non-compliant).
     */
    public function test_fte_ratio_60_percent_is_non_compliant(): void
    {
        // Create 3 FT + 2 PT = 60% FTE ratio
        $this->createStaff(3, $this->ftType);
        $this->createStaff(2, $this->ptType);

        $snapshot = $this->service->calculateSnapshot($this->spo->id);

        $this->assertEquals(60.0, $snapshot['fte_ratio']);
        $this->assertFalse($snapshot['is_compliant']);
        $this->assertEquals(FteComplianceService::BAND_RED, $snapshot['band']);
    }

    /**
     * Test SSPO staff are excluded from FTE ratio calculation.
     *
     * Per RFP Q&A: SSPO staff do NOT count in FTE ratio (neither numerator nor denominator).
     */
    public function test_sspo_staff_excluded_from_fte_ratio(): void
    {
        // Create 4 FT + 1 PT (direct staff) + 5 SSPO
        // Direct staff: 4/5 = 80% (should be compliant)
        // If SSPO were included: 4/10 = 40% (would be non-compliant)
        $this->createStaff(4, $this->ftType);
        $this->createStaff(1, $this->ptType);
        $this->createStaff(5, $this->sspoType);

        $snapshot = $this->service->calculateSnapshot($this->spo->id);

        // SSPO should be excluded, so ratio should be 80%
        $this->assertEquals(80.0, $snapshot['fte_ratio']);
        $this->assertTrue($snapshot['is_compliant']);
        $this->assertEquals(5, $snapshot['total_staff']); // Only direct staff counted
    }

    /**
     * Test casual staff count in denominator but not numerator.
     */
    public function test_casual_staff_in_denominator_only(): void
    {
        // Create 4 FT + 1 Casual = 80% FTE ratio
        $this->createStaff(4, $this->ftType);
        $this->createStaff(1, $this->casualType);

        $snapshot = $this->service->calculateSnapshot($this->spo->id);

        $this->assertEquals(80.0, $snapshot['fte_ratio']);
        $this->assertEquals(5, $snapshot['total_staff']);
        $this->assertEquals(4, $snapshot['full_time_staff']);
        $this->assertEquals(1, $snapshot['casual_staff']);
    }

    /**
     * Test HHR complement breakdown by role.
     */
    public function test_hhr_complement_breakdown_by_role(): void
    {
        // Create staff with roles
        $this->createStaffWithRole(3, $this->ftType, $this->rnRole);
        $this->createStaffWithRole(2, $this->ftType, $this->pswRole);
        $this->createStaffWithRole(1, $this->ptType, $this->rnRole);

        $hhr = $this->service->getHhrComplement($this->spo->id);

        // Check totals
        $this->assertEquals(6, $hhr['totals']['grand_total']);
        $this->assertEquals(6, $hhr['totals']['direct_staff_total']);
        $this->assertEquals(5, $hhr['totals']['full_time_total']);

        // Check breakdown by role
        $this->assertEquals(4, $hhr['totals']['by_role']['RN'] ?? 0);
        $this->assertEquals(2, $hhr['totals']['by_role']['PSW'] ?? 0);

        // Check breakdown by employment type
        $this->assertEquals(5, $hhr['totals']['by_employment_type']['FT'] ?? 0);
        $this->assertEquals(1, $hhr['totals']['by_employment_type']['PT'] ?? 0);
    }

    /**
     * Test staff satisfaction metrics calculation.
     */
    public function test_staff_satisfaction_metrics(): void
    {
        // Create staff with satisfaction scores
        $this->createStaffWithSatisfaction(5, $this->ftType, 90); // Very satisfied
        $this->createStaffWithSatisfaction(3, $this->ftType, 75); // Satisfied
        $this->createStaffWithSatisfaction(2, $this->ptType, 60); // Neutral

        $satisfaction = $this->service->getStaffSatisfactionMetrics($this->spo->id);

        $this->assertEquals(10, $satisfaction['total_responses']);
        $this->assertNotNull($satisfaction['average_satisfaction']);

        // 8 staff with satisfaction >= 80 = 80% satisfaction rate
        $this->assertEquals(80.0, $satisfaction['satisfaction_rate']);
        $this->assertFalse($satisfaction['meets_target']); // Target is 95%
    }

    /**
     * Test compliance gap calculation.
     */
    public function test_compliance_gap_calculation(): void
    {
        // Create 3 FT + 2 PT = 60% FTE ratio (need to reach 80%)
        $this->createStaff(3, $this->ftType);
        $this->createStaff(2, $this->ptType);

        $gap = $this->service->calculateComplianceGap($this->spo->id);

        $this->assertFalse($gap['is_compliant']);
        $this->assertEquals(60.0, $gap['current_ratio']);
        $this->assertEquals(80.0, $gap['target_ratio']);
        $this->assertEquals(20.0, $gap['gap']);
        $this->assertGreaterThan(0, $gap['full_time_needed']);
    }

    /**
     * Test hire projection for full-time hire.
     */
    public function test_hire_projection_full_time(): void
    {
        // Create 3 FT + 2 PT = 60% FTE ratio
        $this->createStaff(3, $this->ftType);
        $this->createStaff(2, $this->ptType);

        $projection = $this->service->calculateProjection('full_time', $this->spo->id);

        // Adding FT: 4/6 = 66.7%
        $this->assertGreaterThan(60.0, $projection['projected']['ratio']);
        $this->assertGreaterThan(0, $projection['impact']);
    }

    /**
     * Test hire projection for part-time hire.
     */
    public function test_hire_projection_part_time(): void
    {
        // Create 4 FT + 1 PT = 80% FTE ratio
        $this->createStaff(4, $this->ftType);
        $this->createStaff(1, $this->ptType);

        $projection = $this->service->calculateProjection('part_time', $this->spo->id);

        // Adding PT: 4/6 = 66.7%
        $this->assertLessThan(80.0, $projection['projected']['ratio']);
        $this->assertLessThan(0, $projection['impact']);
    }

    /**
     * Test hire projection for SSPO hire has no impact on FTE ratio.
     *
     * Per RFP Q&A: SSPO staff are excluded from FTE ratio calculation.
     */
    public function test_hire_projection_sspo_has_no_impact(): void
    {
        // Create 4 FT + 1 PT = 80% FTE ratio
        $this->createStaff(4, $this->ftType);
        $this->createStaff(1, $this->ptType);

        $projection = $this->service->calculateProjection('SSPO', $this->spo->id);

        // Adding SSPO should not change the ratio
        $this->assertEquals(80.0, $projection['current']['ratio']);
        $this->assertEquals(80.0, $projection['projected']['ratio']);
        $this->assertEquals(0, $projection['impact']);
        $this->assertStringContainsString('SSPO', $projection['recommendation']);
    }

    /**
     * Test capacity calculation uses EmploymentType metadata.
     */
    public function test_capacity_uses_employment_type_hours(): void
    {
        // Create 2 FT (40h each) + 1 PT (24h) + 1 Casual (16h)
        $this->createStaff(2, $this->ftType);
        $this->createStaff(1, $this->ptType);
        $this->createStaff(1, $this->casualType);

        $snapshot = $this->service->calculateSnapshot($this->spo->id);

        // Expected capacity: 2*40 + 1*24 + 1*16 = 120h
        $this->assertEquals(120.0, $snapshot['total_capacity_hours']);
    }

    /**
     * Test staff metrics returns correct breakdown.
     */
    public function test_staff_metrics_returns_correct_breakdown(): void
    {
        // Create mixed staff: 3 FT, 2 PT, 1 Casual, 2 SSPO
        $this->createStaff(3, $this->ftType);
        $this->createStaff(2, $this->ptType);
        $this->createStaff(1, $this->casualType);
        $this->createStaff(2, $this->sspoType);

        $metrics = $this->service->getStaffMetrics($this->spo->id);

        // Direct staff only: 3 + 2 + 1 = 6
        $this->assertEquals(6, $metrics['total_staff']);
        $this->assertEquals(3, $metrics['full_time_staff']);
        $this->assertEquals(2, $metrics['part_time_staff']);
        $this->assertEquals(1, $metrics['casual_staff']);
        $this->assertEquals(2, $metrics['sspo_staff']);
    }

    /**
     * Test workforce summary combines all metrics.
     */
    public function test_workforce_summary_combines_metrics(): void
    {
        $this->createStaffWithRoleAndSatisfaction(4, $this->ftType, $this->rnRole, 85);
        $this->createStaffWithRoleAndSatisfaction(1, $this->ptType, $this->pswRole, 90);

        $summary = $this->service->getWorkforceSummary($this->spo->id);

        // Check FTE compliance
        $this->assertArrayHasKey('fte_compliance', $summary);
        $this->assertEquals(80.0, $summary['fte_compliance']['ratio']);

        // Check headcount
        $this->assertArrayHasKey('headcount', $summary);
        $this->assertEquals(5, $summary['headcount']['total']);

        // Check HHR complement
        $this->assertArrayHasKey('hhr_complement', $summary);

        // Check satisfaction
        $this->assertArrayHasKey('satisfaction', $summary);
    }

    /**
     * Test empty organization returns empty snapshot.
     */
    public function test_empty_organization_returns_empty_snapshot(): void
    {
        $snapshot = $this->service->calculateSnapshot($this->spo->id);

        $this->assertEquals(0, $snapshot['total_staff']);
        $this->assertEquals(0, $snapshot['fte_ratio']);
        $this->assertEquals(FteComplianceService::BAND_GREY, $snapshot['band']);
    }

    /**
     * Test compliance trend returns weekly data.
     */
    public function test_compliance_trend_returns_weekly_data(): void
    {
        $this->createStaff(4, $this->ftType);
        $this->createStaff(1, $this->ptType);

        $trend = $this->service->getComplianceTrend(8, $this->spo->id);

        $this->assertCount(8, $trend);
        $this->assertArrayHasKey('week_start', $trend[0]);
        $this->assertArrayHasKey('fte_ratio', $trend[0]);
        $this->assertArrayHasKey('band', $trend[0]);
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    protected function createStaff(int $count, EmploymentType $empType): void
    {
        for ($i = 0; $i < $count; $i++) {
            User::factory()->create([
                'organization_id' => $this->spo->id,
                'role' => User::ROLE_FIELD_STAFF,
                'staff_status' => User::STAFF_STATUS_ACTIVE,
                'employment_type' => strtolower($empType->name),
                'employment_type_id' => $empType->id,
                'fte_value' => $empType->is_full_time ? 1.0 : 0.5,
                'max_weekly_hours' => $empType->standard_hours_per_week ?? 40,
            ]);
        }
    }

    protected function createStaffWithRole(int $count, EmploymentType $empType, StaffRole $role): void
    {
        for ($i = 0; $i < $count; $i++) {
            User::factory()->create([
                'organization_id' => $this->spo->id,
                'role' => User::ROLE_FIELD_STAFF,
                'staff_status' => User::STAFF_STATUS_ACTIVE,
                'employment_type' => strtolower($empType->name),
                'employment_type_id' => $empType->id,
                'staff_role_id' => $role->id,
                'organization_role' => $role->code,
                'fte_value' => $empType->is_full_time ? 1.0 : 0.5,
                'max_weekly_hours' => $empType->standard_hours_per_week ?? 40,
            ]);
        }
    }

    protected function createStaffWithSatisfaction(int $count, EmploymentType $empType, int $satisfaction): void
    {
        for ($i = 0; $i < $count; $i++) {
            User::factory()->create([
                'organization_id' => $this->spo->id,
                'role' => User::ROLE_FIELD_STAFF,
                'staff_status' => User::STAFF_STATUS_ACTIVE,
                'employment_type' => strtolower($empType->name),
                'employment_type_id' => $empType->id,
                'fte_value' => $empType->is_full_time ? 1.0 : 0.5,
                'max_weekly_hours' => $empType->standard_hours_per_week ?? 40,
                'job_satisfaction' => $satisfaction,
                'job_satisfaction_recorded_at' => Carbon::now(),
            ]);
        }
    }

    protected function createStaffWithRoleAndSatisfaction(
        int $count,
        EmploymentType $empType,
        StaffRole $role,
        int $satisfaction
    ): void {
        for ($i = 0; $i < $count; $i++) {
            User::factory()->create([
                'organization_id' => $this->spo->id,
                'role' => User::ROLE_FIELD_STAFF,
                'staff_status' => User::STAFF_STATUS_ACTIVE,
                'employment_type' => strtolower($empType->name),
                'employment_type_id' => $empType->id,
                'staff_role_id' => $role->id,
                'organization_role' => $role->code,
                'fte_value' => $empType->is_full_time ? 1.0 : 0.5,
                'max_weekly_hours' => $empType->standard_hours_per_week ?? 40,
                'job_satisfaction' => $satisfaction,
                'job_satisfaction_recorded_at' => Carbon::now(),
            ]);
        }
    }
}
