<?php

namespace Tests\Unit\Services;

use App\Models\OrganizationCostProfile;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceRate;
use App\Models\ServiceType;
use App\Models\StaffWageRate;
use App\Models\User;
use App\Repositories\ServiceRateRepository;
use App\Services\BillingEngine;
use App\Services\DeliveredServiceData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for BillingEngine
 *
 * Verifies billing rate calculation and true cost calculation
 * including wage rates, overhead, and travel costs.
 */
class BillingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected BillingEngine $billingEngine;
    protected ServiceRateRepository $rateRepository;
    protected ServiceProviderOrganization $organization;
    protected ServiceType $pswType;
    protected User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateRepository = new ServiceRateRepository();
        $this->billingEngine = new BillingEngine($this->rateRepository);

        // Create organization
        $this->organization = ServiceProviderOrganization::factory()->create([
            'name' => 'Test SPO',
            'type' => 'primary',
        ]);

        // Create service type
        $this->pswType = ServiceType::factory()->create([
            'code' => 'PSW',
            'name' => 'Personal Support Worker',
            'active' => true,
        ]);

        // Create staff user
        $this->staffUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Create billing rate
        ServiceRate::create([
            'service_type_id' => $this->pswType->id,
            'organization_id' => null,
            'unit_type' => 'hour',
            'rate_cents' => 3500, // $35/hour
            'effective_from' => Carbon::today()->subMonth(),
        ]);

        // Create wage rate
        StaffWageRate::create([
            'organization_id' => $this->organization->id,
            'role' => 'PSW',
            'wage_cents_per_hour' => 2200, // $22/hour
            'benefits_multiplier' => 1.15, // 15% benefits
            'effective_from' => Carbon::today()->subMonth(),
        ]);

        // Create cost profile
        OrganizationCostProfile::create([
            'organization_id' => $this->organization->id,
            'overhead_multiplier' => 1.40, // 40% overhead
            'travel_flat_cents_per_visit' => 500, // $5 flat
            'travel_cents_per_km' => 60, // $0.60/km
            'travel_average_distance_km' => 10.0,
            'admin_overhead_percent' => 15.0,
            'supplies_percent' => 5.0,
        ]);
    }

    /**
     * Test calculating billing amount from rate card.
     */
    public function test_calculate_billing_amount_from_rate_card(): void
    {
        $serviceData = new DeliveredServiceData(
            serviceTypeId: $this->pswType->id,
            organizationId: $this->organization->id,
            staffId: $this->staffUser->id,
            durationMinutes: 60, // 1 hour
            serviceDate: Carbon::today()
        );

        $result = $this->billingEngine->calculateBillingAmount($serviceData);

        // 1 hour × $35 = $35 = 3500 cents
        $this->assertEquals(3500, $result->billingAmountCents);
        $this->assertEquals('hour', $result->unitType);
        $this->assertEquals(1.0, $result->quantity);
        $this->assertContains($result->rateSource, ['system_default', 'organization_rate']);
    }

    /**
     * Test billing amount for fractional hours.
     */
    public function test_billing_amount_for_fractional_hours(): void
    {
        $serviceData = new DeliveredServiceData(
            serviceTypeId: $this->pswType->id,
            organizationId: $this->organization->id,
            staffId: $this->staffUser->id,
            durationMinutes: 90, // 1.5 hours
            serviceDate: Carbon::today()
        );

        $result = $this->billingEngine->calculateBillingAmount($serviceData);

        // 1.5 hours × $35 = $52.50 = 5250 cents
        $this->assertEquals(5250, $result->billingAmountCents);
        $this->assertEquals(1.5, $result->quantity);
    }

    /**
     * Test calculating true cost with wages and overhead.
     */
    public function test_calculate_true_cost_with_wages_and_overhead(): void
    {
        $serviceData = new DeliveredServiceData(
            serviceTypeId: $this->pswType->id,
            organizationId: $this->organization->id,
            staffId: $this->staffUser->id,
            durationMinutes: 60, // 1 hour
            serviceDate: Carbon::today(),
            distanceKm: 10.0
        );

        $result = $this->billingEngine->calculateTrueCost($serviceData);

        // Base wage: $22/hour
        // With benefits (1.15): $22 × 1.15 = $25.30 = 2530 cents
        // With overhead (1.40): $25.30 × 0.40 = $10.12 = 1012 cents
        // Travel: $5 flat + (10km × $0.60) = $11 = 1100 cents
        // Total: 2530 + 1012 + 1100 = 4642 cents (approximately)

        $this->assertGreaterThan(0, $result->trueCostCents);
        $this->assertGreaterThan(0, $result->laborCostCents);
        $this->assertGreaterThan(0, $result->overheadCostCents);
        $this->assertGreaterThan(0, $result->travelCostCents);

        // Labor cost should be wage × hours × benefits
        $expectedLabor = (int) round(2200 * 1 * 1.15); // 2530
        $this->assertEquals($expectedLabor, $result->laborCostCents);
    }

    /**
     * Test margin calculation.
     */
    public function test_calculate_margin(): void
    {
        $serviceData = new DeliveredServiceData(
            serviceTypeId: $this->pswType->id,
            organizationId: $this->organization->id,
            staffId: $this->staffUser->id,
            durationMinutes: 60,
            serviceDate: Carbon::today(),
            distanceKm: 10.0
        );

        $result = $this->billingEngine->calculateMargin($serviceData);

        // Billing: $35 = 3500 cents
        $this->assertEquals(3500, $result->billingAmountCents);

        // True cost should be less than billing (we have margin)
        // Labor: ~2530 + Overhead: ~1012 + Travel: ~1100 = ~4642
        // This example actually shows a loss scenario (true cost > billing)
        // Which is realistic for home care

        $this->assertEquals(
            $result->billingAmountCents - $result->trueCostCents,
            $result->marginCents
        );
    }

    /**
     * Test true cost result toArray method.
     */
    public function test_true_cost_result_to_array(): void
    {
        $serviceData = new DeliveredServiceData(
            serviceTypeId: $this->pswType->id,
            organizationId: $this->organization->id,
            staffId: $this->staffUser->id,
            durationMinutes: 60,
            serviceDate: Carbon::today()
        );

        $result = $this->billingEngine->calculateTrueCost($serviceData);
        $array = $result->toArray();

        $this->assertArrayHasKey('true_cost_cents', $array);
        $this->assertArrayHasKey('true_cost_dollars', $array);
        $this->assertArrayHasKey('labor_cost_cents', $array);
        $this->assertArrayHasKey('overhead_cost_cents', $array);
        $this->assertArrayHasKey('travel_cost_cents', $array);
        $this->assertArrayHasKey('hours_worked', $array);
    }

    /**
     * Test billing result toArray method.
     */
    public function test_billing_result_to_array(): void
    {
        $serviceData = new DeliveredServiceData(
            serviceTypeId: $this->pswType->id,
            organizationId: $this->organization->id,
            staffId: $this->staffUser->id,
            durationMinutes: 60,
            serviceDate: Carbon::today()
        );

        $result = $this->billingEngine->calculateBillingAmount($serviceData);
        $array = $result->toArray();

        $this->assertArrayHasKey('billing_amount_cents', $array);
        $this->assertArrayHasKey('billing_amount_dollars', $array);
        $this->assertArrayHasKey('unit_type', $array);
        $this->assertArrayHasKey('quantity', $array);
        $this->assertArrayHasKey('rate_source', $array);
    }

    /**
     * Test margin analysis toArray method.
     */
    public function test_margin_analysis_to_array(): void
    {
        $serviceData = new DeliveredServiceData(
            serviceTypeId: $this->pswType->id,
            organizationId: $this->organization->id,
            staffId: $this->staffUser->id,
            durationMinutes: 60,
            serviceDate: Carbon::today()
        );

        $result = $this->billingEngine->calculateMargin($serviceData);
        $array = $result->toArray();

        $this->assertArrayHasKey('billing_amount_cents', $array);
        $this->assertArrayHasKey('true_cost_cents', $array);
        $this->assertArrayHasKey('margin_cents', $array);
        $this->assertArrayHasKey('margin_percent', $array);
        $this->assertArrayHasKey('billing_detail', $array);
        $this->assertArrayHasKey('true_cost_detail', $array);
    }

    /**
     * Test returns zero when no rate found.
     */
    public function test_returns_zero_when_no_rate_found(): void
    {
        // Create service type with no rate
        $unknownType = ServiceType::factory()->create([
            'code' => 'UNKNOWN',
            'name' => 'Unknown Service',
            'active' => true,
        ]);

        $serviceData = new DeliveredServiceData(
            serviceTypeId: $unknownType->id,
            organizationId: $this->organization->id,
            staffId: null,
            durationMinutes: 60,
            serviceDate: Carbon::today()
        );

        $result = $this->billingEngine->calculateBillingAmount($serviceData);

        $this->assertEquals(0, $result->billingAmountCents);
        $this->assertEquals('none', $result->rateSource);
    }

    /**
     * Test returns zero true cost when no wage rate found.
     */
    public function test_returns_zero_true_cost_when_no_wage_rate(): void
    {
        // Create a different service type with no wage rate
        $nursingType = ServiceType::factory()->create([
            'code' => 'NUR',
            'name' => 'Nursing',
            'active' => true,
        ]);

        $serviceData = new DeliveredServiceData(
            serviceTypeId: $nursingType->id,
            organizationId: $this->organization->id,
            staffId: null,
            durationMinutes: 60,
            serviceDate: Carbon::today()
        );

        $result = $this->billingEngine->calculateTrueCost($serviceData);

        $this->assertEquals(0, $result->trueCostCents);
        $this->assertStringContainsString('No wage rate', $result->notes);
    }
}
