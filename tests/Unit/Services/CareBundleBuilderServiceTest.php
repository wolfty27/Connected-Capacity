<?php

namespace Tests\Unit\Services;

use App\Models\CareBundleTemplate;
use App\Models\CareBundleTemplateService;
use App\Models\CareBundle;
use App\Models\CarePlan;
use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Models\RUGClassification;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\CareBundleBuilderService;
use App\Services\CareBundleTemplateRepository;
use App\Services\MetadataEngine;
use App\Services\RUGClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for CareBundleBuilderService
 *
 * Verifies the RUG-based care bundle building pipeline:
 * - Template matching based on RUG classification
 * - Service configuration for patients
 * - Care plan creation from templates
 */
class CareBundleBuilderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CareBundleBuilderService $service;
    protected RUGClassificationService $rugService;
    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        // Create service with mocked dependencies
        $metadataEngine = $this->createMock(MetadataEngine::class);
        $this->rugService = new RUGClassificationService();
        $this->service = new CareBundleBuilderService(
            $metadataEngine,
            new CareBundleTemplateRepository(),
            $this->rugService
        );

        // Create a test patient
        $user = User::factory()->create();
        $this->patient = Patient::factory()->create([
            'user_id' => $user->id,
            'status' => 'Active',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RUG-Based Bundle Methods Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test getRugBasedBundles returns error when patient not found.
     */
    public function test_get_rug_based_bundles_returns_error_for_invalid_patient(): void
    {
        $result = $this->service->getRugBasedBundles(99999);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Patient not found', $result['error']);
        $this->assertEmpty($result['bundles']);
    }

    /**
     * Test getRugBasedBundles returns error when no assessment exists.
     */
    public function test_get_rug_based_bundles_returns_error_when_no_assessment(): void
    {
        $result = $this->service->getRugBasedBundles($this->patient->id);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('No InterRAI assessment', $result['error']);
        $this->assertEmpty($result['bundles']);
    }

    /**
     * Test getRugBasedBundles generates RUG classification if missing.
     */
    public function test_get_rug_based_bundles_generates_classification_from_assessment(): void
    {
        // Create assessment without classification
        InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
            'adl_hierarchy' => 3,
            'cognitive_performance_scale' => 2,
        ]);

        // Seed a template
        $this->seedCB0Template();

        $result = $this->service->getRugBasedBundles($this->patient->id);

        // Should have generated a classification
        $this->assertArrayHasKey('rug_classification', $result);
        $this->assertNotNull($result['rug_classification']);
    }

    /**
     * Test getRugBasedBundles returns matching templates.
     */
    public function test_get_rug_based_bundles_returns_matching_templates(): void
    {
        // Create assessment and classification
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
            'adl_hierarchy' => 3,
            'cognitive_performance_scale' => 2,
            'chess_score' => 3,
        ]);

        $rug = $this->rugService->classify($assessment);

        // Seed matching template
        $template = $this->seedTemplateForRug($rug);

        $result = $this->service->getRugBasedBundles($this->patient->id);

        $this->assertArrayHasKey('bundles', $result);
        $this->assertNotEmpty($result['bundles']);
        $this->assertEquals($rug->rug_group, $result['rug_classification']['rug_group']);
    }

    /**
     * Test that recommended bundle is marked correctly.
     */
    public function test_recommended_bundle_is_marked(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
            'adl_hierarchy' => 3,
            'cognitive_performance_scale' => 2,
            'chess_score' => 3,
        ]);

        $rug = $this->rugService->classify($assessment);
        $template = $this->seedTemplateForRug($rug);

        $result = $this->service->getRugBasedBundles($this->patient->id);

        // Find the recommended bundle
        $recommended = collect($result['bundles'])->firstWhere('isRecommended', true);

        $this->assertNotNull($recommended);
        $this->assertEquals($template->rug_group, $recommended['rug_group']);
        $this->assertEquals(100, $recommended['matchScore']);
    }

    /**
     * Test getRugTemplateForPatient returns configured template.
     */
    public function test_get_rug_template_for_patient_returns_configured_template(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
            'adl_hierarchy' => 3,
        ]);

        $this->rugService->classify($assessment);

        $template = $this->seedCB0Template();

        $result = $this->service->getRugTemplateForPatient($template->id, $this->patient->id);

        $this->assertNotNull($result);
        $this->assertEquals($template->id, $result['id']);
        $this->assertEquals($template->code, $result['code']);
        $this->assertArrayHasKey('services', $result);
    }

    /**
     * Test getRugTemplateForPatient returns null for invalid patient.
     */
    public function test_get_rug_template_for_patient_returns_null_for_invalid_patient(): void
    {
        $template = $this->seedCB0Template();

        $result = $this->service->getRugTemplateForPatient($template->id, 99999);

        $this->assertNull($result);
    }

    /**
     * Test getRugTemplateForPatient returns null for invalid template.
     */
    public function test_get_rug_template_for_patient_returns_null_for_invalid_template(): void
    {
        $result = $this->service->getRugTemplateForPatient(99999, $this->patient->id);

        $this->assertNull($result);
    }

    /*
    |--------------------------------------------------------------------------
    | Template Configuration Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test that services are filtered by patient flags.
     */
    public function test_services_filtered_by_patient_flags(): void
    {
        // Create assessment with high needs that trigger flags
        $assessment = InterraiAssessment::factory()->highNeeds()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
        ]);

        $rug = $this->rugService->classify($assessment);

        // Create template with conditional service
        $template = $this->seedTemplateWithConditionalService();

        $result = $this->service->getRugTemplateForPatient($template->id, $this->patient->id);

        // Services should be filtered based on flags
        $this->assertArrayHasKey('services', $result);
    }

    /**
     * Test cost calculations are correct.
     */
    public function test_cost_calculations_are_correct(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
            'adl_hierarchy' => 3,
        ]);

        $this->rugService->classify($assessment);
        $template = $this->seedCB0TemplateWithServices();

        $result = $this->service->getRugTemplateForPatient($template->id, $this->patient->id);

        $this->assertArrayHasKey('estimatedWeeklyCost', $result);
        $this->assertArrayHasKey('estimatedMonthlyCost', $result);
        $this->assertArrayHasKey('withinBudget', $result);

        // Monthly should be ~4x weekly
        $this->assertEqualsWithDelta(
            $result['estimatedWeeklyCost'] * 4,
            $result['estimatedMonthlyCost'],
            0.01
        );
    }

    /**
     * Test color theme based on RUG category.
     */
    public function test_color_theme_based_on_rug_category(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
            'adl_hierarchy' => 3,
            'cognitive_performance_scale' => 4, // Impaired cognition
        ]);

        $rug = $this->rugService->classify($assessment);

        // Create template for impaired cognition
        $template = CareBundleTemplate::create([
            'code' => 'LTC_IB0_STANDARD',
            'name' => 'Impaired Cognition - Moderate ADL',
            'description' => 'Test template',
            'rug_group' => 'IB0',
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 0,
            'max_adl_sum' => 18,
            'min_iadl_sum' => 0,
            'max_iadl_sum' => 18,
            'weekly_cap_cents' => 450000,
            'is_active' => true,
            'is_current_version' => true,
        ]);

        $result = $this->service->getRugTemplateForPatient($template->id, $this->patient->id);

        $this->assertEquals('amber', $result['colorTheme']);
    }

    /*
    |--------------------------------------------------------------------------
    | Care Plan Building Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test buildCarePlanFromTemplate creates care plan.
     */
    public function test_build_care_plan_from_template_creates_plan(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
        ]);

        $this->rugService->classify($assessment);

        $template = $this->seedCB0TemplateWithServices();

        $serviceConfigurations = [
            [
                'service_type_id' => ServiceType::first()->id,
                'currentFrequency' => 3,
                'name' => 'Nursing',
                'description' => 'RN visits',
            ],
        ];

        $carePlan = $this->service->buildCarePlanFromTemplate(
            $this->patient->id,
            $template->id,
            $serviceConfigurations
        );

        $this->assertInstanceOf(CarePlan::class, $carePlan);
        $this->assertEquals($this->patient->id, $carePlan->patient_id);
        $this->assertEquals('draft', $carePlan->status);
        $this->assertEquals($template->id, $carePlan->care_bundle_template_id);
    }

    /**
     * Test care plan version increments correctly.
     */
    public function test_care_plan_version_increments(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
        ]);

        $this->rugService->classify($assessment);
        $template = $this->seedCB0TemplateWithServices();

        $serviceConfigurations = [
            [
                'service_type_id' => ServiceType::first()->id,
                'currentFrequency' => 3,
            ],
        ];

        // Create first plan
        $plan1 = $this->service->buildCarePlanFromTemplate(
            $this->patient->id,
            $template->id,
            $serviceConfigurations
        );

        // Create second plan
        $plan2 = $this->service->buildCarePlanFromTemplate(
            $this->patient->id,
            $template->id,
            $serviceConfigurations
        );

        $this->assertEquals(1, $plan1->version);
        $this->assertEquals(2, $plan2->version);
    }

    /**
     * Test service assignments are created.
     */
    public function test_service_assignments_are_created(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
        ]);

        $this->rugService->classify($assessment);
        $template = $this->seedCB0TemplateWithServices();

        $serviceType = ServiceType::first();
        $serviceConfigurations = [
            [
                'service_type_id' => $serviceType->id,
                'currentFrequency' => 5,
                'name' => $serviceType->name,
            ],
        ];

        $carePlan = $this->service->buildCarePlanFromTemplate(
            $this->patient->id,
            $template->id,
            $serviceConfigurations
        );

        $this->assertEquals(1, $carePlan->serviceAssignments()->count());
        $this->assertEquals($serviceType->id, $carePlan->serviceAssignments->first()->service_type_id);
    }

    /**
     * Test services with zero frequency are not assigned.
     */
    public function test_zero_frequency_services_not_assigned(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
        ]);

        $this->rugService->classify($assessment);
        $template = $this->seedCB0TemplateWithServices();

        $serviceConfigurations = [
            [
                'service_type_id' => ServiceType::first()->id,
                'currentFrequency' => 0, // Zero frequency
            ],
        ];

        $carePlan = $this->service->buildCarePlanFromTemplate(
            $this->patient->id,
            $template->id,
            $serviceConfigurations
        );

        $this->assertEquals(0, $carePlan->serviceAssignments()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Care Plan Publishing Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test publishCarePlan activates the plan.
     */
    public function test_publish_care_plan_activates_plan(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
        ]);

        $this->rugService->classify($assessment);
        $template = $this->seedCB0TemplateWithServices();

        $carePlan = $this->service->buildCarePlanFromTemplate(
            $this->patient->id,
            $template->id,
            [['service_type_id' => ServiceType::first()->id, 'currentFrequency' => 3]]
        );

        $user = User::factory()->create();
        $publishedPlan = $this->service->publishCarePlan($carePlan, $user->id);

        $this->assertEquals('active', $publishedPlan->status);
        $this->assertEquals($user->id, $publishedPlan->approved_by);
        $this->assertNotNull($publishedPlan->approved_at);
    }

    /**
     * Test publishing archives previous active plans.
     */
    public function test_publish_archives_previous_active_plans(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
        ]);

        $this->rugService->classify($assessment);
        $template = $this->seedCB0TemplateWithServices();

        $serviceConfig = [['service_type_id' => ServiceType::first()->id, 'currentFrequency' => 3]];

        // Create and publish first plan
        $plan1 = $this->service->buildCarePlanFromTemplate(
            $this->patient->id,
            $template->id,
            $serviceConfig
        );
        $this->service->publishCarePlan($plan1);

        // Create and publish second plan
        $plan2 = $this->service->buildCarePlanFromTemplate(
            $this->patient->id,
            $template->id,
            $serviceConfig
        );
        $this->service->publishCarePlan($plan2);

        // Refresh and check
        $plan1->refresh();

        $this->assertEquals('archived', $plan1->status);
        $this->assertEquals('active', $plan2->fresh()->status);
    }

    /**
     * Test patient transitions to active on publish.
     */
    public function test_patient_transitions_to_active_on_publish(): void
    {
        // Set patient to non-active status
        $this->patient->update(['status' => 'Inactive', 'is_in_queue' => true]);

        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'is_current' => true,
        ]);

        $this->rugService->classify($assessment);
        $template = $this->seedCB0TemplateWithServices();

        $carePlan = $this->service->buildCarePlanFromTemplate(
            $this->patient->id,
            $template->id,
            [['service_type_id' => ServiceType::first()->id, 'currentFrequency' => 3]]
        );

        $this->service->publishCarePlan($carePlan);

        $this->patient->refresh();

        $this->assertEquals('Active', $this->patient->status);
        $this->assertFalse($this->patient->is_in_queue);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }

    protected function seedCB0Template(): CareBundleTemplate
    {
        return CareBundleTemplate::create([
            'code' => 'LTC_CB0_STANDARD',
            'name' => 'Clinically Complex - Moderate ADL',
            'description' => 'Test template for CB0',
            'rug_group' => 'CB0',
            'rug_category' => RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 0,
            'max_adl_sum' => 18,
            'min_iadl_sum' => 0,
            'max_iadl_sum' => 18,
            'weekly_cap_cents' => 450000,
            'priority_weight' => 73,
            'is_active' => true,
            'is_current_version' => true,
        ]);
    }

    protected function seedCB0TemplateWithServices(): CareBundleTemplate
    {
        $template = $this->seedCB0Template();

        // Create service type if not exists
        $serviceType = ServiceType::firstOrCreate(
            ['code' => 'NUR'],
            [
                'name' => 'Nursing',
                'description' => 'Registered Nurse visits',
                'service_category_id' => 1,
                'default_cost_per_visit_cents' => 12000,
            ]
        );

        // Add service to template
        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $serviceType->id,
            'default_frequency_per_week' => 5,
            'default_duration_minutes' => 45,
            'default_duration_weeks' => 12,
            'is_required' => true,
            'is_conditional' => false,
        ]);

        return $template;
    }

    protected function seedTemplateForRug(RUGClassification $rug): CareBundleTemplate
    {
        return CareBundleTemplate::create([
            'code' => 'LTC_' . $rug->rug_group . '_STANDARD',
            'name' => $rug->rug_category . ' Template',
            'description' => 'Test template for ' . $rug->rug_group,
            'rug_group' => $rug->rug_group,
            'rug_category' => $rug->rug_category,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 0,
            'max_adl_sum' => 18,
            'min_iadl_sum' => 0,
            'max_iadl_sum' => 18,
            'weekly_cap_cents' => 450000,
            'priority_weight' => 50,
            'is_active' => true,
            'is_current_version' => true,
        ]);
    }

    protected function seedTemplateWithConditionalService(): CareBundleTemplate
    {
        $template = $this->seedCB0Template();

        $serviceType = ServiceType::firstOrCreate(
            ['code' => 'RT'],
            [
                'name' => 'Respiratory Therapy',
                'description' => 'RT visits',
                'service_category_id' => 1,
                'default_cost_per_visit_cents' => 13000,
            ]
        );

        CareBundleTemplateService::create([
            'care_bundle_template_id' => $template->id,
            'service_type_id' => $serviceType->id,
            'default_frequency_per_week' => 2,
            'default_duration_minutes' => 60,
            'default_duration_weeks' => 12,
            'is_required' => false,
            'is_conditional' => true,
            'condition_flags' => ['respiratory'],
        ]);

        return $template;
    }
}
