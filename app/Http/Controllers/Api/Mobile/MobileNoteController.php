<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\InterdisciplinaryNote;
use App\Models\ServiceAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MobileNoteController - Mobile note submission API
 *
 * Per MOB-004: Handles note creation from mobile devices with offline sync support
 * - Create notes linked to assignments
 * - Batch submit notes (for offline sync)
 * - Track sync status for offline-first approach
 */
class MobileNoteController extends Controller
{
    /**
     * Get note templates for quick entry.
     *
     * GET /api/mobile/notes/templates
     */
    public function templates(): JsonResponse
    {
        // Common note templates for field staff
        $templates = [
            [
                'id' => 'visit_complete',
                'name' => 'Visit Complete',
                'category' => 'psw',
                'template' => "Visit completed as scheduled.\n\nServices provided:\n- \n\nPatient status: [stable/improved/declined]\n\nNotes:",
            ],
            [
                'id' => 'medication_reminder',
                'name' => 'Medication Reminder',
                'category' => 'clinical',
                'template' => "Medication reminder provided.\n\nMedications reviewed:\n- \n\nPatient confirmed understanding: [yes/no]\n\nConcerns:",
            ],
            [
                'id' => 'fall_incident',
                'name' => 'Fall/Incident Report',
                'category' => 'escalation',
                'template' => "INCIDENT REPORT\n\nType: Fall\nTime observed: \nLocation: \n\nDescription:\n\nInjuries observed: [none/minor/requires assessment]\n\nAction taken:\n\nFamily/coordinator notified: [yes/no]",
            ],
            [
                'id' => 'patient_declined',
                'name' => 'Patient Declined Service',
                'category' => 'psw',
                'template' => "Patient declined scheduled service.\n\nReason given:\n\nPatient advised of: \n\nFollow-up required: [yes/no]",
            ],
            [
                'id' => 'condition_change',
                'name' => 'Condition Change',
                'category' => 'clinical',
                'template' => "CONDITION CHANGE OBSERVED\n\nSymptoms observed:\n- \n\nVital signs (if taken):\n\nPatient response:\n\nRecommendation:",
            ],
            [
                'id' => 'equipment_issue',
                'name' => 'Equipment Issue',
                'category' => 'psw',
                'template' => "Equipment issue reported.\n\nEquipment: \nIssue: \n\nWorkaround used: \n\nReplacement needed: [yes/no]",
            ],
        ];

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Create a new note.
     *
     * POST /api/mobile/notes
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'service_assignment_id' => ['nullable', 'exists:service_assignments,id'],
            'note_type' => ['required', 'string', 'in:clinical,psw,mh,rpm,escalation'],
            'content' => ['required', 'string', 'min:10', 'max:5000'],
            'client_note_id' => ['nullable', 'string', 'max:100'], // For offline sync deduplication
            'offline_created_at' => ['nullable', 'date'],
        ]);

        // Check for duplicate if client_note_id provided
        if (!empty($validated['client_note_id'])) {
            $existing = InterdisciplinaryNote::where('client_note_id', $validated['client_note_id'])->first();
            if ($existing) {
                return response()->json([
                    'message' => 'Note already synced',
                    'data' => $this->formatNote($existing),
                    'duplicate' => true,
                ]);
            }
        }

        $note = InterdisciplinaryNote::create([
            'patient_id' => $validated['patient_id'],
            'service_assignment_id' => $validated['service_assignment_id'] ?? null,
            'author_id' => $user->id,
            'author_role' => $user->organization_role ?? 'Staff',
            'note_type' => $validated['note_type'],
            'content' => $validated['content'],
            'client_note_id' => $validated['client_note_id'] ?? Str::uuid()->toString(),
            'created_at' => $validated['offline_created_at'] ?? now(),
        ]);

        Log::info('Mobile note created', [
            'note_id' => $note->id,
            'patient_id' => $note->patient_id,
            'user_id' => $user->id,
            'type' => $note->note_type,
        ]);

        return response()->json([
            'message' => 'Note created successfully',
            'data' => $this->formatNote($note),
        ], 201);
    }

    /**
     * Batch submit multiple notes (for offline sync).
     *
     * POST /api/mobile/notes/batch
     */
    public function batchStore(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'notes' => ['required', 'array', 'min:1', 'max:50'],
            'notes.*.patient_id' => ['required', 'exists:patients,id'],
            'notes.*.service_assignment_id' => ['nullable', 'exists:service_assignments,id'],
            'notes.*.note_type' => ['required', 'string', 'in:clinical,psw,mh,rpm,escalation'],
            'notes.*.content' => ['required', 'string', 'min:10', 'max:5000'],
            'notes.*.client_note_id' => ['required', 'string', 'max:100'],
            'notes.*.offline_created_at' => ['nullable', 'date'],
        ]);

        $results = [];
        $created = 0;
        $duplicates = 0;
        $errors = 0;

        // Process notes without transaction wrapping individual creates
        // This allows partial success - some notes may be created even if others fail
        foreach ($validated['notes'] as $noteData) {
            // Check for duplicate
            $existing = InterdisciplinaryNote::where('client_note_id', $noteData['client_note_id'])->first();

            if ($existing) {
                $results[] = [
                    'client_note_id' => $noteData['client_note_id'],
                    'status' => 'duplicate',
                    'note_id' => $existing->id,
                ];
                $duplicates++;
                continue;
            }

            try {
                $note = InterdisciplinaryNote::create([
                    'patient_id' => $noteData['patient_id'],
                    'service_assignment_id' => $noteData['service_assignment_id'] ?? null,
                    'author_id' => $user->id,
                    'author_role' => $user->organization_role ?? 'Staff',
                    'note_type' => $noteData['note_type'],
                    'content' => $noteData['content'],
                    'client_note_id' => $noteData['client_note_id'],
                    'created_at' => $noteData['offline_created_at'] ?? now(),
                ]);

                $results[] = [
                    'client_note_id' => $noteData['client_note_id'],
                    'status' => 'created',
                    'note_id' => $note->id,
                ];
                $created++;
            } catch (\Exception $e) {
                $results[] = [
                    'client_note_id' => $noteData['client_note_id'],
                    'status' => 'error',
                    'error' => 'Failed to create note: ' . $e->getMessage(),
                ];
                $errors++;
            }
        }

        Log::info('Mobile batch note sync', [
            'user_id' => $user->id,
            'total' => count($validated['notes']),
            'created' => $created,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ]);

        return response()->json([
            'message' => "Batch sync complete: {$created} created, {$duplicates} duplicates, {$errors} errors",
            'summary' => [
                'total' => count($validated['notes']),
                'created' => $created,
                'duplicates' => $duplicates,
                'errors' => $errors,
            ],
            'results' => $results,
        ]);
    }

    /**
     * Get notes pending sync confirmation.
     *
     * GET /api/mobile/notes/pending-sync
     */
    public function pendingSync(Request $request): JsonResponse
    {
        $user = Auth::user();
        $clientNoteIds = $request->input('client_note_ids', []);

        if (empty($clientNoteIds)) {
            return response()->json([
                'data' => [],
            ]);
        }

        // Find which notes have been synced
        $syncedNotes = InterdisciplinaryNote::whereIn('client_note_id', $clientNoteIds)
            ->pluck('client_note_id')
            ->toArray();

        return response()->json([
            'data' => [
                'synced' => $syncedNotes,
                'pending' => array_values(array_diff($clientNoteIds, $syncedNotes)),
            ],
        ]);
    }

    /**
     * Acknowledge a synced note (for client-side cleanup).
     *
     * POST /api/mobile/notes/{note}/acknowledge-sync
     */
    public function acknowledgeSync(InterdisciplinaryNote $note): JsonResponse
    {
        $this->authorize('view', $note);

        return response()->json([
            'message' => 'Sync acknowledged',
            'data' => [
                'note_id' => $note->id,
                'client_note_id' => $note->client_note_id,
                'synced_at' => $note->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Format note for response.
     */
    protected function formatNote(InterdisciplinaryNote $note): array
    {
        return [
            'id' => $note->id,
            'patient_id' => $note->patient_id,
            'service_assignment_id' => $note->service_assignment_id,
            'note_type' => $note->note_type,
            'content' => $note->content,
            'author_id' => $note->author_id,
            'author_role' => $note->author_role,
            'client_note_id' => $note->client_note_id,
            'created_at' => $note->created_at->toIso8601String(),
        ];
    }
}
