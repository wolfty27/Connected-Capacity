<?php

namespace App\Services\Travel;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Distance Matrix API implementation of TravelTimeService.
 *
 * Uses Google Distance Matrix API to calculate traffic-aware travel times.
 * Requires GOOGLE_MAPS_API_KEY environment variable.
 *
 * Features:
 * - Traffic-aware calculations (uses duration_in_traffic when available)
 * - Caching to reduce API calls and costs
 * - Fallback to straight-line distance estimate if API fails
 *
 * API Documentation:
 * @see https://developers.google.com/maps/documentation/distance-matrix/overview
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class GoogleDistanceMatrixTravelTimeService implements TravelTimeService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    // Cache duration: 1 hour (traffic patterns don't change drastically)
    protected int $cacheTtl = 3600;

    // Minimum travel time in minutes (even for same location)
    protected int $minimumMinutes = 5;

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key', env('GOOGLE_MAPS_API_KEY', ''));

        if (empty($this->apiKey)) {
            Log::warning('GOOGLE_MAPS_API_KEY not configured. Travel time calculations will use estimates.');
        }
    }

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
    ): int {
        // Check if API key is available
        if (empty($this->apiKey)) {
            return $this->estimateFromDistance($originLat, $originLng, $destLat, $destLng);
        }

        // Generate cache key (rounded coordinates + hour of day for traffic variation)
        $cacheKey = $this->getCacheKey($originLat, $originLng, $destLat, $destLng, $departureTime);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use (
            $originLat, $originLng, $destLat, $destLng, $departureTime
        ) {
            return $this->fetchTravelTime($originLat, $originLng, $destLat, $destLng, $departureTime);
        });
    }

    /**
     * Calculate travel times between one origin and multiple destinations.
     *
     * Uses a single API call for efficiency.
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
    ): array {
        if (empty($destinations)) {
            return [];
        }

        // Check if API key is available
        if (empty($this->apiKey)) {
            return array_map(function ($dest) use ($originLat, $originLng) {
                return $this->estimateFromDistance($originLat, $originLng, $dest['lat'], $dest['lng']);
            }, $destinations);
        }

        // Build destinations string
        $destStrings = array_map(
            fn($d) => "{$d['lat']},{$d['lng']}",
            $destinations
        );

        try {
            $response = Http::get($this->baseUrl, [
                'origins' => "{$originLat},{$originLng}",
                'destinations' => implode('|', $destStrings),
                'mode' => 'driving',
                'departure_time' => $departureTime->timestamp,
                'key' => $this->apiKey,
            ]);

            if (!$response->successful()) {
                Log::error('Google Distance Matrix API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                // Fallback to estimates
                return array_map(function ($dest) use ($originLat, $originLng) {
                    return $this->estimateFromDistance($originLat, $originLng, $dest['lat'], $dest['lng']);
                }, $destinations);
            }

            $data = $response->json();

            if ($data['status'] !== 'OK' || empty($data['rows'])) {
                Log::warning('Google Distance Matrix returned non-OK status', [
                    'status' => $data['status'] ?? 'unknown',
                ]);
                return array_map(function ($dest) use ($originLat, $originLng) {
                    return $this->estimateFromDistance($originLat, $originLng, $dest['lat'], $dest['lng']);
                }, $destinations);
            }

            $results = [];
            foreach ($data['rows'][0]['elements'] as $index => $element) {
                if ($element['status'] === 'OK') {
                    // Prefer duration_in_traffic, fall back to duration
                    $seconds = $element['duration_in_traffic']['value']
                        ?? $element['duration']['value']
                        ?? 0;
                    $results[] = max($this->minimumMinutes, (int) ceil($seconds / 60));
                } else {
                    // Fallback for this destination
                    $results[] = $this->estimateFromDistance(
                        $originLat,
                        $originLng,
                        $destinations[$index]['lat'],
                        $destinations[$index]['lng']
                    );
                }
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('Google Distance Matrix API exception', [
                'error' => $e->getMessage(),
            ]);

            // Fallback to estimates
            return array_map(function ($dest) use ($originLat, $originLng) {
                return $this->estimateFromDistance($originLat, $originLng, $dest['lat'], $dest['lng']);
            }, $destinations);
        }
    }

    /**
     * Check if this is a real (API-based) implementation.
     */
    public function isRealImplementation(): bool
    {
        return true;
    }

    /**
     * Fetch travel time from Google Distance Matrix API.
     */
    protected function fetchTravelTime(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        Carbon $departureTime
    ): int {
        try {
            $response = Http::get($this->baseUrl, [
                'origins' => "{$originLat},{$originLng}",
                'destinations' => "{$destLat},{$destLng}",
                'mode' => 'driving',
                'departure_time' => $departureTime->timestamp,
                'key' => $this->apiKey,
            ]);

            if (!$response->successful()) {
                Log::error('Google Distance Matrix API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $this->estimateFromDistance($originLat, $originLng, $destLat, $destLng);
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                Log::warning('Google Distance Matrix returned non-OK status', [
                    'status' => $data['status'],
                    'error_message' => $data['error_message'] ?? null,
                ]);
                return $this->estimateFromDistance($originLat, $originLng, $destLat, $destLng);
            }

            $element = $data['rows'][0]['elements'][0] ?? null;

            if (!$element || $element['status'] !== 'OK') {
                Log::warning('Google Distance Matrix element status not OK', [
                    'element_status' => $element['status'] ?? 'missing',
                ]);
                return $this->estimateFromDistance($originLat, $originLng, $destLat, $destLng);
            }

            // Prefer duration_in_traffic (traffic-aware), fall back to duration
            $seconds = $element['duration_in_traffic']['value']
                ?? $element['duration']['value']
                ?? 0;

            $minutes = (int) ceil($seconds / 60);

            return max($this->minimumMinutes, $minutes);
        } catch (\Exception $e) {
            Log::error('Google Distance Matrix API exception', [
                'error' => $e->getMessage(),
            ]);

            return $this->estimateFromDistance($originLat, $originLng, $destLat, $destLng);
        }
    }

    /**
     * Estimate travel time based on straight-line distance.
     *
     * Uses Haversine formula for distance, assumes 30 km/h average speed
     * (urban driving with traffic).
     */
    protected function estimateFromDistance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): int {
        $distanceKm = $this->haversineDistance($originLat, $originLng, $destLat, $destLng);

        // Assume 30 km/h average urban speed (includes traffic, parking, etc.)
        $avgSpeedKmh = 30;

        // Add 5 minutes for parking/walking at each end
        $baseMinutes = ($distanceKm / $avgSpeedKmh) * 60;
        $minutes = $baseMinutes + 10;

        return max($this->minimumMinutes, (int) ceil($minutes));
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
     * Generate cache key for travel time lookup.
     *
     * Rounds coordinates to 3 decimal places (~111m precision) and includes
     * hour of day for traffic pattern variation.
     */
    protected function getCacheKey(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        Carbon $departureTime
    ): string {
        // Round to 3 decimal places (~111m precision)
        $originLatRounded = round($originLat, 3);
        $originLngRounded = round($originLng, 3);
        $destLatRounded = round($destLat, 3);
        $destLngRounded = round($destLng, 3);

        // Include hour of day for traffic variation
        $hour = $departureTime->hour;

        return sprintf(
            'travel_time:%s,%s:%s,%s:%d',
            $originLatRounded,
            $originLngRounded,
            $destLatRounded,
            $destLngRounded,
            $hour
        );
    }
}
