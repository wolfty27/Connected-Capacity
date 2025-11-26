<?php

namespace Tests\Browser\CC2;

use Laravel\Dusk\Browser;
use Tests\Browser\CC2\CC2DuskTestCase;

class SpoCoordinatorCrawlTest extends CC2DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $organization = \App\Models\ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'test-org'],
            [
                'name' => 'Test Organization',
                'type' => 'se_health',
                'active' => true,
            ]
        );

        $this->user = \App\Models\User::updateOrCreate(
            ['email' => 'spo_coordinator@example.com'],
            [
                'password' => bcrypt('password'),
                'role' => 'SPO_COORDINATOR',
                'organization_role' => 'SPO_COORDINATOR',
                'organization_id' => $organization->id,
                'name' => 'SPO Coordinator',
            ]
        );
    }

    public function testDashboardAccess()
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user->email, 'password');
            $browser->assertSee('CC2 Workspace')
                ->clickLink('CC2 Workspace')
                ->waitForLocation('/cc2', 5)
                ->assertPathIs('/cc2')
                ->assertSee('Connected Capacity 2.1 (CC2)');
        });
    }
}
