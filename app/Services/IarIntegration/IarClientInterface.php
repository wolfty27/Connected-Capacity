<?php

namespace App\Services\IarIntegration;

/**
 * IR-008: Interface for IAR (Integrated Assessment Record) API client
 *
 * This interface defines the contract for IAR integration,
 * allowing for mock implementations during development and
 * real implementations when OH API specs are available.
 */
interface IarClientInterface
{
    /**
     * Submit an InterRAI assessment to IAR.
     *
     * @param array $assessmentData The assessment data in IAR format
     * @return IarResponse The response from IAR
     */
    public function submitAssessment(array $assessmentData): IarResponse;

    /**
     * Query IAR for existing assessments for a patient.
     *
     * @param string $healthCardNumber The patient's health card number
     * @param \DateTimeInterface|null $since Only return assessments after this date
     * @return array Array of IarAssessment objects
     */
    public function queryAssessments(string $healthCardNumber, ?\DateTimeInterface $since = null): array;

    /**
     * Get the status of a previously submitted assessment.
     *
     * @param string $submissionId The IAR submission ID
     * @return IarSubmissionStatus The submission status
     */
    public function getSubmissionStatus(string $submissionId): IarSubmissionStatus;

    /**
     * Validate assessment data before submission.
     *
     * @param array $assessmentData The assessment data to validate
     * @return IarValidationResult Validation result with any errors
     */
    public function validateAssessment(array $assessmentData): IarValidationResult;

    /**
     * Check if the IAR service is available.
     *
     * @return bool True if service is reachable
     */
    public function healthCheck(): bool;
}
