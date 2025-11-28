<?php

namespace Tests\Unit\Services\CareOps;

use App\Models\ServiceAssignment;
use App\Models\User;
use App\Services\CareOps\JeopardyBoardService;
use App\Services\CareOps\VisitVerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JeopardyBoardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected JeopardyBoardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JeopardyBoardService(new VisitVerificationService());
    }

    public function test_getActiveAlerts_returns_correct_structure(): void
    {
        $result = $this->service->getActiveAlerts();

        $this->assertArrayHasKey('total_active', $result);
        $this->assertArrayHasKey('critical_count', $result);
        $this->assertArrayHasKey('warning_count', $result);
        $this->assertArrayHasKey('alerts', $result);
    }

    public function test_getCriticalAlerts_returns_overdue_unverified(): void
    {
        // Create overdue assignment (2 days ago, past 24h grace period)
        $overdue = ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->subDays(2),
        ]);

        // Create recent assignment (12 hours ago, within grace period)
        ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->subHours(12),
        ]);

        // Create verified assignment
        ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'scheduled_start' => Carbon::now()->subDays(2),
        ]);

        $result = $this->service->getCriticalAlerts();

        $this->assertCount(1, $result);
        $this->assertEquals($overdue->id, $result->first()['id']);
        $this->assertEquals('CRITICAL', $result->first()['risk_level']);
        $this->assertEquals('visit_verification_overdue', $result->first()['type']);
    }

    public function test_getWarningAlerts_returns_upcoming_at_risk(): void
    {
        // Create assignment scheduled in 1 hour (within 2h warning threshold)
        $upcoming = ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->addHour(),
        ]);

        // Create assignment scheduled in 3 hours (outside warning threshold)
        ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->addHours(3),
        ]);

        $result = $this->service->getWarningAlerts();

        $this->assertCount(1, $result);
        $this->assertEquals($upcoming->id, $result->first()['id']);
        $this->assertEquals('WARNING', $result->first()['risk_level']);
        $this->assertEquals('late_start_risk', $result->first()['type']);
    }

    public function test_getActiveAlerts_combines_critical_and_warning(): void
    {
        // Create overdue (critical)
        ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->subDays(2),
        ]);

        // Create upcoming (warning)
        ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->addHour(),
        ]);

        $result = $this->service->getActiveAlerts();

        $this->assertEquals(2, $result['total_active']);
        $this->assertEquals(1, $result['critical_count']);
        $this->assertEquals(1, $result['warning_count']);
        $this->assertCount(2, $result['alerts']);
    }

    public function test_resolveAlert_marks_assignment_as_verified(): void
    {
        $user = User::factory()->create();
        $assignment = ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'scheduled_start' => Carbon::now()->subDays(2),
        ]);

        $result = $this->service->resolveAlert($assignment->id, $user);

        $this->assertNotNull($result);
        $this->assertEquals(ServiceAssignment::VERIFICATION_VERIFIED, $result->verification_status);
        $this->assertEquals(ServiceAssignment::VERIFICATION_SOURCE_COORDINATOR, $result->verification_source);
    }

    public function test_resolveAlert_returns_null_for_invalid_id(): void
    {
        $result = $this->service->resolveAlert(99999);

        $this->assertNull($result);
    }

    public function test_getSummaryStats_returns_correct_statistics(): void
    {
        $orgId = 1;

        // Create overdue alerts
        ServiceAssignment::factory()->count(5)->create([
            'service_provider_organization_id' => $orgId,
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->subDays(2),
        ]);

        // Create upcoming alerts
        ServiceAssignment::factory()->count(3)->create([
            'service_provider_organization_id' => $orgId,
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->addHour(),
        ]);

        $result = $this->service->getSummaryStats($orgId);

        $this->assertEquals(8, $result['active_alerts']);
        $this->assertEquals(5, $result['critical_count']);
        $this->assertEquals(3, $result['warning_count']);
        $this->assertArrayHasKey('weekly_missed_rate', $result);
        $this->assertArrayHasKey('weekly_verification_rate', $result);
        $this->assertArrayHasKey('is_compliant', $result);
    }

    public function test_alerts_filtered_by_organization(): void
    {
        // Create alerts for org 1
        ServiceAssignment::factory()->count(5)->create([
            'service_provider_organization_id' => 1,
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->subDays(2),
        ]);

        // Create alerts for org 2
        ServiceAssignment::factory()->count(3)->create([
            'service_provider_organization_id' => 2,
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->subDays(2),
        ]);

        $result = $this->service->getActiveAlerts(1);

        $this->assertEquals(5, $result['total_active']);
    }

    public function test_resolved_alerts_no_longer_appear(): void
    {
        $user = User::factory()->create();

        // Create overdue alerts
        $assignments = ServiceAssignment::factory()->count(3)->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
            'scheduled_start' => Carbon::now()->subDays(2),
        ]);

        // Initial count
        $initialResult = $this->service->getActiveAlerts();
        $this->assertEquals(3, $initialResult['total_active']);

        // Resolve one alert
        $this->service->resolveAlert($assignments->first()->id, $user);

        // Updated count
        $updatedResult = $this->service->getActiveAlerts();
        $this->assertEquals(2, $updatedResult['total_active']);
    }
}
