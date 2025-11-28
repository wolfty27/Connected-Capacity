<?php

namespace App\Services;

use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Models\RUGClassification;

/**
 * InterraiSummaryService
 *
 * Generates narrative summaries and clinical flags from InterRAI HC
 * assessments and RUG classifications. This replaces the legacy
 * TransitionNeedsProfile (TNP) functionality.
 *
 * The service produces:
 * - narrative_summary: Human-readable paragraph summarizing patient status
 * - clinical_flags: Structured boolean flags for risk indicators
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class InterraiSummaryService
{
    /**
     * Generate a complete summary for a patient.
     *
     * @param Patient $patient
     * @return array{narrative_summary: string, clinical_flags: array, rug_summary: array|null}
     */
    public function generateSummary(Patient $patient): array
    {
        $assessment = $patient->latestInterraiAssessment;
        $rugClassification = $patient->latestRugClassification;

        if (!$assessment) {
            return [
                'narrative_summary' => 'No InterRAI HC assessment available. Assessment required for care planning.',
                'clinical_flags' => $this->getDefaultFlags(),
                'rug_summary' => null,
                'assessment_status' => 'missing',
            ];
        }

        return [
            'narrative_summary' => $this->buildNarrativeSummary($assessment, $rugClassification),
            'clinical_flags' => $this->buildClinicalFlags($assessment, $rugClassification),
            'rug_summary' => $rugClassification?->toSummaryArray(),
            'assessment_status' => $assessment->isStale() ? 'stale' : 'current',
            'assessment_date' => $assessment->assessment_date?->toIso8601String(),
            'days_until_stale' => $assessment->days_until_stale,
        ];
    }

    /**
     * Build narrative summary from assessment and RUG classification.
     */
    public function buildNarrativeSummary(
        InterraiAssessment $assessment,
        ?RUGClassification $rug = null
    ): string {
        $parts = [];

        // Opening with RUG category context
        $parts[] = $this->buildOpeningStatement($assessment, $rug);

        // Functional status
        $parts[] = $this->buildFunctionalStatusSection($assessment, $rug);

        // Cognitive status
        if ($assessment->cognitive_performance_scale >= 2 || $assessment->wandering_flag) {
            $parts[] = $this->buildCognitiveStatusSection($assessment);
        }

        // Clinical risks
        $clinicalRisks = $this->buildClinicalRisksSection($assessment);
        if ($clinicalRisks) {
            $parts[] = $clinicalRisks;
        }

        // Bundle intent
        if ($rug) {
            $parts[] = $this->buildBundleIntentSection($rug);
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * Build opening statement based on RUG category.
     */
    protected function buildOpeningStatement(
        InterraiAssessment $assessment,
        ?RUGClassification $rug
    ): string {
        $maple = $assessment->maple_score;
        $mapleDesc = $assessment->maple_description ?? 'Unknown';

        if ($rug) {
            return match ($rug->rug_category) {
                RUGClassification::CATEGORY_SPECIAL_REHABILITATION =>
                    "Patient requires intensive rehabilitation services with a MAPLe priority of {$mapleDesc} ({$maple}).",

                RUGClassification::CATEGORY_EXTENSIVE_SERVICES =>
                    "Patient has extensive medical service needs (IV therapy, ventilator support, etc.) with MAPLe priority {$mapleDesc} ({$maple}).",

                RUGClassification::CATEGORY_SPECIAL_CARE =>
                    "Patient presents with high clinical complexity and physical dependency, MAPLe priority {$mapleDesc} ({$maple}).",

                RUGClassification::CATEGORY_CLINICALLY_COMPLEX =>
                    "Patient has multiple clinical conditions requiring close monitoring, MAPLe priority {$mapleDesc} ({$maple}).",

                RUGClassification::CATEGORY_IMPAIRED_COGNITION =>
                    "Patient has significant cognitive impairment requiring structured support, MAPLe priority {$mapleDesc} ({$maple}).",

                RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS =>
                    "Patient exhibits behavioural symptoms requiring specialized care approaches, MAPLe priority {$mapleDesc} ({$maple}).",

                RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION =>
                    "Patient requires physical assistance support with MAPLe priority {$mapleDesc} ({$maple}).",

                default =>
                    "Patient assessed with MAPLe priority {$mapleDesc} ({$maple}).",
            };
        }

        return "Patient assessed with MAPLe priority {$mapleDesc} ({$maple}).";
    }

    /**
     * Build functional status section.
     */
    protected function buildFunctionalStatusSection(
        InterraiAssessment $assessment,
        ?RUGClassification $rug
    ): string {
        $adlDesc = $assessment->adl_description ?? 'Unknown';
        $adlLevel = $assessment->adl_hierarchy ?? 0;

        $iadlDesc = match ($assessment->iadl_difficulty ?? 0) {
            0 => 'independent with IADLs',
            1, 2 => 'needs some IADL assistance',
            3, 4 => 'needs significant IADL support',
            5, 6 => 'dependent for most IADLs',
            default => 'IADL status unknown',
        };

        $adlSummary = $rug
            ? "ADL status: {$adlDesc} (sum score {$rug->adl_sum}/18)"
            : "ADL status: {$adlDesc}";

        return "{$adlSummary}, {$iadlDesc}.";
    }

    /**
     * Build cognitive status section.
     */
    protected function buildCognitiveStatusSection(InterraiAssessment $assessment): string
    {
        $cpsDesc = $assessment->cps_description ?? 'Unknown';
        $cps = $assessment->cognitive_performance_scale ?? 0;

        $parts = ["Cognitive status: {$cpsDesc} (CPS {$cps}/6)"];

        if ($assessment->wandering_flag) {
            $parts[] = 'with wandering/elopement risk';
        }

        if ($assessment->depression_rating_scale >= 3) {
            $parts[] = 'and elevated depression indicators';
        }

        return implode(' ', $parts) . '.';
    }

    /**
     * Build clinical risks section.
     */
    protected function buildClinicalRisksSection(InterraiAssessment $assessment): ?string
    {
        $risks = [];

        if ($assessment->falls_in_last_90_days) {
            $risks[] = 'recent fall history';
        }

        if ($assessment->chess_score >= 3) {
            $chessDesc = match ($assessment->chess_score) {
                3 => 'moderate',
                4 => 'high',
                5 => 'very high',
                default => 'elevated',
            };
            $risks[] = "{$chessDesc} health instability (CHESS {$assessment->chess_score})";
        }

        if ($assessment->pain_scale >= 2) {
            $painDesc = match ($assessment->pain_scale) {
                2 => 'moderate',
                3 => 'severe',
                4 => 'very severe',
                default => 'significant',
            };
            $risks[] = "{$painDesc} pain";
        }

        if (empty($risks)) {
            return null;
        }

        return 'Clinical considerations: ' . implode(', ', $risks) . '.';
    }

    /**
     * Build bundle intent section based on RUG classification.
     */
    protected function buildBundleIntentSection(RUGClassification $rug): string
    {
        $intent = match ($rug->rug_category) {
            RUGClassification::CATEGORY_SPECIAL_REHABILITATION =>
                'Care focus: Intensive therapy and rehabilitation to restore function and defer LTC placement.',

            RUGClassification::CATEGORY_EXTENSIVE_SERVICES =>
                'Care focus: Complex medical management in home setting as alternative to institutional care.',

            RUGClassification::CATEGORY_SPECIAL_CARE =>
                'Care focus: High-intensity clinical stabilization with 24/7 support capability.',

            RUGClassification::CATEGORY_CLINICALLY_COMPLEX =>
                'Care focus: Clinical monitoring and condition management to prevent acute episodes.',

            RUGClassification::CATEGORY_IMPAIRED_COGNITION =>
                'Care focus: Structured cognitive support, safety supervision, and caregiver respite.',

            RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS =>
                'Care focus: Behavioural support strategies, BSO alignment, and structured environment.',

            RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION =>
                'Care focus: Personal support services to maintain independence and quality of life.',

            default =>
                'Care focus: Comprehensive home care support tailored to assessed needs.',
        };

        return $intent;
    }

    /**
     * Build clinical flags from assessment and RUG classification.
     */
    public function buildClinicalFlags(
        InterraiAssessment $assessment,
        ?RUGClassification $rug = null
    ): array {
        return [
            // Fall risk
            'high_fall_risk' => $assessment->falls_in_last_90_days ?? false,

            // Cognitive/behaviour
            'high_cognitive_impairment' => ($assessment->cognitive_performance_scale ?? 0) >= 3,
            'wandering_risk' => $assessment->wandering_flag ?? false,
            'high_behaviour_risk' => $rug?->hasFlag('behaviour_problems') ?? false,

            // Clinical instability
            'high_clinical_instability' => ($assessment->chess_score ?? 0) >= 3,
            'health_unstable' => ($assessment->chess_score ?? 0) >= 4,

            // Pain and depression
            'significant_pain' => ($assessment->pain_scale ?? 0) >= 2,
            'depression_risk' => ($assessment->depression_rating_scale ?? 0) >= 3,

            // Care intensity
            'high_adl_needs' => ($assessment->adl_hierarchy ?? 0) >= 4,
            'total_adl_dependence' => ($assessment->adl_hierarchy ?? 0) >= 6,

            // LTC indicators
            'high_maple_priority' => in_array($assessment->maple_score, ['4', '5', 4, 5]),
            'ltc_crisis_priority' => $assessment->maple_score === '5' || $assessment->maple_score === 5,

            // ED risk (composite)
            'frequent_ed_risk' => $this->calculateEdRisk($assessment, $rug),

            // Caregiver
            'caregiver_burden_high' => $this->assessCaregiverBurden($assessment, $rug),

            // Assessment status
            'assessment_stale' => $assessment->isStale(),

            // RUG-specific
            'requires_rehabilitation' => $rug?->hasFlag('rehab') ?? false,
            'requires_extensive_services' => $rug?->hasFlag('extensive_services') ?? false,
            'clinically_complex' => $rug?->hasFlag('clinically_complex') ?? false,
        ];
    }

    /**
     * Calculate ED visit risk based on clinical indicators.
     */
    protected function calculateEdRisk(
        InterraiAssessment $assessment,
        ?RUGClassification $rug
    ): bool {
        $riskFactors = 0;

        // CHESS score is strong predictor
        if (($assessment->chess_score ?? 0) >= 3) {
            $riskFactors += 2;
        }

        // Falls increase ED risk
        if ($assessment->falls_in_last_90_days) {
            $riskFactors++;
        }

        // High MAPLe
        if (in_array($assessment->maple_score, ['4', '5', 4, 5])) {
            $riskFactors++;
        }

        // High ADL needs with instability
        if (($assessment->adl_hierarchy ?? 0) >= 4 && ($assessment->chess_score ?? 0) >= 2) {
            $riskFactors++;
        }

        return $riskFactors >= 3;
    }

    /**
     * Assess caregiver burden based on patient needs.
     */
    protected function assessCaregiverBurden(
        InterraiAssessment $assessment,
        ?RUGClassification $rug
    ): bool {
        // High burden if high ADL needs
        if (($assessment->adl_hierarchy ?? 0) >= 5) {
            return true;
        }

        // High burden if cognitive impairment with wandering
        if (($assessment->cognitive_performance_scale ?? 0) >= 3 && $assessment->wandering_flag) {
            return true;
        }

        // High burden if behaviour problems
        if ($rug?->hasFlag('behaviour_problems')) {
            return true;
        }

        return false;
    }

    /**
     * Get default flags when no assessment is available.
     */
    protected function getDefaultFlags(): array
    {
        return [
            'high_fall_risk' => false,
            'high_cognitive_impairment' => false,
            'wandering_risk' => false,
            'high_behaviour_risk' => false,
            'high_clinical_instability' => false,
            'health_unstable' => false,
            'significant_pain' => false,
            'depression_risk' => false,
            'high_adl_needs' => false,
            'total_adl_dependence' => false,
            'high_maple_priority' => false,
            'ltc_crisis_priority' => false,
            'frequent_ed_risk' => false,
            'caregiver_burden_high' => false,
            'assessment_stale' => true,
            'requires_rehabilitation' => false,
            'requires_extensive_services' => false,
            'clinically_complex' => false,
        ];
    }

    /**
     * Get a simplified flag summary for UI display.
     *
     * @return array<string, array{label: string, severity: string}>
     */
    public function getFlagLabels(): array
    {
        return [
            'high_fall_risk' => ['label' => 'Fall Risk', 'severity' => 'warning'],
            'high_cognitive_impairment' => ['label' => 'Cognitive Impairment', 'severity' => 'info'],
            'wandering_risk' => ['label' => 'Wandering/Elopement Risk', 'severity' => 'danger'],
            'high_behaviour_risk' => ['label' => 'Behavioural Concerns', 'severity' => 'warning'],
            'high_clinical_instability' => ['label' => 'Health Instability', 'severity' => 'danger'],
            'health_unstable' => ['label' => 'Acute Health Risk', 'severity' => 'danger'],
            'significant_pain' => ['label' => 'Pain Management Needed', 'severity' => 'warning'],
            'depression_risk' => ['label' => 'Depression Risk', 'severity' => 'info'],
            'high_adl_needs' => ['label' => 'High ADL Support', 'severity' => 'info'],
            'total_adl_dependence' => ['label' => 'Total ADL Dependence', 'severity' => 'warning'],
            'high_maple_priority' => ['label' => 'High LTC Priority', 'severity' => 'warning'],
            'ltc_crisis_priority' => ['label' => 'Crisis LTC Priority', 'severity' => 'danger'],
            'frequent_ed_risk' => ['label' => 'ED Visit Risk', 'severity' => 'danger'],
            'caregiver_burden_high' => ['label' => 'Caregiver Burden', 'severity' => 'warning'],
            'assessment_stale' => ['label' => 'Assessment Needs Update', 'severity' => 'warning'],
            'requires_rehabilitation' => ['label' => 'Rehab Required', 'severity' => 'info'],
            'requires_extensive_services' => ['label' => 'Extensive Services', 'severity' => 'warning'],
            'clinically_complex' => ['label' => 'Clinically Complex', 'severity' => 'info'],
        ];
    }

    /**
     * Get active flags with their labels for display.
     */
    public function getActiveFlagsWithLabels(array $flags): array
    {
        $labels = $this->getFlagLabels();
        $active = [];

        foreach ($flags as $key => $value) {
            if ($value === true && isset($labels[$key])) {
                $active[] = [
                    'key' => $key,
                    'label' => $labels[$key]['label'],
                    'severity' => $labels[$key]['severity'],
                ];
            }
        }

        // Sort by severity (danger first, then warning, then info)
        usort($active, function ($a, $b) {
            $order = ['danger' => 0, 'warning' => 1, 'info' => 2];
            return ($order[$a['severity']] ?? 3) <=> ($order[$b['severity']] ?? 3);
        });

        return $active;
    }
}
