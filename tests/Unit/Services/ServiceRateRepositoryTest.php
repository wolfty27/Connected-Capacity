<?php

namespace Tests\Unit\Services;

use App\Models\ServiceProviderOrganization;
use App\Models\ServiceRate;
use App\Models\ServiceType;
use App\Repositories\ServiceRateRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for ServiceRateRepository
 *
 * Verifies rate resolution logic including:
 * - Organization-specific overrides vs system defaults
 * - Effective date filtering
 * - Rate creation and management
 */
class ServiceRateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceRateRepository $repository;
    protected ServiceType $serviceType;
    protected ServiceProviderOrganization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ServiceRateRepository();

        // Create test data
        $this->serviceType = ServiceType::factory()->create([
            'code' => 'PSW',
            'name' => 'Personal Support Worker',
            'active' => true,
        ]);

        $this->organization = ServiceProviderOrganization::factory()->create([
            'name' => 'Test SPO',
            'type' => 'primary',
        ]);
    }

    /**
     * Test resolving system default rate when no org rate exists.
     */
    public function test_resolves_system_default_rate_when_no_org_rate(): void
    {
        // Create system default rate
        $defaultRate = ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => null, // System default
            'unit_type' => 'hour',
            'rate_cents' => 3500, // $35
            'effective_from' => Carbon::today()->subMonth(),
        ]);

        $rate = $this->repository->getEffectiveRate(
            $this->serviceType,
            $this->organization,
            Carbon::today()
        );

        $this->assertNotNull($rate);
        $this->assertEquals($defaultRate->id, $rate->id);
        $this->assertEquals(3500, $rate->rate_cents);
        $this->assertTrue($rate->is_system_default);
    }

    /**
     * Test org-specific rate overrides system default.
     */
    public function test_org_rate_overrides_system_default(): void
    {
        // Create system default rate
        ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => null,
            'unit_type' => 'hour',
            'rate_cents' => 3500,
            'effective_from' => Carbon::today()->subMonth(),
        ]);

        // Create org-specific rate
        $orgRate = ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => $this->organization->id,
            'unit_type' => 'hour',
            'rate_cents' => 4000, // $40 - higher
            'effective_from' => Carbon::today()->subWeek(),
        ]);

        $rate = $this->repository->getEffectiveRate(
            $this->serviceType,
            $this->organization,
            Carbon::today()
        );

        $this->assertNotNull($rate);
        $this->assertEquals($orgRate->id, $rate->id);
        $this->assertEquals(4000, $rate->rate_cents);
        $this->assertFalse($rate->is_system_default);
    }

    /**
     * Test effective_from/effective_to date filtering.
     */
    public function test_respects_effective_dates(): void
    {
        // Create old rate (expired)
        ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => null,
            'unit_type' => 'hour',
            'rate_cents' => 3000,
            'effective_from' => Carbon::today()->subMonths(3),
            'effective_to' => Carbon::today()->subMonth(),
        ]);

        // Create current rate
        $currentRate = ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => null,
            'unit_type' => 'hour',
            'rate_cents' => 3500,
            'effective_from' => Carbon::today()->subMonth()->addDay(),
        ]);

        $rate = $this->repository->getEffectiveRate(
            $this->serviceType,
            null,
            Carbon::today()
        );

        $this->assertNotNull($rate);
        $this->assertEquals($currentRate->id, $rate->id);
        $this->assertEquals(3500, $rate->rate_cents);
    }

    /**
     * Test historical rate lookup for past date.
     */
    public function test_returns_historical_rate_for_past_date(): void
    {
        // Create old rate
        $oldRate = ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => null,
            'unit_type' => 'hour',
            'rate_cents' => 3000,
            'effective_from' => Carbon::today()->subMonths(3),
            'effective_to' => Carbon::today()->subMonth(),
        ]);

        // Create current rate
        ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => null,
            'unit_type' => 'hour',
            'rate_cents' => 3500,
            'effective_from' => Carbon::today()->subMonth()->addDay(),
        ]);

        // Look up rate for a date when old rate was active
        $rate = $this->repository->getEffectiveRate(
            $this->serviceType,
            null,
            Carbon::today()->subMonths(2)
        );

        $this->assertNotNull($rate);
        $this->assertEquals($oldRate->id, $rate->id);
        $this->assertEquals(3000, $rate->rate_cents);
    }

    /**
     * Test createOrganizationRate method.
     */
    public function test_create_organization_rate(): void
    {
        $rate = $this->repository->createOrganizationRate(
            serviceTypeId: $this->serviceType->id,
            organizationId: $this->organization->id,
            unitType: 'hour',
            rateCents: 4500,
            effectiveFrom: Carbon::today(),
            notes: 'Test rate'
        );

        $this->assertNotNull($rate);
        $this->assertEquals($this->organization->id, $rate->organization_id);
        $this->assertEquals(4500, $rate->rate_cents);
        $this->assertEquals('Test rate', $rate->notes);
    }

    /**
     * Test that creating a new rate closes the previous one.
     */
    public function test_creating_new_rate_closes_previous(): void
    {
        // Create initial rate
        $oldRate = $this->repository->createOrganizationRate(
            serviceTypeId: $this->serviceType->id,
            organizationId: $this->organization->id,
            unitType: 'hour',
            rateCents: 4000,
            effectiveFrom: Carbon::today()->subMonth()
        );

        // Create new rate (should close the old one)
        $this->repository->createOrganizationRate(
            serviceTypeId: $this->serviceType->id,
            organizationId: $this->organization->id,
            unitType: 'hour',
            rateCents: 4500,
            effectiveFrom: Carbon::today()
        );

        $oldRate->refresh();

        $this->assertNotNull($oldRate->effective_to);
        $this->assertTrue($oldRate->effective_to->lessThan(Carbon::today()));
    }

    /**
     * Test getEffectiveRatesForOrganization returns combined view.
     */
    public function test_get_effective_rates_for_organization(): void
    {
        // Create another service type
        $nursingType = ServiceType::factory()->create([
            'code' => 'NUR',
            'name' => 'Nursing',
            'active' => true,
        ]);

        // Create system defaults
        ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => null,
            'unit_type' => 'hour',
            'rate_cents' => 3500,
            'effective_from' => Carbon::today()->subMonth(),
        ]);

        ServiceRate::create([
            'service_type_id' => $nursingType->id,
            'organization_id' => null,
            'unit_type' => 'visit',
            'rate_cents' => 11000,
            'effective_from' => Carbon::today()->subMonth(),
        ]);

        // Create org override for PSW only
        ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => $this->organization->id,
            'unit_type' => 'hour',
            'rate_cents' => 4000,
            'effective_from' => Carbon::today()->subWeek(),
        ]);

        $rates = $this->repository->getEffectiveRatesForOrganization($this->organization->id);

        // Find PSW and NUR in results
        $pswRate = $rates->firstWhere('service_type_code', 'PSW');
        $nurRate = $rates->firstWhere('service_type_code', 'NUR');

        $this->assertNotNull($pswRate);
        $this->assertTrue($pswRate['has_org_override']);
        $this->assertEquals(4000, $pswRate['effective_rate']['rate_cents']);

        $this->assertNotNull($nurRate);
        $this->assertFalse($nurRate['has_org_override']);
        $this->assertEquals(11000, $nurRate['effective_rate']['rate_cents']);
    }

    /**
     * Test calculateCost method.
     */
    public function test_calculate_cost(): void
    {
        ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => null,
            'unit_type' => 'hour',
            'rate_cents' => 3500, // $35/hour
            'effective_from' => Carbon::today()->subMonth(),
        ]);

        // 2 hours at $35/hour = $70 = 7000 cents
        $cost = $this->repository->calculateCost(
            $this->serviceType,
            2.0,
            null,
            Carbon::today()
        );

        $this->assertEquals(7000, $cost);
    }

    /**
     * Test hasCustomRates method.
     */
    public function test_has_custom_rates(): void
    {
        // Initially no custom rates
        $this->assertFalse($this->repository->hasCustomRates($this->organization->id));

        // Add custom rate
        ServiceRate::create([
            'service_type_id' => $this->serviceType->id,
            'organization_id' => $this->organization->id,
            'unit_type' => 'hour',
            'rate_cents' => 4000,
            'effective_from' => Carbon::today(),
        ]);

        $this->assertTrue($this->repository->hasCustomRates($this->organization->id));
    }
}
