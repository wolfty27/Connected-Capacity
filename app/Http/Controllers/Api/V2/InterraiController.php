<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Jobs\UploadInterraiToIarJob;
use App\Models\InterraiAssessment;
use App\Models\InterraiDocument;
use App\Models\Patient;
use App\Models\ReassessmentTrigger;
use App\Services\InterraiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * InterraiController - InterRAI HC Assessment API
 *
 * Per IR-006: Handles InterRAI completion workflow including:
 * - Listing patients needing assessments
 * - Creating new SPO-completed assessments
 * - Viewing assessment history
 * - Triggering IAR uploads
 */
class InterraiController extends Controller
{
    public function __construct(
        protected InterraiService $interraiService
    ) {}

    /**
     * List patients needing InterRAI assessment.
     *
     * GET /api/v2/interrai/patients-needing-assessment
     */
    public function patientsNeedingAssessment(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 50), 200);
        $patients = $this->interraiService->getPatientsNeedingAssessment($limit);

        $data = $patients->map(function ($patient) {
            $latestAssessment = $patient->latestInterraiAssessment;
            $requiresInfo = $this->interraiService->requiresCompletion($patient);

            return [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'health_card_number' => substr($patient->health_card_number ?? '', -4) ? '****' . substr($patient->health_card_number, -4) : null,
                'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),
                'current_status' => $patient->status,
                'maple_score' => $patient->maple_score,
                'requires_assessment' => $requiresInfo['required'],
                'reason' => $requiresInfo['reason'],
                'message' => $requiresInfo['message'],
                'last_assessment_date' => $latestAssessment?->assessment_date?->format('Y-m-d'),
                'days_since_assessment' => $latestAssessment?->assessment_date?->diffInDays(now()),
                'care_plan_id' => $patient->carePlans()->latest()->first()?->id,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $data->count(),
                'with_stale_assessment' => $data->where('reason', 'stale_assessment')->count(),
                'without_assessment' => $data->where('reason', 'no_assessment')->count(),
            ],
        ]);
    }

    /**
     * Get assessment status for a specific patient.
     *
     * GET /api/v2/interrai/patients/{patient}/status
     */
    public function patientStatus(Patient $patient): JsonResponse
    {
        $requiresInfo = $this->interraiService->requiresCompletion($patient);
        $latestAssessment = $patient->latestInterraiAssessment;

        return response()->json([
            'data' => [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'requires_assessment' => $requiresInfo['required'],
                'reason' => $requiresInfo['reason'],
                'message' => $requiresInfo['message'],
                'latest_assessment' => $latestAssessment?->toSummaryArray(),
                'assessment_history_count' => $patient->interraiAssessments()->count(),
            ],
        ]);
    }

    /**
     * Get assessment history for a patient.
     *
     * GET /api/v2/interrai/patients/{patient}/assessments
     */
    public function patientAssessments(Patient $patient): JsonResponse
    {
        $assessments = $patient->interraiAssessments()
            ->orderBy('assessment_date', 'desc')
            ->get()
            ->map(fn ($a) => $a->toSummaryArray());

        return response()->json([
            'data' => $assessments,
            'meta' => [
                'total' => $assessments->count(),
            ],
        ]);
    }

    /**
     * Get single assessment details.
     *
     * GET /api/v2/interrai/assessments/{assessment}
     */
    public function show(InterraiAssessment $assessment): JsonResponse
    {
        $assessment->load(['patient', 'assessor']);

        return response()->json([
            'data' => [
                'id' => $assessment->id,
                'patient_id' => $assessment->patient_id,
                'patient_name' => $assessment->patient?->name,
                'assessment_type' => $assessment->assessment_type,
                'assessment_date' => $assessment->assessment_date->toIso8601String(),
                'assessor_id' => $assessment->assessor_id,
                'assessor_name' => $assessment->assessor?->name,
                'assessor_role' => $assessment->assessor_role,
                'source' => $assessment->source,
                'is_stale' => $assessment->isStale(),
                'days_until_stale' => $assessment->days_until_stale,

                // Clinical Scores
                'maple_score' => $assessment->maple_score,
                'maple_description' => $assessment->maple_description,
                'rai_cha_score' => $assessment->rai_cha_score,
                'adl_hierarchy' => $assessment->adl_hierarchy,
                'adl_description' => $assessment->adl_description,
                'iadl_difficulty' => $assessment->iadl_difficulty,
                'cognitive_performance_scale' => $assessment->cognitive_performance_scale,
                'cps_description' => $assessment->cps_description,
                'depression_rating_scale' => $assessment->depression_rating_scale,
                'pain_scale' => $assessment->pain_scale,
                'chess_score' => $assessment->chess_score,
                'method_for_locomotion' => $assessment->method_for_locomotion,
                'falls_in_last_90_days' => $assessment->falls_in_last_90_days,
                'wandering_flag' => $assessment->wandering_flag,

                // CAPs and Diagnoses
                'caps_triggered' => $assessment->caps_triggered,
                'primary_diagnosis_icd10' => $assessment->primary_diagnosis_icd10,
                'secondary_diagnoses' => $assessment->secondary_diagnoses,

                // Risk Flags
                'high_risk_flags' => $assessment->high_risk_flags,

                // Integration Status
                'iar_upload_status' => $assessment->iar_upload_status,
                'iar_upload_timestamp' => $assessment->iar_upload_timestamp?->toIso8601String(),
                'iar_confirmation_id' => $assessment->iar_confirmation_id,
                'chris_sync_status' => $assessment->chris_sync_status,
                'chris_sync_timestamp' => $assessment->chris_sync_timestamp?->toIso8601String(),

                // Timestamps
                'created_at' => $assessment->created_at->toIso8601String(),
                'updated_at' => $assessment->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create a new SPO-completed InterRAI assessment.
     *
     * POST /api/v2/interrai/patients/{patient}/assessments
     */
    public function store(Request $request, Patient $patient): JsonResponse
    {
        $validated = $request->validate([
            'assessment_type' => ['required', Rule::in([
                InterraiAssessment::TYPE_HC,
                InterraiAssessment::TYPE_CHA,
                InterraiAssessment::TYPE_CONTACT,
            ])],
            'assessment_date' => ['required', 'date', 'before_or_equal:today'],
            'assessor_role' => ['nullable', 'string', 'max:100'],

            // InterRAI HC Scores
            'maple_score' => ['required', Rule::in(['1', '2', '3', '4', '5'])],
            'rai_cha_score' => ['nullable', 'string', 'max:10'],
            'adl_hierarchy' => ['required', 'integer', 'min:0', 'max:6'],
            'iadl_difficulty' => ['nullable', 'integer', 'min:0', 'max:6'],
            'cognitive_performance_scale' => ['required', 'integer', 'min:0', 'max:6'],
            'depression_rating_scale' => ['nullable', 'integer', 'min:0', 'max:14'],
            'pain_scale' => ['nullable', 'integer', 'min:0', 'max:4'],
            'chess_score' => ['nullable', 'integer', 'min:0', 'max:5'],
            'method_for_locomotion' => ['nullable', 'string', 'max:50'],
            'falls_in_last_90_days' => ['required', 'boolean'],
            'wandering_flag' => ['required', 'boolean'],

            // Clinical Data
            'caps_triggered' => ['nullable', 'array'],
            'caps_triggered.*' => ['string'],
            'primary_diagnosis_icd10' => ['nullable', 'string', 'max:20'],
            'secondary_diagnoses' => ['nullable', 'array'],
            'secondary_diagnoses.*' => ['string', 'max:20'],

            // Optional notes
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $assessment = $this->interraiService->createSpoAssessment(
            patient: $patient,
            data: $validated,
            assessor: Auth::user(),
        );

        // Queue for IAR upload
        UploadInterraiToIarJob::dispatch($assessment);

        Log::info('InterRAI assessment created via API', [
            'assessment_id' => $assessment->id,
            'patient_id' => $patient->id,
            'assessor_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'InterRAI assessment created successfully',
            'data' => $assessment->toSummaryArray(),
        ], 201);
    }

    /**
     * Get InterRAI completion form schema/options.
     *
     * GET /api/v2/interrai/form-schema
     */
    public function formSchema(): JsonResponse
    {
        return response()->json([
            'data' => [
                'assessment_types' => [
                    ['value' => InterraiAssessment::TYPE_HC, 'label' => 'Home Care (RAI-HC)', 'description' => 'Full Home Care assessment'],
                    ['value' => InterraiAssessment::TYPE_CHA, 'label' => 'Contact Assessment (RAI-CHA)', 'description' => 'Contact assessment for screening'],
                    ['value' => InterraiAssessment::TYPE_CONTACT, 'label' => 'Contact', 'description' => 'Brief contact assessment'],
                ],
                'maple_scores' => [
                    ['value' => '1', 'label' => '1 - Low', 'description' => 'Low priority for home care placement'],
                    ['value' => '2', 'label' => '2 - Mild', 'description' => 'Mild priority'],
                    ['value' => '3', 'label' => '3 - Moderate', 'description' => 'Moderate priority'],
                    ['value' => '4', 'label' => '4 - High', 'description' => 'High priority'],
                    ['value' => '5', 'label' => '5 - Very High', 'description' => 'Very high priority for placement'],
                ],
                'adl_hierarchy' => [
                    ['value' => 0, 'label' => '0 - Independent', 'description' => 'No supervision or assistance required'],
                    ['value' => 1, 'label' => '1 - Supervision Required', 'description' => 'Oversight/cueing required'],
                    ['value' => 2, 'label' => '2 - Limited Assistance', 'description' => 'Help with some ADLs'],
                    ['value' => 3, 'label' => '3 - Extensive Assistance (1)', 'description' => 'Help with most ADLs'],
                    ['value' => 4, 'label' => '4 - Extensive Assistance (2)', 'description' => 'Full assistance with most ADLs'],
                    ['value' => 5, 'label' => '5 - Dependent', 'description' => 'Total assistance required'],
                    ['value' => 6, 'label' => '6 - Total Dependence', 'description' => 'Complete dependence in all ADLs'],
                ],
                'cps_scale' => [
                    ['value' => 0, 'label' => '0 - Intact', 'description' => 'No cognitive impairment'],
                    ['value' => 1, 'label' => '1 - Borderline Intact', 'description' => 'Borderline cognitive function'],
                    ['value' => 2, 'label' => '2 - Mild Impairment', 'description' => 'Mild cognitive impairment'],
                    ['value' => 3, 'label' => '3 - Moderate Impairment', 'description' => 'Moderate cognitive impairment'],
                    ['value' => 4, 'label' => '4 - Moderate-Severe', 'description' => 'Moderate to severe impairment'],
                    ['value' => 5, 'label' => '5 - Severe Impairment', 'description' => 'Severe cognitive impairment'],
                    ['value' => 6, 'label' => '6 - Very Severe', 'description' => 'Very severe cognitive impairment'],
                ],
                'chess_scale' => [
                    ['value' => 0, 'label' => '0 - Stable', 'description' => 'Health stable'],
                    ['value' => 1, 'label' => '1 - Minimal instability', 'description' => 'Minimal health instability'],
                    ['value' => 2, 'label' => '2 - Low instability', 'description' => 'Low health instability'],
                    ['value' => 3, 'label' => '3 - Moderate instability', 'description' => 'Moderate health instability'],
                    ['value' => 4, 'label' => '4 - High instability', 'description' => 'High health instability'],
                    ['value' => 5, 'label' => '5 - Very high instability', 'description' => 'Very high health instability'],
                ],
                'pain_scale' => [
                    ['value' => 0, 'label' => '0 - No Pain', 'description' => 'No pain reported'],
                    ['value' => 1, 'label' => '1 - Less than daily', 'description' => 'Pain less than daily'],
                    ['value' => 2, 'label' => '2 - Daily, mild-moderate', 'description' => 'Daily pain, mild to moderate'],
                    ['value' => 3, 'label' => '3 - Daily, severe', 'description' => 'Daily pain, severe or excruciating'],
                ],
                'common_caps' => [
                    'ADL/Rehab Potential',
                    'Activities',
                    'Cardio-Respiratory',
                    'Cognitive Loss',
                    'Communication',
                    'Dehydration',
                    'Depression and Anxiety',
                    'Falls',
                    'Health Promotion',
                    'IADL',
                    'Incontinence',
                    'Institutional Risk',
                    'Medication Management',
                    'Nutrition',
                    'Pain',
                    'Pressure Ulcer',
                    'Psychotropic Drug Use',
                    'Skin and Foot',
                    'Social Function',
                    'Tobacco and Alcohol',
                ],
                'locomotion_methods' => [
                    'Independent',
                    'Cane',
                    'Walker',
                    'Wheelchair',
                    'Scooter',
                    'Bedbound',
                ],
            ],
        ]);
    }

    /**
     * Retry IAR upload for a failed assessment.
     *
     * POST /api/v2/interrai/assessments/{assessment}/retry-iar
     */
    public function retryIarUpload(InterraiAssessment $assessment): JsonResponse
    {
        if ($assessment->iar_upload_status === InterraiAssessment::IAR_UPLOADED) {
            return response()->json([
                'message' => 'Assessment already uploaded to IAR',
                'confirmation_id' => $assessment->iar_confirmation_id,
            ], 400);
        }

        // Reset status and queue for upload
        $assessment->update(['iar_upload_status' => InterraiAssessment::IAR_PENDING]);
        UploadInterraiToIarJob::dispatch($assessment);

        Log::info('IAR upload retry queued', [
            'assessment_id' => $assessment->id,
            'requested_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'IAR upload queued for retry',
            'assessment_id' => $assessment->id,
        ]);
    }

    /**
     * Get pending IAR uploads.
     *
     * GET /api/v2/interrai/pending-iar-uploads
     */
    public function pendingIarUploads(): JsonResponse
    {
        $pending = $this->interraiService->getPendingIarUploads();

        return response()->json([
            'data' => $pending->map(fn ($a) => [
                'assessment_id' => $a->id,
                'patient_id' => $a->patient_id,
                'patient_name' => $a->patient?->name,
                'assessment_date' => $a->assessment_date->format('Y-m-d'),
                'created_at' => $a->created_at->toIso8601String(),
                'hours_pending' => $a->created_at->diffInHours(now()),
            ]),
            'meta' => [
                'total' => $pending->count(),
            ],
        ]);
    }

    /**
     * Get failed IAR uploads.
     *
     * GET /api/v2/interrai/failed-iar-uploads
     */
    public function failedIarUploads(): JsonResponse
    {
        $failed = InterraiAssessment::where('iar_upload_status', InterraiAssessment::IAR_FAILED)
            ->with('patient')
            ->orderBy('updated_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $failed->map(fn ($a) => [
                'assessment_id' => $a->id,
                'patient_id' => $a->patient_id,
                'patient_name' => $a->patient?->name,
                'assessment_date' => $a->assessment_date->format('Y-m-d'),
                'failed_at' => $a->updated_at->toIso8601String(),
                'can_retry' => true,
            ]),
            'meta' => [
                'total' => $failed->count(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | IR-003: External Assessment Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Create an assessment marked as completed externally.
     *
     * POST /api/v2/interrai/patients/{patient}/assessments/external
     */
    public function storeExternal(Request $request, Patient $patient): JsonResponse
    {
        $validated = $request->validate([
            'assessment_type' => ['nullable', Rule::in([
                InterraiAssessment::TYPE_HC,
                InterraiAssessment::TYPE_CHA,
                InterraiAssessment::TYPE_CONTACT,
            ])],
            'assessment_date' => ['required', 'date', 'before_or_equal:today'],
            'assessor_role' => ['nullable', 'string', 'max:100'],

            // Required scores for external entry
            'maple_score' => ['required', Rule::in(['1', '2', '3', '4', '5'])],
            'adl_hierarchy' => ['required', 'integer', 'min:0', 'max:6'],
            'cognitive_performance_scale' => ['required', 'integer', 'min:0', 'max:6'],
            'chess_score' => ['nullable', 'integer', 'min:0', 'max:5'],

            // Optional scores
            'rai_cha_score' => ['nullable', 'string', 'max:10'],
            'iadl_difficulty' => ['nullable', 'integer', 'min:0', 'max:6'],
            'depression_rating_scale' => ['nullable', 'integer', 'min:0', 'max:14'],
            'pain_scale' => ['nullable', 'integer', 'min:0', 'max:4'],
            'method_for_locomotion' => ['nullable', 'string', 'max:50'],
            'falls_in_last_90_days' => ['nullable', 'boolean'],
            'wandering_flag' => ['nullable', 'boolean'],

            // Already in IAR?
            'already_in_iar' => ['nullable', 'boolean'],
            'external_iar_id' => ['nullable', 'string', 'max:100'],
        ]);

        $assessment = $this->interraiService->createExternalAssessment(
            patient: $patient,
            data: $validated,
            enteredBy: Auth::user(),
        );

        // If external IAR ID provided, link it
        if (!empty($validated['external_iar_id'])) {
            $this->interraiService->linkExternalIar(
                $assessment,
                $validated['external_iar_id'],
                Auth::user()
            );
        }

        // Queue for IAR upload if not already there
        if ($assessment->iar_upload_status === InterraiAssessment::IAR_PENDING) {
            UploadInterraiToIarJob::dispatch($assessment);
        }

        Log::info('External InterRAI assessment created via API', [
            'assessment_id' => $assessment->id,
            'patient_id' => $patient->id,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'External assessment recorded successfully',
            'data' => $assessment->toSummaryArray(),
        ], 201);
    }

    /**
     * Link an external IAR document ID to a patient.
     *
     * POST /api/v2/interrai/patients/{patient}/link-external
     */
    public function linkExternal(Request $request, Patient $patient): JsonResponse
    {
        $validated = $request->validate([
            'iar_document_id' => ['required', 'string', 'max:100'],
            'assessment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Get or create an assessment to link to
        $assessment = $patient->latestInterraiAssessment;

        if (!$assessment) {
            return response()->json([
                'message' => 'No assessment found. Please create an assessment first.',
            ], 400);
        }

        $document = $this->interraiService->linkExternalIar(
            $assessment,
            $validated['iar_document_id'],
            Auth::user()
        );

        return response()->json([
            'message' => 'IAR document ID linked successfully',
            'data' => [
                'document_id' => $document->id,
                'assessment_id' => $assessment->id,
                'iar_document_id' => $validated['iar_document_id'],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | IR-004: Document Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Upload a document to an assessment.
     *
     * POST /api/v2/interrai/assessments/{assessment}/documents
     */
    public function uploadDocument(Request $request, InterraiAssessment $assessment): JsonResponse
    {
        $validated = $request->validate([
            'document' => [
                'required',
                'file',
                'max:' . (InterraiDocument::MAX_FILE_SIZE / 1024), // KB
                'mimes:pdf,jpeg,jpg,png,gif',
            ],
            'document_type' => ['nullable', Rule::in([
                InterraiDocument::TYPE_PDF,
                InterraiDocument::TYPE_ATTACHMENT,
            ])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $document = $this->interraiService->attachDocument(
            assessment: $assessment,
            data: [
                'document_type' => $validated['document_type'] ?? InterraiDocument::TYPE_PDF,
                'metadata' => ['notes' => $validated['notes'] ?? null],
            ],
            file: $request->file('document'),
            uploadedBy: Auth::user()
        );

        return response()->json([
            'message' => 'Document uploaded successfully',
            'data' => $document->toApiArray(),
        ], 201);
    }

    /**
     * List documents for an assessment.
     *
     * GET /api/v2/interrai/assessments/{assessment}/documents
     */
    public function listDocuments(InterraiAssessment $assessment): JsonResponse
    {
        $documents = $assessment->documents()
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $documents->map(fn ($d) => $d->toApiArray()),
            'meta' => [
                'total' => $documents->count(),
            ],
        ]);
    }

    /**
     * Delete a document.
     *
     * DELETE /api/v2/interrai/assessments/{assessment}/documents/{document}
     */
    public function deleteDocument(InterraiAssessment $assessment, InterraiDocument $document): JsonResponse
    {
        if ($document->interrai_assessment_id !== $assessment->id) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        // Delete file from storage
        $document->deleteFile();

        // Soft delete the record
        $document->delete();

        Log::info('InterRAI document deleted', [
            'document_id' => $document->id,
            'assessment_id' => $assessment->id,
            'deleted_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Document deleted successfully',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | IR-005: Reassessment Trigger Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Request a reassessment for a patient.
     *
     * POST /api/v2/interrai/patients/{patient}/request-reassessment
     */
    public function requestReassessment(Request $request, Patient $patient): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', Rule::in([
                ReassessmentTrigger::REASON_CONDITION_CHANGE,
                ReassessmentTrigger::REASON_MANUAL_REQUEST,
                ReassessmentTrigger::REASON_CLINICAL_EVENT,
            ])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', Rule::in([
                ReassessmentTrigger::PRIORITY_LOW,
                ReassessmentTrigger::PRIORITY_MEDIUM,
                ReassessmentTrigger::PRIORITY_HIGH,
                ReassessmentTrigger::PRIORITY_URGENT,
            ])],
        ]);

        $trigger = $this->interraiService->requestReassessment(
            patient: $patient,
            reason: $validated['reason'],
            notes: $validated['notes'] ?? null,
            priority: $validated['priority'] ?? ReassessmentTrigger::PRIORITY_MEDIUM,
            triggeredBy: Auth::user()
        );

        return response()->json([
            'message' => 'Reassessment request created',
            'data' => $trigger->toApiArray(),
        ], 201);
    }

    /**
     * Get pending reassessment triggers.
     *
     * GET /api/v2/interrai/reassessment-triggers
     */
    public function reassessmentTriggers(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 50), 200);
        $priority = $request->input('priority');

        $triggers = $this->interraiService->getReassessmentTriggers($limit, $priority);

        return response()->json([
            'data' => $triggers->map(fn ($t) => $t->toApiArray()),
            'meta' => [
                'total' => $triggers->count(),
            ],
        ]);
    }

    /**
     * Resolve a reassessment trigger.
     *
     * POST /api/v2/interrai/reassessment-triggers/{trigger}/resolve
     */
    public function resolveReassessmentTrigger(Request $request, ReassessmentTrigger $trigger): JsonResponse
    {
        if ($trigger->isResolved()) {
            return response()->json([
                'message' => 'Trigger already resolved',
            ], 400);
        }

        $validated = $request->validate([
            'assessment_id' => ['required', 'exists:interrai_assessments,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $assessment = InterraiAssessment::findOrFail($validated['assessment_id']);

        $this->interraiService->resolveReassessmentTrigger(
            trigger: $trigger,
            assessment: $assessment,
            resolvedBy: Auth::user(),
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Reassessment trigger resolved',
            'data' => $trigger->fresh()->toApiArray(),
        ]);
    }

    /**
     * Get reassessment trigger options for forms.
     *
     * GET /api/v2/interrai/reassessment-trigger-options
     */
    public function reassessmentTriggerOptions(): JsonResponse
    {
        return response()->json([
            'data' => [
                'reasons' => ReassessmentTrigger::getReasonOptions(),
                'priorities' => ReassessmentTrigger::getPriorityOptions(),
            ],
        ]);
    }
}
