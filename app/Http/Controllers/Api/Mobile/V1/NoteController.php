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
            'note_text' => 'required|string',
            'note_type' => 'nullable|string', // e.g., 'voice', 'text'
        ]);

        $note = InterdisciplinaryNote::create([
            'patient_id' => $validated['patient_id'],
            'author_id' => $request->user()->id,
            'author_role' => $request->user()->role, // Assuming role is string on user
            'note_text' => $validated['note_text'],
            'note_type' => $validated['note_type'] ?? 'text',
            'created_at' => now(),
        ]);

        return response()->json($note, 201);
    }
}
