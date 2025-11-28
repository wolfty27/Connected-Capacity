<?php

namespace Tests\Unit\Services;

use App\Models\CareBundleTemplate;
use App\Models\CareBundleTemplateService;
use App\Models\Patient;
use App\Models\RUGClassification;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceRate;
use App\Models\ServiceType;
use App\Models\User;
use App\Repositories\ServiceRateRepository;
use App\Services\CostEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for CostEngine
 *
 * Verifies cost calculation, budget validation, and rationale generation.
 */
class CostEngineTest extends TestCase
{
    use RefreshDatabase;

    protected CostEngine $costEngine;
    protected ServiceRateRepository $rateRepository;
    protected Patient $patient;
    protected CareBundleTemplate $template;
    protected ServiceType $pswType;
    protected ServiceType $nursingType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateRepository = new ServiceRateRepository();
        $this->costEngine = new CostEngine($this->rateRepository);

        // Create test user and patient
        $user = User::factory()->create();
        $this->patient = Patient::factory()->create([
            'user_id' => $user->id,
            'status' => 'Active',
        ]);

        // Create service types
        $this->pswType = ServiceType::factory()->create([
            'code' => 'PSW',
            'name' => 'Personal Support Worker',
            'category' => 'Personal Care',
            'active' => true,
        ]);

        $this->nursingType = ServiceType::factory()->create([
            'code' => 'NUR',
            'name' => 'Nursing',
            'category' => 'Clinical',
            'active' => true,
        ]);

        // Create bundle template
        $this->template = CareBundleTemplate::factory()->create([
            'code' => 'PA1',
            'name' => 'PA1 - Low ADL Support',
            'rug_group' => 'PA1',
            'rug_category' => 'REDUCED_PHYSICAL_FUNCTION',
            'tier' => 1,
            'weekly_cap_cents' => 500000, // $5,000
            'is_active' => true,
            'is_current_version' => true,
        ]);

        // Create template services
        CareBundleTemplateService::create([
            'care_bundle_template_id' => $this->template->id,
            'service_type_id' => $this->pswType->id,
            'default_frequency_per_week' => 7,
            'default_duration_minutes' => 60,
            'is_required' => true,
        ]);

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $this->template->id,
            'service_type_id' => $this->nursingType->id,
            'default_frequency_per_week' => 1,
            'default_duration_minutes' => 60,
            'is_required' => true,
        ]);

        // Create system default rates
        ServiceRate::create([
            'service_type_id' => $this->pswType->id,
            'organization_id' => null,
            'unit_type' => 'hour',
            'rate_cents' => 3500, // $35/hour
            'effective_from' => Carbon::today()->subMonth(),
        ]);

        ServiceRate::create([
            'service_type_id' => $this->nursingType->id,
            'organization_id' => null,
            'unit_type' => 'visit',
            'rate_cents' => 11000, // $110/visit
            'effective_from' => Carbon::today()->subMonth(),
        ]);
    }

    /**
     * Test evaluating a template calculates correct weekly cost.
     */
    public function test_evaluate_template_calculates_weekly_cost(): void
    {
        $evaluation = $this->costEngine->evaluateTemplate($this->template, $this->patient);

        // PSW: 7 visits × 1 hour × $35 = $245 = 24500 cents
        // NUR: 1 visit × $110 = $110 = 11000 cents
        // Total: $355 = 35500 cents
        $this->assertEquals(35500, $evaluation->totalWeeklyCostCents);
        $this->assertTrue($evaluation->isWithinCap);
        $this->assertEquals('OK', $evaluation->budgetStatus);
    }

    /**
     * Test budget status when within cap.
     */
    public function test_budget_status_ok_when_within_cap(): void
    {
        $evaluation = $this->costEngine->evaluateTemplate($this->template, $this->patient);

        $this->assertEquals('OK', $evaluation->budgetStatus);
        $this->assertTrue($evaluation->isWithinCap);
    }

    /**
     * Test budget status warning when near cap.
     */
    public function test_budget_status_warning_when_near_cap(): void
    {
        // Create high-cost template
        $template = CareBundleTemplate::factory()->create([
            'code' => 'SE3',
            'rug_group' => 'SE3',
            'tier' => 4,
            'weekly_cap_cents' => 500000,
            'is_active' => true,
            'is_current_version' => true,
        ]);

        // Add enough services to exceed cap but within warning
        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $this->pswType->id,
            'default_frequency_per_week' => 140, // 140 hours × $35 = $4,900
            'default_duration_minutes' => 60,
            'is_required' => true,
        ]);

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $this->nursingType->id,
            'default_frequency_per_week' => 2, // 2 × $110 = $220
            'default_duration_minutes' => 60,
            'is_required' => true,
        ]);

        // Total: $5,120 - within 10% over cap
        $evaluation = $this->costEngine->evaluateTemplate($template, $this->patient);

        $this->assertEquals('WARNING', $evaluation->budgetStatus);
        $this->assertFalse($evaluation->isWithinCap);
    }

    /**
     * Test budget status over_cap when significantly over.
     */
    public function test_budget_status_over_cap(): void
    {
        // Create very high-cost template
        $template = CareBundleTemplate::factory()->create([
            'code' => 'TEST_OVER',
            'weekly_cap_cents' => 500000,
            'is_active' => true,
            'is_current_version' => true,
        ]);

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $this->pswType->id,
            'default_frequency_per_week' => 200, // 200 hours × $35 = $7,000
            'default_duration_minutes' => 60,
            'is_required' => true,
        ]);

        $evaluation = $this->costEngine->evaluateTemplate($template, $this->patient);

        $this->assertEquals('OVER_CAP', $evaluation->budgetStatus);
        $this->assertFalse($evaluation->isWithinCap);
    }

    /**
     * Test rationale includes tier information.
     */
    public function test_rationale_includes_tier_info(): void
    {
        $evaluation = $this->costEngine->evaluateTemplate($this->template, $this->patient);

        $this->assertArrayHasKey('tier', $evaluation->rationale);
        $this->assertArrayHasKey('tier_label', $evaluation->rationale);
        $this->assertArrayHasKey('rug_group', $evaluation->rationale);
        $this->assertArrayHasKey('budget_utilization_percent', $evaluation->rationale);
    }

    /**
     * Test organization-specific rates are used when available.
     */
    public function test_uses_org_specific_rates_when_available(): void
    {
        $org = ServiceProviderOrganization::factory()->create();

        // Create org-specific rate (higher than default)
        ServiceRate::create([
            'service_type_id' => $this->pswType->id,
            'organization_id' => $org->id,
            'unit_type' => 'hour',
            'rate_cents' => 4000, // $40/hour vs $35 default
            'effective_from' => Carbon::today()->subWeek(),
        ]);

        $evaluation = $this->costEngine->evaluateTemplate(
            $this->template,
            $this->patient,
            $org->id
        );

        // PSW should now be: 7 × $40 = $280 = 28000 cents
        // NUR: 1 × $110 = 11000 cents
        // Total: 39000 cents
        $this->assertEquals(39000, $evaluation->totalWeeklyCostCents);
    }

    /**
     * Test services array contains detailed breakdown.
     */
    public function test_services_array_contains_breakdown(): void
    {
        $evaluation = $this->costEngine->evaluateTemplate($this->template, $this->patient);

        $this->assertCount(2, $evaluation->services);

        $pswService = collect($evaluation->services)
            ->firstWhere('service_type_code', 'PSW');

        $this->assertNotNull($pswService);
        $this->assertEquals(3500, $pswService['rate_cents']);
        $this->assertEquals('hour', $pswService['unit_type']);
        $this->assertEquals(7, $pswService['frequency_per_week']);
        $this->assertEquals(24500, $pswService['weekly_cost_cents']);
    }

    /**
     * Test validateBundleConfiguration returns valid for under-cap config.
     */
    public function test_validate_bundle_configuration_valid(): void
    {
        $services = [
            [
                'service_type_id' => $this->pswType->id,
                'frequency_per_week' => 5,
                'duration_minutes' => 60,
            ],
        ];

        $result = $this->costEngine->validateBundleConfiguration($services);

        $this->assertTrue($result->isValid);
        $this->assertEquals('OK', $result->budgetStatus);
    }

    /**
     * Test validateBundleConfiguration returns invalid for over-cap config.
     */
    public function test_validate_bundle_configuration_invalid(): void
    {
        $services = [
            [
                'service_type_id' => $this->pswType->id,
                'frequency_per_week' => 200,
                'duration_minutes' => 60,
            ],
        ];

        $result = $this->costEngine->validateBundleConfiguration($services);

        $this->assertFalse($result->isValid);
        $this->assertEquals('OVER_CAP', $result->budgetStatus);
    }

    /**
     * Test toArray method on BundleEvaluation.
     */
    public function test_evaluation_to_array(): void
    {
        $evaluation = $this->costEngine->evaluateTemplate($this->template, $this->patient);
        $array = $evaluation->toArray();

        $this->assertArrayHasKey('template_id', $array);
        $this->assertArrayHasKey('total_weekly_cost_cents', $array);
        $this->assertArrayHasKey('total_weekly_cost_dollars', $array);
        $this->assertArrayHasKey('is_within_cap', $array);
        $this->assertArrayHasKey('budget_status', $array);
        $this->assertArrayHasKey('services', $array);
        $this->assertArrayHasKey('rationale', $array);

        $this->assertEquals(355, $array['total_weekly_cost_dollars']);
    }
}
