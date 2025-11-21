<?php

namespace Tests\Browser\CC2;

use Laravel\Dusk\Browser;
use Tests\Browser\CC2\CC2DuskTestCase;

class SspoCoordinatorCrawlTest extends CC2DuskTestCase
{
    public function testDashboardAccess()
    {
        $organization = \App\Models\ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'test-org'],
            [
                'name' => 'Test Organization',
                'type' => 'se_health',
                'active' => true,
            ]
        );

        $user = \App\Models\User::updateOrCreate(
            ['email' => 'sspo_coordinator@example.com'],
            [
                'password' => bcrypt('password'),
                'role' => 'SSPO_COORDINATOR',
                'organization_role' => 'SSPO_COORDINATOR',
                'organization_id' => $organization->id,
                'name' => 'SSPO Coordinator',
            ]
        );

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user->email, 'password');
            $browser->assertSee('CC2 Workspace')
                ->clickLink('CC2 Workspace')
                ->waitForLocation('/cc2', 5)
                ->assertPathIs('/cc2')
                ->assertSee('Connected Capacity 2.1 (CC2)');
        });
    }
}
