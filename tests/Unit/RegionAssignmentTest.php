<?php

namespace Tests\Unit;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Region;
use App\Models\RegionArea;
use App\Models\User;
use App\Services\RegionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RegionAssignmentTest - Tests for metadata-driven region assignment.
 *
 * Verifies that:
 * - Region assignment via FSA (postal code prefix) works correctly
 * - Region assignment via lat/lng bounding box fallback works
 * - No hardcoded region logic exists
 */
class RegionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected RegionService $regionService;
    protected Region $torontoCentral;
    protected Region $centralEast;
    protected Hospital $hospital;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regionService = new RegionService();

        // Create regions
        $this->torontoCentral = Region::create([
            'code' => 'TORONTO_CENTRAL',
            'name' => 'Toronto Central',
            'is_active' => true,
        ]);

        $this->centralEast = Region::create([
            'code' => 'CENTRAL_EAST',
            'name' => 'Central East',
            'is_active' => true,
        ]);

        // Create region areas with FSA mappings
        RegionArea::create([
            'region_id' => $this->torontoCentral->id,
            'fsa_prefix' => 'M5G', // Downtown Toronto
        ]);
        RegionArea::create([
            'region_id' => $this->torontoCentral->id,
            'fsa_prefix' => 'M5B', // St. Lawrence
        ]);

        RegionArea::create([
            'region_id' => $this->centralEast->id,
            'fsa_prefix' => 'M1C', // Scarborough
            // Also add bounding box for lat/lng fallback testing
            'min_lat' => 43.75,
            'max_lat' => 43.85,
            'min_lng' => -79.20,
            'max_lng' => -79.10,
        ]);

        // Create hospital for patients
        $hospitalUser = User::create([
            'name' => 'Test Hospital',
            'email' => 'hospital@test.com',
            'password' => bcrypt('secret'),
            'role' => 'hospital',
        ]);

        $this->hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'documents' => null,
        ]);
    }

    public function test_region_assigned_via_postal_code_fsa()
    {
        $patient = $this->createPatient('Patient Downtown', 'M5G 1X8');

        $this->regionService->assignRegion($patient);

        $patient->refresh();
        $this->assertNotNull($patient->region_id);
        $this->assertEquals($this->torontoCentral->id, $patient->region_id);
    }

    public function test_different_fsa_assigns_different_region()
    {
        $patient = $this->createPatient('Patient Scarborough', 'M1C 2T4');

        $this->regionService->assignRegion($patient);

        $patient->refresh();
        $this->assertNotNull($patient->region_id);
        $this->assertEquals($this->centralEast->id, $patient->region_id);
    }

    public function test_region_assigned_via_lat_lng_fallback()
    {
        // Create patient with coordinates in Central East bounding box but no postal code
        $patient = $this->createPatientWithCoords('Patient GeoOnly', null, 43.80, -79.15);

        $this->regionService->assignRegion($patient);

        $patient->refresh();
        $this->assertNotNull($patient->region_id);
        $this->assertEquals($this->centralEast->id, $patient->region_id);
    }

    public function test_no_region_when_no_matching_fsa_or_coords()
    {
        // Create patient with unrecognized postal code and no coords
        $patient = $this->createPatient('Patient Unknown', 'X9Z 0A0');

        $this->regionService->assignRegion($patient);

        $patient->refresh();
        $this->assertNull($patient->region_id);
    }

    public function test_fsa_priority_over_lat_lng()
    {
        // Patient has Toronto Central postal code but coords in Central East area
        $patient = $this->createPatientWithCoords('Patient Mixed', 'M5G 1X8', 43.80, -79.15);

        $this->regionService->assignRegion($patient);

        $patient->refresh();
        // FSA should take priority
        $this->assertEquals($this->torontoCentral->id, $patient->region_id);
    }

    public function test_find_by_fsa_static_method()
    {
        $area = RegionArea::findByFsa('M5G');
        $this->assertNotNull($area);
        $this->assertEquals($this->torontoCentral->id, $area->region_id);

        $noArea = RegionArea::findByFsa('X9Z');
        $this->assertNull($noArea);
    }

    public function test_find_by_coordinates_static_method()
    {
        // Within bounding box
        $area = RegionArea::findByCoordinates(43.80, -79.15);
        $this->assertNotNull($area);
        $this->assertEquals($this->centralEast->id, $area->region_id);

        // Outside any bounding box
        $noArea = RegionArea::findByCoordinates(45.0, -75.0);
        $this->assertNull($noArea);
    }

    public function test_region_find_by_code()
    {
        $region = Region::findByCode('TORONTO_CENTRAL');
        $this->assertNotNull($region);
        $this->assertEquals($this->torontoCentral->id, $region->id);

        $noRegion = Region::findByCode('NONEXISTENT');
        $this->assertNull($noRegion);
    }

    protected function createPatient(string $name, ?string $postalCode): Patient
    {
        static $counter = 0;
        $counter++;

        $patientUser = User::create([
            'name' => $name,
            'email' => "patient{$counter}@test.com",
            'password' => bcrypt('secret'),
            'role' => 'patient',
        ]);

        return Patient::create([
            'user_id' => $patientUser->id,
            'hospital_id' => $this->hospital->id,
            'status' => 'Active',
            'gender' => 'Male',
            'postal_code' => $postalCode,
        ]);
    }

    protected function createPatientWithCoords(
        string $name,
        ?string $postalCode,
        ?float $lat,
        ?float $lng
    ): Patient {
        static $counter = 0;
        $counter++;

        $patientUser = User::create([
            'name' => $name,
            'email' => "patient_coords{$counter}@test.com",
            'password' => bcrypt('secret'),
            'role' => 'patient',
        ]);

        return Patient::create([
            'user_id' => $patientUser->id,
            'hospital_id' => $this->hospital->id,
            'status' => 'Active',
            'gender' => 'Male',
            'postal_code' => $postalCode,
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }
}
