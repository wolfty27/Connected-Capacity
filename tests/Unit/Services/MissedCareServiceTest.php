<?php

namespace Tests\Unit\Services;

use App\DTOs\MissedCareMetricsDTO;
use App\Models\ServiceAssignment;
use App\Services\MissedCareService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissedCareServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MissedCareService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MissedCareService();
    }

    public function test_calculateForOrg_returns_dto(): void
    {
        $result = $this->service->calculateForOrg();

        $this->assertInstanceOf(MissedCareMetricsDTO::class, $result);
    }

    public function test_calculateForOrg_with_verified_visits_only(): void
    {
        ServiceAssignment::factory()->count(10)->create([
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'status' => ServiceAssignment::STATUS_COMPLETED,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        $result = $this->service->calculateForOrg();

        $this->assertEquals(10, $result->deliveredEvents);
        $this->assertEquals(0, $result->missedEvents);
        $this->assertEquals(0.0, $result->ratePercent);
        $this->assertTrue($result->isCompliant);
        $this->assertEquals('A', $result->getComplianceBand());
    }

    public function test_calculateForOrg_with_missed_visits(): void
    {
        // Create 97 verified visits
        ServiceAssignment::factory()->count(97)->create([
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'status' => ServiceAssignment::STATUS_COMPLETED,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        // Create 3 missed visits
        ServiceAssignment::factory()->count(3)->create([
            'verification_status' => ServiceAssignment::VERIFICATION_MISSED,
            'status' => ServiceAssignment::STATUS_MISSED,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        $result = $this->service->calculateForOrg();

        $this->assertEquals(97, $result->deliveredEvents);
        $this->assertEquals(3, $result->missedEvents);
        $this->assertEquals(100, $result->totalEvents);
        $this->assertEquals(3.0, $result->ratePercent); // 3 / 100 * 100
        $this->assertFalse($result->isCompliant);
        $this->assertEquals('C', $result->getComplianceBand()); // >0.5%
    }

    public function test_calculateForOrg_respects_date_range(): void
    {
        // Create visits within range
        ServiceAssignment::factory()->count(5)->create([
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'scheduled_start' => Carbon::now()->subDays(10),
        ]);

        // Create visits outside range (too old)
        ServiceAssignment::factory()->count(5)->create([
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'scheduled_start' => Carbon::now()->subDays(60),
        ]);

        $result = $this->service->calculateForOrg(
            null,
            Carbon::now()->subDays(28),
            Carbon::now()
        );

        $this->assertEquals(5, $result->deliveredEvents);
    }

    public function test_calculateForOrg_respects_organization_filter(): void
    {
        // Create visits for org 1
        ServiceAssignment::factory()->count(5)->create([
            'service_provider_organization_id' => 1,
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        // Create visits for org 2
        ServiceAssignment::factory()->count(3)->create([
            'service_provider_organization_id' => 2,
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        $result = $this->service->calculateForOrg(1);

        $this->assertEquals(5, $result->deliveredEvents);
    }

    public function test_calculate_returns_legacy_array_format(): void
    {
        ServiceAssignment::factory()->count(5)->create([
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        $result = $this->service->calculate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('delivered', $result);
        $this->assertArrayHasKey('missed', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('missed_rate', $result);
        $this->assertArrayHasKey('compliance', $result);
        $this->assertArrayHasKey('period', $result);
    }

    public function test_compliance_band_boundaries(): void
    {
        // Band A: 0%
        $dtoA = MissedCareMetricsDTO::fromCalculation(0, 100, Carbon::now()->subDays(7), Carbon::now());
        $this->assertEquals('A', $dtoA->getComplianceBand());

        // Band B: 0.01% - 0.5%
        $dtoB = MissedCareMetricsDTO::fromCalculation(1, 500, Carbon::now()->subDays(7), Carbon::now());
        $this->assertEquals('B', $dtoB->getComplianceBand()); // 0.2%

        // Band C: > 0.5%
        $dtoC = MissedCareMetricsDTO::fromCalculation(5, 100, Carbon::now()->subDays(7), Carbon::now());
        $this->assertEquals('C', $dtoC->getComplianceBand()); // ~4.8%
    }

    public function test_fallback_to_status_when_verification_status_null(): void
    {
        // Create completed visits without verification_status set
        ServiceAssignment::factory()->count(5)->create([
            'verification_status' => null,
            'status' => ServiceAssignment::STATUS_COMPLETED,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        // Create missed visits without verification_status set
        ServiceAssignment::factory()->count(2)->create([
            'verification_status' => null,
            'status' => ServiceAssignment::STATUS_MISSED,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        $result = $this->service->calculateForOrg();

        $this->assertEquals(5, $result->deliveredEvents);
        $this->assertEquals(2, $result->missedEvents);
    }
}
