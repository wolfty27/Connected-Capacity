<?php

namespace Tests\Browser\CC2;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

class CC2DuskTestCase extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\FeatureFlag::updateOrCreate(
            ['key' => 'cc2.enabled'],
            ['enabled' => true]
        );
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
    }

    /**
     * Helper to login as a specific user type.
     */
    public function loginAs(Browser $browser, string $email, string $password = 'password')
    {
        $browser->visit('/login')
            ->type('email', $email)
            ->type('password', $password)
            ->press('Log In')
            ->waitForText('CC2 Workspace', 5);
    }
}
