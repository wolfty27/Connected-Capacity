<?php

namespace App\Services\BundleEngine\Mappers;

use App\Models\InterraiAssessment;

/**
 * BmhsAssessmentMapper
 *
 * Maps InterRAI Brief Mental Health Screener (BMHS) assessment data to
 * PatientNeedsProfile fields. BMHS is designed to document:
 * 1. Indicators of disordered thought (Section B)
 * 2. Indicators of risk of harm (Section C)
 *
 * BMHS is a supplement to HC/CA assessments, providing mental health
 * and behavioural complexity data when indicated.
 *
 * NOTE: This mapper does NOT implement AssessmentMapperInterface because
 * BMHS is a specialized supplement, not a full assessment. It only provides
 * mental health and behavioral data, not ADL/IADL/mobility etc.
 *
 * Key BMHS Sections:
 * - Section B: Mental state indicators, insight, cognition
 * - Section C: Risk of harm (violence, self-harm, environment)
 *
 * @see docs/interRAI Brief Mental Health Screener (BMHS) Assessment Form.txt
 */
class BmhsAssessmentMapper
{
    /**
     * BMHS item codes for Section B (Disordered Thought).
     * Coding: 0=Not present, 1=Present but not in last 24h, 2=Exhibited in last 24h
     */
    private const SECTION_B_ITEMS = [
        'B1a' => 'bmhs_irritability',
        'B1b' => 'bmhs_hallucinations',
        'B1c' => 'bmhs_command_hallucinations',
        'B1d' => 'bmhs_delusions',
        'B1e' => 'bmhs_hyperarousal',
        'B1f' => 'bmhs_pressured_speech',
        'B1g' => 'bmhs_abnormal_thought',
        'B1h' => 'bmhs_inappropriate_behaviour',
        'B1i' => 'bmhs_verbal_abuse',
        'B1j' => 'bmhs_intoxication',
    ];

    /**
     * BMHS item codes for Section C (Risk of Harm).
     */
    private const SECTION_C_ITEMS = [
        'C1' => 'bmhs_previous_police_contact',
        'C2' => 'bmhs_weapon_history',
        'C3a' => 'bmhs_violent_ideation',
        'C3b' => 'bmhs_intimidation',
        'C3c' => 'bmhs_violence_to_others',
        'C4a' => 'bmhs_self_injury_attempt',
        'C4b' => 'bmhs_self_injury_considered',
        'C4c' => 'bmhs_suicide_plan',
        'C4d' => 'bmhs_others_concern_self_harm',
        'C5' => 'bmhs_squalid_home',
        'C6' => 'bmhs_medication_refusal',
    ];

    /**
     * Map BMHS assessment to profile fields.
     *
     * @inheritDoc
     */
    public function mapToProfileFields(InterraiAssessment $assessment): array
    {
        $rawItems = $assessment->raw_items ?? [];

        // Calculate complexity scores
        $disorderedThoughtScore = $this->calculateDisorderedThoughtScore($rawItems);
        $riskOfHarmScore = $this->calculateRiskOfHarmScore($rawItems);
        $selfHarmRisk = $this->assessSelfHarmRisk($rawItems);
        $violenceRisk = $this->assessViolenceRisk($rawItems);

        return [
            // Mental Health Complexity (derived from BMHS)
            'mentalHealthComplexity' => $this->deriveMentalHealthComplexity($rawItems),
            'behaviouralComplexity' => $this->deriveBehaviouralComplexity($rawItems),

            // BMHS-specific indicators
            'hasDisorderedThought' => $disorderedThoughtScore > 0,
            'disorderedThoughtScore' => $disorderedThoughtScore,
            'riskOfHarmScore' => $riskOfHarmScore,

            // Psychotic symptoms
            'hasHallucinations' => $this->hasSymptom($rawItems, 'bmhs_hallucinations'),
            'hasCommandHallucinations' => $this->hasSymptom($rawItems, 'bmhs_command_hallucinations'),
            'hasDelusions' => $this->hasSymptom($rawItems, 'bmhs_delusions'),

            // Insight and cognition
            'mentalHealthInsight' => $this->getInsightLevel($rawItems),
            'bmhsCognitiveImpairment' => $this->getCognitiveImpairment($rawItems),

            // Risk indicators
            'hasSelfHarmRisk' => $selfHarmRisk['hasRisk'],
            'selfHarmRiskLevel' => $selfHarmRisk['level'],
            'hasViolenceRisk' => $violenceRisk['hasRisk'],
            'violenceRiskLevel' => $violenceRisk['level'],

            // Environment
            'hasSqualidHome' => $this->getValue($rawItems, 'bmhs_squalid_home') == 1,
            'hasMedicationRefusal' => $this->getValue($rawItems, 'bmhs_medication_refusal') == 1,

            // Substance use
            'hasActiveIntoxication' => $this->hasSymptom($rawItems, 'bmhs_intoxication'),

            // Flags for bundling decisions
            'requiresPsychiatricConsult' => $this->requiresPsychiatricConsult($rawItems),
            'requiresBehaviouralSupport' => $this->requiresBehaviouralSupport($rawItems),
            'requiresCrisisIntervention' => $selfHarmRisk['level'] >= 2 || $violenceRisk['level'] >= 2,
        ];
    }

    /**
     * Calculate disordered thought score from Section B items.
     * Range: 0-20 (10 items × 2 max points each)
     */
    private function calculateDisorderedThoughtScore(array $rawItems): int
    {
        $score = 0;
        foreach (self::SECTION_B_ITEMS as $code => $field) {
            $value = $this->getValue($rawItems, $field);
            if ($value === 2) {
                $score += 2; // Exhibited in last 24h
            } elseif ($value === 1) {
                $score += 1; // Present but not in last 24h
            }
        }
        return $score;
    }

    /**
     * Calculate risk of harm score from Section C items.
     * Range: 0-11 (various scoring)
     */
    private function calculateRiskOfHarmScore(array $rawItems): int
    {
        $score = 0;

        // Violence items (C3a-c): 0-2 each
        foreach (['bmhs_violent_ideation', 'bmhs_intimidation', 'bmhs_violence_to_others'] as $field) {
            $score += $this->getValue($rawItems, $field);
        }

        // Self-harm items (C4a-d): 0-1 each
        foreach (['bmhs_self_injury_attempt', 'bmhs_self_injury_considered', 'bmhs_suicide_plan', 'bmhs_others_concern_self_harm'] as $field) {
            $score += $this->getValue($rawItems, $field) > 0 ? 1 : 0;
        }

        // Weapon history
        if ($this->getValue($rawItems, 'bmhs_weapon_history') == 1) {
            $score += 1;
        }

        return $score;
    }

    /**
     * Assess self-harm risk level.
     */
    private function assessSelfHarmRisk(array $rawItems): array
    {
        $attemptRecent = $this->getValue($rawItems, 'bmhs_self_injury_attempt') == 1;
        $considered = $this->getValue($rawItems, 'bmhs_self_injury_considered') == 1;
        $hasPlan = $this->getValue($rawItems, 'bmhs_suicide_plan') == 1;
        $othersConcerned = $this->getValue($rawItems, 'bmhs_others_concern_self_harm') == 1;
        $hasCommandHallucinations = $this->hasSymptom($rawItems, 'bmhs_command_hallucinations');

        // Level 3: Critical (recent attempt or active plan + command hallucinations)
        if ($attemptRecent || ($hasPlan && $hasCommandHallucinations)) {
            return ['hasRisk' => true, 'level' => 3];
        }

        // Level 2: High (suicide plan or considered + concerning factors)
        if ($hasPlan || ($considered && ($othersConcerned || $hasCommandHallucinations))) {
            return ['hasRisk' => true, 'level' => 2];
        }

        // Level 1: Moderate (considered or others concerned)
        if ($considered || $othersConcerned) {
            return ['hasRisk' => true, 'level' => 1];
        }

        return ['hasRisk' => false, 'level' => 0];
    }

    /**
     * Assess violence risk level.
     */
    private function assessViolenceRisk(array $rawItems): array
    {
        $violenceToOthers = $this->getValue($rawItems, 'bmhs_violence_to_others');
        $intimidation = $this->getValue($rawItems, 'bmhs_intimidation');
        $violentIdeation = $this->getValue($rawItems, 'bmhs_violent_ideation');
        $weaponHistory = $this->getValue($rawItems, 'bmhs_weapon_history') == 1;
        $hasCommandHallucinations = $this->hasSymptom($rawItems, 'bmhs_command_hallucinations');

        // Level 3: Critical (recent violence to others)
        if ($violenceToOthers === 2) {
            return ['hasRisk' => true, 'level' => 3];
        }

        // Level 2: High (history of violence or active intimidation + risk factors)
        if ($violenceToOthers === 1 || ($intimidation === 2 && ($weaponHistory || $hasCommandHallucinations))) {
            return ['hasRisk' => true, 'level' => 2];
        }

        // Level 1: Moderate (violent ideation or intimidation)
        if ($violentIdeation >= 1 || $intimidation >= 1) {
            return ['hasRisk' => true, 'level' => 1];
        }

        return ['hasRisk' => false, 'level' => 0];
    }

    /**
     * Derive mental health complexity (0-5 scale).
     */
    private function deriveMentalHealthComplexity(array $rawItems): int
    {
        $complexity = 0;

        // Psychotic symptoms are most severe
        if ($this->hasSymptom($rawItems, 'bmhs_command_hallucinations')) {
            $complexity += 2;
        }
        if ($this->hasSymptom($rawItems, 'bmhs_hallucinations')) {
            $complexity += 1;
        }
        if ($this->hasSymptom($rawItems, 'bmhs_delusions')) {
            $complexity += 1;
        }

        // Insight
        $insight = $this->getInsightLevel($rawItems);
        if ($insight === 'none') {
            $complexity += 1;
        }

        // Thought process abnormalities
        if ($this->hasSymptom($rawItems, 'bmhs_abnormal_thought')) {
            $complexity += 1;
        }

        return min(5, $complexity);
    }

    /**
     * Derive behavioural complexity (0-5 scale).
     */
    private function deriveBehaviouralComplexity(array $rawItems): int
    {
        $complexity = 0;

        // Violence and aggression
        $violenceRisk = $this->assessViolenceRisk($rawItems);
        $complexity += $violenceRisk['level'];

        // Behavioural indicators
        if ($this->hasSymptom($rawItems, 'bmhs_inappropriate_behaviour')) {
            $complexity += 1;
        }
        if ($this->hasSymptom($rawItems, 'bmhs_verbal_abuse')) {
            $complexity += 1;
        }
        if ($this->hasSymptom($rawItems, 'bmhs_hyperarousal')) {
            $complexity += 1;
        }

        return min(5, $complexity);
    }

    /**
     * Get insight level.
     */
    private function getInsightLevel(array $rawItems): string
    {
        $value = $this->getValue($rawItems, 'bmhs_insight');

        return match ($value) {
            0 => 'full',
            1 => 'limited',
            2 => 'none',
            default => 'unknown',
        };
    }

    /**
     * Get cognitive impairment from B3.
     */
    private function getCognitiveImpairment(array $rawItems): bool
    {
        return $this->getValue($rawItems, 'bmhs_cognitive_skills') == 1;
    }

    /**
     * Check if a symptom is present (value 1 or 2).
     */
    private function hasSymptom(array $rawItems, string $field): bool
    {
        return $this->getValue($rawItems, $field) >= 1;
    }

    /**
     * Check if psychiatric consultation is recommended.
     */
    private function requiresPsychiatricConsult(array $rawItems): bool
    {
        // Command hallucinations always require psychiatric evaluation
        if ($this->hasSymptom($rawItems, 'bmhs_command_hallucinations')) {
            return true;
        }

        // Self-harm risk
        $selfHarmRisk = $this->assessSelfHarmRisk($rawItems);
        if ($selfHarmRisk['level'] >= 2) {
            return true;
        }

        // High disordered thought score
        if ($this->calculateDisorderedThoughtScore($rawItems) >= 8) {
            return true;
        }

        // No insight with significant symptoms
        $insight = $this->getInsightLevel($rawItems);
        if ($insight === 'none' && $this->calculateDisorderedThoughtScore($rawItems) >= 4) {
            return true;
        }

        return false;
    }

    /**
     * Check if behavioural support services are recommended.
     */
    private function requiresBehaviouralSupport(array $rawItems): bool
    {
        $behaviouralScore = $this->deriveBehaviouralComplexity($rawItems);
        return $behaviouralScore >= 2;
    }

    /**
     * Get a value from raw items with fallback.
     */
    private function getValue(array $rawItems, string $field, mixed $default = 0): mixed
    {
        return $rawItems[$field] ?? $default;
    }
}

