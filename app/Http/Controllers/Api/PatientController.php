<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\User;
use App\Services\InterraiSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Patient::with([
                'user',
                'queueEntry',
                'latestRugClassification',
                'latestInterraiAssessment',
                'hospital.user',
            ]);

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

            // Get InterRAI summary service for clinical flags
            $summaryService = App::make(InterraiSummaryService::class);

            // Transform data for API
            $data = $patients->map(function ($patient) use ($summaryService) {
                $userObj = $patient->user;
                $hospitalObj = $patient->hospital;
                $hospitalUser = $hospitalObj ? $hospitalObj->user : null;
                $queueEntry = $patient->queueEntry;

                // Get top clinical flags (first 3) for display
                $topFlags = [];
                if ($patient->latestInterraiAssessment) {
                    $summary = $summaryService->generateSummary($patient);
                    $activeFlags = $summaryService->getActiveFlagsWithLabels($summary['clinical_flags']);
                    $topFlags = array_slice($activeFlags, 0, 3);
                }

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
                    // RUG classification info (from InterRAI assessment)
                    'rug_group' => $patient->latestRugClassification?->rug_group,
                    'rug_category' => $patient->latestRugClassification?->rug_category,
                    'rug_description' => $patient->latestRugClassification?->rug_description,
                    'rug_label' => $patient->latestRugClassification?->rug_label,
                    'rug_numeric_rank' => $patient->latestRugClassification?->numeric_rank,
                    'has_interrai_assessment' => $patient->interraiAssessments()->where('assessment_type', 'hc')->exists(),
                    // Clinical flags (top 3 for display)
                    'top_clinical_flags' => $topFlags,
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
            $patient = Patient::with(['user', 'queueEntry', 'latestRugClassification', 'carePlans.careBundle'])->find($id);
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
                    // RUG classification info (from InterRAI assessment)
                    'rug_group' => $patient->latestRugClassification?->rug_group,
                    'rug_category' => $patient->latestRugClassification?->rug_category,
                    'rug_description' => $patient->latestRugClassification?->rug_description,
                    'rug_label' => $patient->latestRugClassification?->rug_label,
                    'has_interrai_assessment' => $patient->interraiAssessments()->where('assessment_type', 'hc')->exists(),
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

    /**
     * Get assessment history for a patient.
     *
     * Returns all InterRAI assessments and their RUG classifications,
     * ordered by assessment date descending (newest first).
     *
     * @param int $id Patient ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function assessments($id)
    {
        try {
            $patient = Patient::with([
                'interraiAssessments' => function ($q) {
                    $q->orderBy('assessment_date', 'desc');
                },
                'rugClassifications' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                },
            ])->find($id);

            if (!$patient) {
                return response()->json(['error' => 'Patient not found'], 404);
            }

            // Map assessments with their classifications
            $assessmentsData = $patient->interraiAssessments->map(function ($assessment) {
                // Find the RUG classification for this assessment (via relationship)
                $rugClassification = $assessment->rugClassifications()->first();

                return [
                    'id' => $assessment->id,
                    'assessment_date' => $assessment->assessment_date?->toIso8601String(),
                    'assessment_type' => $assessment->assessment_type,
                    'source' => $assessment->source,
                    'workflow_status' => $assessment->workflow_status,
                    'is_current' => $assessment->is_current,
                    'is_stale' => $assessment->isStale(),

                    // Core scores
                    'maple_score' => $assessment->maple_score,
                    'maple_description' => $assessment->maple_description,
                    'adl_hierarchy' => $assessment->adl_hierarchy,
                    'iadl_difficulty' => $assessment->iadl_difficulty,
                    'cognitive_performance_scale' => $assessment->cognitive_performance_scale,
                    'chess_score' => $assessment->chess_score,
                    'depression_rating_scale' => $assessment->depression_rating_scale,
                    'pain_scale' => $assessment->pain_scale,

                    // Flags
                    'falls_in_last_90_days' => $assessment->falls_in_last_90_days,
                    'wandering_flag' => $assessment->wandering_flag,

                    // Clinical
                    'primary_diagnosis_icd10' => $assessment->primary_diagnosis_icd10,
                    'caps_triggered' => $assessment->caps_triggered,

                    // RUG Classification (if computed)
                    'rug_classification' => $rugClassification ? [
                        'id' => $rugClassification->id,
                        'rug_group' => $rugClassification->rug_group,
                        'rug_category' => $rugClassification->rug_category,
                        'category_description' => $rugClassification->category_description,
                        'adl_sum' => $rugClassification->adl_sum,
                        'adl_level' => $rugClassification->adl_level,
                        'iadl_sum' => $rugClassification->iadl_sum,
                        'cps_score' => $rugClassification->cps_score,
                        'cps_level' => $rugClassification->cps_level,
                        'numeric_rank' => $rugClassification->numeric_rank,
                        'flags' => $rugClassification->flags,
                        'is_current' => $rugClassification->is_current,
                        'computed_at' => $rugClassification->created_at?->toIso8601String(),
                    ] : null,

                    // Timestamps
                    'created_at' => $assessment->created_at?->toIso8601String(),
                ];
            });

            return response()->json([
                'data' => [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->user?->name,
                    'total_assessments' => $assessmentsData->count(),
                    'assessments' => $assessmentsData,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get patient overview with InterRAI-driven summary and clinical flags.
     *
     * This endpoint returns comprehensive patient overview data including:
     * - Basic patient info
     * - Current RUG group/category
     * - Narrative summary (InterRAI-driven)
     * - Clinical flags (InterRAI-driven)
     * - Current care bundle/plan state
     *
     * @param int $id Patient ID
     * @param InterraiSummaryService $summaryService
     * @return \Illuminate\Http\JsonResponse
     */
    public function overview($id, InterraiSummaryService $summaryService)
    {
        try {
            $patient = Patient::with([
                'user',
                'queueEntry',
                'latestInterraiAssessment',
                'latestRugClassification',
                'carePlans' => function ($q) {
                    $q->where('status', 'active')->with('careBundle');
                },
            ])->find($id);

            if (!$patient) {
                return response()->json(['error' => 'Patient not found'], 404);
            }

            $userObj = $patient->user;
            $queueEntry = $patient->queueEntry;
            $assessment = $patient->latestInterraiAssessment;
            $rugClassification = $patient->latestRugClassification;
            $activePlan = $patient->carePlans->first();

            // Generate InterRAI-driven summary
            $summary = $summaryService->generateSummary($patient);

            // Get active flags with labels for UI display
            $activeFlags = $summaryService->getActiveFlagsWithLabels($summary['clinical_flags']);

            return response()->json([
                'data' => [
                    // Basic patient info
                    'id' => $patient->id,
                    'name' => $userObj?->name ?? 'Unknown',
                    'ohip' => $patient->ohip,
                    'date_of_birth' => $patient->date_of_birth,
                    'gender' => $patient->gender,
                    'status' => $patient->status,
                    'photo' => $userObj?->image ?? '/assets/images/patients/default.png',

                    // Queue status
                    'is_in_queue' => $patient->is_in_queue ?? false,
                    'queue_status' => $queueEntry?->queue_status,
                    'queue_status_label' => $queueEntry
                        ? PatientQueue::STATUS_LABELS[$queueEntry->queue_status] ?? $queueEntry->queue_status
                        : null,

                    // RUG Classification
                    'rug_classification' => $rugClassification ? [
                        'rug_group' => $rugClassification->rug_group,
                        'rug_category' => $rugClassification->rug_category,
                        'category_description' => $rugClassification->category_description,
                        'adl_sum' => $rugClassification->adl_sum,
                        'adl_level' => $rugClassification->adl_level,
                        'cps_score' => $rugClassification->cps_score,
                        'cps_level' => $rugClassification->cps_level,
                        'numeric_rank' => $rugClassification->numeric_rank,
                        'is_high_care_needs' => $rugClassification->isHighCareNeeds(),
                    ] : null,

                    // InterRAI Assessment summary
                    'assessment' => $assessment ? [
                        'id' => $assessment->id,
                        'date' => $assessment->assessment_date?->toIso8601String(),
                        'is_stale' => $assessment->isStale(),
                        'days_until_stale' => $assessment->days_until_stale,
                        'maple_score' => $assessment->maple_score,
                        'maple_description' => $assessment->maple_description,
                    ] : null,

                    // InterRAI-driven narrative and flags (replaces TNP)
                    'narrative_summary' => $summary['narrative_summary'],
                    'clinical_flags' => $summary['clinical_flags'],
                    'active_flags' => $activeFlags,
                    'assessment_status' => $summary['assessment_status'],

                    // Active care plan
                    'active_care_plan' => $activePlan ? [
                        'id' => $activePlan->id,
                        'bundle_name' => $activePlan->careBundle?->name,
                        'bundle_code' => $activePlan->careBundle?->code,
                        'status' => $activePlan->status,
                        'approved_at' => $activePlan->approved_at?->toIso8601String(),
                        'first_service_delivered' => $activePlan->hasFirstServiceDelivered(),
                        'first_service_sla_status' => $activePlan->first_service_sla_status,
                    ] : null,

                    // Timestamps
                    'activated_at' => $patient->activated_at,
                    'created_at' => $patient->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
