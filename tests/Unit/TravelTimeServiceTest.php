<?php

namespace Tests\Unit;

use App\Services\Travel\FakeTravelTimeService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * TravelTimeServiceTest - Tests for FakeTravelTimeService.
 *
 * Verifies that:
 * - FakeTravelTimeService returns deterministic values
 * - Distance-based calculations are reasonable
 * - Service is suitable for testing and seeding
 */
class TravelTimeServiceTest extends TestCase
{
    protected FakeTravelTimeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FakeTravelTimeService();
    }

    public function test_same_location_returns_minimum_time()
    {
        // Same coordinates should return minimum travel time
        $result = $this->service->getTravelMinutes(
            43.6532, -79.3832,  // Toronto downtown
            43.6532, -79.3832,  // Same location
            Carbon::now()
        );

        $this->assertEquals(10, $result); // Minimum is 10 minutes
    }

    public function test_nearby_locations_return_reasonable_time()
    {
        // ~1km apart (downtown Toronto to CN Tower)
        $result = $this->service->getTravelMinutes(
            43.6532, -79.3832,  // Toronto downtown
            43.6426, -79.3871,  // CN Tower
            Carbon::now()
        );

        // Should be around 10-15 minutes for short distance
        $this->assertGreaterThanOrEqual(10, $result);
        $this->assertLessThanOrEqual(20, $result);
    }

    public function test_distant_locations_return_longer_time()
    {
        // ~25km apart (Downtown to Scarborough)
        $result = $this->service->getTravelMinutes(
            43.6532, -79.3832,  // Downtown Toronto
            43.7731, -79.2580,  // Scarborough
            Carbon::now()
        );

        // Should be 40-60 minutes for longer distance
        $this->assertGreaterThanOrEqual(30, $result);
        $this->assertLessThanOrEqual(60, $result);
    }

    public function test_get_travel_minutes_multiple()
    {
        $destinations = [
            ['lat' => 43.6532, 'lng' => -79.3832], // Same location
            ['lat' => 43.6426, 'lng' => -79.3871], // Nearby
            ['lat' => 43.7731, 'lng' => -79.2580], // Distant
        ];

        $results = $this->service->getTravelMinutesMultiple(
            43.6532, -79.3832,
            $destinations,
            Carbon::now()
        );

        $this->assertCount(3, $results);
        $this->assertEquals(10, $results[0]); // Same location = minimum
        $this->assertGreaterThanOrEqual(10, $results[1]); // Nearby
        $this->assertGreaterThanOrEqual(30, $results[2]); // Distant
    }

    public function test_is_not_real_implementation()
    {
        $this->assertFalse($this->service->isRealImplementation());
    }

    public function test_fixed_factory_returns_constant_time()
    {
        $fixed = FakeTravelTimeService::fixed(20);

        $result1 = $fixed->getTravelMinutes(43.6, -79.3, 43.7, -79.4, Carbon::now());
        $result2 = $fixed->getTravelMinutes(43.6, -79.3, 44.0, -80.0, Carbon::now());

        // Fixed service should return the same value regardless of distance
        $this->assertEquals(20, $result1);
        $this->assertEquals(20, $result2);
    }

    public function test_results_are_deterministic()
    {
        $lat1 = 43.6564;
        $lng1 = -79.3887;
        $lat2 = 43.7123;
        $lng2 = -79.3774;
        $departureTime = Carbon::parse('2024-01-15 09:00:00');

        $result1 = $this->service->getTravelMinutes($lat1, $lng1, $lat2, $lng2, $departureTime);
        $result2 = $this->service->getTravelMinutes($lat1, $lng1, $lat2, $lng2, $departureTime);

        // Results should be identical for same inputs
        $this->assertEquals($result1, $result2);
    }

    public function test_departure_time_does_not_affect_fake_results()
    {
        // FakeTravelTimeService ignores departure time (no traffic simulation)
        $morning = Carbon::parse('2024-01-15 08:00:00');
        $afternoon = Carbon::parse('2024-01-15 17:00:00');

        $result1 = $this->service->getTravelMinutes(43.6, -79.3, 43.7, -79.4, $morning);
        $result2 = $this->service->getTravelMinutes(43.6, -79.3, 43.7, -79.4, $afternoon);

        $this->assertEquals($result1, $result2);
    }

    public function test_configurable_parameters()
    {
        $customService = (new FakeTravelTimeService())
            ->setMinimumMinutes(5)
            ->setMaximumMinutes(30)
            ->setAverageSpeed(50); // Faster speed

        $result = $customService->getTravelMinutes(
            43.6532, -79.3832,
            43.6532, -79.3832, // Same location
            Carbon::now()
        );

        // Should return custom minimum
        $this->assertEquals(5, $result);
    }
}
