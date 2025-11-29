<?php

namespace App\Services\Travel;

use Carbon\Carbon;

/**
 * Fake implementation of TravelTimeService for local development and testing.
 *
 * Returns deterministic travel times based on straight-line distance.
 * Does not make any API calls - safe for seeding and unit tests.
 *
 * Uses realistic Toronto-scale travel time estimates:
 * - Minimum: 10 minutes (same neighborhood)
 * - Average: ~2 minutes per km (urban driving)
 * - Maximum: 60 minutes (cross-GTA)
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class FakeTravelTimeService implements TravelTimeService
{
    // Minimum travel time in minutes
    protected int $minimumMinutes = 10;

    // Maximum travel time in minutes (cap for very long distances)
    protected int $maximumMinutes = 60;

    // Base time (parking, walking, etc.)
    protected int $baseMinutes = 8;

    // Average speed in km/h for urban driving
    protected float $avgSpeedKmh = 25;

    /**
     * Calculate travel time in minutes between two locations.
     *
     * Uses Haversine formula for distance and applies urban driving estimates.
     *
     * @param float $originLat Origin latitude
     * @param float $originLng Origin longitude
     * @param float $destLat Destination latitude
     * @param float $destLng Destination longitude
     * @param Carbon $departureTime Departure time (not used in fake implementation)
     * @return int Travel time in minutes (rounded up)
     */
    public function getTravelMinutes(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        Carbon $departureTime
    ): int {
        return $this->calculateTravelTime($originLat, $originLng, $destLat, $destLng);
    }

    /**
     * Calculate travel times between one origin and multiple destinations.
     *
     * @param float $originLat Origin latitude
     * @param float $originLng Origin longitude
     * @param array $destinations Array of ['lat' => float, 'lng' => float]
     * @param Carbon $departureTime Departure time (not used in fake implementation)
     * @return array Array of travel times in minutes, matching destination order
     */
    public function getTravelMinutesMultiple(
        float $originLat,
        float $originLng,
        array $destinations,
        Carbon $departureTime
    ): array {
        return array_map(
            fn($dest) => $this->calculateTravelTime($originLat, $originLng, $dest['lat'], $dest['lng']),
            $destinations
        );
    }

    /**
     * Check if this is a real (API-based) implementation.
     */
    public function isRealImplementation(): bool
    {
        return false;
    }

    /**
     * Calculate travel time based on straight-line distance.
     */
    protected function calculateTravelTime(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): int {
        // Same location or very close
        $distanceKm = $this->haversineDistance($originLat, $originLng, $destLat, $destLng);

        if ($distanceKm < 0.5) {
            return $this->minimumMinutes;
        }

        // Calculate driving time (distance / speed in km/h * 60 for minutes)
        $drivingMinutes = ($distanceKm / $this->avgSpeedKmh) * 60;

        // Add base time (parking, walking, etc.)
        $totalMinutes = $drivingMinutes + $this->baseMinutes;

        // Clamp to min/max
        return min($this->maximumMinutes, max($this->minimumMinutes, (int) ceil($totalMinutes)));
    }

    /**
     * Calculate straight-line distance between two points using Haversine formula.
     *
     * @return float Distance in kilometers
     */
    protected function haversineDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * Set minimum travel time (useful for tests).
     */
    public function setMinimumMinutes(int $minutes): self
    {
        $this->minimumMinutes = $minutes;
        return $this;
    }

    /**
     * Set maximum travel time (useful for tests).
     */
    public function setMaximumMinutes(int $minutes): self
    {
        $this->maximumMinutes = $minutes;
        return $this;
    }

    /**
     * Set average speed (useful for tests).
     */
    public function setAverageSpeed(float $speedKmh): self
    {
        $this->avgSpeedKmh = $speedKmh;
        return $this;
    }

    /**
     * Get a fixed travel time for testing scenarios.
     *
     * Creates a mock implementation that always returns the specified value.
     */
    public static function fixed(int $minutes): self
    {
        $instance = new self();
        $instance->minimumMinutes = $minutes;
        $instance->maximumMinutes = $minutes;
        $instance->baseMinutes = $minutes;
        return $instance;
    }
}
