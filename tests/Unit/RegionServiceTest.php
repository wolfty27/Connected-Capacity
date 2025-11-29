<?php

namespace Tests\Unit;

use App\Models\Patient;
use App\Models\Region;
use App\Models\RegionArea;
use App\Models\User;
use App\Models\Hospital;
use App\Services\RegionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RegionServiceTest - Tests for metadata-driven region assignment.
 *
 * Verifies that:
 * - Postal code FSA mapping works correctly
 * - Region assignment is metadata-driven (not hardcoded)
 * - Edge cases are handled properly
 */
class RegionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RegionService $regionService;
    protected Hospital $hospital;

    protected function setUp(): void
    {
        parent::setUp();
        $this->regionService = new RegionService();

        // Create a hospital for patient creation
        $hospitalUser = User::create([
            'name' => 'Test Hospital',
            'email' => 'hospital@test.com',
            'password' => bcrypt('secret'),
            'role' => 'hospital',
        ]);

        $this->hospital = Hospital::create([
            'user_id' => $hospitalUser->id,
            'documents' => null,
            'website' => 'https://test-hospital.com',
            'calendly' => null,
        ]);
    }

    public function test_assign_region_via_postal_code_fsa()
    {
        // Create region and FSA mapping
        $region = Region::create([
            'code' => 'TORONTO_CENTRAL',
            'name' => 'Toronto Central',
            'ohah_code' => 'TC',
            'is_active' => true,
        ]);

        RegionArea::create([
            'region_id' => $region->id,
            'fsa_prefix' => 'M5G',
        ]);

        // Create patient with M5G postal code
        $patient = $this->createPatient([
            'postal_code' => 'M5G 1X8',
        ]);

        // Assign region
        $result = $this->regionService->assignRegion($patient);

        $this->assertTrue($result);
        $this->assertEquals($region->id, $patient->fresh()->region_id);
    }

    public function test_assign_region_handles_different_postal_code_formats()
    {
        $region = Region::create([
            'code' => 'NORTH',
            'name' => 'North York',
            'is_active' => true,
        ]);

        RegionArea::create([
            'region_id' => $region->id,
            'fsa_prefix' => 'M2J',
        ]);

        // Test without space
        $patient1 = $this->createPatient(['postal_code' => 'M2J3K5']);
        $result1 = $this->regionService->assignRegion($patient1);
        $this->assertTrue($result1);
        $this->assertEquals($region->id, $patient1->fresh()->region_id);

        // Test with space
        $patient2 = $this->createPatient(['postal_code' => 'm2j 3k5']);
        $result2 = $this->regionService->assignRegion($patient2);
        $this->assertTrue($result2);
        $this->assertEquals($region->id, $patient2->fresh()->region_id);
    }

    public function test_assign_region_returns_false_for_unknown_fsa()
    {
        // Create region with different FSA
        $region = Region::create([
            'code' => 'EAST',
            'name' => 'East Toronto',
            'is_active' => true,
        ]);

        RegionArea::create([
            'region_id' => $region->id,
            'fsa_prefix' => 'M1B',
        ]);

        // Create patient with unknown FSA
        $patient = $this->createPatient([
            'postal_code' => 'L4A 1A1', // Not mapped
        ]);

        $result = $this->regionService->assignRegion($patient);

        $this->assertFalse($result);
        $this->assertNull($patient->fresh()->region_id);
    }

    public function test_assign_region_via_coordinates_fallback()
    {
        $region = Region::create([
            'code' => 'CENTRAL_WEST',
            'name' => 'Central West',
            'is_active' => true,
        ]);

        // Create area with bounding box
        RegionArea::create([
            'region_id' => $region->id,
            'fsa_prefix' => 'XXX', // Non-matching FSA
            'min_lat' => 43.60,
            'max_lat' => 43.70,
            'min_lng' => -79.50,
            'max_lng' => -79.40,
        ]);

        // Create patient with coordinates in bounding box but no postal code
        $patient = $this->createPatient([
            'lat' => 43.65,
            'lng' => -79.45,
        ]);

        $result = $this->regionService->assignRegion($patient);

        $this->assertTrue($result);
        $this->assertEquals($region->id, $patient->fresh()->region_id);
    }

    public function test_find_region_by_postal_code()
    {
        $region = Region::create([
            'code' => 'CENTRAL_EAST',
            'name' => 'Central East',
            'is_active' => true,
        ]);

        RegionArea::create([
            'region_id' => $region->id,
            'fsa_prefix' => 'M4C',
        ]);

        $found = $this->regionService->findRegionByPostalCode('M4C 2T5');

        $this->assertNotNull($found);
        $this->assertEquals($region->id, $found->id);
    }

    public function test_extract_fsa_from_various_formats()
    {
        // Valid FSAs
        $this->assertEquals('M5G', $this->regionService->extractFsa('M5G 1X8'));
        $this->assertEquals('M5G', $this->regionService->extractFsa('m5g1x8'));
        $this->assertEquals('M5G', $this->regionService->extractFsa('M5G'));
        $this->assertEquals('L4A', $this->regionService->extractFsa('L4A 2B3'));

        // Invalid FSAs
        $this->assertNull($this->regionService->extractFsa('12'));
        $this->assertNull($this->regionService->extractFsa(''));
        $this->assertNull($this->regionService->extractFsa('123')); // Numbers only
    }

    public function test_batch_assign_regions()
    {
        $region = Region::create([
            'code' => 'TORONTO_CENTRAL',
            'name' => 'Toronto Central',
            'is_active' => true,
        ]);

        RegionArea::create([
            'region_id' => $region->id,
            'fsa_prefix' => 'M5G',
        ]);

        // Create patients without regions
        $this->createPatient(['postal_code' => 'M5G 1X8']);
        $this->createPatient(['postal_code' => 'M5G 2C4']);
        $this->createPatient(['postal_code' => 'L4A 1A1']); // Unknown

        $result = $this->regionService->batchAssignRegions();

        $this->assertEquals(2, $result['assigned']);
        $this->assertEquals(1, $result['failed']);
    }

    public function test_get_region_statistics()
    {
        $region1 = Region::create([
            'code' => 'TORONTO_CENTRAL',
            'name' => 'Toronto Central',
            'is_active' => true,
        ]);

        $region2 = Region::create([
            'code' => 'NORTH',
            'name' => 'North York',
            'is_active' => true,
        ]);

        // Create patients in regions
        $this->createPatient(['region_id' => $region1->id]);
        $this->createPatient(['region_id' => $region1->id]);
        $this->createPatient(['region_id' => $region2->id]);

        $stats = $this->regionService->getRegionStatistics();

        $this->assertCount(2, $stats);
        $this->assertEquals(1, collect($stats)->firstWhere('code', 'NORTH')['patient_count']);
        $this->assertEquals(2, collect($stats)->firstWhere('code', 'TORONTO_CENTRAL')['patient_count']);
    }

    protected function createPatient(array $attributes = []): Patient
    {
        static $counter = 0;
        $counter++;

        $patientUser = User::create([
            'name' => "Test Patient {$counter}",
            'email' => "patient{$counter}@test.com",
            'password' => bcrypt('secret'),
            'role' => 'patient',
        ]);

        return Patient::create(array_merge([
            'user_id' => $patientUser->id,
            'hospital_id' => $this->hospital->id,
            'status' => 'Active',
            'gender' => 'Male',
        ], $attributes));
    }
}
