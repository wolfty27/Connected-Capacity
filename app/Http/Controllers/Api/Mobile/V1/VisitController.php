<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VisitController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Fetch visits for today where the user is assigned via the CareAssignment
        // This assumes a relationship chain: Visit -> CareAssignment -> assigned_user_id
        $visits = Visit::whereHas('careAssignment', function ($query) use ($user) {
            $query->where('assigned_user_id', $user->id);
        })
        ->whereDate('scheduled_at', now())
        ->with('patient.user') // Eager load patient info
        ->orderBy('scheduled_at')
        ->get();

        return response()->json($visits);
    }

    public function clockIn(Request $request, Visit $visit)
    {
        // Ensure the user is assigned to this visit's assignment
        if ($visit->careAssignment->assigned_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $visit->update([
            'actual_start_at' => now(),
            'status' => 'in_progress'
        ]);

        return response()->json($visit);
    }

    public function clockOut(Request $request, Visit $visit)
    {
        if ($visit->careAssignment->assigned_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $visit->update([
            'actual_end_at' => now(),
            'status' => 'completed'
        ]);

        return response()->json($visit);
    }
}
