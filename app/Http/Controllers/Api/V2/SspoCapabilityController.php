<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Models\SspoServiceCapability;
use App\Models\Patient;
use App\Services\SspoMarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * STAFF-020: SSPO Capability Management Controller
 */
class SspoCapabilityController extends Controller
{
    protected SspoMarketplaceService $marketplaceService;

    public function __construct(SspoMarketplaceService $marketplaceService)
    {
        $this->marketplaceService = $marketplaceService;
    }

    /**
     * List capabilities for an SSPO
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $sspoId = $request->input('sspo_id', $user->organization_id);

        // Authorization check
        if (!$user->isMaster() && $user->organization_id != $sspoId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $capabilities = SspoServiceCapability::where('sspo_id', $sspoId)
            ->with('serviceType')
            ->orderBy('service_type_id')
            ->get()
            ->map(fn($c) => $this->transformCapability($c));

        return response()->json(['data' => $capabilities]);
    }

    /**
     * Get a single capability
     */
    public function show(int $id): JsonResponse
    {
        $capability = SspoServiceCapability::with(['sspo', 'serviceType'])->findOrFail($id);

        $user = Auth::user();
        if (!$user->isMaster() && $user->organization_id != $capability->sspo_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $this->transformCapability($capability, true)]);
    }

    /**
     * Create a new capability
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sspo_id' => 'required|exists:service_provider_organizations,id',
            'service_type_id' => 'required|exists:service_types,id',
            'is_active' => 'boolean',
            'max_weekly_hours' => 'nullable|integer|min:0',
            'min_notice_hours' => 'nullable|integer|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'visit_rate' => 'nullable|numeric|min:0',
            'service_areas' => 'nullable|array',
            'available_days' => 'nullable|array',
            'earliest_start_time' => 'nullable|date_format:H:i',
            'latest_end_time' => 'nullable|date_format:H:i',
            'staff_qualifications' => 'nullable|array',
            'available_staff_count' => 'nullable|integer|min:0',
            'can_handle_complex_care' => 'boolean',
            'can_handle_dementia' => 'boolean',
            'can_handle_palliative' => 'boolean',
            'bilingual_french' => 'boolean',
            'languages_available' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $sspoId = $request->input('sspo_id');

        if (!$user->isMaster() && $user->organization_id != $sspoId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check for existing capability
        $exists = SspoServiceCapability::where('sspo_id', $sspoId)
            ->where('service_type_id', $request->input('service_type_id'))
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'Capability already exists for this service type',
            ], 409);
        }

        $capability = SspoServiceCapability::create($request->all());

        return response()->json([
            'message' => 'Capability created successfully',
            'data' => $this->transformCapability($capability->fresh(['sspo', 'serviceType'])),
        ], 201);
    }

    /**
     * Update a capability
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $capability = SspoServiceCapability::findOrFail($id);

        $user = Auth::user();
        if (!$user->isMaster() && $user->organization_id != $capability->sspo_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'boolean',
            'max_weekly_hours' => 'nullable|integer|min:0',
            'min_notice_hours' => 'nullable|integer|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'visit_rate' => 'nullable|numeric|min:0',
            'rate_modifiers' => 'nullable|array',
            'service_areas' => 'nullable|array',
            'available_days' => 'nullable|array',
            'earliest_start_time' => 'nullable|date_format:H:i',
            'latest_end_time' => 'nullable|date_format:H:i',
            'staff_qualifications' => 'nullable|array',
            'available_staff_count' => 'nullable|integer|min:0',
            'can_handle_complex_care' => 'boolean',
            'can_handle_dementia' => 'boolean',
            'can_handle_palliative' => 'boolean',
            'bilingual_french' => 'boolean',
            'languages_available' => 'nullable|array',
            'capability_effective_date' => 'nullable|date',
            'capability_expiry_date' => 'nullable|date',
            'insurance_verified' => 'boolean',
            'insurance_expiry_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $capability->fill($request->only([
            'is_active', 'max_weekly_hours', 'min_notice_hours',
            'hourly_rate', 'visit_rate', 'rate_modifiers',
            'service_areas', 'available_days',
            'earliest_start_time', 'latest_end_time',
            'staff_qualifications', 'available_staff_count',
            'can_handle_complex_care', 'can_handle_dementia', 'can_handle_palliative',
            'bilingual_french', 'languages_available',
            'capability_effective_date', 'capability_expiry_date',
            'insurance_verified', 'insurance_expiry_date',
        ]));
        $capability->save();

        return response()->json([
            'message' => 'Capability updated successfully',
            'data' => $this->transformCapability($capability->fresh(['sspo', 'serviceType'])),
        ]);
    }

    /**
     * Delete a capability
     */
    public function destroy(int $id): JsonResponse
    {
        $capability = SspoServiceCapability::findOrFail($id);

        $user = Auth::user();
        if (!$user->isMaster() && $user->organization_id != $capability->sspo_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $capability->delete();

        return response()->json(['message' => 'Capability deleted successfully']);
    }

    /**
     * Find matching SSPOs for a service request
     */
    public function findMatches(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_type_id' => 'required|exists:service_types,id',
            'patient_id' => 'required|exists:patients,id',
            'requested_start' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0.5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $serviceType = ServiceType::findOrFail($request->input('service_type_id'));
        $patient = Patient::findOrFail($request->input('patient_id'));
        $requestedStart = $request->filled('requested_start')
            ? \Carbon\Carbon::parse($request->input('requested_start'))
            : null;
        $estimatedHours = $request->input('estimated_hours');

        $matches = $this->marketplaceService->findMatchingSSPOs(
            $serviceType,
            $patient,
            $requestedStart,
            $estimatedHours
        );

        return response()->json([
            'data' => $matches,
            'meta' => [
                'total_matches' => $matches->count(),
                'service_type' => $serviceType->name,
                'patient_id' => $patient->id,
            ],
        ]);
    }

    /**
     * Get SSPO rankings for a service type
     */
    public function rankings(int $serviceTypeId): JsonResponse
    {
        $rankings = $this->marketplaceService->getSspoRankings($serviceTypeId);

        return response()->json(['data' => $rankings]);
    }

    /**
     * Get all service types with SSPO coverage summary
     */
    public function serviceTypeCoverage(): JsonResponse
    {
        $serviceTypes = ServiceType::where('active', true)
            ->withCount(['skills as required_skills_count' => function ($q) {
                $q->wherePivot('is_required', true);
            }])
            ->get()
            ->map(function ($serviceType) {
                $capabilities = SspoServiceCapability::active()
                    ->forServiceType($serviceType->id)
                    ->get();

                return [
                    'id' => $serviceType->id,
                    'name' => $serviceType->name,
                    'code' => $serviceType->code,
                    'category' => $serviceType->category,
                    'required_skills_count' => $serviceType->required_skills_count ?? 0,
                    'sspo_count' => $capabilities->count(),
                    'total_weekly_capacity' => $capabilities->sum('max_weekly_hours'),
                    'avg_quality_score' => round($capabilities->avg('quality_score') ?? 0, 1),
                    'avg_hourly_rate' => round($capabilities->avg('hourly_rate') ?? 0, 2),
                ];
            });

        return response()->json(['data' => $serviceTypes]);
    }

    /**
     * Transform capability for API response
     */
    protected function transformCapability(SspoServiceCapability $capability, bool $detailed = false): array
    {
        $data = [
            'id' => $capability->id,
            'sspo_id' => $capability->sspo_id,
            'sspo_name' => $capability->sspo?->name,
            'service_type_id' => $capability->service_type_id,
            'service_type_name' => $capability->serviceType?->name,
            'service_type_code' => $capability->serviceType?->code,
            'is_active' => $capability->is_active,
            'is_valid' => $capability->isValid(),

            // Capacity
            'max_weekly_hours' => $capability->max_weekly_hours,
            'current_utilization_hours' => $capability->current_utilization_hours,
            'available_hours' => $capability->available_hours,
            'utilization_rate' => $capability->utilization_rate,
            'min_notice_hours' => $capability->min_notice_hours,

            // Pricing
            'hourly_rate' => $capability->hourly_rate,
            'visit_rate' => $capability->visit_rate,

            // Quality
            'quality_score' => $capability->quality_score,
            'acceptance_rate' => $capability->acceptance_rate,
            'completion_rate' => $capability->completion_rate,
            'capability_score' => $capability->capability_score,

            // Special capabilities
            'can_handle_dementia' => $capability->can_handle_dementia,
            'can_handle_palliative' => $capability->can_handle_palliative,
            'can_handle_complex_care' => $capability->can_handle_complex_care,
            'bilingual_french' => $capability->bilingual_french,
            'available_staff_count' => $capability->available_staff_count,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'rate_modifiers' => $capability->rate_modifiers,
                'service_areas' => $capability->service_areas,
                'available_days' => $capability->available_days,
                'earliest_start_time' => $capability->earliest_start_time,
                'latest_end_time' => $capability->latest_end_time,
                'staff_qualifications' => $capability->staff_qualifications,
                'languages_available' => $capability->languages_available,
                'capability_effective_date' => $capability->capability_effective_date,
                'capability_expiry_date' => $capability->capability_expiry_date,
                'insurance_verified' => $capability->insurance_verified,
                'insurance_expiry_date' => $capability->insurance_expiry_date,
            ]);
        }

        return $data;
    }
}
