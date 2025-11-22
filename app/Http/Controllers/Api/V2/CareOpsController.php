<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CareAssignment;
use App\Services\CareOpsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CareOpsController extends Controller
{
    protected $careOpsService;

    public function __construct(CareOpsService $careOpsService)
    {
        $this->careOpsService = $careOpsService;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', CareAssignment::class);

        $date = $request->query('date');
        $assignments = $this->careOpsService->getAssignmentsForUser(Auth::user(), $date);

        return response()->json($assignments);
    }

    public function store(Request $request)
    {
        $this->authorize('create', CareAssignment::class);

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'service_provider_organization_id' => 'required|exists:service_provider_organizations,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $assignment = $this->careOpsService->createAssignment($validated);

        return response()->json($assignment, 201);
    }

    public function update(Request $request, CareAssignment $assignment)
    {
        $this->authorize('update', $assignment);

        $validated = $request->validate([
            'status' => 'required|string|in:pending,in_progress,completed,cancelled',
        ]);

        $updatedAssignment = $this->careOpsService->updateAssignmentStatus($assignment, $validated['status']);

        return response()->json($updatedAssignment);
    }
}
