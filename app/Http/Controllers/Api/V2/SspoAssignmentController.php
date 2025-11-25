<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * SspoAssignmentController - SSPO assignment acceptance workflow
 *
 * Per OHaH RFS: When SPO subcontracts services to SSPO partners,
 * the SSPO must explicitly accept or decline the assignment.
 * SPO retains full liability regardless of SSPO response.
 */
class SspoAssignmentController extends Controller
{
    /**
     * Get assignments pending SSPO acceptance.
     *
     * GET /api/v2/assignments/pending-sspo
     *
     * Query params:
     * - organization_id: Filter by specific SSPO organization
     */
    public function pendingAcceptance(Request $request): JsonResponse
    {
        $query = ServiceAssignment::pendingSspoAcceptance()
            ->with([
                'patient.user',
                'serviceType',
                'serviceProviderOrganization',
                'carePlan',
            ]);

        // Filter by organization if specified
        if ($request->has('organization_id')) {
            $query->forOrganization($request->organization_id);
        }

        // If user belongs to an SSPO, filter to their organization
        $user = $request->user();
        if ($user && $user->organization_id) {
            $org = ServiceProviderOrganization::find($user->organization_id);
            if ($org && $org->type === 'partner') {
                $query->forOrganization($user->organization_id);
            }
        }

        $assignments = $query->orderBy('sspo_notified_at', 'asc')->get();

        return response()->json([
            'data' => $assignments->map(fn($a) => $this->transformAssignment($a)),
            'meta' => [
                'total' => $assignments->count(),
            ],
        ]);
    }

    /**
     * Accept an assignment on behalf of SSPO.
     *
     * POST /api/v2/assignments/{id}/accept
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $assignment = ServiceAssignment::with([
            'patient.user',
            'serviceType',
            'serviceProviderOrganization',
        ])->find($id);

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        // Verify assignment requires SSPO acceptance
        if (!$assignment->requiresSspoAcceptance()) {
            return response()->json([
                'message' => 'This assignment does not require SSPO acceptance',
            ], 400);
        }

        // Verify assignment is still pending
        if (!$assignment->isSspoPending()) {
            return response()->json([
                'message' => 'This assignment has already been ' . $assignment->sspo_acceptance_status,
            ], 400);
        }

        // Authorization check - user must belong to the assigned organization
        $user = $request->user();
        if ($user && $user->organization_id !== $assignment->service_provider_organization_id) {
            // Allow if user is SPO admin (can accept on behalf of SSPO)
            $userOrg = ServiceProviderOrganization::find($user->organization_id);
            if (!$userOrg || $userOrg->type !== 'se_health') {
                return response()->json([
                    'message' => 'You are not authorized to accept this assignment',
                ], 403);
            }
        }

        $assignment->acceptBySspo($user?->id);

        Log::info('SSPO assignment accepted', [
            'assignment_id' => $assignment->id,
            'patient_id' => $assignment->patient_id,
            'organization_id' => $assignment->service_provider_organization_id,
            'accepted_by' => $user?->id,
            'response_time_minutes' => $assignment->sspo_response_time_minutes,
        ]);

        return response()->json([
            'message' => 'Assignment accepted successfully',
            'data' => $this->transformAssignment($assignment),
        ]);
    }

    /**
     * Decline an assignment on behalf of SSPO.
     *
     * POST /api/v2/assignments/{id}/decline
     *
     * Body:
     * - reason: string (required) - Reason for declining
     */
    public function decline(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $assignment = ServiceAssignment::with([
            'patient.user',
            'serviceType',
            'serviceProviderOrganization',
        ])->find($id);

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        // Verify assignment requires SSPO acceptance
        if (!$assignment->requiresSspoAcceptance()) {
            return response()->json([
                'message' => 'This assignment does not require SSPO acceptance',
            ], 400);
        }

        // Verify assignment is still pending
        if (!$assignment->isSspoPending()) {
            return response()->json([
                'message' => 'This assignment has already been ' . $assignment->sspo_acceptance_status,
            ], 400);
        }

        // Authorization check
        $user = $request->user();
        if ($user && $user->organization_id !== $assignment->service_provider_organization_id) {
            $userOrg = ServiceProviderOrganization::find($user->organization_id);
            if (!$userOrg || $userOrg->type !== 'se_health') {
                return response()->json([
                    'message' => 'You are not authorized to decline this assignment',
                ], 403);
            }
        }

        $assignment->declineBySspo($request->reason, $user?->id);

        Log::info('SSPO assignment declined', [
            'assignment_id' => $assignment->id,
            'patient_id' => $assignment->patient_id,
            'organization_id' => $assignment->service_provider_organization_id,
            'declined_by' => $user?->id,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Assignment declined',
            'data' => $this->transformAssignment($assignment),
        ]);
    }

    /**
     * Get SSPO acceptance status for an assignment.
     *
     * GET /api/v2/assignments/{id}/sspo-status
     */
    public function sspoStatus(int $id): JsonResponse
    {
        $assignment = ServiceAssignment::with([
            'serviceProviderOrganization',
            'sspoRespondedBy',
        ])->find($id);

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        return response()->json([
            'data' => [
                'assignment_id' => $assignment->id,
                'requires_sspo_acceptance' => $assignment->requiresSspoAcceptance(),
                'status' => $assignment->sspo_acceptance_status,
                'status_label' => $assignment->sspo_status_label,
                'organization' => $assignment->serviceProviderOrganization ? [
                    'id' => $assignment->serviceProviderOrganization->id,
                    'name' => $assignment->serviceProviderOrganization->name,
                    'type' => $assignment->serviceProviderOrganization->type,
                ] : null,
                'notified_at' => $assignment->sspo_notified_at?->toIso8601String(),
                'responded_at' => $assignment->sspo_responded_at?->toIso8601String(),
                'responded_by' => $assignment->sspoRespondedBy ? [
                    'id' => $assignment->sspoRespondedBy->id,
                    'name' => $assignment->sspoRespondedBy->name,
                ] : null,
                'response_time_minutes' => $assignment->sspo_response_time_minutes,
                'decline_reason' => $assignment->sspo_decline_reason,
            ],
        ]);
    }

    /**
     * Get SSPO acceptance metrics for an organization.
     *
     * GET /api/v2/assignments/sspo-metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        $organizationId = $request->organization_id;

        $query = ServiceAssignment::requiresSspoResponse();

        if ($organizationId) {
            $query->forOrganization($organizationId);
        }

        $total = (clone $query)->count();
        $pending = (clone $query)->pendingSspoAcceptance()->count();
        $accepted = (clone $query)->sspoAccepted()->count();
        $declined = (clone $query)->sspoDeclined()->count();

        // Calculate average response time
        $avgResponseTime = ServiceAssignment::requiresSspoResponse()
            ->whereNotNull('sspo_responded_at')
            ->whereNotNull('sspo_notified_at')
            ->when($organizationId, fn($q) => $q->forOrganization($organizationId))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, sspo_notified_at, sspo_responded_at)) as avg_response')
            ->value('avg_response');

        // Calculate acceptance rate
        $responded = $accepted + $declined;
        $acceptanceRate = $responded > 0 ? round(($accepted / $responded) * 100, 1) : null;

        return response()->json([
            'data' => [
                'total_subcontracted' => $total,
                'pending' => $pending,
                'accepted' => $accepted,
                'declined' => $declined,
                'acceptance_rate' => $acceptanceRate,
                'average_response_time_minutes' => round($avgResponseTime ?? 0, 1),
            ],
        ]);
    }

    /**
     * Transform assignment for API response.
     */
    protected function transformAssignment(ServiceAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'patient' => $assignment->patient ? [
                'id' => $assignment->patient->id,
                'name' => $assignment->patient->user?->name,
            ] : null,
            'service_type' => $assignment->serviceType ? [
                'id' => $assignment->serviceType->id,
                'code' => $assignment->serviceType->code,
                'name' => $assignment->serviceType->name,
            ] : null,
            'organization' => $assignment->serviceProviderOrganization ? [
                'id' => $assignment->serviceProviderOrganization->id,
                'name' => $assignment->serviceProviderOrganization->name,
                'type' => $assignment->serviceProviderOrganization->type,
            ] : null,
            'care_plan_id' => $assignment->care_plan_id,
            'status' => $assignment->status,
            'sspo_status' => $assignment->sspo_acceptance_status,
            'sspo_status_label' => $assignment->sspo_status_label,
            'notified_at' => $assignment->sspo_notified_at?->toIso8601String(),
            'responded_at' => $assignment->sspo_responded_at?->toIso8601String(),
            'decline_reason' => $assignment->sspo_decline_reason,
            'response_time_minutes' => $assignment->sspo_response_time_minutes,
            'scheduled_start' => $assignment->scheduled_start?->toIso8601String(),
            'scheduled_end' => $assignment->scheduled_end?->toIso8601String(),
            'frequency_rule' => $assignment->frequency_rule,
            'notes' => $assignment->notes,
        ];
    }
}
