<?php

namespace App\Jobs;

use App\Events\IarUploadFailed;
use App\Models\InterraiAssessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * UploadInterraiToIarJob - Upload InterRAI assessment to IAR system
 *
 * Per IR-005: Handles uploading InterRAI HC assessments to the Ontario
 * Integrated Assessment Record (IAR) system with:
 * - Retry logic with exponential backoff
 * - Confirmation ID capture
 * - Failure alerting after exhausting retries
 *
 * IAR Requirements:
 * - All InterRAI HC assessments must be uploaded within 72 hours
 * - Failed uploads trigger SPO escalation
 * - Confirmation IDs required for OHaH compliance reporting
 */
class UploadInterraiToIarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of retry attempts.
     */
    public int $tries = 5;

    /**
     * Retry backoff intervals in seconds (exponential).
     */
    public array $backoff = [60, 300, 900, 3600, 7200];

    /**
     * Timeout for the job in seconds.
     */
    public int $timeout = 120;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    protected InterraiAssessment $assessment;

    /**
     * Create a new job instance.
     */
    public function __construct(InterraiAssessment $assessment)
    {
        $this->assessment = $assessment;
        $this->onQueue('iar-uploads');
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'iar-upload-' . $this->assessment->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('IAR Upload: Starting upload', [
            'assessment_id' => $this->assessment->id,
            'patient_id' => $this->assessment->patient_id,
            'attempt' => $this->attempts(),
        ]);

        // Check if already uploaded (idempotency)
        if ($this->assessment->iar_upload_status === InterraiAssessment::IAR_UPLOADED) {
            Log::info('IAR Upload: Already uploaded, skipping', [
                'assessment_id' => $this->assessment->id,
                'confirmation_id' => $this->assessment->iar_confirmation_id,
            ]);
            return;
        }

        try {
            // Transform assessment to IAR format
            $payload = $this->transformToIarFormat();

            // Upload to IAR (stub - actual API integration TBD)
            $response = $this->uploadToIar($payload);

            // Capture confirmation ID and mark as uploaded
            $this->assessment->markIarUploaded($response['confirmation_id']);

            Log::info('IAR Upload: Success', [
                'assessment_id' => $this->assessment->id,
                'confirmation_id' => $response['confirmation_id'],
            ]);

        } catch (\Exception $e) {
            Log::error('IAR Upload: Failed', [
                'assessment_id' => $this->assessment->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Transform InterRAI assessment to IAR submission format.
     *
     * IAR expects specific field names and formats per Ontario Health specs.
     */
    protected function transformToIarFormat(): array
    {
        $patient = $this->assessment->patient;

        return [
            'submission' => [
                'organization_id' => config('services.iar.organization_id'),
                'submission_timestamp' => now()->toIso8601String(),
                'submitter_id' => config('services.iar.submitter_id'),
            ],
            'client' => [
                'health_card_number' => $patient->health_card_number ?? null,
                'date_of_birth' => $patient->date_of_birth instanceof \DateTimeInterface
                    ? $patient->date_of_birth->format('Y-m-d')
                    : $patient->date_of_birth,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'gender' => $patient->gender,
            ],
            'assessment' => [
                'type' => $this->mapAssessmentType($this->assessment->assessment_type),
                'assessment_date' => $this->assessment->assessment_date instanceof \DateTimeInterface
                    ? $this->assessment->assessment_date->format('Y-m-d')
                    : $this->assessment->assessment_date,
                'assessor_id' => $this->assessment->assessor_id,
                'assessor_role' => $this->assessment->assessor_role,
                'source_system' => 'connected_capacity',
            ],
            'clinical_data' => [
                'maple_score' => $this->assessment->maple_score,
                'rai_cha_score' => $this->assessment->rai_cha_score,
                'adl_hierarchy' => $this->assessment->adl_hierarchy,
                'iadl_difficulty' => $this->assessment->iadl_difficulty,
                'cognitive_performance_scale' => $this->assessment->cognitive_performance_scale,
                'depression_rating_scale' => $this->assessment->depression_rating_scale,
                'pain_scale' => $this->assessment->pain_scale,
                'chess_score' => $this->assessment->chess_score,
                'method_for_locomotion' => $this->assessment->method_for_locomotion,
                'falls_in_last_90_days' => $this->assessment->falls_in_last_90_days,
                'wandering_flag' => $this->assessment->wandering_flag,
                'caps_triggered' => $this->assessment->caps_triggered,
            ],
            'diagnoses' => [
                'primary_icd10' => $this->assessment->primary_diagnosis_icd10,
                'secondary' => $this->assessment->secondary_diagnoses,
            ],
            'metadata' => [
                'internal_assessment_id' => $this->assessment->id,
                'internal_patient_id' => $this->assessment->patient_id,
            ],
        ];
    }

    /**
     * Map internal assessment type to IAR type code.
     */
    protected function mapAssessmentType(string $type): string
    {
        return match ($type) {
            InterraiAssessment::TYPE_HC => 'RAI-HC',
            InterraiAssessment::TYPE_CHA => 'RAI-CHA',
            InterraiAssessment::TYPE_CONTACT => 'RAI-CONTACT',
            default => 'RAI-HC',
        };
    }

    /**
     * Upload to IAR system.
     *
     * Stub implementation - actual API integration will use Ontario Health IAR API.
     * Returns mock response in non-production environments.
     */
    protected function uploadToIar(array $payload): array
    {
        $iarEndpoint = config('services.iar.endpoint');
        $iarApiKey = config('services.iar.api_key');

        // In production, make actual API call
        if (app()->environment('production') && $iarEndpoint && $iarApiKey) {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$iarApiKey}",
                    'Content-Type' => 'application/json',
                    'X-Request-ID' => Str::uuid()->toString(),
                ])
                ->post($iarEndpoint . '/assessments', $payload);

            if ($response->failed()) {
                throw new \Exception(
                    'IAR API Error: ' . ($response->json('error.message') ?? $response->status())
                );
            }

            return [
                'confirmation_id' => $response->json('data.confirmation_id'),
                'iar_reference' => $response->json('data.reference_number'),
                'submitted_at' => $response->json('data.submitted_at'),
            ];
        }

        // Stub response for development/staging
        Log::info('IAR Upload: Using stub response (non-production)', [
            'assessment_id' => $this->assessment->id,
        ]);

        return [
            'confirmation_id' => 'IAR-' . strtoupper(Str::random(8)) . '-' . now()->format('Ymd'),
            'iar_reference' => 'REF-' . $this->assessment->id,
            'submitted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Handle a job failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('IAR Upload: All retries exhausted', [
            'assessment_id' => $this->assessment->id,
            'patient_id' => $this->assessment->patient_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mark assessment as failed
        $this->assessment->markIarFailed();

        // Dispatch failure event for alerting
        IarUploadFailed::dispatch($this->assessment, $exception->getMessage());
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'iar-upload',
            'assessment:' . $this->assessment->id,
            'patient:' . $this->assessment->patient_id,
        ];
    }
}
