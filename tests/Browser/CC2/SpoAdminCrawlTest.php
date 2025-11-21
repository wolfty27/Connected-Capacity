<?php

namespace Tests\Browser\CC2;

use Laravel\Dusk\Browser;
use Tests\Browser\CC2\CC2DuskTestCase;

class SpoAdminCrawlTest extends CC2DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $organization = \App\Models\ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'test-org'],
            [
                'name' => 'Test Organization',
                'type' => 'se_health',
                'contact_name' => 'Org Contact',
                'contact_email' => 'contact@example.com',
                'contact_phone' => '1112223333',
                'regions' => ['Toronto'],
                'capabilities' => [],
                'active' => true,
            ]
        );

        $this->user = \App\Models\User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'password' => bcrypt('password'),
                'role' => 'SPO_ADMIN',
                'organization_role' => 'SPO_ADMIN',
                'organization_id' => $organization->id,
                'name' => 'SPO Admin',
            ]
        );
    }

    public function testDashboardAccess()
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user->email, 'password');

            // Navigate via sidebar link into CC2 Workspace
            $browser->assertSee('CC2 Workspace')
                ->clickLink('CC2 Workspace')
                ->waitForLocation('/cc2', 5)
                ->assertPathIs('/cc2')
                ->assertSee('Connected Capacity 2.1 (CC2)');
        });
    }

    public function testProviderProfileRendersCorrectly()
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user->email, 'password');

            // Navigate to Provider Profile and confirm existing values render
            $browser->visit('/cc2/organizations/profile')
                ->assertSee('Provider Profile')
                ->assertInputValue('name', 'Test Organization')
                ->assertSelected('type', 'se_health')
                ->assertInputValue('contact_email', 'contact@example.com')
                ->assertInputValue('contact_phone', '1112223333')
                ->assertSeeIn('textarea[name="regions"]', 'Toronto');
        });
    }

    public function testProviderProfileUpdatesCorrectly()
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user->email, 'password');
            $browser->visit('/cc2/organizations/profile');

            // Update every field and toggle capabilities
            $browser->type('name', 'Updated Organization Name')
                ->select('type', 'partner')
                ->type('contact_name', 'Updated Contact')
                ->type('contact_email', 'updated@example.com')
                ->type('contact_phone', '0987654321')
                ->clear('regions')
                ->type('regions', "Region A\nRegion B")
                ->check('capabilities[]', 'dementia')
                ->check('capabilities[]', 'technology')
                ->press('Save Profile')
                ->waitForLocation('/cc2/organizations/profile', 5)
                ->assertSee('Organization profile updated.')
                ->assertInputValue('name', 'Updated Organization Name')
                ->assertSelected('type', 'partner')
                ->assertInputValue('contact_email', 'updated@example.com')
                ->assertInputValue('contact_phone', '0987654321')
                ->assertSeeIn('textarea[name="regions"]', "Region A\nRegion B")
                ->assertChecked('capabilities[]', 'dementia')
                ->assertChecked('capabilities[]', 'technology');
        });
    }

    public function testProviderProfileUpdatePersists()
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user->email, 'password');
            $browser->visit('/cc2/organizations/profile');

            // Update every field and toggle capabilities
            $browser->type('name', 'Updated Organization Name')
                ->select('type', 'partner')
                ->type('contact_name', 'Updated Contact')
                ->type('contact_email', 'updated@example.com')
                ->type('contact_phone', '0987654321')
                ->clear('regions')
                ->type('regions', "Region A\nRegion B")
                ->check('capabilities[]', 'dementia')
                ->check('capabilities[]', 'technology')
                ->press('Save Profile')
                ->waitForLocation('/cc2/organizations/profile', 5);
        });

        // Verify persistence in Database
        $this->assertDatabaseHas('service_provider_organizations', [
            'id' => $this->user->organization_id,
            'name' => 'Updated Organization Name',
            'type' => 'partner',
            'contact_email' => 'updated@example.com',
            'contact_phone' => '0987654321',
        ]);

        $row = \DB::table('service_provider_organizations')->where('id', $this->user->organization_id)->first();
        $this->assertNotNull($row);
        $this->assertEquals(['Region A', 'Region B'], json_decode($row->regions, true));
        $this->assertEqualsCanonicalizing(['dementia', 'technology'], json_decode($row->capabilities, true));
    }
}
