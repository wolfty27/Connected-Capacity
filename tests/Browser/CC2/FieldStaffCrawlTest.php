<?php

namespace Tests\Browser\CC2;

use Laravel\Dusk\Browser;
use Tests\Browser\CC2\CC2DuskTestCase;

class FieldStaffCrawlTest extends CC2DuskTestCase
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
            ['email' => 'field_staff@example.com'],
            [
                'password' => bcrypt('password'),
                'role' => 'FIELD_STAFF',
                'organization_role' => 'FIELD_STAFF',
                'organization_id' => $organization->id,
                'name' => 'Field Staff',
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
