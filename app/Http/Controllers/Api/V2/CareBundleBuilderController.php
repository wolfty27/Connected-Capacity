<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CareBundleTemplate;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Services\CareBundleBuilderService;
use App\Services\CareBundleTemplateRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * CareBundleBuilderController - API for metadata-driven care bundle building
 *
 * Implements the Workday-style model-at-runtime approach where:
 * - Bundle templates and services are configured via metadata
 * - Rules automatically adjust services based on patient context
 * - Publishing a bundle transitions patients from queue to active profile
 *
 * CC2.1 Architecture: Now supports RUG-III/HC based template matching.
 * Use the /rug-bundles endpoints for the new RUG-driven approach.
 */
class CareBundleBuilderController extends Controller
{
    protected CareBundleBuilderService $bundleBuilder;
    protected CareBundleTemplateRepository $templateRepository;

    public function __construct(
        CareBundleBuilderService $bundleBuilder,
        CareBundleTemplateRepository $templateRepository
    ) {
        $this->bundleBuilder = $bundleBuilder;
        $this->templateRepository = $templateRepository;
    }

    /**
     * Get available bundles configured for a patient.
     *
     * Returns all active bundles with their services pre-configured
     * based on the patient's TNP and clinical flags.
     *
     * @param int $patientId
     * @return JsonResponse
     */
    public function getBundles(int $patientId): JsonResponse
    {
        $patient = Patient::with(['transitionNeedsProfile', 'user'])->find($patientId);

        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $bundles = $this->bundleBuilder->getAvailableBundles($patientId);

        // Find recommended bundle
        $recommended = collect($bundles)->firstWhere('isRecommended', true);

        return response()->json([
            'data' => $bundles,
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->user?->name ?? 'Unknown',
                'status' => $patient->status,
                'is_in_queue' => $patient->is_in_queue,
            ],
            'recommended_bundle' => $recommended ? [
                'id' => $recommended['id'],
                'code' => $recommended['code'],
                'name' => $recommended['name'],
                'reason' => $recommended['recommendationReason'],
            ] : null,
            'metadata' => [
                'total_bundles' => count($bundles),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get a specific bundle configured for a patient.
     *
     * @param int $patientId
     * @param int $bundleId
     * @return JsonResponse
     */
    public function getBundle(int $patientId, int $bundleId): JsonResponse
    {
        $bundle = $this->bundleBuilder->getBundleForPatient($bundleId, $patientId);

        if (!$bundle) {
            return response()->json(['message' => 'Bundle not found or inactive'], 404);
        }

        return response()->json([
            'data' => $bundle,
        ]);
    }

    /**
     * Build a care plan from a bundle configuration.
     *
     * This creates a draft care plan with service assignments.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function buildPlan(Request $request, int $patientId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bundle_id' => 'required|integer',
            'services' => 'required|array',
            'services.*.service_type_id' => 'required|integer',
            'services.*.currentFrequency' => 'required|integer|min:0',
            'services.*.currentDuration' => 'required|integer|min:0',
            'services.*.provider_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify bundle exists
        $bundle = \App\Models\CareBundle::find($request->bundle_id);
        if (!$bundle) {
            return response()->json([
                'message' => 'Bundle not found',
                'errors' => ['bundle_id' => "Bundle ID {$request->bundle_id} does not exist"],
            ], 422);
        }

        // Verify service_type_ids exist
        $serviceTypeIds = collect($request->services)->pluck('service_type_id')->unique();
        $existingIds = \App\Models\ServiceType::whereIn('id', $serviceTypeIds)->pluck('id');
        $missingIds = $serviceTypeIds->diff($existingIds);

        if ($missingIds->isNotEmpty()) {
            return response()->json([
                'message' => 'Invalid service type IDs',
                'errors' => ['services' => "Service type IDs not found: " . $missingIds->implode(', ')],
            ], 422);
        }

        try {
            $carePlan = $this->bundleBuilder->buildCarePlan(
                $patientId,
                $request->bundle_id,
                $request->services,
                Auth::id()
            );

            return response()->json([
                'message' => 'Care plan draft created',
                'data' => $carePlan->load(['careBundle', 'serviceAssignments.serviceType']),
                'next_steps' => [
                    'review_url' => "/patients/{$patientId}/care-plan/{$carePlan->id}",
                    'publish_endpoint' => "/api/v2/care-builder/{$patientId}/plans/{$carePlan->id}/publish",
                ],
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create care plan', [
                'patient_id' => $patientId,
                'bundle_id' => $request->bundle_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create care plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Publish a care plan and transition patient to active.
     *
     * This is the key transition point where the patient moves from
     * the queue list to their regular patient profile.
     *
     * @param Request $request
     * @param int $patientId
     * @param int $carePlanId
     * @return JsonResponse
     */
    public function publishPlan(Request $request, int $patientId, int $carePlanId): JsonResponse
    {
        $carePlan = CarePlan::where('id', $carePlanId)
            ->where('patient_id', $patientId)
            ->first();

        if (!$carePlan) {
            return response()->json(['message' => 'Care plan not found'], 404);
        }

        if ($carePlan->status === 'active') {
            return response()->json(['message' => 'Care plan is already active'], 409);
        }

        try {
            $publishedPlan = $this->bundleBuilder->publishCarePlan($carePlan, Auth::id());
            $patient = Patient::find($patientId);

            // Guard against deleted patient
            if (!$patient) {
                return response()->json([
                    'error' => 'Patient not found. The patient may have been deleted.',
                ], 404);
            }

            return response()->json([
                'message' => 'Care plan published successfully. Patient transitioned to active profile.',
                'data' => [
                    'care_plan' => $publishedPlan->load(['careBundle', 'serviceAssignments.serviceType']),
                    'patient' => [
                        'id' => $patient->id,
                        'status' => $patient->status,
                        'is_in_queue' => $patient->is_in_queue,
                        'activated_at' => $patient->activated_at,
                    ],
                ],
                'transition' => [
                    'from' => 'queue',
                    'to' => 'active_profile',
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to publish care plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the patient's care plan history.
     *
     * @param int $patientId
     * @return JsonResponse
     */
    public function getPlanHistory(int $patientId): JsonResponse
    {
        $plans = CarePlan::where('patient_id', $patientId)
            ->with(['careBundle', 'approver'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $plans,
            'summary' => [
                'total_plans' => $plans->count(),
                'active_plans' => $plans->where('status', 'active')->count(),
                'draft_plans' => $plans->where('status', 'draft')->count(),
            ],
        ]);
    }

    /**
     * Preview a bundle configuration without saving.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function previewBundle(Request $request, int $patientId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bundle_id' => 'required|exists:care_bundles,id',
            'services' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bundle = $this->bundleBuilder->getBundleForPatient($request->bundle_id, $patientId);

        if (!$bundle) {
            return response()->json(['message' => 'Bundle not found'], 404);
        }

        // Apply any service overrides from request
        if ($request->has('services')) {
            $overrides = collect($request->services)->keyBy('service_type_id');
            $bundle['services'] = collect($bundle['services'])->map(function ($service) use ($overrides) {
                if ($overrides->has($service['service_type_id'])) {
                    $override = $overrides->get($service['service_type_id']);
                    $service['currentFrequency'] = $override['currentFrequency'] ?? $service['currentFrequency'];
                    $service['currentDuration'] = $override['currentDuration'] ?? $service['currentDuration'];
                }
                return $service;
            })->toArray();

            // Recalculate costs
            $bundle['estimatedMonthlyCost'] = collect($bundle['services'])->reduce(function ($carry, $service) {
                return $carry + ($service['costPerVisit'] * $service['currentFrequency'] * 4);
            }, 0);
        }

        return response()->json([
            'data' => $bundle,
            'is_preview' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RUG-Based Bundle Endpoints (CC2.1 Architecture)
    |--------------------------------------------------------------------------
    */

    /**
     * Get RUG-based bundle recommendations for a patient.
     *
     * This is the primary endpoint for the CC2.1 architecture. It uses
     * the patient's RUG-III/HC classification to find matching templates.
     *
     * @param int $patientId
     * @return JsonResponse
     */
    public function getRugBundles(int $patientId): JsonResponse
    {
        $patient = Patient::with([
            'user',
            'latestInterraiAssessment',
            'latestRugClassification',
        ])->find($patientId);

        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $result = $this->bundleBuilder->getRugBasedBundles($patientId);

        if (isset($result['error'])) {
            return response()->json([
                'message' => $result['error'],
                'detail' => $result['message'] ?? null,
                'bundles' => [],
            ], 422);
        }

        // Find recommended bundle
        $recommended = collect($result['bundles'])->firstWhere('isRecommended', true);

        return response()->json([
            'data' => $result['bundles'],
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->user?->name ?? 'Unknown',
                'status' => $patient->status,
                'is_in_queue' => $patient->is_in_queue,
            ],
            'rug_classification' => $result['rug_classification'] ?? null,
            'recommended_template' => $recommended ? [
                'id' => $recommended['id'],
                'code' => $recommended['code'],
                'name' => $recommended['name'],
                'rug_group' => $recommended['rug_group'],
                'match_score' => $recommended['matchScore'],
                'reason' => $recommended['recommendationReason'],
            ] : null,
            'metadata' => [
                'total_templates' => count($result['bundles']),
                'generated_at' => now()->toIso8601String(),
                'source' => 'rug_classification',
            ],
        ]);
    }

    /**
     * Get a specific RUG template configured for a patient.
     *
     * @param int $patientId
     * @param int $templateId
     * @return JsonResponse
     */
    public function getRugBundle(int $patientId, int $templateId): JsonResponse
    {
        $template = $this->bundleBuilder->getRugTemplateForPatient($templateId, $patientId);

        if (!$template) {
            return response()->json(['message' => 'Template not found or inactive'], 404);
        }

        return response()->json([
            'data' => $template,
        ]);
    }

    /**
     * Build a care plan from a RUG template configuration.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function buildPlanFromTemplate(Request $request, int $patientId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|integer|exists:care_bundle_templates,id',
            'services' => 'required|array',
            'services.*.service_type_id' => 'required|integer',
            'services.*.currentFrequency' => 'required|integer|min:0',
            'services.*.currentDuration' => 'required|integer|min:0',
            'services.*.provider_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify template is active
        $template = CareBundleTemplate::where('id', $request->template_id)
            ->where('is_active', true)
            ->where('is_current_version', true)
            ->first();

        if (!$template) {
            return response()->json([
                'message' => 'Template not found or inactive',
                'errors' => ['template_id' => "Template ID {$request->template_id} is not available"],
            ], 422);
        }

        // Verify service_type_ids exist
        $serviceTypeIds = collect($request->services)->pluck('service_type_id')->unique();
        $existingIds = \App\Models\ServiceType::whereIn('id', $serviceTypeIds)->pluck('id');
        $missingIds = $serviceTypeIds->diff($existingIds);

        if ($missingIds->isNotEmpty()) {
            return response()->json([
                'message' => 'Invalid service type IDs',
                'errors' => ['services' => "Service type IDs not found: " . $missingIds->implode(', ')],
            ], 422);
        }

        try {
            $carePlan = $this->bundleBuilder->buildCarePlanFromTemplate(
                $patientId,
                $request->template_id,
                $request->services,
                Auth::id()
            );

            return response()->json([
                'message' => 'Care plan draft created from RUG template',
                'data' => $carePlan->load(['careBundle', 'serviceAssignments.serviceType']),
                'template' => [
                    'id' => $template->id,
                    'code' => $template->code,
                    'name' => $template->name,
                    'rug_group' => $template->rug_group,
                ],
                'next_steps' => [
                    'review_url' => "/patients/{$patientId}/care-plan/{$carePlan->id}",
                    'publish_endpoint' => "/api/v2/care-builder/{$patientId}/plans/{$carePlan->id}/publish",
                ],
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create care plan from template', [
                'patient_id' => $patientId,
                'template_id' => $request->template_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create care plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get template recommendation summary for a patient.
     *
     * @param int $patientId
     * @return JsonResponse
     */
    public function getTemplateRecommendation(int $patientId): JsonResponse
    {
        $patient = Patient::find($patientId);

        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $summary = $this->templateRepository->getRecommendationSummary($patient);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * Get all available RUG templates.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllTemplates(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $fundingStream = $request->query('funding_stream', 'LTC');

        if ($category) {
            $templates = $this->templateRepository->getByCategory($category);
        } else {
            $templates = $this->templateRepository->getByFundingStream($fundingStream);
        }

        return response()->json([
            'data' => $templates->map->toSummaryArray(),
            'metadata' => [
                'total' => $templates->count(),
                'filter' => [
                    'category' => $category,
                    'funding_stream' => $fundingStream,
                ],
            ],
        ]);
    }
}
