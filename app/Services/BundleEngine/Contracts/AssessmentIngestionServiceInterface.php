<?php

namespace App\Services\BundleEngine\Contracts;

use App\Models\Patient;
use App\Services\BundleEngine\DTOs\PatientNeedsProfile;

/**
 * AssessmentIngestionServiceInterface
 *
 * Contract for building PatientNeedsProfile from various assessment sources.
 * This is the single entry point for assessment data ingestion.
 *
 * The service aggregates data from:
 * - InterRAI HC assessments (full assessment, highest priority)
 * - InterRAI CA assessments (Contact Assessment, medium priority)
 * - InterRAI BMHS (Behavioural/Mental Health Screener)
 * - Referral data (hospital discharge, OHAH, etc.)
 * - Family/SPO input
 *
 * Business Rules:
 * - At minimum ONE of (HC, CA, referral) must be available
 * - We NEVER block bundling due to missing data
 * - Confidence level is calculated based on data completeness
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 2.1
 */
interface AssessmentIngestionServiceInterface
{
    /**
     * Build a PatientNeedsProfile for a given patient.
     *
     * This is the main entry point. It:
     * 1. Fetches all available assessment data for the patient
     * 2. Normalizes and merges the data
     * 3. Calculates confidence and completeness scores
     * 4. Returns a ready-to-use PatientNeedsProfile
     *
     * @param Patient $patient The patient to build profile for
     * @param array<string, mixed> $options Optional configuration:
     *   - 'force_refresh': bool - Skip cache and rebuild (default: false)
     *   - 'assessment_cutoff_days': int - Max age of assessments to consider (default: 365)
     *   - 'include_referral': bool - Include referral data (default: true)
     *   - 'include_family_input': bool - Include family/SPO input (default: true)
     *
     * @return PatientNeedsProfile The built profile (never null, but may be minimal)
     */
    public function buildPatientNeedsProfile(Patient $patient, array $options = []): PatientNeedsProfile;

    /**
     * Check if a patient has sufficient data for profile building.
     *
     * This is a quick check that doesn't build the full profile.
     *
     * @param Patient $patient The patient to check
     * @return bool True if at least one data source is available
     */
    public function hasSufficientData(Patient $patient): bool;

    /**
     * Get available data sources for a patient.
     *
     * Returns an array indicating which data sources are available:
     *
     * @param Patient $patient The patient to check
     * @return array{
     *   has_hc: bool,
     *   hc_date: ?string,
     *   has_ca: bool,
     *   ca_date: ?string,
     *   has_bmhs: bool,
     *   bmhs_date: ?string,
     *   has_referral: bool,
     *   referral_source: ?string,
     *   has_family_input: bool
     * }
     */
    public function getAvailableDataSources(Patient $patient): array;

    /**
     * Invalidate cached profile for a patient.
     *
     * Call this when new assessment data is available.
     *
     * @param Patient $patient The patient whose cache to invalidate
     * @return void
     */
    public function invalidateCache(Patient $patient): void;
}

