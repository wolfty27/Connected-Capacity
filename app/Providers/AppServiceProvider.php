<?php

namespace App\Providers;

use App\Services\Llm\Contracts\ExplanationProviderInterface;
use App\Services\Llm\Fallback\RulesBasedExplanationProvider;
use App\Services\Llm\LlmExplanationService;
use App\Services\Llm\PromptBuilder;
use App\Services\Llm\VertexAi\VertexAiClient;
use App\Services\Llm\VertexAi\VertexAiConfig;
use App\Services\Scheduling\AutoAssignEngine;
use App\Services\Scheduling\ContinuityService;
use App\Services\Scheduling\StaffScoringService;
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
        // ===========================================
        // Travel Time Service Binding
        // ===========================================
        // Production/staging: Google Distance Matrix API (traffic-aware)
        // Local/testing: Fake implementation (deterministic, no API calls)
        $this->app->bind(TravelTimeService::class, function ($app) {
            if ($app->environment('production', 'staging')) {
                return new GoogleDistanceMatrixTravelTimeService();
            }
            return new FakeTravelTimeService();
        });

        // ===========================================
        // LLM / Vertex AI Service Bindings
        // ===========================================

        // Vertex AI Config (singleton - loaded once from config)
        $this->app->singleton(VertexAiConfig::class, function () {
            return VertexAiConfig::fromConfig();
        });

        // Prompt Builder (singleton - stateless)
        $this->app->singleton(PromptBuilder::class, function () {
            return new PromptBuilder();
        });

        // Rules-Based Fallback Provider (singleton - stateless)
        $this->app->singleton(RulesBasedExplanationProvider::class, function () {
            return new RulesBasedExplanationProvider();
        });

        // Vertex AI Client (singleton - maintains HTTP client)
        $this->app->singleton(VertexAiClient::class, function ($app) {
            $config = $app->make(VertexAiConfig::class);
            return new VertexAiClient($config);
        });

        // LLM Explanation Service (singleton - main entry point)
        $this->app->singleton(LlmExplanationService::class, function ($app) {
            return new LlmExplanationService(
                $app->make(PromptBuilder::class),
                $app->make(RulesBasedExplanationProvider::class)
            );
        });

        // Bind interface to fallback implementation by default
        $this->app->bind(ExplanationProviderInterface::class, RulesBasedExplanationProvider::class);

        // ===========================================
        // Auto Assign / Scheduling Services
        // ===========================================

        // Continuity Service (singleton - stateless query service)
        $this->app->singleton(ContinuityService::class, function () {
            return new ContinuityService();
        });

        // Staff Scoring Service (singleton - depends on other services)
        $this->app->singleton(StaffScoringService::class, function ($app) {
            return new StaffScoringService(
                $app->make(ContinuityService::class),
                $app->make(TravelTimeService::class),
                $app->make(\App\Services\Scheduling\SchedulingEngine::class)
            );
        });

        // Auto Assign Engine (singleton - main orchestrator)
        $this->app->singleton(AutoAssignEngine::class, function ($app) {
            return new AutoAssignEngine(
                $app->make(\App\Services\Scheduling\CareBundleAssignmentPlanner::class),
                $app->make(\App\Services\Scheduling\SchedulingEngine::class),
                $app->make(StaffScoringService::class),
                $app->make(ContinuityService::class)
            );
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
