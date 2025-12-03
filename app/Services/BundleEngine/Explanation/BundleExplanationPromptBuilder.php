<?php

namespace App\Services\BundleEngine\Explanation;

use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;
use RuntimeException;

/**
 * BundleExplanationPromptBuilder
 *
 * Builds PII-safe prompts for Vertex AI Gemini to explain bundle scenario selections.
 *
 * CRITICAL: This class enforces strict PII/PHI masking rules:
 * - NO patient names, addresses, OHIP numbers, DOB
 * - NO exact coordinates or location details
 * - Uses hashed references (P-xxxx) instead of IDs
 * - Only includes clinical metadata relevant to care decisions
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 6.1
 */
class BundleExplanationPromptBuilder
{
    /**
     * Fields that MUST NEVER appear in prompts (PHI/PII).
     */
    private const FORBIDDEN_FIELD_PATTERNS = [
        'name',
        'email',
        'phone',
        'address',
        'street',
        'ohip',
        'health_card',
        'sin',
        'date_of_birth',
        'dob',
        'postal_code',
        'lat',
        'lng',
        'latitude',
        'longitude',
        'coordinates',
    ];

    /**
     * Build the complete prompt payload for Vertex AI bundle explanation.
     *
     * @param PatientNeedsProfile $profile Patient's needs profile (de-identified)
     * @param ScenarioBundleDTO $scenario The selected scenario to explain
     * @param array $comparisonScenarios Optional: other scenarios for comparison context
     * @return array The prompt payload structure
     */
    public function buildPromptPayload(
        PatientNeedsProfile $profile,
        ScenarioBundleDTO $scenario,
        array $comparisonScenarios = []
    ): array {
        $payload = [
            'system_instruction' => $this->getSystemInstruction(),
            'patient_profile' => $this->buildPatientProfileContext($profile),
            'algorithm_scores' => $this->buildAlgorithmScoresContext($profile),
            'triggered_caps' => $this->buildTriggeredCapsContext($profile),
            'selected_scenario' => $this->buildScenarioContext($scenario),
            'services_included' => $this->buildServicesContext($scenario),
            'cost_context' => $this->buildCostContext($scenario),
        ];

        // Add comparison context if other scenarios provided
        if (!empty($comparisonScenarios)) {
            $payload['alternative_scenarios'] = $this->buildAlternativesContext($comparisonScenarios);
        }

        // Safety validation before returning
        $this->validateNoPhiPii($payload);

        return $payload;
    }

    /**
     * Get the system instruction for the LLM.
     */
    private function getSystemInstruction(): string
    {
        return <<<'INSTRUCTION'
You are an AI care planning assistant for Ontario Health at Home bundled care services.
Your role is to explain why a specific care bundle scenario was recommended for a patient.

Guidelines:
- Be concise (2-3 sentences for short explanation)
- Use professional healthcare terminology
- Frame explanations in terms of patient experience and goals, NOT budget constraints
- Reference algorithm scores and CAP triggers when relevant
- Focus on how the bundle addresses identified clinical needs
- Never invent information not provided in the context
- Use patient-centered language ("supports recovery" not "cheaper option")

Key Framing:
- Recovery-focused bundles: Emphasize rehabilitation potential and goal achievement
- Safety-focused bundles: Emphasize risk mitigation and stability
- Caregiver-relief bundles: Emphasize sustainable care and family support
- Tech-enabled bundles: Emphasize continuous monitoring and flexibility

Output Format:
Return a JSON object with exactly these fields:
{
  "short_explanation": "2-3 sentence summary of why this bundle was recommended",
  "key_factors": ["Factor 1", "Factor 2", "Factor 3"],
  "patient_benefit": "What the patient gains from this bundle approach",
  "clinical_alignment": "How this aligns with their clinical profile"
}
INSTRUCTION;
    }

    /**
     * Build patient profile context (de-identified).
     */
    private function buildPatientProfileContext(PatientNeedsProfile $profile): array
    {
        return [
            'patient_ref' => $this->generatePatientRef($profile->patientId),
            'assessment_type' => $profile->hasFullHcAssessment ? 'full_hc' : ($profile->hasCaAssessment ? 'ca_only' : 'referral'),
            'confidence_level' => $profile->confidenceLevel,
            'episode_type' => $profile->episodeType,
            'rug_category' => $profile->rugCategory,
            'needs_cluster' => $profile->needsCluster,
            'adl_support_level' => $profile->adlSupportLevel,
            'iadl_support_level' => $profile->iadlSupportLevel,
            'cognitive_complexity' => $profile->cognitiveComplexity,
            'behavioural_complexity' => $profile->behaviouralComplexity,
            'health_instability' => $profile->healthInstability,
            'falls_risk_level' => $profile->fallsRiskLevel,
            'has_rehab_potential' => $profile->hasRehabPotential,
            'rehab_potential_score' => $profile->rehabPotentialScore,
            'lives_alone' => $profile->livesAlone,
            'caregiver_stress_level' => $profile->caregiverStressLevel,
            'technology_readiness' => $profile->technologyReadiness,
        ];
    }

    /**
     * Build algorithm scores context.
     */
    private function buildAlgorithmScoresContext(PatientNeedsProfile $profile): array
    {
        return [
            'self_reliance_index' => $profile->selfRelianceIndex ? 'self_reliant' : 'requires_assistance',
            'personal_support_algorithm' => [
                'score' => $profile->personalSupportScore,
                'max' => 6,
                'interpretation' => $this->interpretPSA($profile->personalSupportScore),
            ],
            'rehabilitation_algorithm' => [
                'score' => $profile->rehabilitationScore,
                'max' => 5,
                'interpretation' => $this->interpretRehab($profile->rehabilitationScore),
            ],
            'chess_ca' => [
                'score' => $profile->chessCAScore,
                'max' => 5,
                'interpretation' => $this->interpretCHESS($profile->chessCAScore),
            ],
            'distressed_mood_scale' => [
                'score' => $profile->distressedMoodScore,
                'max' => 9,
                'interpretation' => $this->interpretDMS($profile->distressedMoodScore),
            ],
            'pain_scale' => [
                'score' => $profile->painScore,
                'max' => 4,
                'interpretation' => $this->interpretPain($profile->painScore),
            ],
            'service_urgency' => [
                'score' => $profile->serviceUrgencyScore,
                'max' => 4,
                'interpretation' => $this->interpretServiceUrgency($profile->serviceUrgencyScore),
            ],
        ];
    }

    /**
     * Build triggered CAPs context.
     */
    private function buildTriggeredCapsContext(PatientNeedsProfile $profile): array
    {
        $caps = $profile->triggeredCAPs ?? [];
        
        $formatted = [];
        foreach ($caps as $capName => $capResult) {
            if ($capResult['level'] !== 'NOT_TRIGGERED') {
                $formatted[] = [
                    'cap_name' => $capName,
                    'trigger_level' => $capResult['level'],
                    'description' => $capResult['description'] ?? null,
                ];
            }
        }

        return [
            'total_triggered' => count($formatted),
            'caps' => $formatted,
        ];
    }

    /**
     * Build scenario context.
     */
    private function buildScenarioContext(ScenarioBundleDTO $scenario): array
    {
        return [
            'scenario_title' => $scenario->title,
            'primary_axis' => $scenario->primaryAxis->value,
            'primary_axis_label' => $scenario->primaryAxis->getLabel(),
            'description' => $scenario->description,
            'key_benefits' => $scenario->keyBenefits,
            'trade_offs' => $scenario->tradeOffs,
            'risks_addressed' => $scenario->risksAddressed,
            'patient_goals_supported' => $scenario->patientGoalsSupported,
            'confidence_level' => $scenario->confidenceLevel,
            'is_recommended' => $scenario->isRecommended,
        ];
    }

    /**
     * Build services context.
     */
    private function buildServicesContext(ScenarioBundleDTO $scenario): array
    {
        $services = [];
        foreach ($scenario->serviceLines as $line) {
            $services[] = [
                'service_name' => $line->serviceName,
                'service_category' => $line->serviceCategory,
                'frequency' => $line->frequencyCount . 'x/' . $line->frequencyPeriod,
                'duration_minutes' => $line->durationMinutes,
                'priority_level' => $line->priorityLevel,
                'clinical_rationale' => $line->clinicalRationale,
                'is_safety_critical' => $line->isSafetyCritical,
            ];
        }

        return [
            'total_services' => count($services),
            'total_weekly_hours' => $scenario->totalWeeklyHours,
            'total_weekly_visits' => $scenario->totalWeeklyVisits,
            'in_person_percentage' => $scenario->inPersonPercentage,
            'virtual_percentage' => $scenario->virtualPercentage,
            'services' => $services,
        ];
    }

    /**
     * Build cost context (reference only, not constraint).
     */
    private function buildCostContext(ScenarioBundleDTO $scenario): array
    {
        return [
            'weekly_estimated_cost' => $scenario->weeklyEstimatedCost,
            'reference_cap' => $scenario->referenceCap,
            'cost_status' => $scenario->costStatus,
            'cap_utilization_percent' => $scenario->capUtilization,
            'cost_note' => $scenario->costNote,
        ];
    }

    /**
     * Build alternatives context for comparison.
     */
    private function buildAlternativesContext(array $scenarios): array
    {
        $alternatives = [];
        foreach ($scenarios as $scenario) {
            $alternatives[] = [
                'title' => $scenario->title,
                'primary_axis' => $scenario->primaryAxis->value,
                'weekly_cost' => $scenario->weeklyEstimatedCost,
                'total_hours' => $scenario->totalWeeklyHours,
                'service_count' => count($scenario->serviceLines),
            ];
        }
        return $alternatives;
    }

    /**
     * Generate a non-reversible patient reference.
     */
    public function generatePatientRef(int $patientId): string
    {
        $salt = config('app.key', 'connected-capacity');
        $hash = substr(hash('sha256', 'patient_' . $patientId . $salt), 0, 4);
        return "P-{$hash}";
    }

    /**
     * Interpret Personal Support Algorithm score.
     */
    private function interpretPSA(int $score): string
    {
        return match (true) {
            $score >= 5 => 'high_support_need',
            $score >= 3 => 'moderate_support_need',
            default => 'light_or_no_support_need',
        };
    }

    /**
     * Interpret Rehabilitation Algorithm score.
     */
    private function interpretRehab(int $score): string
    {
        return match (true) {
            $score >= 4 => 'high_rehab_potential',
            $score >= 3 => 'moderate_rehab_potential',
            default => 'limited_rehab_potential',
        };
    }

    /**
     * Interpret CHESS-CA score.
     */
    private function interpretCHESS(int $score): string
    {
        return match (true) {
            $score >= 4 => 'high_health_instability',
            $score >= 2 => 'moderate_health_instability',
            default => 'stable',
        };
    }

    /**
     * Interpret Distressed Mood Scale score.
     */
    private function interpretDMS(int $score): string
    {
        return match (true) {
            $score >= 5 => 'significant_mood_disturbance',
            $score >= 3 => 'mild_mood_indicators',
            default => 'no_mood_concerns',
        };
    }

    /**
     * Interpret Pain Scale score.
     */
    private function interpretPain(int $score): string
    {
        return match (true) {
            $score >= 3 => 'significant_pain',
            $score >= 2 => 'moderate_pain',
            $score >= 1 => 'mild_pain',
            default => 'no_pain',
        };
    }

    /**
     * Interpret Service Urgency score.
     */
    private function interpretServiceUrgency(int $score): string
    {
        return match (true) {
            $score >= 4 => 'emergency_same_day',
            $score >= 3 => 'urgent_within_72h',
            $score >= 2 => 'priority_within_week',
            default => 'routine',
        };
    }

    /**
     * Validate that the payload contains no forbidden PHI/PII fields.
     *
     * @throws RuntimeException If PHI/PII is detected
     */
    public function validateNoPhiPii(array $payload): bool
    {
        $json = json_encode($payload);
        $lowerJson = strtolower($json);

        foreach (self::FORBIDDEN_FIELD_PATTERNS as $field) {
            if (str_contains($lowerJson, '"' . $field . '"')) {
                throw new RuntimeException(
                    "PHI/PII field '{$field}' detected in bundle explanation prompt. " .
                    "This is a safety violation."
                );
            }
        }

        // Additional check: ensure no email patterns
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $json)) {
            throw new RuntimeException(
                "Email address pattern detected in bundle explanation prompt."
            );
        }

        // Additional check: ensure no OHIP patterns (10 digits)
        if (preg_match('/\b\d{10}\b/', $json)) {
            throw new RuntimeException(
                "Potential OHIP number detected in bundle explanation prompt."
            );
        }

        return true;
    }
}

