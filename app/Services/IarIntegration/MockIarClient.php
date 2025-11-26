<?php

namespace App\Services\IarIntegration;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * IR-008: Mock IAR Client for development and testing
 *
 * This mock implementation simulates IAR API behavior
 * for use during development before real API integration.
 *
 * Configuration (config/services.php):
 * - iar.mock_mode: 'success' | 'delayed' | 'random_failure' | 'always_fail'
 * - iar.mock_delay_ms: Simulated processing delay in milliseconds
 */
class MockIarClient implements IarClientInterface
{
    private string $mockMode;
    private int $mockDelayMs;

    public function __construct()
    {
        $this->mockMode = config('services.iar.mock_mode', 'success');
        $this->mockDelayMs = config('services.iar.mock_delay_ms', 100);
    }

    public function submitAssessment(array $assessmentData): IarResponse
    {
        $this->simulateDelay();

        Log::channel('iar_integration')->info('Mock IAR: Submitting assessment', [
            'patient_id' => $assessmentData['patient_id'] ?? null,
            'assessment_date' => $assessmentData['assessment_date'] ?? null,
            'mock_mode' => $this->mockMode,
        ]);

        if ($this->shouldFail()) {
            return IarResponse::failure(
                message: 'Mock IAR: Simulated submission failure',
                errors: ['mock_error' => 'This is a simulated error for testing'],
                httpStatus: 500,
            );
        }

        $submissionId = 'MOCK-IAR-' . Str::upper(Str::random(12));

        return IarResponse::success(
            submissionId: $submissionId,
            message: 'Mock IAR: Assessment submitted successfully',
            data: [
                'submission_id' => $submissionId,
                'estimated_processing_time' => '2-5 business days',
                'mock' => true,
            ],
        );
    }

    public function queryAssessments(string $healthCardNumber, ?\DateTimeInterface $since = null): array
    {
        $this->simulateDelay();

        Log::channel('iar_integration')->info('Mock IAR: Querying assessments', [
            'health_card' => substr($healthCardNumber, 0, 4) . '****',
            'since' => $since?->format('Y-m-d'),
        ]);

        // Return mock assessment data
        return [
            [
                'iar_assessment_id' => 'IAR-MOCK-001',
                'assessment_date' => now()->subDays(30)->format('Y-m-d'),
                'maple_score' => 3,
                'cps' => 2,
                'adl_hierarchy' => 3,
                'chess_score' => 2,
                'source' => 'iar_imported',
                'mock' => true,
            ],
            [
                'iar_assessment_id' => 'IAR-MOCK-002',
                'assessment_date' => now()->subDays(120)->format('Y-m-d'),
                'maple_score' => 2,
                'cps' => 1,
                'adl_hierarchy' => 2,
                'chess_score' => 1,
                'source' => 'iar_imported',
                'mock' => true,
            ],
        ];
    }

    public function getSubmissionStatus(string $submissionId): IarSubmissionStatus
    {
        $this->simulateDelay();

        Log::channel('iar_integration')->info('Mock IAR: Getting submission status', [
            'submission_id' => $submissionId,
        ]);

        // For mock, always return accepted after a short delay
        if ($this->mockMode === 'delayed') {
            return new IarSubmissionStatus(
                submissionId: $submissionId,
                status: IarSubmissionStatus::STATUS_PROCESSING,
                message: 'Assessment is being processed',
                submittedAt: now()->subMinutes(5),
            );
        }

        if ($this->shouldFail()) {
            return new IarSubmissionStatus(
                submissionId: $submissionId,
                status: IarSubmissionStatus::STATUS_REJECTED,
                message: 'Mock: Assessment rejected due to validation errors',
                submittedAt: now()->subMinutes(10),
                processedAt: now(),
                validationErrors: ['mock_validation' => 'Simulated validation error'],
            );
        }

        return new IarSubmissionStatus(
            submissionId: $submissionId,
            status: IarSubmissionStatus::STATUS_ACCEPTED,
            message: 'Assessment accepted and recorded',
            submittedAt: now()->subMinutes(10),
            processedAt: now(),
        );
    }

    public function validateAssessment(array $assessmentData): IarValidationResult
    {
        $errors = [];
        $warnings = [];

        // Basic validation rules
        if (empty($assessmentData['patient_id'])) {
            $errors['patient_id'] = 'Patient ID is required';
        }

        if (empty($assessmentData['assessment_date'])) {
            $errors['assessment_date'] = 'Assessment date is required';
        }

        if (empty($assessmentData['maple_score'])) {
            $errors['maple_score'] = 'MAPLe score is required';
        }

        if (empty($assessmentData['cps'])) {
            $errors['cps'] = 'CPS score is required';
        }

        if (empty($assessmentData['adl_hierarchy'])) {
            $errors['adl_hierarchy'] = 'ADL Hierarchy score is required';
        }

        // Warnings
        if (!empty($assessmentData['assessment_date'])) {
            $assessmentDate = new \DateTime($assessmentData['assessment_date']);
            $daysSince = $assessmentDate->diff(new \DateTime())->days;

            if ($daysSince > 90) {
                $warnings['assessment_date'] = 'Assessment is more than 90 days old';
            }
        }

        if (count($errors) > 0) {
            return IarValidationResult::invalid($errors, $warnings);
        }

        return IarValidationResult::valid($warnings);
    }

    public function healthCheck(): bool
    {
        $this->simulateDelay();

        // Mock always returns true unless in always_fail mode
        return $this->mockMode !== 'always_fail';
    }

    private function simulateDelay(): void
    {
        if ($this->mockDelayMs > 0) {
            usleep($this->mockDelayMs * 1000);
        }
    }

    private function shouldFail(): bool
    {
        return match ($this->mockMode) {
            'always_fail' => true,
            'random_failure' => rand(1, 10) <= 2, // 20% failure rate
            default => false,
        };
    }
}
