<?php

namespace Tests\Feature\CC2;

use App\Models\FeatureFlag;
use App\Models\ServiceProviderOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_admin_can_view_profile(): void
    {
        [$organization, $user] = $this->createOrgAdmin();

        $this->actingAs($user)
            ->get('/cc2/organizations/profile')
            ->assertOk()
            ->assertSee($organization->name);
    }

    public function test_org_admin_can_update_profile(): void
    {
        [$organization, $user] = $this->createOrgAdmin();

        $payload = [
            'name' => 'Updated Org Name',
            'type' => 'partner',
            'contact_name' => 'Updated Contact',
            'contact_email' => 'contact@example.com',
            'contact_phone' => '555-1234',
            'regions' => ['Central', 'East'],
            'capabilities' => ['dementia', 'technology'],
        ];

        $response = $this->actingAs($user)
            ->from('/cc2/organizations/profile')
            ->put('/cc2/organizations/profile', $payload);

        $response->assertRedirect('/cc2/organizations/profile');

        $this->assertDatabaseHas('service_provider_organizations', [
            'id' => $organization->id,
            'name' => 'Updated Org Name',
            'contact_email' => 'contact@example.com',
        ]);

        $this->assertSame(
            ['Central', 'East'],
            ServiceProviderOrganization::find($organization->id)->regions
        );
    }

    public function test_non_admin_cannot_update_profile(): void
    {
        $organization = ServiceProviderOrganization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'organization_role' => 'COORDINATOR',
        ]);
        $this->enableCc2();

        $this->actingAs($user)
            ->put('/cc2/organizations/profile', [
                'name' => 'Blocked Update',
                'type' => $organization->type,
            ])
            ->assertStatus(403);
    }

    private function createOrgAdmin(): array
    {
        $organization = ServiceProviderOrganization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'organization_role' => 'SPO_ADMIN',
        ]);

        $this->enableCc2();

        return [$organization, $user];
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
