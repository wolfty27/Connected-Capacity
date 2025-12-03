<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\BundleEngine\Contracts\AssessmentIngestionServiceInterface;
use App\Services\BundleEngine\Contracts\AssessmentMapperInterface;
use App\Services\BundleEngine\Contracts\CostAnnotationServiceInterface;
use App\Services\BundleEngine\Contracts\ScenarioGeneratorInterface;
use App\Services\BundleEngine\AssessmentIngestionService;
use App\Services\BundleEngine\CostAnnotationService;
use App\Services\BundleEngine\ScenarioAxisSelector;
use App\Services\BundleEngine\ScenarioGenerator;
use App\Services\BundleEngine\Mappers\HcAssessmentMapper;
use App\Services\BundleEngine\Mappers\CaAssessmentMapper;
use App\Services\BundleEngine\Mappers\BmhsAssessmentMapper;
use App\Services\BundleEngine\Derivers\EpisodeTypeDeriver;
use App\Services\BundleEngine\Derivers\RehabPotentialDeriver;
// v2.2 Engines for data-driven algorithms and CAP triggers
use App\Services\BundleEngine\Engines\DecisionTreeEngine;
use App\Services\BundleEngine\Engines\CAPTriggerEngine;
use App\Services\BundleEngine\Engines\ServiceIntensityResolver;
use App\Services\BundleEngine\AlgorithmEvaluator;
use App\Services\BundleEngine\BundleEventLogger;

/**
 * BundleEngineServiceProvider
 *
 * Registers all Bundle Engine services for dependency injection.
 *
 * Services provided:
 * - AssessmentIngestionServiceInterface
 * - CostAnnotationServiceInterface
 * - ScenarioAxisSelector
 * - Assessment Mappers (HC, CA)
 * - Derivers (EpisodeType, RehabPotential)
 * - v2.2 Engines (DecisionTreeEngine, CAPTriggerEngine, ServiceIntensityResolver)
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md
 * @see docs/ALGORITHM_DSL.md
 */
class BundleEngineServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // v2.2 Engines - Data-driven algorithm and CAP evaluation
        $this->registerEngines();

        // Singleton for ScenarioAxisSelector (stateless, can be shared)
        $this->app->singleton(ScenarioAxisSelector::class, function ($app) {
            return new ScenarioAxisSelector();
        });

        // Singleton for derivers (stateless)
        $this->app->singleton(EpisodeTypeDeriver::class, function ($app) {
            return new EpisodeTypeDeriver();
        });

        $this->app->singleton(RehabPotentialDeriver::class, function ($app) {
            return new RehabPotentialDeriver();
        });

        // Singleton for mappers (stateless)
        $this->app->singleton(HcAssessmentMapper::class, function ($app) {
            return new HcAssessmentMapper();
        });

        $this->app->singleton(CaAssessmentMapper::class, function ($app) {
            return new CaAssessmentMapper();
        });

        // v2.2: BMHS Assessment Mapper
        $this->app->singleton(BmhsAssessmentMapper::class, function ($app) {
            return new BmhsAssessmentMapper();
        });

        // Bind interface to implementation: AssessmentIngestionService
        // v2.2: Now includes BmhsAssessmentMapper, AlgorithmEvaluator and CAPTriggerEngine
        $this->app->singleton(
            AssessmentIngestionServiceInterface::class,
            function ($app) {
                return new AssessmentIngestionService(
                    $app->make(HcAssessmentMapper::class),
                    $app->make(CaAssessmentMapper::class),
                    $app->make(BmhsAssessmentMapper::class),
                    $app->make(EpisodeTypeDeriver::class),
                    $app->make(RehabPotentialDeriver::class),
                    $app->make(AlgorithmEvaluator::class),
                    $app->make(CAPTriggerEngine::class)
                );
            }
        );

        // Bind interface to implementation: CostAnnotationService
        $this->app->singleton(
            CostAnnotationServiceInterface::class,
            function ($app) {
                return new CostAnnotationService();
            }
        );

        // Bind interface to implementation: ScenarioGenerator
        // v2.2: Now includes ServiceIntensityResolver and CAPTriggerEngine for
        // algorithm-driven service frequencies and CAP-based recommendations
        $this->app->singleton(
            ScenarioGeneratorInterface::class,
            function ($app) {
                return new ScenarioGenerator(
                    $app->make(ScenarioAxisSelector::class),
                    $app->make(CostAnnotationServiceInterface::class),
                    null, // ServiceRateRepository - will use default
                    $app->make(ServiceIntensityResolver::class),
                    $app->make(CAPTriggerEngine::class)
                );
            }
        );
    }

    /**
     * Register v2.2 engines for data-driven algorithm and CAP evaluation.
     */
    protected function registerEngines(): void
    {
        // DecisionTreeEngine - Interprets JSON algorithm definitions
        $this->app->singleton(DecisionTreeEngine::class, function ($app) {
            return new DecisionTreeEngine(
                config_path('bundle_engine/algorithms')
            );
        });

        // CAPTriggerEngine - Interprets YAML CAP trigger definitions
        $this->app->singleton(CAPTriggerEngine::class, function ($app) {
            return new CAPTriggerEngine(
                config_path('bundle_engine/cap_triggers')
            );
        });

        // ServiceIntensityResolver - Maps scores to service intensities
        $this->app->singleton(ServiceIntensityResolver::class, function ($app) {
            return new ServiceIntensityResolver(
                config_path('bundle_engine/service_intensity_matrix.json')
            );
        });

        // AlgorithmEvaluator - Computes all algorithm scores for a patient
        $this->app->singleton(AlgorithmEvaluator::class, function ($app) {
            return new AlgorithmEvaluator(
                $app->make(DecisionTreeEngine::class),
                $app->make(CAPTriggerEngine::class)
            );
        });

        // v2.2: Learning Infrastructure - Event Logger
        $this->app->singleton(BundleEventLogger::class, function ($app) {
            return new BundleEventLogger();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration if needed in the future
        // $this->publishes([
        //     __DIR__.'/../../config/bundle-engine.php' => config_path('bundle-engine.php'),
        // ], 'bundle-engine-config');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            AssessmentIngestionServiceInterface::class,
            CostAnnotationServiceInterface::class,
            ScenarioGeneratorInterface::class,
            ScenarioAxisSelector::class,
            HcAssessmentMapper::class,
            CaAssessmentMapper::class,
            BmhsAssessmentMapper::class,
            EpisodeTypeDeriver::class,
            RehabPotentialDeriver::class,
            // v2.2 Engines
            DecisionTreeEngine::class,
            CAPTriggerEngine::class,
            ServiceIntensityResolver::class,
            AlgorithmEvaluator::class,
            // v2.2 Learning Infrastructure
            BundleEventLogger::class,
        ];
    }
}

