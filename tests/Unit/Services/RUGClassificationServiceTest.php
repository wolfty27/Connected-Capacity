<?php

namespace Tests\Unit\Services;

use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Models\RUGClassification;
use App\Models\User;
use App\Services\RUGClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for RUGClassificationService
 *
 * Verifies the CIHI RUG-III/HC classification algorithm implementation.
 */
class RUGClassificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RUGClassificationService $service;
    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RUGClassificationService();

        // Create a test user and patient
        $user = User::factory()->create();
        $this->patient = Patient::factory()->create([
            'user_id' => $user->id,
            'status' => 'Active',
        ]);
    }

    /**
     * Test classification for Reduced Physical Function - low ADL.
     */
    public function test_classifies_pa1_low_adl_no_iadl(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'assessment_type' => 'hc',
            'assessment_date' => now(),
            'adl_hierarchy' => 1, // Low ADL
            'cognitive_performance_scale' => 0, // Intact cognition
            'chess_score' => 0,
            'is_current' => true,
        ]);

        $classification = $this->service->classify($assessment);

        $this->assertEquals('PA1', $classification->rug_group);
        $this->assertEquals(RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION, $classification->rug_category);
        $this->assertTrue($classification->is_current);
        $this->assertLessThanOrEqual(6, $classification->adl_sum);
    }

    /**
     * Test classification for Impaired Cognition.
     */
    public function test_classifies_ib0_moderate_cognition_moderate_adl(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'assessment_type' => 'hc',
            'assessment_date' => now(),
            'adl_hierarchy' => 3, // Moderate ADL
            'cognitive_performance_scale' => 4, // Moderate-severe impairment
            'chess_score' => 1,
            'is_current' => true,
        ]);

        $classification = $this->service->classify($assessment);

        $this->assertEquals('IB0', $classification->rug_group);
        $this->assertEquals(RUGClassification::CATEGORY_IMPAIRED_COGNITION, $classification->rug_category);
        $this->assertTrue($classification->hasFlag('impaired_cognition'));
    }

    /**
     * Test classification for Clinically Complex.
     */
    public function test_classifies_cb0_high_chess_moderate_adl(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'assessment_type' => 'hc',
            'assessment_date' => now(),
            'adl_hierarchy' => 3, // Moderate ADL
            'cognitive_performance_scale' => 1, // Mild impairment
            'chess_score' => 4, // High health instability
            'pain_scale' => 3,
            'is_current' => true,
        ]);

        $classification = $this->service->classify($assessment);

        $this->assertEquals('CB0', $classification->rug_group);
        $this->assertEquals(RUGClassification::CATEGORY_CLINICALLY_COMPLEX, $classification->rug_category);
        $this->assertTrue($classification->hasFlag('clinically_complex'));
    }

    /**
     * Test classification for high ADL physical function.
     */
    public function test_classifies_pd0_very_high_adl(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'assessment_type' => 'hc',
            'assessment_date' => now(),
            'adl_hierarchy' => 6, // Total dependence
            'cognitive_performance_scale' => 0, // Intact
            'chess_score' => 0,
            'is_current' => true,
        ]);

        $classification = $this->service->classify($assessment);

        $this->assertEquals('PD0', $classification->rug_group);
        $this->assertEquals(RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION, $classification->rug_category);
        $this->assertGreaterThanOrEqual(11, $classification->adl_sum);
    }

    /**
     * Test that new classification supersedes old one.
     */
    public function test_new_classification_supersedes_old(): void
    {
        // Create first assessment and classification
        $assessment1 = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'assessment_type' => 'hc',
            'assessment_date' => now()->subDays(30),
            'adl_hierarchy' => 1,
            'cognitive_performance_scale' => 0,
            'is_current' => false,
        ]);

        $classification1 = $this->service->classify($assessment1);
        $this->assertTrue($classification1->is_current);

        // Create second assessment and classification
        $assessment2 = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'assessment_type' => 'hc',
            'assessment_date' => now(),
            'adl_hierarchy' => 4,
            'cognitive_performance_scale' => 3,
            'is_current' => true,
        ]);

        $classification2 = $this->service->classify($assessment2);

        // Refresh and verify
        $classification1->refresh();

        $this->assertFalse($classification1->is_current);
        $this->assertTrue($classification2->is_current);
    }

    /**
     * Test relationship from patient to latest classification.
     */
    public function test_patient_has_latest_rug_classification(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'assessment_type' => 'hc',
            'assessment_date' => now(),
            'adl_hierarchy' => 2,
            'cognitive_performance_scale' => 1,
            'is_current' => true,
        ]);

        $classification = $this->service->classify($assessment);

        $this->patient->refresh();
        $latestRug = $this->patient->latestRugClassification;

        $this->assertNotNull($latestRug);
        $this->assertEquals($classification->id, $latestRug->id);
    }

    /**
     * Test numeric rank ordering.
     */
    public function test_numeric_ranks_ordered_correctly(): void
    {
        // SE3 should have highest rank (23)
        $this->assertEquals(23, RUGClassification::getRankForGroup('SE3'));

        // PA1 should have lowest rank (1)
        $this->assertEquals(1, RUGClassification::getRankForGroup('PA1'));

        // CB0 should be in middle (14)
        $this->assertEquals(14, RUGClassification::getRankForGroup('CB0'));
    }

    /**
     * Test category lookup.
     */
    public function test_category_lookup_for_groups(): void
    {
        $this->assertEquals(
            RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
            RUGClassification::getCategoryForGroup('CB0')
        );

        $this->assertEquals(
            RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            RUGClassification::getCategoryForGroup('IB0')
        );

        $this->assertEquals(
            RUGClassification::CATEGORY_SPECIAL_REHABILITATION,
            RUGClassification::getCategoryForGroup('RB0')
        );
    }

    /**
     * Test toSummaryArray output.
     */
    public function test_to_summary_array(): void
    {
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'assessment_type' => 'hc',
            'assessment_date' => now(),
            'adl_hierarchy' => 3,
            'cognitive_performance_scale' => 4,
            'is_current' => true,
        ]);

        $classification = $this->service->classify($assessment);
        $summary = $classification->toSummaryArray();

        $this->assertArrayHasKey('rug_group', $summary);
        $this->assertArrayHasKey('rug_category', $summary);
        $this->assertArrayHasKey('adl_sum', $summary);
        $this->assertArrayHasKey('cps_score', $summary);
        $this->assertArrayHasKey('numeric_rank', $summary);
        $this->assertArrayHasKey('active_flags', $summary);
    }
}
