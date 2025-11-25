<?php

namespace App\Providers;

use App\Models\Referral;
use App\Models\TriageResult;
use App\Policies\ReferralPolicy;
use App\Policies\TriageResultPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Referral::class => ReferralPolicy::class,
        TriageResult::class => TriageResultPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
