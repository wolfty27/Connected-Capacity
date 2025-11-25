<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ServiceAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SspoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Ensure user belongs to an organization
        if (!$user->organization_id) {
            return response()->json(['message' => 'User not associated with an organization'], 403);
        }

        $assignments = ServiceAssignment::with(['patient.user', 'serviceType', 'carePlan.careBundle'])
            ->where('service_provider_organization_id', $user->organization_id)
            ->orderBy('scheduled_start', 'asc') // Or created_at if not scheduled
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'patient' => $assignment->patient->user->name ?? 'Unknown',
                    'service' => $assignment->serviceType->name ?? 'Service',
                    'status' => $assignment->status,
                    'frequency' => $assignment->frequency_rule,
                    'next_visit' => $assignment->scheduled_start ? $assignment->scheduled_start->format('M d, g:i A') : 'Not Scheduled',
                    'bundle' => $assignment->carePlan->careBundle->name ?? 'N/A'
                ];
            });

        return response()->json($assignments);
    }
}