<?php

namespace App\Services\Travel;

use Carbon\Carbon;

/**
 * TravelTimeService Interface
 *
 * Abstraction for calculating travel times between locations.
 * Used by SchedulingEngine to validate travel feasibility between appointments.
 *
 * Implementations:
 * - GoogleDistanceMatrixTravelTimeService: Production (uses Google Distance Matrix API)
 * - FakeTravelTimeService: Local/testing/seeding (deterministic, no API calls)
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
interface TravelTimeService
{
    /**
     * Calculate travel time in minutes between two locations.
     *
     * @param float $originLat Origin latitude
     * @param float $originLng Origin longitude
     * @param float $destLat Destination latitude
     * @param float $destLng Destination longitude
     * @param Carbon $departureTime Departure time (for traffic-aware calculations)
     * @return int Travel time in minutes (rounded up)
     */
    public function getTravelMinutes(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        Carbon $departureTime
    ): int;

    /**
     * Calculate travel times between one origin and multiple destinations.
     *
     * Useful for batch calculations (e.g., finding optimal assignment order).
     *
     * @param float $originLat Origin latitude
     * @param float $originLng Origin longitude
     * @param array $destinations Array of ['lat' => float, 'lng' => float]
     * @param Carbon $departureTime Departure time
     * @return array Array of travel times in minutes, matching destination order
     */
    public function getTravelMinutesMultiple(
        float $originLat,
        float $originLng,
        array $destinations,
        Carbon $departureTime
    ): array;

    /**
     * Check if this is a real (API-based) implementation.
     *
     * Used to determine if caching/rate limiting should be applied.
     *
     * @return bool True if this uses a real API, false if fake/mocked
     */
    public function isRealImplementation(): bool;
}
