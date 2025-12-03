<?php

namespace App\Services\BundleEngine\DTOs;

use Carbon\Carbon;

/**
 * PatientNeedsProfile
 *
 * Normalized, assessment-agnostic representation of patient care needs for bundle generation.
 * This DTO can be built from:
 * - Full InterRAI HC assessment (preferred, high confidence)
 * - InterRAI Contact Assessment (CA) alone (medium confidence)
 * - CA + BMHS combination
 * - Referral/discharge data from hospitals (low confidence)
 *
 * IMPORTANT: This DTO is de-identified for AI processing.
 * No names, addresses, OHIP numbers, or other PHI/PII.
 *
 * DESIGN NOTE: This is a PURE DATA CONTAINER with no business logic.
 * Policy decisions (like axis selection) belong in dedicated services.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 1.2
 */
class PatientNeedsProfile
{
    public function __construct(
        // === Profile Metadata ===
        /** @var int Patient ID (for internal reference only, not sent to LLM) */
        public readonly int $patientId,

        /** @var Carbon When this profile was generated */
        public readonly Carbon $profileGeneratedAt,

        /** @var string Version of the profile schema */
        public readonly string $profileVersion = '1.0',

        // === Data Source Tracking ===
        /** @var string|null Primary assessment type: 'hc', 'ca', 'referral_only' */
        public readonly ?string $primaryAssessmentType = null,

        /** @var Carbon|null Date of the primary assessment */
        public readonly ?Carbon $primaryAssessmentDate = null,

        /** @var bool Has a full InterRAI HC assessment */
        public readonly bool $hasFullHcAssessment = false,

        /** @var bool Has an InterRAI Contact Assessment */
        public readonly bool $hasCaAssessment = false,

        /** @var bool Has BMHS (Behavioural/Mental Health Screener) data */
        public readonly bool $hasBmhsAssessment = false,

        /** @var bool Has hospital/OHAH referral data */
        public readonly bool $hasReferralData = false,

        /** @var float Data completeness score (0.0 - 1.0) */
        public readonly float $dataCompletenessScore = 0.0,

        // === Case Classification ===
        /** @var string|null RUG-III/HC group code (e.g., 'CB0', 'IB0') - only if HC available */
        public readonly ?string $rugGroup = null,

        /** @var string|null RUG category (e.g., 'Clinically Complex', 'Impaired Cognition') */
        public readonly ?string $rugCategory = null,

        /** @var string|null Derived needs cluster if no HC (e.g., 'HIGH_ADL', 'COGNITIVE_COMPLEX') */
        public readonly ?string $needsCluster = null,

        /** @var string|null Episode type: 'post_acute', 'chronic', 'complex_continuing', 'acute_exacerbation', 'palliative' */
        public readonly ?string $episodeType = null,

        /** @var int|null Numeric rank for RUG ordering/comparison */
        public readonly ?int $rugNumericRank = null,

        // === Functional Needs (Normalized 0-6 scale) ===
        /** @var int ADL support level: 0=independent, 6=total dependence */
        public readonly int $adlSupportLevel = 0,

        /** @var int IADL support level: 0=independent, 6=total dependence */
        public readonly int $iadlSupportLevel = 0,

        /** @var int Mobility/transfer complexity: 0=independent, 6=total dependence */
        public readonly int $mobilityComplexity = 0,

        /** @var array|null Specific ADL needs identified (e.g., ['bathing', 'dressing', 'transfers']) */
        public readonly ?array $specificAdlNeeds = null,

        // === Cognitive & Behavioural ===
        /** @var int Cognitive complexity derived from CPS: 0=intact, 6=very severe impairment */
        public readonly int $cognitiveComplexity = 0,

        /** @var int Behavioural complexity from behavioural items: 0=none, 4=severe */
        public readonly int $behaviouralComplexity = 0,

        /** @var int Mental health complexity from BMHS if available: 0=none, 3=severe */
        public readonly int $mentalHealthComplexity = 0,

        /** @var bool Has wandering/elopement risk */
        public readonly bool $hasWanderingRisk = false,

        /** @var bool Has aggression risk */
        public readonly bool $hasAggressionRisk = false,

        /** @var array|null Specific behavioural flags (e.g., ['verbal_aggression', 'resists_care']) */
        public readonly ?array $behaviouralFlags = null,

        // === Clinical Risk Profile ===
        /** @var int Falls risk level: 0=low, 1=moderate, 2=high */
        public readonly int $fallsRiskLevel = 0,

        /** @var int Skin integrity risk: 0=low, 1=moderate, 2=high */
        public readonly int $skinIntegrityRisk = 0,

        /** @var int Pain management need: 0=none, 3=severe */
        public readonly int $painManagementNeed = 0,

        /** @var int Continence support level: 0=continent, 5=incontinent */
        public readonly int $continenceSupport = 0,

        /** @var int Health instability (CHESS-derived): 0=stable, 5=highly unstable */
        public readonly int $healthInstability = 0,

        /** @var array|null Clinical risk flags (e.g., ['pressure_ulcer', 'recent_fall']) */
        public readonly ?array $clinicalRiskFlags = null,

        /** @var array|null Active medical conditions (e.g., ['diabetes', 'chf', 'copd']) */
        public readonly ?array $activeConditions = null,

        // === Treatment/Therapy Context ===
        /** @var bool Has rehabilitation potential based on assessment */
        public readonly bool $hasRehabPotential = false,

        /** @var int Rehab potential score: 0-100 (hasRehabPotential = score >= 40) */
        public readonly int $rehabPotentialScore = 0,

        /** @var bool Requires extensive services (IV, ventilator, trach, etc.) */
        public readonly bool $requiresExtensiveServices = false,

        /** @var array|null Specific extensive services needed (e.g., ['iv_therapy', 'wound_care']) */
        public readonly ?array $extensiveServices = null,

        /** @var int Weekly therapy minutes (PT/OT/SLP combined) */
        public readonly int $weeklyTherapyMinutes = 0,

        // === Support Context ===
        /** @var int Caregiver availability: 0=none, 5=24/7 available */
        public readonly int $caregiverAvailabilityScore = 0,

        /** @var int Caregiver stress level: 0=low, 4=very high/burnout */
        public readonly int $caregiverStressLevel = 0,

        /** @var bool Patient lives alone */
        public readonly bool $livesAlone = false,

        /** @var bool Caregiver requires relief/respite */
        public readonly bool $caregiverRequiresRelief = false,

        /** @var int Social support network: 0=isolated, 5=strong network */
        public readonly int $socialSupportScore = 0,

        // === Technology Readiness ===
        /** @var int Technology readiness: 0=none, 3=tech-savvy */
        public readonly int $technologyReadiness = 0,

        /** @var bool Has reliable internet access */
        public readonly bool $hasInternet = false,

        /** @var bool Has Personal Emergency Response System (PERS) */
        public readonly bool $hasPers = false,

        /** @var bool Suitable for Remote Patient Monitoring (RPM) */
        public readonly bool $suitableForRpm = false,

        // === Environmental Context ===
        /** @var string|null Geographic region code */
        public readonly ?string $regionCode = null,

        /** @var string|null Geographic region name */
        public readonly ?string $regionName = null,

        /** @var int Travel complexity: 0=easy access, 3=very difficult */
        public readonly int $travelComplexityScore = 0,

        /** @var bool Is in rural area */
        public readonly bool $isRural = false,

        /** @var array|null Service availability flags for this region */
        public readonly ?array $serviceAvailabilityFlags = null,

        // === Confidence & Completeness ===
        /** @var string Confidence level: 'low', 'medium', 'high' */
        public readonly string $confidenceLevel = 'low',

        /** @var array|null Fields that couldn't be populated */
        public readonly ?array $missingDataFields = null,

        /** @var string|null Notes about data quality */
        public readonly ?string $dataQualityNotes = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Helper Methods (Pure Data Accessors Only)
    |--------------------------------------------------------------------------
    |
    | These methods are for data transformation/display only, not policy.
    | Policy decisions (like axis selection) belong in ScenarioAxisSelector.
    */

    /**
     * Get the confidence label for UI display.
     */
    public function getConfidenceLabel(): string
    {
        return match ($this->confidenceLevel) {
            'high' => 'High Confidence (Full HC Assessment)',
            'medium' => 'Medium Confidence (CA + supplementary data)',
            'low' => 'Low Confidence (Limited assessment data)',
            default => 'Unknown Confidence',
        };
    }

    /**
     * Check if profile has enough data for bundle generation.
     *
     * Per design: ANY of HC, CA, or referral data is sufficient.
     * We never block bundling due to missing data.
     */
    public function isSufficientForBundling(): bool
    {
        return $this->hasFullHcAssessment
            || $this->hasCaAssessment
            || $this->hasReferralData;
    }

    /**
     * Get the primary classification identifier.
     *
     * Returns RUG group if available, otherwise needs cluster.
     */
    public function getPrimaryClassification(): ?string
    {
        return $this->rugGroup ?? $this->needsCluster;
    }

    /**
     * Get the classification type label.
     */
    public function getClassificationType(): string
    {
        if ($this->rugGroup !== null) {
            return 'RUG-III/HC';
        }
        if ($this->needsCluster !== null) {
            return 'Needs Cluster';
        }
        return 'Unclassified';
    }

    /**
     * Convert to array for API/LLM consumption (de-identified).
     *
     * This method returns ONLY de-identified data safe for LLM prompts.
     * Patient ID and other identifiers are excluded.
     *
     * NOTE: Does NOT include 'applicable_axes' - that is determined by
     * ScenarioAxisSelector at generation time, not stored in the profile.
     */
    public function toDeidentifiedArray(): array
    {
        return [
            'profile_version' => $this->profileVersion,
            'data_sources' => [
                'has_hc' => $this->hasFullHcAssessment,
                'has_ca' => $this->hasCaAssessment,
                'has_bmhs' => $this->hasBmhsAssessment,
                'has_referral' => $this->hasReferralData,
                'completeness' => round($this->dataCompletenessScore, 2),
            ],
            'case_classification' => [
                'rug_group' => $this->rugGroup,
                'rug_category' => $this->rugCategory,
                'needs_cluster' => $this->needsCluster,
                'episode_type' => $this->episodeType,
            ],
            'functional_needs' => [
                'adl_level' => $this->adlSupportLevel,
                'iadl_level' => $this->iadlSupportLevel,
                'mobility_complexity' => $this->mobilityComplexity,
                'specific_adl_needs' => $this->specificAdlNeeds,
            ],
            'cognitive_behavioural' => [
                'cognitive_complexity' => $this->cognitiveComplexity,
                'behavioural_complexity' => $this->behaviouralComplexity,
                'mental_health_complexity' => $this->mentalHealthComplexity,
                'wandering_risk' => $this->hasWanderingRisk,
                'aggression_risk' => $this->hasAggressionRisk,
                'behavioural_flags' => $this->behaviouralFlags,
            ],
            'clinical_risks' => [
                'falls_risk' => $this->fallsRiskLevel,
                'skin_risk' => $this->skinIntegrityRisk,
                'pain_level' => $this->painManagementNeed,
                'continence' => $this->continenceSupport,
                'health_instability' => $this->healthInstability,
                'clinical_flags' => $this->clinicalRiskFlags,
                'active_conditions' => $this->activeConditions,
            ],
            'treatment_context' => [
                'rehab_potential' => $this->hasRehabPotential,
                'rehab_score' => $this->rehabPotentialScore,
                'requires_extensive' => $this->requiresExtensiveServices,
                'extensive_services' => $this->extensiveServices,
                'weekly_therapy_minutes' => $this->weeklyTherapyMinutes,
            ],
            'support_context' => [
                'caregiver_availability' => $this->caregiverAvailabilityScore,
                'caregiver_stress' => $this->caregiverStressLevel,
                'lives_alone' => $this->livesAlone,
                'needs_respite' => $this->caregiverRequiresRelief,
                'social_support' => $this->socialSupportScore,
            ],
            'technology' => [
                'readiness' => $this->technologyReadiness,
                'has_internet' => $this->hasInternet,
                'has_pers' => $this->hasPers,
                'rpm_suitable' => $this->suitableForRpm,
            ],
            'environment' => [
                'region_code' => $this->regionCode,
                'travel_complexity' => $this->travelComplexityScore,
                'is_rural' => $this->isRural,
            ],
            'confidence' => [
                'level' => $this->confidenceLevel,
                'label' => $this->getConfidenceLabel(),
            ],
        ];
    }

    /**
     * Convert to full array for internal use (includes patient ID).
     */
    public function toArray(): array
    {
        return array_merge(
            ['patient_id' => $this->patientId],
            $this->toDeidentifiedArray(),
            [
                'generated_at' => $this->profileGeneratedAt->toIso8601String(),
                'primary_assessment_type' => $this->primaryAssessmentType,
                'primary_assessment_date' => $this->primaryAssessmentDate?->toIso8601String(),
                'missing_data_fields' => $this->missingDataFields,
                'data_quality_notes' => $this->dataQualityNotes,
            ]
        );
    }

    /**
     * Create a minimal profile for testing or when only patient ID is known.
     */
    public static function minimal(int $patientId): self
    {
        return new self(
            patientId: $patientId,
            profileGeneratedAt: Carbon::now(),
            confidenceLevel: 'low',
            dataQualityNotes: 'Minimal profile - no assessment data available',
        );
    }
}

