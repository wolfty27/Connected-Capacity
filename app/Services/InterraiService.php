<?php

namespace App\Services;

use App\Models\InterraiAssessment;
use App\Models\InterraiDocument;
use App\Models\Patient;
use App\Models\ReassessmentTrigger;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * InterraiService - Handles InterRAI HC assessment operations
 *
 * Per OHaH RFS requirements:
 * - Extract InterRAI data from HPG referral payloads
 * - Track assessment staleness (>3 months requires reassessment)
 * - Upload assessments to IAR (Integrated Assessment Record)
 * - Sync with CHRIS (Client Health Related Information System)
 */
class InterraiService
{
    /**
     * Extract InterRAI assessment data from HPG referral payload.
     *
     * HPG referrals may include InterRAI HC data in the raw_referral_payload.
     * This method parses that data and creates an InterraiAssessment record.
     */
    public function extractFromHpgPayload(Patient $patient, array $hpgPayload): ?InterraiAssessment
    {
        // Check if InterRAI data exists in payload
        $interraiData = $hpgPayload['interrai'] ?? $hpgPayload['interrai_hc'] ?? $hpgPayload['assessment'] ?? null;

        if (!$interraiData) {
            Log::info('No InterRAI data found in HPG payload', ['patient_id' => $patient->id]);
            return null;
        }

        return DB::transaction(function () use ($patient, $interraiData, $hpgPayload) {
            $assessment = InterraiAssessment::create([
                'patient_id' => $patient->id,
                'assessment_type' => $interraiData['type'] ?? InterraiAssessment::TYPE_HC,
                'assessment_date' => $this->parseDate($interraiData['assessment_date'] ?? $interraiData['date'] ?? null),
                'assessor_role' => $interraiData['assessor_role'] ?? null,
                'source' => InterraiAssessment::SOURCE_HPG,

                // InterRAI HC Output Scores
                'maple_score' => $interraiData['maple_score'] ?? $interraiData['maple'] ?? null,
                'rai_cha_score' => $interraiData['rai_cha_score'] ?? $interraiData['cha_score'] ?? null,
                'adl_hierarchy' => $this->parseInteger($interraiData['adl_hierarchy'] ?? $interraiData['adl'] ?? null),
                'iadl_difficulty' => $this->parseInteger($interraiData['iadl_difficulty'] ?? $interraiData['iadl'] ?? null),
                'cognitive_performance_scale' => $this->parseInteger($interraiData['cps'] ?? $interraiData['cognitive_performance_scale'] ?? null),
                'depression_rating_scale' => $this->parseInteger($interraiData['drs'] ?? $interraiData['depression_rating_scale'] ?? null),
                'pain_scale' => $this->parseInteger($interraiData['pain_scale'] ?? $interraiData['pain'] ?? null),
                'chess_score' => $this->parseInteger($interraiData['chess'] ?? $interraiData['chess_score'] ?? null),
                'method_for_locomotion' => $interraiData['locomotion'] ?? $interraiData['method_for_locomotion'] ?? null,
                'falls_in_last_90_days' => $this->parseBoolean($interraiData['falls'] ?? $interraiData['falls_in_last_90_days'] ?? false),
                'wandering_flag' => $this->parseBoolean($interraiData['wandering'] ?? $interraiData['wandering_flag'] ?? false),

                // Clinical Diagnosis (CAPs)
                'caps_triggered' => $interraiData['caps'] ?? $interraiData['caps_triggered'] ?? null,
                'primary_diagnosis_icd10' => $interraiData['primary_diagnosis'] ?? $interraiData['primary_diagnosis_icd10'] ?? null,
                'secondary_diagnoses' => $interraiData['secondary_diagnoses'] ?? null,

                // IAR Integration - starts as pending
                'iar_upload_status' => InterraiAssessment::IAR_PENDING,
                'chris_sync_status' => InterraiAssessment::CHRIS_PENDING,

                // Store raw data for reference
                'raw_assessment_data' => $interraiData,
            ]);

            // Update patient's cached MAPLe and RAI CHA scores
            $this->updatePatientScores($patient, $assessment);

            Log::info('InterRAI assessment extracted from HPG payload', [
                'patient_id' => $patient->id,
                'assessment_id' => $assessment->id,
                'maple_score' => $assessment->maple_score,
            ]);

            return $assessment;
        });
    }

    /**
     * Create a new InterRAI assessment completed by SPO staff.
     */
    public function createSpoAssessment(Patient $patient, array $data, ?User $assessor = null): InterraiAssessment
    {
        return DB::transaction(function () use ($patient, $data, $assessor) {
            $assessment = InterraiAssessment::create([
                'patient_id' => $patient->id,
                'assessment_type' => $data['assessment_type'] ?? InterraiAssessment::TYPE_HC,
                'assessment_date' => $this->parseDate($data['assessment_date'] ?? now()),
                'assessor_id' => $assessor?->id,
                'assessor_role' => $data['assessor_role'] ?? $assessor?->organization_role ?? 'Care Coordinator',
                'source' => InterraiAssessment::SOURCE_SPO,

                // InterRAI HC Output Scores
                'maple_score' => $data['maple_score'] ?? null,
                'rai_cha_score' => $data['rai_cha_score'] ?? null,
                'adl_hierarchy' => $this->parseInteger($data['adl_hierarchy'] ?? null),
                'iadl_difficulty' => $this->parseInteger($data['iadl_difficulty'] ?? null),
                'cognitive_performance_scale' => $this->parseInteger($data['cognitive_performance_scale'] ?? null),
                'depression_rating_scale' => $this->parseInteger($data['depression_rating_scale'] ?? null),
                'pain_scale' => $this->parseInteger($data['pain_scale'] ?? null),
                'chess_score' => $this->parseInteger($data['chess_score'] ?? null),
                'method_for_locomotion' => $data['method_for_locomotion'] ?? null,
                'falls_in_last_90_days' => $this->parseBoolean($data['falls_in_last_90_days'] ?? false),
                'wandering_flag' => $this->parseBoolean($data['wandering_flag'] ?? false),

                // Clinical Diagnosis
                'caps_triggered' => $data['caps_triggered'] ?? null,
                'primary_diagnosis_icd10' => $data['primary_diagnosis_icd10'] ?? null,
                'secondary_diagnoses' => $data['secondary_diagnoses'] ?? null,

                // IAR Integration
                'iar_upload_status' => InterraiAssessment::IAR_PENDING,
                'chris_sync_status' => InterraiAssessment::CHRIS_PENDING,

                'raw_assessment_data' => $data['raw_assessment_data'] ?? null,
            ]);

            $this->updatePatientScores($patient, $assessment);

            Log::info('SPO InterRAI assessment created', [
                'patient_id' => $patient->id,
                'assessment_id' => $assessment->id,
                'assessor_id' => $assessor?->id,
            ]);

            return $assessment;
        });
    }

    /**
     * Check if a patient requires a new InterRAI assessment.
     *
     * Per OHaH RFS: SPO must complete InterRAI HC if:
     * - No assessment exists
     * - Existing assessment is >3 months old
     * - Clinical condition has significantly changed
     */
    public function requiresCompletion(Patient $patient): array
    {
        $latestAssessment = $patient->latestInterraiAssessment;

        if (!$latestAssessment) {
            return [
                'required' => true,
                'reason' => 'no_assessment',
                'message' => 'No InterRAI HC assessment on file. SPO must complete assessment.',
            ];
        }

        if ($latestAssessment->isStale()) {
            return [
                'required' => true,
                'reason' => 'stale_assessment',
                'message' => "InterRAI HC assessment is {$latestAssessment->assessment_date->diffInDays(now())} days old (>90 days). Reassessment required.",
                'last_assessment_date' => $latestAssessment->assessment_date->toIso8601String(),
                'days_since_assessment' => $latestAssessment->assessment_date->diffInDays(now()),
            ];
        }

        return [
            'required' => false,
            'reason' => 'current_assessment',
            'message' => 'Current InterRAI HC assessment on file.',
            'last_assessment_date' => $latestAssessment->assessment_date->toIso8601String(),
            'days_until_stale' => $latestAssessment->days_until_stale,
        ];
    }

    /**
     * Get all stale assessments that need attention.
     */
    public function getStaleAssessments(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return InterraiAssessment::stale()
            ->with('patient.user')
            ->orderBy('assessment_date', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get patients who need InterRAI assessment.
     */
    public function getPatientsNeedingAssessment(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        // Get patients without any assessment
        $patientsWithoutAssessment = Patient::whereDoesntHave('interraiAssessments')
            ->where('is_in_queue', true)
            ->with('user')
            ->limit($limit)
            ->get();

        // Get patients with only stale assessments
        $patientsWithStaleAssessments = Patient::whereHas('interraiAssessments', function ($query) {
            $query->where('assessment_date', '<', now()->subMonths(InterraiAssessment::STALENESS_MONTHS));
        })
            ->whereDoesntHave('interraiAssessments', function ($query) {
                $query->where('assessment_date', '>=', now()->subMonths(InterraiAssessment::STALENESS_MONTHS));
            })
            ->with('user')
            ->limit($limit)
            ->get();

        return $patientsWithoutAssessment->merge($patientsWithStaleAssessments);
    }

    /**
     * Upload assessment to IAR (Integrated Assessment Record).
     *
     * This is a stub implementation. Real implementation will depend on
     * Ontario Health IAR API specifications.
     */
    public function uploadToIar(InterraiAssessment $assessment): array
    {
        try {
            // TODO: Implement actual IAR API integration
            // For now, this is a stub that simulates the upload

            Log::info('IAR upload initiated', [
                'assessment_id' => $assessment->id,
                'patient_id' => $assessment->patient_id,
            ]);

            // Simulate IAR upload
            // In production, this would:
            // 1. Format data per IAR specification
            // 2. Call IAR API endpoint
            // 3. Handle response and store confirmation ID

            // For now, mark as uploaded with a mock confirmation ID
            $confirmationId = 'IAR-' . strtoupper(substr(md5($assessment->id . now()->timestamp), 0, 12));

            $assessment->markIarUploaded($confirmationId);

            Log::info('IAR upload successful', [
                'assessment_id' => $assessment->id,
                'confirmation_id' => $confirmationId,
            ]);

            return [
                'success' => true,
                'confirmation_id' => $confirmationId,
                'uploaded_at' => $assessment->iar_upload_timestamp->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error('IAR upload failed', [
                'assessment_id' => $assessment->id,
                'error' => $e->getMessage(),
            ]);

            $assessment->markIarFailed();

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync assessment to CHRIS (Client Health Related Information System).
     *
     * This is a stub implementation. Real implementation will depend on
     * OHaH CHRIS integration specifications.
     */
    public function syncToChris(InterraiAssessment $assessment): array
    {
        try {
            // TODO: Implement actual CHRIS sync
            // For now, this is a stub

            Log::info('CHRIS sync initiated', [
                'assessment_id' => $assessment->id,
            ]);

            // Simulate CHRIS sync
            $assessment->markChrisSynced();

            Log::info('CHRIS sync successful', [
                'assessment_id' => $assessment->id,
            ]);

            return [
                'success' => true,
                'synced_at' => $assessment->chris_sync_timestamp->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error('CHRIS sync failed', [
                'assessment_id' => $assessment->id,
                'error' => $e->getMessage(),
            ]);

            $assessment->markChrisFailed();

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get assessments pending IAR upload.
     */
    public function getPendingIarUploads(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return InterraiAssessment::pendingIarUpload()
            ->with('patient.user')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get assessments pending CHRIS sync.
     */
    public function getPendingChrisSyncs(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return InterraiAssessment::pendingChrisSync()
            ->with('patient.user')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Process all pending IAR uploads.
     */
    public function processIarUploadQueue(): array
    {
        $pending = $this->getPendingIarUploads();
        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
        ];

        foreach ($pending as $assessment) {
            $results['processed']++;
            $result = $this->uploadToIar($assessment);

            if ($result['success']) {
                $results['succeeded']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | IR-003: External Assessment & Document Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Create an assessment marked as completed externally (e.g., by OHaH).
     *
     * IR-003-01: Allows coordinators to record assessments completed
     * outside the system and manually enter key scores.
     */
    public function createExternalAssessment(
        Patient $patient,
        array $data,
        ?User $enteredBy = null
    ): InterraiAssessment {
        return DB::transaction(function () use ($patient, $data, $enteredBy) {
            $assessment = InterraiAssessment::create([
                'patient_id' => $patient->id,
                'assessment_type' => $data['assessment_type'] ?? InterraiAssessment::TYPE_HC,
                'assessment_date' => $this->parseDate($data['assessment_date'] ?? now()),
                'assessor_id' => $enteredBy?->id,
                'assessor_role' => $data['assessor_role'] ?? 'External Assessor',
                'source' => InterraiAssessment::SOURCE_OHAH,

                // Clinical scores (manually entered)
                'maple_score' => $data['maple_score'] ?? null,
                'rai_cha_score' => $data['rai_cha_score'] ?? null,
                'adl_hierarchy' => $this->parseInteger($data['adl_hierarchy'] ?? null),
                'iadl_difficulty' => $this->parseInteger($data['iadl_difficulty'] ?? null),
                'cognitive_performance_scale' => $this->parseInteger($data['cognitive_performance_scale'] ?? null),
                'depression_rating_scale' => $this->parseInteger($data['depression_rating_scale'] ?? null),
                'pain_scale' => $this->parseInteger($data['pain_scale'] ?? null),
                'chess_score' => $this->parseInteger($data['chess_score'] ?? null),
                'method_for_locomotion' => $data['method_for_locomotion'] ?? null,
                'falls_in_last_90_days' => $this->parseBoolean($data['falls_in_last_90_days'] ?? false),
                'wandering_flag' => $this->parseBoolean($data['wandering_flag'] ?? false),

                'caps_triggered' => $data['caps_triggered'] ?? null,
                'primary_diagnosis_icd10' => $data['primary_diagnosis_icd10'] ?? null,
                'secondary_diagnoses' => $data['secondary_diagnoses'] ?? null,

                // External assessments may already be in IAR
                'iar_upload_status' => $data['already_in_iar'] ?? false
                    ? InterraiAssessment::IAR_NOT_REQUIRED
                    : InterraiAssessment::IAR_PENDING,
                'chris_sync_status' => InterraiAssessment::CHRIS_PENDING,

                'raw_assessment_data' => $data,
            ]);

            $this->updatePatientScores($patient, $assessment);
            $this->syncPatientInterraiStatus($patient);

            // Resolve any pending reassessment triggers
            $this->resolvePatientTriggers($patient, $assessment, $enteredBy);

            Log::info('External InterRAI assessment created', [
                'patient_id' => $patient->id,
                'assessment_id' => $assessment->id,
                'entered_by' => $enteredBy?->id,
            ]);

            return $assessment;
        });
    }

    /**
     * Attach a document to an assessment.
     *
     * IR-004-02: Handles file uploads and external IAR ID linking.
     */
    public function attachDocument(
        InterraiAssessment $assessment,
        array $data,
        ?UploadedFile $file = null,
        ?User $uploadedBy = null
    ): InterraiDocument {
        return DB::transaction(function () use ($assessment, $data, $file, $uploadedBy) {
            $documentData = [
                'interrai_assessment_id' => $assessment->id,
                'document_type' => $data['document_type'] ?? InterraiDocument::TYPE_ATTACHMENT,
                'uploaded_by' => $uploadedBy?->id,
                'uploaded_at' => now(),
                'metadata' => $data['metadata'] ?? null,
            ];

            // Handle external IAR ID linking
            if (($data['document_type'] ?? null) === InterraiDocument::TYPE_EXTERNAL_IAR) {
                $documentData['external_iar_id'] = $data['external_iar_id'];

                // Mark assessment as not requiring upload if linked to existing IAR
                $assessment->update([
                    'iar_upload_status' => InterraiAssessment::IAR_NOT_REQUIRED,
                    'iar_confirmation_id' => $data['external_iar_id'],
                ]);
            }

            // Handle file upload
            if ($file) {
                $storagePath = InterraiDocument::getStoragePath($assessment->patient_id);
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs($storagePath, $filename, 'local');

                $documentData['file_path'] = $path;
                $documentData['original_filename'] = $file->getClientOriginalName();
                $documentData['mime_type'] = $file->getMimeType();
                $documentData['file_size'] = $file->getSize();
                $documentData['document_type'] = InterraiDocument::TYPE_PDF;
            }

            $document = InterraiDocument::create($documentData);

            Log::info('Document attached to InterRAI assessment', [
                'assessment_id' => $assessment->id,
                'document_id' => $document->id,
                'type' => $document->document_type,
            ]);

            return $document;
        });
    }

    /**
     * Link an external IAR document ID to an assessment.
     *
     * IR-003-02: Quick action to link an already-uploaded IAR record.
     */
    public function linkExternalIar(
        InterraiAssessment $assessment,
        string $iarDocumentId,
        ?User $linkedBy = null
    ): InterraiDocument {
        return $this->attachDocument($assessment, [
            'document_type' => InterraiDocument::TYPE_EXTERNAL_IAR,
            'external_iar_id' => $iarDocumentId,
            'metadata' => [
                'linked_at' => now()->toIso8601String(),
                'linked_by' => $linkedBy?->name,
            ],
        ], null, $linkedBy);
    }

    /*
    |--------------------------------------------------------------------------
    | IR-005: Reassessment Trigger Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Request a reassessment for a patient.
     *
     * IR-005-02: Creates a trigger record for tracking.
     */
    public function requestReassessment(
        Patient $patient,
        string $reason,
        ?string $notes = null,
        string $priority = ReassessmentTrigger::PRIORITY_MEDIUM,
        ?User $triggeredBy = null
    ): ReassessmentTrigger {
        $trigger = ReassessmentTrigger::create([
            'patient_id' => $patient->id,
            'triggered_by' => $triggeredBy?->id,
            'trigger_reason' => $reason,
            'reason_notes' => $notes,
            'priority' => $priority,
        ]);

        Log::info('Reassessment requested', [
            'patient_id' => $patient->id,
            'trigger_id' => $trigger->id,
            'reason' => $reason,
            'priority' => $priority,
        ]);

        return $trigger;
    }

    /**
     * Resolve a reassessment trigger with a new assessment.
     *
     * IR-005-04: Mark trigger as resolved when new assessment is created.
     */
    public function resolveReassessmentTrigger(
        ReassessmentTrigger $trigger,
        InterraiAssessment $assessment,
        ?User $resolvedBy = null,
        ?string $notes = null
    ): ReassessmentTrigger {
        $trigger->resolve($assessment, $resolvedBy, $notes);

        Log::info('Reassessment trigger resolved', [
            'trigger_id' => $trigger->id,
            'assessment_id' => $assessment->id,
            'resolved_by' => $resolvedBy?->id,
        ]);

        return $trigger;
    }

    /**
     * Resolve all pending triggers for a patient.
     */
    public function resolvePatientTriggers(
        Patient $patient,
        InterraiAssessment $assessment,
        ?User $resolvedBy = null
    ): int {
        $triggers = $patient->reassessmentTriggers()->pending()->get();
        $count = 0;

        foreach ($triggers as $trigger) {
            $this->resolveReassessmentTrigger(
                $trigger,
                $assessment,
                $resolvedBy,
                'Resolved by new assessment'
            );
            $count++;
        }

        return $count;
    }

    /**
     * Get pending reassessment triggers.
     */
    public function getReassessmentTriggers(
        int $limit = 100,
        ?string $priority = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = ReassessmentTrigger::pending()
            ->with(['patient', 'triggeredByUser'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('created_at', 'asc');

        if ($priority) {
            $query->where('priority', $priority);
        }

        return $query->limit($limit)->get();
    }

    /*
    |--------------------------------------------------------------------------
    | IR-006: Dashboard & Status Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Sync patient's cached InterRAI status.
     */
    public function syncPatientInterraiStatus(Patient $patient): string
    {
        $status = $patient->computeInterraiStatus();

        $patient->update([
            'interrai_status' => $status,
            'interrai_status_updated_at' => now(),
        ]);

        return $status;
    }

    /**
     * Sync InterRAI status for all patients in queue.
     */
    public function syncAllPatientStatuses(): array
    {
        $patients = Patient::where('is_in_queue', true)->get();
        $results = ['updated' => 0, 'current' => 0, 'stale' => 0, 'missing' => 0];

        foreach ($patients as $patient) {
            $status = $this->syncPatientInterraiStatus($patient);
            $results['updated']++;
            $results[$status] = ($results[$status] ?? 0) + 1;
        }

        return $results;
    }

    /**
     * Get dashboard statistics for admin view.
     *
     * IR-006-02: KPI cards data.
     */
    public function getDashboardStats(): array
    {
        $pendingIar = InterraiAssessment::pendingIarUpload()->count();
        $failedUploads = InterraiAssessment::where('iar_upload_status', InterraiAssessment::IAR_FAILED)->count();

        $staleAssessments = Patient::where('is_in_queue', true)
            ->where('interrai_status', Patient::INTERRAI_STATUS_STALE)
            ->count();

        $missingAssessments = Patient::where('is_in_queue', true)
            ->where('interrai_status', Patient::INTERRAI_STATUS_MISSING)
            ->count();

        $pendingTriggers = ReassessmentTrigger::pending()->count();
        $urgentTriggers = ReassessmentTrigger::pending()
            ->whereIn('priority', [ReassessmentTrigger::PRIORITY_HIGH, ReassessmentTrigger::PRIORITY_URGENT])
            ->count();

        return [
            'pending_iar' => $pendingIar,
            'failed_uploads' => $failedUploads,
            'stale_assessments' => $staleAssessments,
            'missing_assessments' => $missingAssessments,
            'pending_triggers' => $pendingTriggers,
            'urgent_triggers' => $urgentTriggers,
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Get failed IAR uploads with patient details.
     */
    public function getFailedIarUploads(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return InterraiAssessment::where('iar_upload_status', InterraiAssessment::IAR_FAILED)
            ->with('patient')
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Bulk retry failed IAR uploads.
     */
    public function bulkRetryFailedUploads(): array
    {
        $failed = $this->getFailedIarUploads();
        $queued = 0;

        foreach ($failed as $assessment) {
            $assessment->update(['iar_upload_status' => InterraiAssessment::IAR_PENDING]);
            \App\Jobs\UploadInterraiToIarJob::dispatch($assessment);
            $queued++;
        }

        Log::info('Bulk IAR retry initiated', ['count' => $queued]);

        return [
            'queued' => $queued,
            'message' => "{$queued} assessments queued for IAR upload retry",
        ];
    }

    /**
     * Update patient's cached InterRAI scores.
     */
    protected function updatePatientScores(Patient $patient, InterraiAssessment $assessment): void
    {
        $patient->update([
            'maple_score' => $assessment->maple_score,
            'rai_cha_score' => $assessment->rai_cha_score,
        ]);
    }

    /**
     * Parse a date value from various formats.
     */
    protected function parseDate($value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value)) {
            return Carbon::parse($value);
        }

        return now();
    }

    /**
     * Parse an integer value, returning null if invalid.
     */
    protected function parseInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Parse a boolean value.
     */
    protected function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'y'], true);
        }

        return (bool) $value;
    }
}
