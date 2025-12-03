<?php

namespace App\Services\BundleEngine;

use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\Enums\ScenarioAxis;

/**
 * ScenarioAxisSelector
 *
 * Determines which scenario axes are applicable for a given patient profile.
 * This is the SINGLE SOURCE OF TRUTH for axis selection policy.
 *
 * DESIGN DECISION: This service was separated from PatientNeedsProfile to
 * maintain clean separation of concerns:
 * - PatientNeedsProfile = pure data container (DTO)
 * - ScenarioAxisSelector = policy/business logic
 *
 * The selection rules are based on patient profile attributes and can be
 * adjusted without modifying the DTO structure.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 6.3
 */
class ScenarioAxisSelector
{
    /**
     * Thresholds for axis selection decisions.
     * These can be adjusted or moved to config.
     */
    protected const THRESHOLDS = [
        // Recovery/Rehab thresholds
        'rehab_score_minimum' => 40,
        'weekly_therapy_minutes_minimum' => 30,

        // Safety/Stability thresholds
        'falls_risk_high' => 2,
        'health_instability_high' => 3,
        'cognitive_complexity_safety' => 3,

        // Tech-enabled thresholds
        'tech_readiness_minimum' => 2,

        // Caregiver relief thresholds
        'caregiver_stress_high' => 3,
        'caregiver_availability_with_stress' => 2,

        // Medical intensive thresholds
        'health_instability_medical' => 4,

        // Cognitive support thresholds
        'cognitive_complexity_high' => 3,
        'behavioural_complexity_high' => 3,

        // Community integration thresholds
        'social_support_low' => 2,
        'iadl_support_level_minimum' => 2,
    ];

    /**
     * Get applicable scenario axes for a patient profile.
     *
     * @param PatientNeedsProfile $profile The patient's needs profile
     * @param int $maxAxes Maximum number of axes to return (default 4)
     * @return array<ScenarioAxis> Applicable axes in priority order
     */
    public function getApplicableAxes(PatientNeedsProfile $profile, int $maxAxes = 4): array
    {
        $candidates = [];

        // Always include BALANCED as a fallback option
        $candidates[ScenarioAxis::BALANCED->value] = [
            'axis' => ScenarioAxis::BALANCED,
            'score' => 50, // Base score, always applicable
            'reasons' => ['Default balanced option'],
        ];

        // Check each axis for applicability
        $this->evaluateRecoveryRehab($profile, $candidates);
        $this->evaluateSafetyStability($profile, $candidates);
        $this->evaluateTechEnabled($profile, $candidates);
        $this->evaluateCaregiverRelief($profile, $candidates);
        $this->evaluateMedicalIntensive($profile, $candidates);
        $this->evaluateCognitiveSupport($profile, $candidates);
        $this->evaluateCommunityIntegrated($profile, $candidates);

        // Sort by score (descending) and take top N
        uasort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        $selected = array_slice($candidates, 0, $maxAxes, true);

        return array_map(fn($item) => $item['axis'], $selected);
    }

    /**
     * Get detailed axis evaluation for a patient profile.
     *
     * Returns full evaluation data including scores and reasons.
     *
     * @param PatientNeedsProfile $profile
     * @return array<string, array{axis: ScenarioAxis, score: int, reasons: array<string>, applicable: bool}>
     */
    public function getDetailedEvaluation(PatientNeedsProfile $profile): array
    {
        $candidates = [];

        $candidates[ScenarioAxis::BALANCED->value] = [
            'axis' => ScenarioAxis::BALANCED,
            'score' => 50,
            'reasons' => ['Default balanced option'],
        ];

        $this->evaluateRecoveryRehab($profile, $candidates);
        $this->evaluateSafetyStability($profile, $candidates);
        $this->evaluateTechEnabled($profile, $candidates);
        $this->evaluateCaregiverRelief($profile, $candidates);
        $this->evaluateMedicalIntensive($profile, $candidates);
        $this->evaluateCognitiveSupport($profile, $candidates);
        $this->evaluateCommunityIntegrated($profile, $candidates);

        // Add applicable flag based on score threshold
        foreach ($candidates as $key => $candidate) {
            $candidates[$key]['applicable'] = $candidate['score'] >= 40;
        }

        return $candidates;
    }

    /**
     * Check if a specific axis is applicable for a profile.
     */
    public function isAxisApplicable(PatientNeedsProfile $profile, ScenarioAxis $axis): bool
    {
        $applicable = $this->getApplicableAxes($profile, 8); // Get all possible
        return in_array($axis, $applicable);
    }

    /**
     * Evaluate Recovery-Focused / Rehabilitation-Heavy axis.
     *
     * Applicable when:
     * - Has rehab potential (score >= 40)
     * - Has therapy minutes scheduled
     * - Episode type is post_acute or acute_exacerbation
     */
    protected function evaluateRecoveryRehab(PatientNeedsProfile $profile, array &$candidates): void
    {
        $score = 0;
        $reasons = [];

        // Rehab potential score
        if ($profile->rehabPotentialScore >= self::THRESHOLDS['rehab_score_minimum']) {
            $score += 40;
            $reasons[] = "Rehab potential score: {$profile->rehabPotentialScore}";
        }

        // Weekly therapy minutes
        if ($profile->weeklyTherapyMinutes >= self::THRESHOLDS['weekly_therapy_minutes_minimum']) {
            $score += 30;
            $reasons[] = "Therapy minutes/week: {$profile->weeklyTherapyMinutes}";
        }

        // Episode type
        if (in_array($profile->episodeType, ['post_acute', 'acute_exacerbation'])) {
            $score += 20;
            $reasons[] = "Episode type: {$profile->episodeType}";
        }

        // Has rehab potential flag
        if ($profile->hasRehabPotential) {
            $score += 10;
            $reasons[] = 'Has documented rehab potential';
        }

        if ($score >= 40) {
            $candidates[ScenarioAxis::RECOVERY_REHAB->value] = [
                'axis' => ScenarioAxis::RECOVERY_REHAB,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }
    }

    /**
     * Evaluate Safety & Stability axis.
     *
     * Applicable when:
     * - High falls risk
     * - Health instability (CHESS 3+)
     * - Cognitive complexity (CPS 3+)
     * - Lives alone with risk factors
     */
    protected function evaluateSafetyStability(PatientNeedsProfile $profile, array &$candidates): void
    {
        $score = 0;
        $reasons = [];

        // Falls risk
        if ($profile->fallsRiskLevel >= self::THRESHOLDS['falls_risk_high']) {
            $score += 35;
            $reasons[] = "High falls risk level: {$profile->fallsRiskLevel}";
        }

        // Health instability
        if ($profile->healthInstability >= self::THRESHOLDS['health_instability_high']) {
            $score += 30;
            $reasons[] = "Health instability (CHESS): {$profile->healthInstability}";
        }

        // Cognitive complexity
        if ($profile->cognitiveComplexity >= self::THRESHOLDS['cognitive_complexity_safety']) {
            $score += 20;
            $reasons[] = "Cognitive complexity: {$profile->cognitiveComplexity}";
        }

        // Lives alone (additional risk)
        if ($profile->livesAlone) {
            $score += 15;
            $reasons[] = 'Lives alone';
        }

        // Wandering or aggression risk
        if ($profile->hasWanderingRisk || $profile->hasAggressionRisk) {
            $score += 10;
            $reasons[] = 'Behavioural safety risk';
        }

        if ($score >= 40) {
            $candidates[ScenarioAxis::SAFETY_STABILITY->value] = [
                'axis' => ScenarioAxis::SAFETY_STABILITY,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }
    }

    /**
     * Evaluate Tech-Enabled / Remote-Support axis.
     *
     * Applicable when:
     * - Tech readiness score >= 2
     * - Has internet access
     * - Stable condition (not high acuity)
     * - Caregiver available (for device support)
     */
    protected function evaluateTechEnabled(PatientNeedsProfile $profile, array &$candidates): void
    {
        $score = 0;
        $reasons = [];

        // Tech readiness
        if ($profile->technologyReadiness >= self::THRESHOLDS['tech_readiness_minimum']) {
            $score += 35;
            $reasons[] = "Technology readiness: {$profile->technologyReadiness}";
        }

        // Has internet
        if ($profile->hasInternet) {
            $score += 25;
            $reasons[] = 'Has reliable internet';
        }

        // Stable condition (low instability)
        if ($profile->healthInstability <= 2) {
            $score += 15;
            $reasons[] = 'Stable health status';
        }

        // Already has PERS/RPM
        if ($profile->hasPers) {
            $score += 10;
            $reasons[] = 'Has PERS installed';
        }

        if ($profile->suitableForRpm) {
            $score += 15;
            $reasons[] = 'Suitable for RPM';
        }

        // Rural area (bonus - remote support helps)
        if ($profile->isRural) {
            $score += 10;
            $reasons[] = 'Rural location benefits from remote support';
        }

        // Penalize if cognitive complexity is high (may struggle with tech)
        if ($profile->cognitiveComplexity >= 4) {
            $score -= 20;
        }

        if ($score >= 40) {
            $candidates[ScenarioAxis::TECH_ENABLED->value] = [
                'axis' => ScenarioAxis::TECH_ENABLED,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }
    }

    /**
     * Evaluate Caregiver-Relief / Support-Emphasis axis.
     *
     * Applicable when:
     * - High caregiver stress (3+)
     * - Caregiver requires relief flag
     * - Patient has caregiver available but stressed
     */
    protected function evaluateCaregiverRelief(PatientNeedsProfile $profile, array &$candidates): void
    {
        $score = 0;
        $reasons = [];

        // Caregiver stress level
        if ($profile->caregiverStressLevel >= self::THRESHOLDS['caregiver_stress_high']) {
            $score += 40;
            $reasons[] = "High caregiver stress: {$profile->caregiverStressLevel}";
        }

        // Explicit caregiver relief flag
        if ($profile->caregiverRequiresRelief) {
            $score += 30;
            $reasons[] = 'Caregiver requires relief';
        }

        // Has caregiver available (so relief is meaningful)
        if ($profile->caregiverAvailabilityScore >= self::THRESHOLDS['caregiver_availability_with_stress']) {
            $score += 15;
            $reasons[] = 'Caregiver is engaged and available';
        }

        // Cognitive complexity (caregiver burden)
        if ($profile->cognitiveComplexity >= 3) {
            $score += 10;
            $reasons[] = 'Cognitive complexity increases caregiver burden';
        }

        // Behavioural complexity (caregiver burden)
        if ($profile->behaviouralComplexity >= 2) {
            $score += 10;
            $reasons[] = 'Behavioural complexity increases caregiver burden';
        }

        if ($score >= 40) {
            $candidates[ScenarioAxis::CAREGIVER_RELIEF->value] = [
                'axis' => ScenarioAxis::CAREGIVER_RELIEF,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }
    }

    /**
     * Evaluate Medical Intensive axis.
     *
     * Applicable when:
     * - Requires extensive services (IV, wound care, etc.)
     * - Very high health instability (CHESS 4+)
     * - Complex medical conditions
     */
    protected function evaluateMedicalIntensive(PatientNeedsProfile $profile, array &$candidates): void
    {
        $score = 0;
        $reasons = [];

        // Requires extensive services
        if ($profile->requiresExtensiveServices) {
            $score += 50;
            $reasons[] = 'Requires extensive services';
            if (!empty($profile->extensiveServices)) {
                $reasons[] = 'Services: ' . implode(', ', $profile->extensiveServices);
            }
        }

        // Very high health instability
        if ($profile->healthInstability >= self::THRESHOLDS['health_instability_medical']) {
            $score += 30;
            $reasons[] = "Very high health instability: {$profile->healthInstability}";
        }

        // Skin integrity risk
        if ($profile->skinIntegrityRisk >= 2) {
            $score += 15;
            $reasons[] = "Skin integrity risk: {$profile->skinIntegrityRisk}";
        }

        // Pain management needs
        if ($profile->painManagementNeed >= 2) {
            $score += 10;
            $reasons[] = "Pain management need: {$profile->painManagementNeed}";
        }

        // Multiple active conditions
        if (!empty($profile->activeConditions) && count($profile->activeConditions) >= 3) {
            $score += 10;
            $reasons[] = 'Multiple active conditions';
        }

        if ($score >= 40) {
            $candidates[ScenarioAxis::MEDICAL_INTENSIVE->value] = [
                'axis' => ScenarioAxis::MEDICAL_INTENSIVE,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }
    }

    /**
     * Evaluate Cognitive Support axis.
     *
     * Applicable when:
     * - High cognitive complexity (CPS 3+)
     * - Behavioural complexity
     * - Wandering or aggression risk
     */
    protected function evaluateCognitiveSupport(PatientNeedsProfile $profile, array &$candidates): void
    {
        $score = 0;
        $reasons = [];

        // Cognitive complexity
        if ($profile->cognitiveComplexity >= self::THRESHOLDS['cognitive_complexity_high']) {
            $score += 40;
            $reasons[] = "Cognitive complexity: {$profile->cognitiveComplexity}";
        }

        // Behavioural complexity
        if ($profile->behaviouralComplexity >= self::THRESHOLDS['behavioural_complexity_high']) {
            $score += 25;
            $reasons[] = "Behavioural complexity: {$profile->behaviouralComplexity}";
        }

        // Mental health complexity (from BMHS)
        if ($profile->mentalHealthComplexity >= 2) {
            $score += 15;
            $reasons[] = "Mental health complexity: {$profile->mentalHealthComplexity}";
        }

        // Wandering risk
        if ($profile->hasWanderingRisk) {
            $score += 15;
            $reasons[] = 'Wandering risk';
        }

        // Aggression risk
        if ($profile->hasAggressionRisk) {
            $score += 10;
            $reasons[] = 'Aggression risk';
        }

        // Behavioural flags
        if (!empty($profile->behaviouralFlags)) {
            $score += 10;
            $reasons[] = 'Documented behavioural concerns';
        }

        if ($score >= 40) {
            $candidates[ScenarioAxis::COGNITIVE_SUPPORT->value] = [
                'axis' => ScenarioAxis::COGNITIVE_SUPPORT,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }
    }

    /**
     * Evaluate Community Integrated axis.
     *
     * Applicable when:
     * - Low social support
     * - IADL support needs
     * - Lives alone but not high medical acuity
     * - Suitable for day programs
     */
    protected function evaluateCommunityIntegrated(PatientNeedsProfile $profile, array &$candidates): void
    {
        $score = 0;
        $reasons = [];

        // Low social support
        if ($profile->socialSupportScore <= self::THRESHOLDS['social_support_low']) {
            $score += 30;
            $reasons[] = "Low social support: {$profile->socialSupportScore}";
        }

        // IADL support needs
        if ($profile->iadlSupportLevel >= self::THRESHOLDS['iadl_support_level_minimum']) {
            $score += 25;
            $reasons[] = "IADL support level: {$profile->iadlSupportLevel}";
        }

        // Lives alone
        if ($profile->livesAlone) {
            $score += 15;
            $reasons[] = 'Lives alone - may benefit from social connection';
        }

        // Low cognitive complexity (can participate in programs)
        if ($profile->cognitiveComplexity <= 2) {
            $score += 15;
            $reasons[] = 'Cognitive capacity for program participation';
        }

        // Stable health
        if ($profile->healthInstability <= 2) {
            $score += 10;
            $reasons[] = 'Stable for community participation';
        }

        if ($score >= 40) {
            $candidates[ScenarioAxis::COMMUNITY_INTEGRATED->value] = [
                'axis' => ScenarioAxis::COMMUNITY_INTEGRATED,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }
    }

    /**
     * Get threshold value by name.
     *
     * Useful for testing and debugging.
     */
    public function getThreshold(string $name): ?int
    {
        return self::THRESHOLDS[$name] ?? null;
    }

    /**
     * Get all thresholds.
     */
    public function getAllThresholds(): array
    {
        return self::THRESHOLDS;
    }
}

