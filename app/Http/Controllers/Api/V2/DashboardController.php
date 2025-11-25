<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * General Dashboard Controller for role-based dashboard data
 *
 * Note: The primary SPO dashboard is handled by SpoDashboardController.
 * This controller provides basic metrics for authenticated users.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role;
        $orgId = $user->organization_id;

        // Base metrics available to all authenticated users
        $metrics = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $role,
                'organization_id' => $orgId,
            ],
            'dashboard_type' => $this->getDashboardType($role),
        ];

        // Add role-specific metrics
        if ($user->isMaster() || $role === User::ROLE_ADMIN) {
            $metrics['counts'] = [
                'total_patients' => Patient::count(),
                'active_patients' => Patient::where('is_in_queue', false)->where('status', 'Active')->count(),
                'queue_patients' => Patient::where('is_in_queue', true)->count(),
                'total_assignments' => ServiceAssignment::count(),
            ];
        } elseif (in_array($role, [User::ROLE_SPO_ADMIN, User::ROLE_SPO_COORDINATOR])) {
            $metrics['counts'] = [
                'active_patients' => Patient::where('is_in_queue', false)->where('status', 'Active')->count(),
                'queue_patients' => Patient::where('is_in_queue', true)->count(),
                'active_assignments' => ServiceAssignment::where('status', 'active')->count(),
            ];
            $metrics['redirect_to'] = '/care-dashboard';
        } elseif (in_array($role, [User::ROLE_SSPO_ADMIN, User::ROLE_SSPO_COORDINATOR])) {
            $assignmentCount = $orgId
                ? ServiceAssignment::where('service_provider_organization_id', $orgId)->where('status', 'active')->count()
                : 0;
            $metrics['counts'] = [
                'my_assignments' => $assignmentCount,
            ];
            $metrics['redirect_to'] = '/sspo/dashboard';
        } elseif ($role === User::ROLE_FIELD_STAFF) {
            $metrics['redirect_to'] = '/worklist';
        }

        return response()->json($metrics);
    }

    protected function getDashboardType(string $role): string
    {
        return match ($role) {
            User::ROLE_MASTER, User::ROLE_ADMIN => 'admin',
            User::ROLE_SPO_ADMIN, User::ROLE_SPO_COORDINATOR => 'spo',
            User::ROLE_SSPO_ADMIN, User::ROLE_SSPO_COORDINATOR => 'sspo',
            User::ROLE_FIELD_STAFF => 'field',
            default => 'unknown',
        };
    }
}
