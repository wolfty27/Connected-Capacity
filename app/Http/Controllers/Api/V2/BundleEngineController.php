<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\BundleEngine\Contracts\AssessmentIngestionServiceInterface;
use App\Services\BundleEngine\Contracts\ScenarioGeneratorInterface;
use App\Services\BundleEngine\ScenarioAxisSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BundleEngineController
 *
 * API endpoints for the AI-Assisted Bundle Engine.
 *
 * Endpoints:
 * - GET /v2/bundle-engine/profile/{patient} - Build patient needs profile
 * - GET /v2/bundle-engine/axes/{patient} - Get applicable scenario axes
 * - GET /v2/bundle-engine/scenarios/{patient} - Generate scenarios for patient
 * - GET /v2/bundle-engine/data-sources/{patient} - Get available data sources
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md
 */
class BundleEngineController extends Controller
{
    public function __construct(
        protected AssessmentIngestionServiceInterface $ingestionService,
        protected ScenarioGeneratorInterface $scenarioGenerator,
        protected ScenarioAxisSelector $axisSelector
    ) {}

    /**
     * Build patient needs profile.
     *
     * GET /v2/bundle-engine/profile/{patient}
     *
     * Query params:
     * - force_refresh: bool - Skip cache and rebuild
     * - include_referral: bool - Include referral data (default: true)
     */
    public function getProfile(Patient $patient, Request $request): JsonResponse
    {
        $options = [
            'force_refresh' => $request->boolean('force_refresh', false),
            'include_referral' => $request->boolean('include_referral', true),
        ];

        $profile = $this->ingestionService->buildPatientNeedsProfile($patient, $options);

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $profile->toArray(),
                'sufficient_for_bundling' => $profile->isSufficientForBundling(),
                'classification_type' => $profile->getClassificationType(),
                'primary_classification' => $profile->getPrimaryClassification(),
            ],
        ]);
    }

    /**
     * Get applicable scenario axes for a patient.
     *
     * GET /v2/bundle-engine/axes/{patient}
     *
     * Query params:
     * - max_axes: int - Maximum axes to return (default: 4)
     * - detailed: bool - Include full evaluation details (default: false)
     */
    public function getAxes(Patient $patient, Request $request): JsonResponse
    {
        $maxAxes = $request->integer('max_axes', 4);
        $detailed = $request->boolean('detailed', false);

        $profile = $this->ingestionService->buildPatientNeedsProfile($patient);

        if ($detailed) {
            $evaluation = $this->axisSelector->getDetailedEvaluation($profile);

            return response()->json([
                'success' => true,
                'data' => [
                    'evaluation' => array_map(function ($item) {
                        return [
                            'axis' => $item['axis']->value,
                            'label' => $item['axis']->getLabel(),
                            'emoji' => $item['axis']->getEmoji(),
                            'score' => $item['score'],
                            'reasons' => $item['reasons'],
                            'applicable' => $item['applicable'],
                        ];
                    }, $evaluation),
                ],
            ]);
        }

        $applicableAxes = $this->axisSelector->getApplicableAxes($profile, $maxAxes);

        return response()->json([
            'success' => true,
            'data' => [
                'applicable_axes' => array_map(function ($axis) {
                    return [
                        'value' => $axis->value,
                        'label' => $axis->getLabel(),
                        'description' => $axis->getDescription(),
                        'emoji' => $axis->getEmoji(),
                        'is_primary' => $axis->isPrimary(),
                    ];
                }, $applicableAxes),
            ],
        ]);
    }

    /**
     * Generate scenario bundles for a patient.
     *
     * GET /v2/bundle-engine/scenarios/{patient}
     *
     * Query params:
     * - min_scenarios: int - Minimum scenarios (default: 3)
     * - max_scenarios: int - Maximum scenarios (default: 5)
     * - include_balanced: bool - Include balanced option (default: true)
     * - reference_cap: float - Weekly cost reference (default: 5000)
     */
    public function getScenarios(Patient $patient, Request $request): JsonResponse
    {
        try {
            $options = [
                'min_scenarios' => $request->integer('min_scenarios', 3),
                'max_scenarios' => $request->integer('max_scenarios', 5),
                'include_balanced' => $request->boolean('include_balanced', true),
                'reference_cap' => $request->float('reference_cap', 5000.0),
            ];

            // Build profile
            $profile = $this->ingestionService->buildPatientNeedsProfile($patient);

            // Check if we have sufficient data
            if (!$profile->isSufficientForBundling()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient assessment data for scenario generation',
                    'data' => [
                        'available_sources' => $this->ingestionService->getAvailableDataSources($patient),
                    ],
                ], 422);
            }

            // Generate scenarios
            $scenarios = $this->scenarioGenerator->generateScenarios($profile, $options);

            return response()->json([
                'success' => true,
                'data' => [
                    'patient_id' => $patient->id,
                    'profile_summary' => [
                        'classification_type' => $profile->getClassificationType(),
                        'primary_classification' => $profile->getPrimaryClassification(),
                        'confidence' => $profile->confidenceLevel,
                        'episode_type' => $profile->episodeType,
                        'rehab_potential' => $profile->hasRehabPotential,
                    ],
                    'scenario_count' => count($scenarios),
                    'scenarios' => array_map(fn($s) => $s->toArray(), $scenarios),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to generate scenarios', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate scenarios: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available data sources for a patient.
     *
     * GET /v2/bundle-engine/data-sources/{patient}
     */
    public function getDataSources(Patient $patient): JsonResponse
    {
        $sources = $this->ingestionService->getAvailableDataSources($patient);
        $hasSufficientData = $this->ingestionService->hasSufficientData($patient);

        return response()->json([
            'success' => true,
            'data' => [
                'patient_id' => $patient->id,
                'sources' => $sources,
                'sufficient_for_bundling' => $hasSufficientData,
                'recommendations' => $this->getDataRecommendations($sources),
            ],
        ]);
    }

    /**
     * Compare two scenarios.
     *
     * POST /v2/bundle-engine/compare
     *
     * Body:
     * - patient_id: int
     * - scenario_id_1: string
     * - scenario_id_2: string
     */
    public function compareScenarios(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'scenario_id_1' => 'required|string',
            'scenario_id_2' => 'required|string',
        ]);

        // For now, we need to regenerate scenarios to compare
        // In production, scenarios would be cached/stored
        $patient = Patient::findOrFail($request->patient_id);
        $profile = $this->ingestionService->buildPatientNeedsProfile($patient);
        $scenarios = $this->scenarioGenerator->generateScenarios($profile);

        $scenario1 = collect($scenarios)->firstWhere('scenarioId', $request->scenario_id_1);
        $scenario2 = collect($scenarios)->firstWhere('scenarioId', $request->scenario_id_2);

        if (!$scenario1 || !$scenario2) {
            return response()->json([
                'success' => false,
                'error' => 'One or both scenarios not found',
            ], 404);
        }

        $comparison = $this->scenarioGenerator->compareScenarios($scenario1, $scenario2);

        return response()->json([
            'success' => true,
            'data' => [
                'comparison' => $comparison,
                'scenario_1' => [
                    'id' => $scenario1->scenarioId,
                    'title' => $scenario1->title,
                    'weekly_cost' => $scenario1->weeklyEstimatedCost,
                ],
                'scenario_2' => [
                    'id' => $scenario2->scenarioId,
                    'title' => $scenario2->title,
                    'weekly_cost' => $scenario2->weeklyEstimatedCost,
                ],
            ],
        ]);
    }

    /**
     * Invalidate cached profile for a patient.
     *
     * POST /v2/bundle-engine/invalidate-cache/{patient}
     */
    public function invalidateCache(Patient $patient): JsonResponse
    {
        $this->ingestionService->invalidateCache($patient);

        return response()->json([
            'success' => true,
            'message' => 'Profile cache invalidated',
        ]);
    }

    /**
     * Get recommendations for missing data sources.
     */
    protected function getDataRecommendations(array $sources): array
    {
        $recommendations = [];

        if (!$sources['has_hc']) {
            $recommendations[] = [
                'priority' => 'high',
                'message' => 'Complete InterRAI HC assessment for full RUG classification',
                'impact' => 'Higher confidence in bundle recommendations',
            ];
        }

        if (!$sources['has_ca'] && !$sources['has_hc']) {
            $recommendations[] = [
                'priority' => 'high',
                'message' => 'Complete at least a Contact Assessment (CA) for basic needs profile',
                'impact' => 'Enables scenario generation with medium confidence',
            ];
        }

        if ($sources['has_ca'] && !$sources['has_hc'] && !$sources['has_bmhs']) {
            $recommendations[] = [
                'priority' => 'medium',
                'message' => 'Consider BMHS screening if behavioural/mental health concerns',
                'impact' => 'Refines cognitive and behavioural support recommendations',
            ];
        }

        return $recommendations;
    }
}

