<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Patient::with(['user', 'queueEntry', 'transitionNeedsProfile', 'hospital.user']);

            // Filter by queue status if requested
            $showQueue = $request->get('show_queue', null);
            if ($showQueue === 'true' || $showQueue === '1') {
                $query->where('is_in_queue', true);
            } elseif ($showQueue === 'false' || $showQueue === '0') {
                $query->where('is_in_queue', false);
            }

            // Basic role-based filtering
            if ($user->role == 'hospital') {
                $hospitalObj = Hospital::where('user_id', $user->id)->first();
                if ($hospitalObj) {
                    $query->where('hospital_id', $hospitalObj->id);
                }
            } elseif ($user->role == 'retirement-home') {
                $query->where('status', '!=', 'Inactive')
                    ->where('status', '!=', 'Placement Made');
            }
            // Admin or other roles see all patients

            $patients = $query->get();

            // Transform data for API
            $data = $patients->map(function ($patient) {
                $userObj = $patient->user;
                $hospitalObj = $patient->hospital;
                $hospitalUser = $hospitalObj ? $hospitalObj->user : null;
                $queueEntry = $patient->queueEntry;

                return [
                    'id' => $patient->id,
                    'name' => $userObj ? $userObj->name : 'Unknown', // Direct name field for frontend
                    'user' => [
                        'name' => $userObj ? $userObj->name : 'Unknown',
                        'email' => $userObj ? $userObj->email : null,
                        'phone' => $userObj ? $userObj->phone_number : null,
                    ],
                    'ohip' => $patient->ohip,
                    'photo' => $userObj && $userObj->image ? $userObj->image : '/assets/images/patients/default.png',
                    'gender' => $patient->gender,
                    'status' => $patient->status,
                    'hospital' => $hospitalUser ? $hospitalUser->name : 'Unknown',
                    'created_at' => $patient->created_at,
                    // Queue-related fields
                    'is_in_queue' => $patient->is_in_queue ?? false,
                    'queue_status' => $queueEntry ? $queueEntry->queue_status : null,
                    'queue_status_label' => $queueEntry ? PatientQueue::STATUS_LABELS[$queueEntry->queue_status] ?? $queueEntry->queue_status : null,
                    'activated_at' => $patient->activated_at,
                    // TNP info if available
                    'has_tnp' => $patient->transitionNeedsProfile !== null,
                    'tnp_status' => $patient->transitionNeedsProfile?->status,
                ];
            });

            // Calculate summary stats
            $summary = [
                'total' => $patients->count(),
                'active' => $patients->where('status', 'Active')->where('is_in_queue', false)->count(),
                'in_queue' => $patients->where('is_in_queue', true)->count(),
            ];

            return response()->json([
                'data' => $data,
                'summary' => $summary,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $patient = Patient::with(['user', 'queueEntry', 'transitionNeedsProfile', 'carePlans.careBundle'])->find($id);
            if (!$patient) {
                return response()->json(['error' => 'Patient not found'], 404);
            }

            $userObj = $patient->user;
            $queueEntry = $patient->queueEntry;

            return response()->json([
                'data' => [
                    'id' => $patient->id,
                    'name' => $userObj ? $userObj->name : 'Unknown',
                    'user' => [
                        'name' => $userObj ? $userObj->name : 'Unknown',
                        'email' => $userObj ? $userObj->email : '',
                        'phone' => $userObj ? $userObj->phone_number : '',
                    ],
                    'ohip' => $patient->ohip,
                    'date_of_birth' => $patient->date_of_birth,
                    'diagnosis' => null, // Placeholder
                    'gender' => $patient->gender,
                    'status' => $patient->status,
                    'photo' => $userObj && $userObj->image ? $userObj->image : '/assets/images/patients/default.png',
                    // Queue-related fields
                    'is_in_queue' => $patient->is_in_queue ?? false,
                    'queue_status' => $queueEntry ? $queueEntry->queue_status : null,
                    'queue_status_label' => $queueEntry ? PatientQueue::STATUS_LABELS[$queueEntry->queue_status] ?? $queueEntry->queue_status : null,
                    'queue_entry' => $queueEntry ? [
                        'id' => $queueEntry->id,
                        'status' => $queueEntry->queue_status,
                        'priority' => $queueEntry->priority,
                        'entered_queue_at' => $queueEntry->entered_queue_at,
                    ] : null,
                    'activated_at' => $patient->activated_at,
                    // TNP info
                    'has_tnp' => $patient->transitionNeedsProfile !== null,
                    'tnp' => $patient->transitionNeedsProfile ? [
                        'id' => $patient->transitionNeedsProfile->id,
                        'status' => $patient->transitionNeedsProfile->status,
                        'clinical_flags' => $patient->transitionNeedsProfile->clinical_flags,
                        'narrative_summary' => $patient->transitionNeedsProfile->narrative_summary,
                    ] : null,
                    // Care plans
                    'care_plans' => $patient->carePlans->map(function ($plan) {
                        return [
                            'id' => $plan->id,
                            'bundle_name' => $plan->careBundle?->name,
                            'status' => $plan->status,
                            'created_at' => $plan->created_at,
                        ];
                    }),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'gender' => 'required|in:Male,Female,Other',
            'date_of_birth' => 'nullable|date',
            'ohip' => 'nullable|string|max:50',
            'hospital_id' => 'nullable|exists:hospitals,id',
            'add_to_queue' => 'nullable|boolean',
        ]);

        try {
            // Create user account for patient
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt('password'), // Default password
                'role' => 'patient',
            ]);

            // Create patient record
            $patient = Patient::create([
                'user_id' => $user->id,
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'ohip' => $request->ohip,
                'hospital_id' => $request->hospital_id,
                'status' => $request->add_to_queue ? 'Pending' : 'Active',
                'is_in_queue' => $request->add_to_queue ?? false,
            ]);

            // Add to queue if requested
            if ($request->add_to_queue) {
                PatientQueue::create([
                    'patient_id' => $patient->id,
                    'queue_status' => 'pending_intake',
                    'priority' => 5,
                    'entered_queue_at' => now(),
                ]);
            }

            return response()->json([
                'message' => 'Patient created successfully',
                'data' => [
                    'id' => $patient->id,
                    'name' => $user->name,
                    'status' => $patient->status,
                    'is_in_queue' => $patient->is_in_queue,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $patient = Patient::find($id);
        if (!$patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:Male,Female,Other',
            'date_of_birth' => 'nullable|date',
            'ohip' => 'nullable|string|max:50',
            'status' => 'nullable|string',
        ]);

        try {
            // Update user name if provided
            if ($request->has('name') && $patient->user) {
                $patient->user->update(['name' => $request->name]);
            }

            // Update patient fields
            $patient->update($request->only(['gender', 'date_of_birth', 'ohip', 'status']));

            return response()->json([
                'message' => 'Patient updated successfully',
                'data' => [
                    'id' => $patient->id,
                    'name' => $patient->user?->name,
                    'status' => $patient->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $patient = Patient::find($id);
        if (!$patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }

        try {
            // Soft delete the patient
            $patient->delete();

            return response()->json([
                'message' => 'Patient deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
