<?php

namespace App\Providers;

use App\Services\IarIntegration\IarClientInterface;
use App\Services\IarIntegration\MockIarClient;
use Illuminate\Support\ServiceProvider;

/**
 * IR-008: Service provider for IAR integration
 *
 * Registers the appropriate IAR client implementation based on environment.
 * In development/testing, uses MockIarClient.
 * In production, will use the real IAR client when available.
 */
class IarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IarClientInterface::class, function ($app) {
            // Check if we should use mock client
            if ($this->shouldUseMock()) {
                return new MockIarClient();
            }

            // TODO: Return real IAR client when OH API specs are available
            // return new IarClient();

            // For now, always use mock
            return new MockIarClient();
        });
    }

    public function boot(): void
    {
        //
    }

    private function shouldUseMock(): bool
    {
        // Use mock in local and testing environments
        if (app()->environment(['local', 'testing', 'staging'])) {
            return true;
        }

        // Use mock if explicitly configured
        if (config('services.iar.use_mock', false)) {
            return true;
        }

        // Use mock if no real endpoint is configured
        if (empty(config('services.iar.endpoint'))) {
            return true;
        }

        return false;
    }
}
