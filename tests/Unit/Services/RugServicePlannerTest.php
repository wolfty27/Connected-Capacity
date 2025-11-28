<?php

namespace Tests\Unit\Services;

use App\Models\CareBundleTemplate;
use App\Models\CareBundleTemplateService;
use App\Models\RUGClassification;
use App\Models\RugServiceRecommendation;
use App\Models\ServiceType;
use App\Services\RugServicePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RugServicePlannerTest
 *
 * Tests for the RugServicePlanner domain service.
 * Verifies that services are correctly built from bundle templates
 * and clinically indicated recommendations based on RUG/interRAI criteria.
 *
 * @see docs/CC21_BundleEngine_Architecture.md STEP 6
 */
class RugServicePlannerTest extends TestCase
{
    use RefreshDatabase;

    protected RugServicePlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new RugServicePlanner();

        // Seed basic service types
        $this->seedServiceTypes();
    }

    protected function seedServiceTypes(): void
    {
        $services = [
            ['code' => 'PSW', 'name' => 'Personal Care (PSW)', 'cost_per_visit' => 45, 'default_duration_minutes' => 60],
            ['code' => 'HMK', 'name' => 'Homemaking', 'cost_per_visit' => 40, 'default_duration_minutes' => 60],
            ['code' => 'NUR', 'name' => 'Nursing (RN/RPN)', 'cost_per_visit' => 120, 'default_duration_minutes' => 60],
            ['code' => 'BEH', 'name' => 'Behavioral Supports', 'cost_per_visit' => 100, 'default_duration_minutes' => 60],
            ['code' => 'REC', 'name' => 'Social/Recreational', 'cost_per_visit' => 50, 'default_duration_minutes' => 120],
            ['code' => 'SW', 'name' => 'Social Work (SW)', 'cost_per_visit' => 135, 'default_duration_minutes' => 60],
            ['code' => 'OT', 'name' => 'Occupational Therapy', 'cost_per_visit' => 150, 'default_duration_minutes' => 45],
        ];

        foreach ($services as $service) {
            ServiceType::create(array_merge($service, ['active' => true]));
        }
    }

    /** @test */
    public function it_builds_services_from_template_for_pd0_classification()
    {
        // Create PD0 template (Reduced Physical Function - High ADL)
        $template = CareBundleTemplate::create([
            'code' => 'LTC_PD0_STANDARD',
            'name' => 'Reduced Physical Function - High ADL',
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'weekly_cap_cents' => 400000,
            'priority_weight' => 28,
        ]);

        // Add template services
        $pswType = ServiceType::where('code', 'PSW')->first();
        $nurType = ServiceType::where('code', 'NUR')->first();

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $pswType->id,
            'default_frequency_per_week' => 21,
            'default_duration_minutes' => 60,
            'is_required' => true,
        ]);

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $nurType->id,
            'default_frequency_per_week' => 2,
            'default_duration_minutes' => 45,
            'is_required' => false,
        ]);

        // Create RUG classification
        $rug = RUGClassification::create([
            'patient_id' => 1,
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'adl_sum' => 14,
            'iadl_sum' => 2,
            'cps_score' => 2,
            'is_current' => true,
        ]);

        // Build services
        $services = $this->planner->buildServicesFor($rug, $template);

        // Verify PSW is included with correct frequency
        $pswService = $services->firstWhere('service_type_code', 'PSW');
        $this->assertNotNull($pswService);
        $this->assertEquals(21, $pswService['frequency_per_week']);
        $this->assertTrue($pswService['is_required']);
        $this->assertEquals('template', $pswService['source']);
    }

    /** @test */
    public function it_adds_homemaking_for_high_adl_in_physical_function_category()
    {
        // Create service recommendation for homemaking
        $hmkType = ServiceType::where('code', 'HMK')->first();

        RugServiceRecommendation::create([
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'service_type_id' => $hmkType->id,
            'min_frequency_per_week' => 3,
            'default_duration_minutes' => 60,
            'trigger_conditions' => ['adl_min' => 11, 'iadl_min' => 1],
            'justification' => 'High ADL with IADL impairment needs homemaking support',
            'priority_weight' => 70,
            'is_required' => false,
            'is_active' => true,
        ]);

        // Create minimal template
        $template = CareBundleTemplate::create([
            'code' => 'LTC_PD0_STANDARD',
            'name' => 'Reduced Physical Function - High ADL',
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'weekly_cap_cents' => 400000,
        ]);

        $pswType = ServiceType::where('code', 'PSW')->first();
        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $pswType->id,
            'default_frequency_per_week' => 21,
            'default_duration_minutes' => 60,
            'is_required' => true,
        ]);

        // Create RUG with high ADL and IADL
        $rug = RUGClassification::create([
            'patient_id' => 1,
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'adl_sum' => 14, // >= 11 threshold
            'iadl_sum' => 2,  // >= 1 threshold
            'cps_score' => 1,
            'is_current' => true,
        ]);

        $services = $this->planner->buildServicesFor($rug, $template);

        // Homemaking should be added from recommendation
        $hmkService = $services->firstWhere('service_type_code', 'HMK');
        $this->assertNotNull($hmkService, 'Homemaking should be added for high ADL patient');
        $this->assertEquals(3, $hmkService['frequency_per_week']);
        $this->assertEquals('recommendation', $hmkService['source']);
    }

    /** @test */
    public function it_adds_behavioral_supports_for_behaviour_problems_category()
    {
        $behType = ServiceType::where('code', 'BEH')->first();

        // Create required BEH recommendation for Behaviour Problems
        RugServiceRecommendation::create([
            'rug_category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
            'service_type_id' => $behType->id,
            'min_frequency_per_week' => 3,
            'default_duration_minutes' => 60,
            'justification' => 'Behaviour Problems category requires behavioural supports',
            'priority_weight' => 90,
            'is_required' => true,
            'is_active' => true,
        ]);

        // Create BB0 template without BEH
        $template = CareBundleTemplate::create([
            'code' => 'LTC_BB0_STANDARD',
            'name' => 'Behaviour Problems - Moderate ADL',
            'rug_group' => 'BB0',
            'rug_category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
            'weekly_cap_cents' => 450000,
        ]);

        $pswType = ServiceType::where('code', 'PSW')->first();
        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $pswType->id,
            'default_frequency_per_week' => 24,
            'default_duration_minutes' => 60,
            'is_required' => true,
        ]);

        $rug = RUGClassification::create([
            'patient_id' => 1,
            'rug_group' => 'BB0',
            'rug_category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
            'adl_sum' => 8,
            'iadl_sum' => 1,
            'cps_score' => 3,
            'flags' => ['behaviour_problems' => true],
            'is_current' => true,
        ]);

        $services = $this->planner->buildServicesFor($rug, $template);

        // BEH should be added from recommendation
        $behService = $services->firstWhere('service_type_code', 'BEH');
        $this->assertNotNull($behService, 'Behavioral Supports should be added for BB0');
        $this->assertEquals(3, $behService['frequency_per_week']);
        $this->assertTrue($behService['is_required']);
    }

    /** @test */
    public function it_adds_social_recreational_for_impaired_cognition()
    {
        $recType = ServiceType::where('code', 'REC')->first();

        RugServiceRecommendation::create([
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'service_type_id' => $recType->id,
            'min_frequency_per_week' => 3,
            'default_duration_minutes' => 60,
            'justification' => 'Cognitive impairment benefits from activation',
            'priority_weight' => 70,
            'is_required' => false,
            'is_active' => true,
        ]);

        $template = CareBundleTemplate::create([
            'code' => 'LTC_IB0_STANDARD',
            'name' => 'Impaired Cognition - Moderate ADL',
            'rug_group' => 'IB0',
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'weekly_cap_cents' => 450000,
        ]);

        $pswType = ServiceType::where('code', 'PSW')->first();
        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $pswType->id,
            'default_frequency_per_week' => 24,
            'default_duration_minutes' => 60,
            'is_required' => true,
        ]);

        $rug = RUGClassification::create([
            'patient_id' => 1,
            'rug_group' => 'IB0',
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'adl_sum' => 8,
            'iadl_sum' => 2,
            'cps_score' => 4,
            'is_current' => true,
        ]);

        $services = $this->planner->buildServicesFor($rug, $template);

        $recService = $services->firstWhere('service_type_code', 'REC');
        $this->assertNotNull($recService, 'Social/Recreational should be added for IB0');
        $this->assertEquals(3, $recService['frequency_per_week']);
    }

    /** @test */
    public function it_merges_recommendations_with_higher_frequency()
    {
        $hmkType = ServiceType::where('code', 'HMK')->first();

        // Create recommendation with higher frequency than template
        RugServiceRecommendation::create([
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'service_type_id' => $hmkType->id,
            'min_frequency_per_week' => 5, // Higher than template's 2
            'default_duration_minutes' => 60,
            'trigger_conditions' => ['adl_min' => 11],
            'priority_weight' => 70,
            'is_required' => false,
            'is_active' => true,
        ]);

        $template = CareBundleTemplate::create([
            'code' => 'LTC_PD0_STANDARD',
            'name' => 'Reduced Physical Function - High ADL',
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'weekly_cap_cents' => 400000,
        ]);

        // Template has HMK at 2/week
        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $hmkType->id,
            'default_frequency_per_week' => 2,
            'default_duration_minutes' => 60,
            'is_required' => false,
        ]);

        $rug = RUGClassification::create([
            'patient_id' => 1,
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'adl_sum' => 14,
            'iadl_sum' => 2,
            'is_current' => true,
        ]);

        $services = $this->planner->buildServicesFor($rug, $template);

        $hmkService = $services->firstWhere('service_type_code', 'HMK');
        $this->assertNotNull($hmkService);
        $this->assertEquals(5, $hmkService['frequency_per_week'], 'Should use higher recommendation frequency');
        $this->assertEquals('template+recommendation', $hmkService['source']);
    }

    /** @test */
    public function it_validates_required_services()
    {
        $template = CareBundleTemplate::create([
            'code' => 'LTC_PD0_STANDARD',
            'name' => 'Reduced Physical Function',
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'weekly_cap_cents' => 400000,
        ]);

        $pswType = ServiceType::where('code', 'PSW')->first();
        $nurType = ServiceType::where('code', 'NUR')->first();

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $pswType->id,
            'default_frequency_per_week' => 21,
            'is_required' => true,
        ]);

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $nurType->id,
            'default_frequency_per_week' => 2,
            'is_required' => true,
        ]);

        // Build services (both required should be included)
        $rug = RUGClassification::create([
            'patient_id' => 1,
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'adl_sum' => 14,
            'is_current' => true,
        ]);

        $services = $this->planner->buildServicesFor($rug, $template);
        $validation = $this->planner->validateRequiredServices($services, $template);

        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['missing']);
    }

    /** @test */
    public function it_calculates_total_weekly_cost()
    {
        $template = CareBundleTemplate::create([
            'code' => 'LTC_PD0_STANDARD',
            'name' => 'Reduced Physical Function',
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'weekly_cap_cents' => 400000,
        ]);

        $pswType = ServiceType::where('code', 'PSW')->first(); // $45/visit
        $nurType = ServiceType::where('code', 'NUR')->first(); // $120/visit

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $pswType->id,
            'default_frequency_per_week' => 21, // 21 * $45 = $945
            'is_required' => true,
        ]);

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $nurType->id,
            'default_frequency_per_week' => 2, // 2 * $120 = $240
            'is_required' => true,
        ]);

        $rug = RUGClassification::create([
            'patient_id' => 1,
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'adl_sum' => 14,
            'is_current' => true,
        ]);

        $services = $this->planner->buildServicesFor($rug, $template);
        $totalCost = $this->planner->calculateTotalWeeklyCost($services);

        // Expected: 21*45 + 2*120 = 945 + 240 = 1185
        $this->assertEquals(1185, $totalCost);
    }

    /** @test */
    public function it_checks_budget_compliance()
    {
        $template = CareBundleTemplate::create([
            'code' => 'LTC_PD0_STANDARD',
            'name' => 'Reduced Physical Function',
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'weekly_cap_cents' => 400000, // $4000 cap
        ]);

        $pswType = ServiceType::where('code', 'PSW')->first();

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $pswType->id,
            'default_frequency_per_week' => 21,
            'is_required' => true,
        ]);

        $rug = RUGClassification::create([
            'patient_id' => 1,
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'adl_sum' => 14,
            'is_current' => true,
        ]);

        $services = $this->planner->buildServicesFor($rug, $template);

        // 21 * $45 = $945, well under $4000
        $this->assertTrue($this->planner->isWithinBudget($services, $template));
    }

    /** @test */
    public function it_does_not_add_homemaking_when_adl_threshold_not_met()
    {
        $hmkType = ServiceType::where('code', 'HMK')->first();

        RugServiceRecommendation::create([
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'service_type_id' => $hmkType->id,
            'min_frequency_per_week' => 3,
            'trigger_conditions' => ['adl_min' => 11], // Requires ADL >= 11
            'priority_weight' => 70,
            'is_active' => true,
        ]);

        $template = CareBundleTemplate::create([
            'code' => 'LTC_PA1_STANDARD',
            'name' => 'Reduced Physical Function - Low ADL',
            'rug_group' => 'PA1',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'weekly_cap_cents' => 200000,
        ]);

        $pswType = ServiceType::where('code', 'PSW')->first();
        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $pswType->id,
            'default_frequency_per_week' => 7,
            'is_required' => true,
        ]);

        // RUG with low ADL (below threshold)
        $rug = RUGClassification::create([
            'patient_id' => 1,
            'rug_group' => 'PA1',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'adl_sum' => 5, // Below 11 threshold
            'iadl_sum' => 0,
            'is_current' => true,
        ]);

        $services = $this->planner->buildServicesFor($rug, $template);

        // Homemaking should NOT be added (ADL too low)
        $hmkService = $services->firstWhere('service_type_code', 'HMK');
        $this->assertNull($hmkService, 'Homemaking should not be added when ADL below threshold');
    }
}
