<?php

namespace Tests\Feature\CC2;

use App\Models\FeatureFlag;
use App\Models\ServiceProviderOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationContextMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_cc2_route_returns_403_when_user_has_no_organization(): void
    {
        $this->enableCc2();

        $user = User::factory()->create([
            'organization_id' => null,
            'role' => 'hospital',
        ]);

        $this->actingAs($user)
            ->get('/cc2/organizations/profile')
            ->assertStatus(403);
    }

    public function test_cc2_route_returns_200_when_user_has_organization(): void
    {
        $this->enableCc2();

        $organization = ServiceProviderOrganization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'organization_role' => 'SPO_ADMIN',
        ]);

        $this->actingAs($user)
            ->get('/cc2/organizations/profile')
            ->assertOk()
            ->assertSee($organization->name);
    }

    private function enableCc2(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'cc2.enabled', 'scope' => 'global', 'target_id' => null],
            ['enabled' => true]
        );

        app(\App\Services\FeatureToggle::class)->flush('cc2.enabled');
    }
}
