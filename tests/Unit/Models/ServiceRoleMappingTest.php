<?php

namespace Tests\Unit\Models;

use App\Models\ServiceRoleMapping;
use App\Models\ServiceType;
use App\Models\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ServiceRoleMappingTest
 *
 * Tests for the ServiceRoleMapping metadata model.
 * Verifies that staff roles are correctly mapped to the services they can deliver.
 */
class ServiceRoleMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        // Create staff roles
        StaffRole::create(['code' => 'PSW', 'name' => 'Personal Support Worker', 'is_clinical' => false]);
        StaffRole::create(['code' => 'RN', 'name' => 'Registered Nurse', 'is_clinical' => true]);
        StaffRole::create(['code' => 'RPN', 'name' => 'Registered Practical Nurse', 'is_clinical' => true]);
        StaffRole::create(['code' => 'OT', 'name' => 'Occupational Therapist', 'is_clinical' => true]);

        // Create service types
        ServiceType::create(['code' => 'PSW', 'name' => 'Personal Care (PSW)', 'active' => true]);
        ServiceType::create(['code' => 'HMK', 'name' => 'Homemaking', 'active' => true]);
        ServiceType::create(['code' => 'NUR', 'name' => 'Nursing (RN/RPN)', 'active' => true]);
        ServiceType::create(['code' => 'OT', 'name' => 'Occupational Therapy', 'active' => true]);
        ServiceType::create(['code' => 'BEH', 'name' => 'Behavioral Supports', 'active' => true]);

        // Create role-service mappings
        $pswRole = StaffRole::where('code', 'PSW')->first();
        $rnRole = StaffRole::where('code', 'RN')->first();
        $rpnRole = StaffRole::where('code', 'RPN')->first();
        $otRole = StaffRole::where('code', 'OT')->first();

        $pswService = ServiceType::where('code', 'PSW')->first();
        $hmkService = ServiceType::where('code', 'HMK')->first();
        $nurService = ServiceType::where('code', 'NUR')->first();
        $otService = ServiceType::where('code', 'OT')->first();
        $behService = ServiceType::where('code', 'BEH')->first();

        // PSW can deliver PSW and HMK services
        ServiceRoleMapping::create([
            'staff_role_id' => $pswRole->id,
            'service_type_id' => $pswService->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        ServiceRoleMapping::create([
            'staff_role_id' => $pswRole->id,
            'service_type_id' => $hmkService->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        // RN can deliver NUR and BEH services
        ServiceRoleMapping::create([
            'staff_role_id' => $rnRole->id,
            'service_type_id' => $nurService->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        ServiceRoleMapping::create([
            'staff_role_id' => $rnRole->id,
            'service_type_id' => $behService->id,
            'is_primary' => false,
            'requires_delegation' => false,
            'is_active' => true,
        ]);

        // RPN can also deliver NUR (secondary)
        ServiceRoleMapping::create([
            'staff_role_id' => $rpnRole->id,
            'service_type_id' => $nurService->id,
            'is_primary' => false,
            'is_active' => true,
        ]);

        // OT delivers OT service
        ServiceRoleMapping::create([
            'staff_role_id' => $otRole->id,
            'service_type_id' => $otService->id,
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_returns_service_types_for_role()
    {
        $pswRole = StaffRole::where('code', 'PSW')->first();

        $services = ServiceRoleMapping::getServiceTypesForRole($pswRole->id);

        $this->assertCount(2, $services);
        $this->assertTrue($services->contains('code', 'PSW'));
        $this->assertTrue($services->contains('code', 'HMK'));
    }

    /** @test */
    public function it_determines_if_role_can_deliver_service()
    {
        $pswRole = StaffRole::where('code', 'PSW')->first();
        $pswService = ServiceType::where('code', 'PSW')->first();
        $nurService = ServiceType::where('code', 'NUR')->first();

        // PSW can deliver PSW service
        $this->assertTrue(ServiceRoleMapping::canRoleDeliverService($pswRole->id, $pswService->id));

        // PSW cannot deliver NUR service
        $this->assertFalse(ServiceRoleMapping::canRoleDeliverService($pswRole->id, $nurService->id));
    }

    /** @test */
    public function it_returns_primary_role_for_service()
    {
        $nurService = ServiceType::where('code', 'NUR')->first();

        // RN is primary for NUR, RPN is secondary
        $primaryRole = ServiceRoleMapping::getPrimaryRoleForService($nurService->id);

        $this->assertNotNull($primaryRole);
        $this->assertEquals('RN', $primaryRole->code);
    }

    /** @test */
    public function it_filters_active_mappings()
    {
        $pswRole = StaffRole::where('code', 'PSW')->first();
        $pswService = ServiceType::where('code', 'PSW')->first();

        // Deactivate one mapping
        ServiceRoleMapping::where('staff_role_id', $pswRole->id)
            ->where('service_type_id', $pswService->id)
            ->update(['is_active' => false]);

        // Active scope should exclude the inactive mapping
        $activeServices = ServiceRoleMapping::active()
            ->forRole($pswRole->id)
            ->get();

        $this->assertCount(1, $activeServices);
        $this->assertEquals('HMK', $activeServices->first()->serviceType->code);
    }

    /** @test */
    public function it_filters_primary_mappings()
    {
        $rnRole = StaffRole::where('code', 'RN')->first();

        // RN has NUR (primary) and BEH (not primary)
        $primaryMappings = ServiceRoleMapping::primary()
            ->forRole($rnRole->id)
            ->get();

        $this->assertCount(1, $primaryMappings);
        $this->assertEquals('NUR', $primaryMappings->first()->serviceType->code);
    }

    /** @test */
    public function it_filters_by_role_code()
    {
        $mappings = ServiceRoleMapping::forRoleCode('PSW')->get();

        $this->assertCount(2, $mappings);
    }

    /** @test */
    public function it_filters_by_service_type()
    {
        $nurService = ServiceType::where('code', 'NUR')->first();

        // Both RN and RPN can deliver NUR
        $mappings = ServiceRoleMapping::forServiceType($nurService->id)->get();

        $this->assertCount(2, $mappings);
    }

    /** @test */
    public function it_loads_relationships_correctly()
    {
        $mapping = ServiceRoleMapping::with(['staffRole', 'serviceType'])->first();

        $this->assertNotNull($mapping->staffRole);
        $this->assertNotNull($mapping->serviceType);
        $this->assertInstanceOf(StaffRole::class, $mapping->staffRole);
        $this->assertInstanceOf(ServiceType::class, $mapping->serviceType);
    }
}
