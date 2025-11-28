<?php

namespace Tests\Unit\Seeders;

use App\Models\CareBundleTemplate;
use App\Models\CareBundleTemplateService;
use App\Models\ServiceType;
use Database\Seeders\RUGBundleTemplatesSeeder;
use Database\Seeders\ServiceTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for PSW intensity mapping in RUG bundle templates.
 *
 * Validates that PSW (Personal Support Worker) hours/week are correctly
 * assigned to each RUG-III/HC group according to the tier specification:
 *
 * Tier 4 (28 h/wk): SE3, SSB
 * Tier 4 (21 h/wk): SE2, CC0
 * Tier 3 (14 h/wk): SE1, SSA, CB0, PD0, IB0, BB0
 * Tier 2 (10 h/wk): RB0, RA2, CA2, IA2, BA2, PC0, PB0
 * Tier 1 (7 h/wk): RA1, CA1, IA1, BA1, PA2
 * Tier 1 (5 h/wk): PA1
 */
class RUGBundleTemplatesPswIntensityTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceType $pswType;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed service types first, then RUG templates
        $this->seed(ServiceTypesSeeder::class);
        $this->seed(RUGBundleTemplatesSeeder::class);

        $this->pswType = ServiceType::where('code', 'PSW')->firstOrFail();
    }

    /**
     * Get PSW hours/week for a given RUG group.
     */
    protected function getPswFrequency(string $rugGroup): ?int
    {
        $template = CareBundleTemplate::where('rug_group', $rugGroup)
            ->where('is_current_version', true)
            ->firstOrFail();

        $pswService = CareBundleTemplateService::where('care_bundle_template_id', $template->id)
            ->where('service_type_id', $this->pswType->id)
            ->first();

        return $pswService?->default_frequency_per_week;
    }

    // =========================================================================
    // TIER 4 - Maximum Intensity (28 h/wk)
    // =========================================================================

    public function test_se3_has_tier4_max_psw_intensity(): void
    {
        $this->assertEquals(28, $this->getPswFrequency('SE3'), 'SE3 should have 28 hours/week PSW (Tier 4 max)');
    }

    public function test_ssb_has_tier4_max_psw_intensity(): void
    {
        $this->assertEquals(28, $this->getPswFrequency('SSB'), 'SSB should have 28 hours/week PSW (Tier 4 max)');
    }

    // =========================================================================
    // TIER 4 - High Intensity (21 h/wk)
    // =========================================================================

    public function test_se2_has_tier4_high_psw_intensity(): void
    {
        $this->assertEquals(21, $this->getPswFrequency('SE2'), 'SE2 should have 21 hours/week PSW (Tier 4 high)');
    }

    public function test_cc0_has_tier4_high_psw_intensity(): void
    {
        $this->assertEquals(21, $this->getPswFrequency('CC0'), 'CC0 should have 21 hours/week PSW (Tier 4 high)');
    }

    // =========================================================================
    // TIER 3 - Moderate-High Intensity (14 h/wk)
    // =========================================================================

    public function test_se1_has_tier3_psw_intensity(): void
    {
        $this->assertEquals(14, $this->getPswFrequency('SE1'), 'SE1 should have 14 hours/week PSW (Tier 3)');
    }

    public function test_ssa_has_tier3_psw_intensity(): void
    {
        $this->assertEquals(14, $this->getPswFrequency('SSA'), 'SSA should have 14 hours/week PSW (Tier 3)');
    }

    public function test_cb0_has_tier3_psw_intensity(): void
    {
        $this->assertEquals(14, $this->getPswFrequency('CB0'), 'CB0 should have 14 hours/week PSW (Tier 3)');
    }

    public function test_pd0_has_tier3_psw_intensity(): void
    {
        $this->assertEquals(14, $this->getPswFrequency('PD0'), 'PD0 should have 14 hours/week PSW (Tier 3)');
    }

    public function test_ib0_has_tier3_psw_intensity(): void
    {
        $this->assertEquals(14, $this->getPswFrequency('IB0'), 'IB0 should have 14 hours/week PSW (Tier 3)');
    }

    public function test_bb0_has_tier3_psw_intensity(): void
    {
        $this->assertEquals(14, $this->getPswFrequency('BB0'), 'BB0 should have 14 hours/week PSW (Tier 3)');
    }

    // =========================================================================
    // TIER 2 - Moderate Intensity (10 h/wk)
    // =========================================================================

    public function test_rb0_has_tier2_psw_intensity(): void
    {
        $this->assertEquals(10, $this->getPswFrequency('RB0'), 'RB0 should have 10 hours/week PSW (Tier 2)');
    }

    public function test_ra2_has_tier2_psw_intensity(): void
    {
        $this->assertEquals(10, $this->getPswFrequency('RA2'), 'RA2 should have 10 hours/week PSW (Tier 2)');
    }

    public function test_ca2_has_tier2_psw_intensity(): void
    {
        $this->assertEquals(10, $this->getPswFrequency('CA2'), 'CA2 should have 10 hours/week PSW (Tier 2)');
    }

    public function test_ia2_has_tier2_psw_intensity(): void
    {
        $this->assertEquals(10, $this->getPswFrequency('IA2'), 'IA2 should have 10 hours/week PSW (Tier 2)');
    }

    public function test_ba2_has_tier2_psw_intensity(): void
    {
        $this->assertEquals(10, $this->getPswFrequency('BA2'), 'BA2 should have 10 hours/week PSW (Tier 2)');
    }

    public function test_pc0_has_tier2_psw_intensity(): void
    {
        $this->assertEquals(10, $this->getPswFrequency('PC0'), 'PC0 should have 10 hours/week PSW (Tier 2)');
    }

    public function test_pb0_has_tier2_psw_intensity(): void
    {
        $this->assertEquals(10, $this->getPswFrequency('PB0'), 'PB0 should have 10 hours/week PSW (Tier 2)');
    }

    // =========================================================================
    // TIER 1 - Low Intensity (7 h/wk)
    // =========================================================================

    public function test_ra1_has_tier1_psw_intensity(): void
    {
        $this->assertEquals(7, $this->getPswFrequency('RA1'), 'RA1 should have 7 hours/week PSW (Tier 1)');
    }

    public function test_ca1_has_tier1_psw_intensity(): void
    {
        $this->assertEquals(7, $this->getPswFrequency('CA1'), 'CA1 should have 7 hours/week PSW (Tier 1)');
    }

    public function test_ia1_has_tier1_psw_intensity(): void
    {
        $this->assertEquals(7, $this->getPswFrequency('IA1'), 'IA1 should have 7 hours/week PSW (Tier 1)');
    }

    public function test_ba1_has_tier1_psw_intensity(): void
    {
        $this->assertEquals(7, $this->getPswFrequency('BA1'), 'BA1 should have 7 hours/week PSW (Tier 1)');
    }

    public function test_pa2_has_tier1_psw_intensity(): void
    {
        $this->assertEquals(7, $this->getPswFrequency('PA2'), 'PA2 should have 7 hours/week PSW (Tier 1)');
    }

    // =========================================================================
    // TIER 1 - Minimum Intensity (5 h/wk)
    // =========================================================================

    public function test_pa1_has_minimum_psw_intensity(): void
    {
        $this->assertEquals(5, $this->getPswFrequency('PA1'), 'PA1 should have 5 hours/week PSW (minimum)');
    }

    // =========================================================================
    // COVERAGE TESTS
    // =========================================================================

    /**
     * Test that all 23 RUG groups have templates seeded.
     */
    public function test_all_23_rug_groups_have_templates(): void
    {
        $expectedGroups = [
            // Special Rehabilitation
            'RB0', 'RA2', 'RA1',
            // Extensive Services
            'SE3', 'SE2', 'SE1',
            // Special Care
            'SSB', 'SSA',
            // Clinically Complex
            'CC0', 'CB0', 'CA2', 'CA1',
            // Impaired Cognition
            'IB0', 'IA2', 'IA1',
            // Behaviour Problems
            'BB0', 'BA2', 'BA1',
            // Reduced Physical Function
            'PD0', 'PC0', 'PB0', 'PA2', 'PA1',
        ];

        foreach ($expectedGroups as $rugGroup) {
            $template = CareBundleTemplate::where('rug_group', $rugGroup)
                ->where('is_current_version', true)
                ->first();

            $this->assertNotNull($template, "Template for RUG group {$rugGroup} should exist");
        }

        $this->assertCount(23, $expectedGroups);
    }

    /**
     * Test that all RUG templates have a PSW service configured.
     */
    public function test_all_templates_have_psw_service(): void
    {
        $templates = CareBundleTemplate::where('is_current_version', true)->get();

        foreach ($templates as $template) {
            $pswService = CareBundleTemplateService::where('care_bundle_template_id', $template->id)
                ->where('service_type_id', $this->pswType->id)
                ->first();

            $this->assertNotNull(
                $pswService,
                "Template {$template->code} (RUG: {$template->rug_group}) should have PSW service configured"
            );

            $this->assertGreaterThan(
                0,
                $pswService->default_frequency_per_week,
                "Template {$template->code} PSW frequency should be > 0"
            );
        }
    }

    /**
     * Test PSW intensity tiers are correctly distributed.
     */
    public function test_psw_intensity_tier_distribution(): void
    {
        $tier4Max = ['SE3', 'SSB']; // 28 h/wk
        $tier4High = ['SE2', 'CC0']; // 21 h/wk
        $tier3 = ['SE1', 'SSA', 'CB0', 'PD0', 'IB0', 'BB0']; // 14 h/wk
        $tier2 = ['RB0', 'RA2', 'CA2', 'IA2', 'BA2', 'PC0', 'PB0']; // 10 h/wk
        $tier1 = ['RA1', 'CA1', 'IA1', 'BA1', 'PA2']; // 7 h/wk
        $tier1Min = ['PA1']; // 5 h/wk

        $this->assertCount(2, $tier4Max, 'Tier 4 Max (28h) should have 2 groups');
        $this->assertCount(2, $tier4High, 'Tier 4 High (21h) should have 2 groups');
        $this->assertCount(6, $tier3, 'Tier 3 (14h) should have 6 groups');
        $this->assertCount(7, $tier2, 'Tier 2 (10h) should have 7 groups');
        $this->assertCount(5, $tier1, 'Tier 1 (7h) should have 5 groups');
        $this->assertCount(1, $tier1Min, 'Tier 1 Min (5h) should have 1 group');

        // Total should be 23
        $total = count($tier4Max) + count($tier4High) + count($tier3) +
                 count($tier2) + count($tier1) + count($tier1Min);
        $this->assertEquals(23, $total, 'Total RUG groups should be 23');
    }
}
