<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Region;
use App\Models\RegionArea;
use Illuminate\Support\Facades\Log;

/**
 * RegionService - Auto-assigns patients to regions based on geographic data.
 *
 * This service uses ONLY metadata lookups (RegionArea table) to determine regions.
 * No region names or codes are hardcoded in this service.
 *
 * Assignment priority:
 * 1. FSA prefix matching (first 3 chars of postal code)
 * 2. Lat/lng bounding box matching (fallback)
 * 3. Null (logged for admin review)
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class RegionService
{
    /**
     * Assign a region to a patient based on their geographic data.
     *
     * Uses metadata-driven lookups - no hardcoded region assignments.
     *
     * @param Patient $patient The patient to assign a region to
     * @return bool True if region was assigned, false otherwise
     */
    public function assignRegion(Patient $patient): bool
    {
        // Step 1: Try FSA prefix matching from postal code
        if ($patient->postal_code) {
            $region = $this->findRegionByPostalCode($patient->postal_code);
            if ($region) {
                $patient->region()->associate($region);
                $patient->save();

                Log::info('Region assigned via postal code', [
                    'patient_id' => $patient->id,
                    'postal_code' => $patient->postal_code,
                    'region_id' => $region->id,
                    'region_code' => $region->code,
                ]);

                return true;
            }
        }

        // Step 2: Try lat/lng bounding box matching
        if ($patient->lat && $patient->lng) {
            $region = $this->findRegionByCoordinates($patient->lat, $patient->lng);
            if ($region) {
                $patient->region()->associate($region);
                $patient->save();

                Log::info('Region assigned via coordinates', [
                    'patient_id' => $patient->id,
                    'lat' => $patient->lat,
                    'lng' => $patient->lng,
                    'region_id' => $region->id,
                    'region_code' => $region->code,
                ]);

                return true;
            }
        }

        // Step 3: No match found - log for admin review
        Log::warning('Could not assign region to patient', [
            'patient_id' => $patient->id,
            'postal_code' => $patient->postal_code,
            'lat' => $patient->lat,
            'lng' => $patient->lng,
        ]);

        return false;
    }

    /**
     * Find a region by postal code using FSA prefix lookup.
     *
     * @param string $postalCode Canadian postal code (e.g., "M5G 1X8")
     * @return Region|null
     */
    public function findRegionByPostalCode(string $postalCode): ?Region
    {
        // Extract FSA (first 3 characters, ignoring spaces)
        $fsa = $this->extractFsa($postalCode);
        if (!$fsa) {
            return null;
        }

        $area = RegionArea::findByFsa($fsa);
        if (!$area) {
            return null;
        }

        return $area->region;
    }

    /**
     * Find a region by coordinates using bounding box lookup.
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return Region|null
     */
    public function findRegionByCoordinates(float $lat, float $lng): ?Region
    {
        $area = RegionArea::findByCoordinates($lat, $lng);
        if (!$area) {
            return null;
        }

        return $area->region;
    }

    /**
     * Extract FSA (Forward Sortation Area) from postal code.
     *
     * FSA is the first 3 characters of a Canadian postal code.
     *
     * @param string $postalCode Raw postal code (may contain spaces)
     * @return string|null Uppercase FSA or null if invalid
     */
    public function extractFsa(string $postalCode): ?string
    {
        // Remove spaces and convert to uppercase
        $cleaned = strtoupper(str_replace(' ', '', trim($postalCode)));

        // Canadian postal codes are 6 characters (e.g., "M5G1X8")
        // FSA is the first 3 characters
        if (strlen($cleaned) < 3) {
            return null;
        }

        $fsa = substr($cleaned, 0, 3);

        // Validate FSA format: Letter-Number-Letter
        // First character: A-Z (excluding D, F, I, O, Q, U, W, Z)
        // Second character: 0-9
        // Third character: A-Z
        if (!preg_match('/^[A-CEGHJ-NPR-TV-Y][0-9][A-Z]$/', $fsa)) {
            return null;
        }

        return $fsa;
    }

    /**
     * Batch assign regions to patients without regions.
     *
     * Useful for importing patients or fixing data.
     *
     * @param int $limit Maximum patients to process (0 = unlimited)
     * @return array ['assigned' => int, 'failed' => int]
     */
    public function batchAssignRegions(int $limit = 0): array
    {
        $query = Patient::whereNull('region_id')
            ->where(function ($q) {
                $q->whereNotNull('postal_code')
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('lat')
                            ->whereNotNull('lng');
                    });
            });

        if ($limit > 0) {
            $query->limit($limit);
        }

        $patients = $query->get();
        $assigned = 0;
        $failed = 0;

        foreach ($patients as $patient) {
            if ($this->assignRegion($patient)) {
                $assigned++;
            } else {
                $failed++;
            }
        }

        Log::info('Batch region assignment complete', [
            'total' => $patients->count(),
            'assigned' => $assigned,
            'failed' => $failed,
        ]);

        return [
            'assigned' => $assigned,
            'failed' => $failed,
        ];
    }

    /**
     * Get all active regions.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveRegions()
    {
        return Region::active()->orderBy('name')->get();
    }

    /**
     * Get region statistics (patient count per region).
     *
     * @return array
     */
    public function getRegionStatistics(): array
    {
        return Region::active()
            ->withCount('patients')
            ->orderBy('name')
            ->get()
            ->map(fn($region) => [
                'id' => $region->id,
                'code' => $region->code,
                'name' => $region->name,
                'patient_count' => $region->patients_count,
            ])
            ->toArray();
    }
}
