<?php

namespace Tests\Unit;

use App\Models\ServiceProviderOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceProviderOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regions_and_capabilities_are_cast_to_arrays(): void
    {
        $org = ServiceProviderOrganization::factory()->create([
            'regions' => ['Central', 'West'],
            'capabilities' => ['dementia', 'clinical'],
        ]);

        $this->assertIsArray($org->regions);
        $this->assertSame(['Central', 'West'], $org->regions);
        $this->assertIsArray($org->capabilities);
        $this->assertSame(['dementia', 'clinical'], $org->capabilities);
    }

    public function test_factory_creates_valid_defaults(): void
    {
        $org = ServiceProviderOrganization::factory()->create();

        $this->assertNotEmpty($org->name);
        $this->assertIsArray($org->regions);
        $this->assertNotEmpty($org->regions);
        $this->assertIsArray($org->capabilities);
        $this->assertNotEmpty($org->capabilities);
        $this->assertTrue($org->active);
    }
}
