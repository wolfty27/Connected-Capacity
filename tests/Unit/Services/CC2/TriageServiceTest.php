<?php

namespace Tests\Unit\Services\CC2;

use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\TriageResult;
use App\Models\User;
use App\Services\CC2\TriageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TriageServiceTest extends TestCase
{
    use RefreshDatabase;

    private TriageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TriageService();
    }

    public function test_record_result_creates_triage_result_and_care_plan(): void
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create();
        Carbon::setTestNow('2025-01-01 10:00:00');

        $result = $this->service->recordResult($patient, [
            'acuity_level' => 'high',
            'dementia_flag' => true,
            'mh_flag' => false,
            'rpm_required' => true,
            'fall_risk' => true,
            'behavioural_risk' => false,
            'notes' => 'Requires urgent home care',
        ], $user);

        $patient->refresh();

        $this->assertInstanceOf(TriageResult::class, $result);
        $this->assertNotNull($result->received_at);
        $this->assertNotNull($result->triaged_at);
        $this->assertEquals($user->id, $result->triaged_by);

        $this->assertEquals('high', $patient->triage_summary['acuity_level']);
        $this->assertEquals('Requires urgent home care', $patient->triage_summary['notes']);
        $this->assertEquals($result->triaged_at->toJSON(), $patient->triage_summary['triaged_at']);

        $this->assertEquals([
            'dementia' => true,
            'mental_health' => false,
            'rpm' => true,
            'fall' => true,
            'behavioural' => false,
        ], $patient->risk_flags);

        $carePlan = CarePlan::where('patient_id', $patient->id)->first();
        $this->assertNotNull($carePlan);
        $this->assertEquals(1, $carePlan->version);
        $this->assertEquals('draft', $carePlan->status);

        Carbon::setTestNow();
    }

    public function test_record_result_updates_existing_entries_without_duplicate_care_plans(): void
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create();

        $originalResult = TriageResult::create([
            'patient_id' => $patient->id,
            'received_at' => Carbon::parse('2025-01-01 08:00:00'),
            'triaged_at' => Carbon::parse('2025-01-01 08:30:00'),
            'acuity_level' => 'medium',
            'dementia_flag' => false,
            'mh_flag' => false,
            'rpm_required' => false,
            'fall_risk' => false,
            'behavioural_risk' => false,
            'triaged_by' => $user->id,
        ]);

        CarePlan::create([
            'patient_id' => $patient->id,
            'version' => 1,
            'status' => 'draft',
        ]);

        Carbon::setTestNow('2025-01-01 12:00:00');

        $result = $this->service->recordResult($patient, [
            'acuity_level' => 'critical',
            'dementia_flag' => false,
            'mh_flag' => true,
            'rpm_required' => true,
            'fall_risk' => false,
            'behavioural_risk' => true,
            'notes' => 'Escalated presentation',
        ], $user);

        $patient->refresh();
        $this->assertEquals($originalResult->received_at, $result->received_at);
        $this->assertNotEquals($originalResult->triaged_at, $result->triaged_at);
        $this->assertCount(1, TriageResult::where('patient_id', $patient->id)->get());
        $this->assertCount(1, CarePlan::where('patient_id', $patient->id)->get());
        $this->assertEquals('critical', $patient->triage_summary['acuity_level']);
        $this->assertTrue($patient->risk_flags['mental_health']);
        $this->assertTrue($patient->risk_flags['behavioural']);

        Carbon::setTestNow();
    }

    public function test_record_result_creates_distinct_care_plans_per_patient(): void
    {
        $user = User::factory()->create();
        $patientA = Patient::factory()->create();
        $patientB = Patient::factory()->create();

        $this->service->recordResult($patientA, [
            'acuity_level' => 'low',
        ], $user);

        $this->service->recordResult($patientB, [
            'acuity_level' => 'medium',
        ], $user);

        $planPatients = CarePlan::pluck('patient_id')->sort()->values();

        $this->assertEquals(
            [$patientA->id, $patientB->id],
            $planPatients->toArray()
        );
    }
}
