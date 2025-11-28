<?php

namespace Tests\Unit\Services;

use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Models\RUGClassification;
use App\Models\User;
use App\Services\InterraiSummaryService;
use App\Services\RUGClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for InterraiSummaryService
 *
 * Verifies that narrative summaries and clinical flags are correctly
 * generated from InterRAI HC assessments and RUG classifications.
 */
class InterraiSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InterraiSummaryService $service;
    protected RUGClassificationService $rugService;
    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InterraiSummaryService();
        $this->rugService = new RUGClassificationService();

        // Create a test user and patient
        $user = User::factory()->create();
        $this->patient = Patient::factory()->create([
            'user_id' => $user->id,
            'status' => 'Active',
        ]);
    }

    /**
     * Test summary generation when no assessment exists.
     */
    public function test_generates_default_summary_when_no_assessment(): void
    {
        $summary = $this->service->generateSummary($this->patient);

        $this->assertStringContains('No InterRAI HC assessment', $summary['narrative_summary']);
        $this->assertEquals('missing', $summary['assessment_status']);
        $this->assertNull($summary['rug_summary']);
        $this->assertTrue($summary['clinical_flags']['assessment_stale']);
    }

    /**
     * Test summary generation with low-needs patient.
     */
    public function test_generates_summary_for_low_needs_patient(): void
    {
        $assessment = InterraiAssessment::factory()->lowNeeds()->create([
            'patient_id' => $this->patient->id,
        ]);

        $rug = $this->rugService->classify($assessment);

        $this->patient->refresh();
        $summary = $this->service->generateSummary($this->patient);

        $this->assertStringContains('MAPLe priority', $summary['narrative_summary']);
        $this->assertEquals('current', $summary['assessment_status']);
        $this->assertNotNull($summary['rug_summary']);
        $this->assertFalse($summary['clinical_flags']['high_adl_needs']);
        $this->assertFalse($summary['clinical_flags']['high_cognitive_impairment']);
    }

    /**
     * Test summary generation with high-needs patient.
     */
    public function test_generates_summary_for_high_needs_patient(): void
    {
        $assessment = InterraiAssessment::factory()->highNeeds()->create([
            'patient_id' => $this->patient->id,
        ]);

        $rug = $this->rugService->classify($assessment);

        $this->patient->refresh();
        $summary = $this->service->generateSummary($this->patient);

        $this->assertStringContains('MAPLe priority', $summary['narrative_summary']);
        $this->assertTrue($summary['clinical_flags']['high_adl_needs']);
        $this->assertTrue($summary['clinical_flags']['high_fall_risk']);
        $this->assertTrue($summary['clinical_flags']['high_maple_priority']);
    }

    /**
     * Test clinical flags for cognitive impairment.
     */
    public function test_sets_cognitive_flags_correctly(): void
    {
        $assessment = InterraiAssessment::factory()->withCognitiveImpairment()->create([
            'patient_id' => $this->patient->id,
            'cognitive_performance_scale' => 4,
            'wandering_flag' => true,
        ]);

        $rug = $this->rugService->classify($assessment);

        $this->patient->refresh();
        $summary = $this->service->generateSummary($this->patient);

        $this->assertTrue($summary['clinical_flags']['high_cognitive_impairment']);
        $this->assertTrue($summary['clinical_flags']['wandering_risk']);
        $this->assertStringContains('Cognitive status', $summary['narrative_summary']);
    }

    /**
     * Test clinical flags for health instability.
     */
    public function test_sets_clinical_instability_flags(): void
    {
        $assessment = InterraiAssessment::factory()->clinicallyComplex()->create([
            'patient_id' => $this->patient->id,
            'chess_score' => 4,
        ]);

        $rug = $this->rugService->classify($assessment);

        $this->patient->refresh();
        $summary = $this->service->generateSummary($this->patient);

        $this->assertTrue($summary['clinical_flags']['high_clinical_instability']);
        $this->assertTrue($summary['clinical_flags']['health_unstable']);
        $this->assertStringContains('health instability', $summary['narrative_summary']);
    }

    /**
     * Test narrative includes bundle intent based on RUG category.
     */
    public function test_narrative_includes_bundle_intent(): void
    {
        $assessment = InterraiAssessment::factory()->withCognitiveImpairment()->create([
            'patient_id' => $this->patient->id,
            'cognitive_performance_scale' => 4,
            'adl_hierarchy' => 3,
        ]);

        $rug = $this->rugService->classify($assessment);

        $this->patient->refresh();
        $summary = $this->service->generateSummary($this->patient);

        $this->assertStringContains('Care focus:', $summary['narrative_summary']);
    }

    /**
     * Test active flags with labels for UI.
     */
    public function test_get_active_flags_with_labels(): void
    {
        $flags = [
            'high_fall_risk' => true,
            'high_cognitive_impairment' => false,
            'wandering_risk' => true,
            'high_clinical_instability' => true,
        ];

        $activeFlags = $this->service->getActiveFlagsWithLabels($flags);

        $this->assertCount(3, $activeFlags);

        // Check that danger flags come first
        $this->assertEquals('danger', $activeFlags[0]['severity']);

        // Check that labels are included
        $labels = array_column($activeFlags, 'label');
        $this->assertContains('Fall Risk', $labels);
        $this->assertContains('Wandering/Elopement Risk', $labels);
    }

    /**
     * Test ED risk calculation.
     */
    public function test_calculates_ed_risk_correctly(): void
    {
        // High-risk patient: high CHESS, falls, high MAPLe
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'chess_score' => 4,
            'falls_in_last_90_days' => true,
            'maple_score' => '5',
            'adl_hierarchy' => 4,
        ]);

        $rug = $this->rugService->classify($assessment);

        $this->patient->refresh();
        $summary = $this->service->generateSummary($this->patient);

        $this->assertTrue($summary['clinical_flags']['frequent_ed_risk']);
    }

    /**
     * Test caregiver burden assessment.
     */
    public function test_assesses_caregiver_burden(): void
    {
        // High burden: high ADL needs
        $assessment = InterraiAssessment::factory()->create([
            'patient_id' => $this->patient->id,
            'adl_hierarchy' => 5,
        ]);

        $rug = $this->rugService->classify($assessment);

        $this->patient->refresh();
        $summary = $this->service->generateSummary($this->patient);

        $this->assertTrue($summary['clinical_flags']['caregiver_burden_high']);
    }

    /**
     * Test stale assessment detection.
     */
    public function test_detects_stale_assessment(): void
    {
        $assessment = InterraiAssessment::factory()->stale()->create([
            'patient_id' => $this->patient->id,
        ]);

        $this->patient->refresh();
        $summary = $this->service->generateSummary($this->patient);

        $this->assertEquals('stale', $summary['assessment_status']);
        $this->assertTrue($summary['clinical_flags']['assessment_stale']);
    }

    /**
     * Helper to check if string contains substring.
     */
    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
