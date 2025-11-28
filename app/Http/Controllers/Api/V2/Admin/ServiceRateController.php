<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRate;
use App\Models\ServiceType;
use App\Repositories\ServiceRateRepository;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * ServiceRateController
 *
 * Admin API for managing service rates (rate card).
 *
 * Access control:
 * - SPO_ADMIN: Can view system defaults and edit their org-specific rates
 * - SSPO_ADMIN: Can view defaults and edit rates for their organization
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class ServiceRateController extends Controller
{
    public function __construct(
        protected ServiceRateRepository $rateRepository
    ) {}

    /**
     * Get the rate card for the current user's organization.
     *
     * Returns system defaults and org-specific overrides.
     *
     * GET /api/v2/admin/service-rates
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->organization_id;

        $rateCard = $this->rateRepository->getRateCardForDisplay($organizationId);

        return response()->json([
            'success' => true,
            'data' => $rateCard,
            'user_organization_id' => $organizationId,
            'can_edit' => $this->userCanEditRates($user),
        ]);
    }

    /**
     * Get system default rates only.
     *
     * GET /api/v2/admin/service-rates/system-defaults
     */
    public function systemDefaults(): JsonResponse
    {
        $rates = $this->rateRepository->getSystemDefaultRates();

        return response()->json([
            'success' => true,
            'data' => $rates->map(fn ($rate) => $rate->toApiArray()),
        ]);
    }

    /**
     * Get organization-specific rates.
     *
     * GET /api/v2/admin/service-rates/organization/{organizationId?}
     */
    public function organizationRates(Request $request, ?int $organizationId = null): JsonResponse
    {
        $user = Auth::user();

        // Default to user's organization
        $organizationId = $organizationId ?? $user->organization_id;

        // Check authorization
        if (!$this->userCanViewOrganizationRates($user, $organizationId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view rates for this organization',
            ], 403);
        }

        $rates = $this->rateRepository->getOrganizationRates($organizationId);

        return response()->json([
            'success' => true,
            'data' => $rates->map(fn ($rate) => $rate->toApiArray()),
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * Get a specific service rate.
     *
     * GET /api/v2/admin/service-rates/{id}
     */
    public function show(int $id): JsonResponse
    {
        $rate = ServiceRate::with(['serviceType', 'organization'])->find($id);

        if (!$rate) {
            return response()->json([
                'success' => false,
                'message' => 'Rate not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $rate->toApiArray(),
        ]);
    }

    /**
     * Create or update an organization-specific rate.
     *
     * POST /api/v2/admin/service-rates
     *
     * Body:
     * {
     *   "service_type_id": 1,
     *   "rate_cents": 3500,
     *   "unit_type": "hour",
     *   "effective_from": "2024-01-01",
     *   "effective_to": null,
     *   "notes": "Updated PSW rate"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$this->userCanEditRates($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit rates',
            ], 403);
        }

        $validated = $request->validate([
            'service_type_id' => 'required|exists:service_types,id',
            'rate_cents' => 'required|integer|min:0|max:10000000',
            'unit_type' => ['required', Rule::in(ServiceRate::VALID_UNIT_TYPES)],
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string|max:500',
        ]);

        $organizationId = $user->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'User must belong to an organization to create rates',
            ], 400);
        }

        $rate = $this->rateRepository->createOrganizationRate(
            serviceTypeId: $validated['service_type_id'],
            organizationId: $organizationId,
            unitType: $validated['unit_type'],
            rateCents: $validated['rate_cents'],
            effectiveFrom: $validated['effective_from'] ? Carbon::parse($validated['effective_from']) : null,
            effectiveTo: $validated['effective_to'] ? Carbon::parse($validated['effective_to']) : null,
            notes: $validated['notes'] ?? null,
            createdBy: $user->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Rate created successfully',
            'data' => $rate->toApiArray(),
        ], 201);
    }

    /**
     * Update an existing organization-specific rate.
     *
     * PUT /api/v2/admin/service-rates/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        $rate = ServiceRate::find($id);

        if (!$rate) {
            return response()->json([
                'success' => false,
                'message' => 'Rate not found',
            ], 404);
        }

        // Cannot edit system defaults through this endpoint
        if ($rate->is_system_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit system default rates. Create an organization-specific override instead.',
            ], 403);
        }

        // Check if user can edit this rate
        if (!$this->userCanEditOrganizationRate($user, $rate)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit this rate',
            ], 403);
        }

        $validated = $request->validate([
            'rate_cents' => 'sometimes|integer|min:0|max:10000000',
            'unit_type' => ['sometimes', Rule::in(ServiceRate::VALID_UNIT_TYPES)],
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string|max:500',
        ]);

        $rate->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rate updated successfully',
            'data' => $rate->fresh()->toApiArray(),
        ]);
    }

    /**
     * Delete an organization-specific rate.
     *
     * DELETE /api/v2/admin/service-rates/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();

        $rate = ServiceRate::find($id);

        if (!$rate) {
            return response()->json([
                'success' => false,
                'message' => 'Rate not found',
            ], 404);
        }

        // Cannot delete system defaults
        if ($rate->is_system_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system default rates',
            ], 403);
        }

        // Check if user can delete this rate
        if (!$this->userCanEditOrganizationRate($user, $rate)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this rate',
            ], 403);
        }

        $rate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rate deleted successfully',
        ]);
    }

    /**
     * Get rate history for a service type.
     *
     * GET /api/v2/admin/service-rates/history/{serviceTypeId}
     */
    public function history(Request $request, int $serviceTypeId): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->organization_id;

        // Get system default history
        $systemHistory = $this->rateRepository->getRateHistory($serviceTypeId, null);

        // Get org-specific history
        $orgHistory = $organizationId
            ? $this->rateRepository->getRateHistory($serviceTypeId, $organizationId)
            : collect();

        return response()->json([
            'success' => true,
            'data' => [
                'service_type_id' => $serviceTypeId,
                'service_type' => ServiceType::find($serviceTypeId)?->only(['id', 'code', 'name']),
                'system_defaults' => $systemHistory->map(fn ($r) => $r->toApiArray()),
                'organization_rates' => $orgHistory->map(fn ($r) => $r->toApiArray()),
            ],
        ]);
    }

    /**
     * Bulk update rates for an organization.
     *
     * POST /api/v2/admin/service-rates/bulk
     *
     * Body:
     * {
     *   "rates": [
     *     { "service_type_id": 1, "rate_cents": 3500, "unit_type": "hour" },
     *     { "service_type_id": 2, "rate_cents": 11000, "unit_type": "visit" }
     *   ]
     * }
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$this->userCanEditRates($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit rates',
            ], 403);
        }

        $validated = $request->validate([
            'rates' => 'required|array|min:1',
            'rates.*.service_type_id' => 'required|exists:service_types,id',
            'rates.*.rate_cents' => 'required|integer|min:0|max:10000000',
            'rates.*.unit_type' => ['required', Rule::in(ServiceRate::VALID_UNIT_TYPES)],
            'rates.*.notes' => 'nullable|string|max:500',
            'effective_from' => 'nullable|date',
        ]);

        $organizationId = $user->organization_id;
        $effectiveFrom = $validated['effective_from']
            ? Carbon::parse($validated['effective_from'])
            : Carbon::today();

        $createdRates = [];

        foreach ($validated['rates'] as $rateData) {
            $rate = $this->rateRepository->createOrganizationRate(
                serviceTypeId: $rateData['service_type_id'],
                organizationId: $organizationId,
                unitType: $rateData['unit_type'],
                rateCents: $rateData['rate_cents'],
                effectiveFrom: $effectiveFrom,
                notes: $rateData['notes'] ?? null,
                createdBy: $user->id
            );

            $createdRates[] = $rate->toApiArray();
        }

        return response()->json([
            'success' => true,
            'message' => count($createdRates) . ' rates created successfully',
            'data' => $createdRates,
        ], 201);
    }

    /**
     * Check if user can edit rates.
     */
    protected function userCanEditRates($user): bool
    {
        if (!$user) {
            return false;
        }

        // Check for admin roles
        $adminRoles = ['SPO_ADMIN', 'SSPO_ADMIN', 'admin'];
        return in_array($user->role, $adminRoles, true);
    }

    /**
     * Check if user can view rates for an organization.
     */
    protected function userCanViewOrganizationRates($user, int $organizationId): bool
    {
        if (!$user) {
            return false;
        }

        // Admin can view any org
        if ($user->role === 'admin') {
            return true;
        }

        // SPO/SSPO admin can only view their own org
        return $user->organization_id === $organizationId;
    }

    /**
     * Check if user can edit a specific organization rate.
     */
    protected function userCanEditOrganizationRate($user, ServiceRate $rate): bool
    {
        if (!$user) {
            return false;
        }

        // Admin can edit any rate
        if ($user->role === 'admin') {
            return true;
        }

        // SPO/SSPO admin can only edit their own org's rates
        return $user->organization_id === $rate->organization_id;
    }
}
