<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\ServiceProviderOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * PatientNotesController
 *
 * Handles CRUD operations for patient clinical notes and narrative summaries.
 * Replaces the legacy TNP narrative functionality with a more flexible
 * note-based system.
 */
class PatientNotesController extends Controller
{
    /**
     * Get all notes for a patient.
     *
     * Returns notes ordered by created_at DESC (newest first),
     * with the summary note (if any) highlighted separately.
     */
    public function index(Patient $patient)
    {
        try {
            // Get all notes ordered by date descending
            $notes = $patient->patientNotes()
                ->with('author')
                ->orderBy('created_at', 'desc')
                ->get();

            // Separate summary from other notes
            $summaryNote = $notes->firstWhere('note_type', PatientNote::TYPE_SUMMARY);
            $otherNotes = $notes->where('note_type', '!=', PatientNote::TYPE_SUMMARY)->values();

            return response()->json([
                'data' => [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->user?->name,
                    'summary_note' => $summaryNote?->toApiArray(),
                    'notes' => $otherNotes->map(fn($note) => $note->toApiArray()),
                    'total_count' => $notes->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new note for a patient.
     *
     * Automatically sets the source based on the authenticated user's
     * organization (OHaH vs SPO).
     */
    public function store(Request $request, Patient $patient)
    {
        $request->validate([
            'content' => 'required|string|min:1',
            'note_type' => 'nullable|string|in:summary,update,contact,clinical,general',
        ]);

        try {
            $user = Auth::user();

            // Determine source based on user's organization
            $source = $this->determineSourceForUser($user);

            $note = PatientNote::create([
                'patient_id' => $patient->id,
                'author_id' => $user->id,
                'source' => $source,
                'note_type' => $request->note_type ?? PatientNote::TYPE_GENERAL,
                'content' => $request->content,
            ]);

            return response()->json([
                'message' => 'Note created successfully',
                'data' => $note->load('author')->toApiArray(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing note.
     */
    public function update(Request $request, PatientNote $note)
    {
        $request->validate([
            'content' => 'required|string|min:1',
        ]);

        try {
            $note->update([
                'content' => $request->content,
            ]);

            return response()->json([
                'message' => 'Note updated successfully',
                'data' => $note->fresh()->load('author')->toApiArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a note.
     */
    public function destroy(PatientNote $note)
    {
        try {
            $note->delete();

            return response()->json([
                'message' => 'Note deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Determine the source string based on the user's organization.
     */
    protected function determineSourceForUser($user): string
    {
        // Check if user belongs to an SPO organization
        if ($user->service_provider_organization_id) {
            $spo = ServiceProviderOrganization::find($user->service_provider_organization_id);
            if ($spo) {
                return $spo->name;
            }
        }

        // Check user role for organization context
        if ($user->role === 'spo' || $user->role === 'spo_admin') {
            // Try to find SPO from user's relationships
            $spo = ServiceProviderOrganization::whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })->first();

            if ($spo) {
                return $spo->name;
            }
        }

        // Default to OHaH for all other users (admin, coordinator, etc.)
        return PatientNote::SOURCE_OHAH;
    }
}
