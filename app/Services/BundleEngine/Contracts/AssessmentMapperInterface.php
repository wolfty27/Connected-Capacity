<?php

namespace App\Services\BundleEngine\Contracts;

use App\Models\InterraiAssessment;

/**
 * AssessmentMapperInterface
 *
 * Contract for mapping assessment data to PatientNeedsProfile fields.
 * Each assessment type (HC, CA, BMHS) has its own mapper implementation.
 *
 * The mapper extracts normalized values from raw assessment data,
 * handling the specifics of each assessment instrument.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 2.2
 */
interface AssessmentMapperInterface
{
    /**
     * Get the assessment type this mapper handles.
     *
     * @return string 'hc', 'ca', or 'bmhs'
     */
    public function getAssessmentType(): string;

    /**
     * Map an assessment to profile field values.
     *
     * @param InterraiAssessment $assessment The assessment to map
     * @return array<string, mixed> Mapped field values for PatientNeedsProfile
     */
    public function mapToProfileFields(InterraiAssessment $assessment): array;

    /**
     * Extract ADL support level (0-6 scale).
     *
     * @param InterraiAssessment $assessment
     * @return int ADL support level
     */
    public function extractAdlSupportLevel(InterraiAssessment $assessment): int;

    /**
     * Extract IADL support level (0-6 scale).
     *
     * @param InterraiAssessment $assessment
     * @return int IADL support level
     */
    public function extractIadlSupportLevel(InterraiAssessment $assessment): int;

    /**
     * Extract mobility complexity (0-6 scale).
     *
     * @param InterraiAssessment $assessment
     * @return int Mobility complexity
     */
    public function extractMobilityComplexity(InterraiAssessment $assessment): int;

    /**
     * Extract cognitive complexity (CPS-based, 0-6 scale).
     *
     * @param InterraiAssessment $assessment
     * @return int Cognitive complexity
     */
    public function extractCognitiveComplexity(InterraiAssessment $assessment): int;

    /**
     * Extract behavioural complexity (0-4 scale).
     *
     * @param InterraiAssessment $assessment
     * @return int Behavioural complexity
     */
    public function extractBehaviouralComplexity(InterraiAssessment $assessment): int;

    /**
     * Extract health instability (CHESS-based, 0-5 scale).
     *
     * @param InterraiAssessment $assessment
     * @return int Health instability score
     */
    public function extractHealthInstability(InterraiAssessment $assessment): int;

    /**
     * Extract falls risk level (0-2 scale).
     *
     * @param InterraiAssessment $assessment
     * @return int Falls risk level
     */
    public function extractFallsRiskLevel(InterraiAssessment $assessment): int;

    /**
     * Get confidence weight for this assessment type.
     *
     * HC has highest confidence (1.0), CA medium (0.7), BMHS lower (0.5).
     *
     * @return float Confidence weight (0.0 - 1.0)
     */
    public function getConfidenceWeight(): float;

    /**
     * Check if this assessment type can provide RUG classification.
     *
     * @return bool True if this assessment supports RUG
     */
    public function supportsRugClassification(): bool;

    /**
     * Get fields that this mapper can populate.
     *
     * @return array<string> List of PatientNeedsProfile field names
     */
    public function getPopulatableFields(): array;
}

