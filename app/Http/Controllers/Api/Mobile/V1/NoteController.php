<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\InterdisciplinaryNote;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'content' => 'required|string',
            'note_type' => 'nullable|string|in:clinical,psw,mh,rpm,escalation',
            'service_assignment_id' => 'nullable|exists:service_assignments,id',
        ]);

        $note = InterdisciplinaryNote::create([
            'patient_id' => $validated['patient_id'],
            'service_assignment_id' => $validated['service_assignment_id'] ?? null,
            'author_id' => $request->user()->id,
            'author_role' => $request->user()->organization_role ?? $request->user()->role ?? 'Staff',
            'content' => $validated['content'],
            'note_type' => $validated['note_type'] ?? 'clinical',
        ]);

        return response()->json($note, 201);
    }
}
