<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\TransitionNeedsProfile;
use App\Services\TnpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TnpController extends Controller
{
    protected $tnpService;

    public function __construct(TnpService $tnpService)
    {
        $this->tnpService = $tnpService;
    }

    public function show(Patient $patient)
    {
        $tnp = $this->tnpService->getProfile($patient);

        if (!$tnp) {
            return response()->json(['message' => 'Transition Needs Profile not found.'], 404);
        }

        $this->authorize('view', $tnp);

        return response()->json($tnp);
    }

    public function store(Request $request, Patient $patient)
    {
        $this->authorize('create', TransitionNeedsProfile::class);

        $validated = $request->validate([
            'clinical_flags' => 'nullable|array',
            'narrative_summary' => 'nullable|string',
        ]);

        $tnp = $this->tnpService->createProfile($patient, $validated, Auth::user());

        return response()->json($tnp, 201);
    }

    public function update(Request $request, TransitionNeedsProfile $tnp)
    {
        $this->authorize('update', $tnp);

        $validated = $request->validate([
            'clinical_flags' => 'nullable|array',
            'narrative_summary' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $updatedTnp = $this->tnpService->updateProfile($tnp, $validated);

        return response()->json($updatedTnp);
    }
}
