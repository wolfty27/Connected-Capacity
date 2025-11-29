<?php

namespace App\Providers;

use App\Services\Travel\FakeTravelTimeService;
use App\Services\Travel\GoogleDistanceMatrixTravelTimeService;
use App\Services\Travel\TravelTimeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind TravelTimeService interface to appropriate implementation
        // Production/staging: Google Distance Matrix API (traffic-aware)
        // Local/testing: Fake implementation (deterministic, no API calls)
        $this->app->bind(TravelTimeService::class, function ($app) {
            if ($app->environment('production', 'staging')) {
                return new GoogleDistanceMatrixTravelTimeService();
            }
            return new FakeTravelTimeService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->validateSanctumConfiguration();
    }

    /**
     * Validate Sanctum/CSRF configuration in development.
     * Logs warnings if common misconfigurations are detected.
     */
    protected function validateSanctumConfiguration(): void
    {
        if (!$this->app->environment('local', 'development')) {
            return;
        }

        $issues = [];

        // Check SANCTUM_STATEFUL_DOMAINS
        $statefulDomains = config('sanctum.stateful', []);
        if (is_string($statefulDomains)) {
            $statefulDomains = explode(',', $statefulDomains);
        }

        $commonDevDomains = ['localhost:8000', 'localhost:5173', '127.0.0.1:8000'];
        $missingDomains = array_filter($commonDevDomains, fn($d) => !in_array($d, $statefulDomains));

        if (!empty($missingDomains)) {
            $issues[] = "SANCTUM_STATEFUL_DOMAINS may be missing: " . implode(', ', $missingDomains);
        }

        // Check SESSION_DOMAIN
        $sessionDomain = config('session.domain');
        if (empty($sessionDomain)) {
            $issues[] = "SESSION_DOMAIN is not set (recommended: 'localhost' for local development)";
        }

        // Log warnings
        if (!empty($issues)) {
            Log::warning('Sanctum/CSRF Configuration Issues Detected', [
                'issues' => $issues,
                'help' => 'Add to .env: SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,localhost:5173,127.0.0.1,127.0.0.1:8000 and SESSION_DOMAIN=localhost',
            ]);
        }
    }
}