<?php

namespace App\Services\Llm;

use App\Services\Scheduling\DTOs\AssignmentSuggestionDTO;
use RuntimeException;

/**
 * PromptBuilder
 *
 * Builds PII-safe prompts for Vertex AI Gemini from AssignmentSuggestionDTOs.
 *
 * CRITICAL: This class enforces strict PII/PHI masking rules:
 * - NO patient names, addresses, OHIP numbers, DOB
 * - NO staff names, emails, phone numbers
 * - NO exact coordinates (only derived travel_minutes)
 * - Uses hashed references (P-xxxx, S-xxxx) instead of IDs
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class PromptBuilder
{
    /**
     * Fields that MUST NEVER appear in prompts (PHI/PII).
     * Used by validateNoPhiPii() as a safety check.
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
        'postal_code',  // Full postal code - FSA prefix handled via region
        'lat',
        'lng',
        'latitude',
        'longitude',
        'coordinates',
    ];

    /**
     * Build the complete prompt payload for Vertex AI.
     *
     * This method ONLY uses de-identified, metadata-driven information.
     * NO PHI/PII is ever included.
     *
     * @param AssignmentSuggestionDTO $suggestion The suggestion to explain
     * @return array The prompt payload structure
     */
    public function buildPromptPayload(AssignmentSuggestionDTO $suggestion): array
    {
        $payload = [
            'system_instruction' => $this->getSystemInstruction(),
            'context' => $this->buildContext($suggestion),
            'service_request' => $this->buildServiceRequest($suggestion),
            'patient_context' => $this->buildPatientContext($suggestion),
            'suggested_staff' => $this->buildStaffContext($suggestion),
            'scoring_summary' => $this->buildScoringSummary($suggestion),
            'alternatives_summary' => $this->buildAlternativesSummary($suggestion),
        ];

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
You are an AI scheduling assistant for Ontario Health at Home care services.
Your role is to explain why a staff member was recommended for a care assignment.

Guidelines:
- Be concise (1-2 sentences for short explanation)
- Use professional healthcare terminology
- Focus on operational factors: continuity, travel, capacity, skills
- Never invent information not provided in the context
- If continuity is strong (>3 visits), mention it prominently
- If travel is efficient (<20 min), mention it as a benefit
- If capacity is tight, acknowledge it appropriately

Output Format:
Return a JSON object with exactly these fields:
{
  "short_explanation": "1-2 sentence summary of why this staff is recommended",
  "detailed_points": ["Key factor 1", "Key factor 2", "Key factor 3"],
  "confidence_label": "High Match" | "Good Match" | "Acceptable" | "Limited Options"
}
INSTRUCTION;
    }

    /**
     * Build general context section.
     */
    private function buildContext(AssignmentSuggestionDTO $suggestion): array
    {
        return [
            'organization_type' => $suggestion->organizationType ?? 'spo',
            'week_of' => $suggestion->weekStart?->format('Y-m-d') ?? now()->startOfWeek()->format('Y-m-d'),
            'time_slot' => $suggestion->targetSlot?->format('l H:i'),
        ];
    }

    /**
     * Build service request section from ServiceType metadata.
     */
    private function buildServiceRequest(AssignmentSuggestionDTO $suggestion): array
    {
        return [
            'service_type_code' => $suggestion->serviceTypeCode,
            'service_type_name' => $suggestion->serviceTypeName,
            'duration_minutes' => $suggestion->durationMinutes,
            'preferred_provider' => $suggestion->preferredProvider,
            'delivery_mode' => $suggestion->deliveryMode,
            'required_skills' => $suggestion->requiredSkillNames ?? [],
        ];
    }

    /**
     * Build patient context section (de-identified).
     *
     * IMPORTANT: Uses hashed patient_ref, not actual patient_id or name.
     */
    private function buildPatientContext(AssignmentSuggestionDTO $suggestion): array
    {
        return [
            'patient_ref' => $this->generatePatientRef($suggestion->patientId),
            'region_code' => $suggestion->patientRegionCode,
            'region_name' => $suggestion->patientRegionName,
            'acuity_level' => $suggestion->patientAcuityLevel,
            'maple_score' => $suggestion->patientMapleScore,
            'risk_flags' => $suggestion->patientRiskFlags ?? [],
            'days_since_activation' => $suggestion->daysSinceActivation,
            'continuity_preference' => $this->getContinuityPreference($suggestion),
            'previous_care_providers_count' => $suggestion->previousStaffCount ?? 0,
        ];
    }

    /**
     * Build staff context section (de-identified).
     *
     * IMPORTANT: Uses hashed staff_ref, not actual user_id or name.
     */
    private function buildStaffContext(AssignmentSuggestionDTO $suggestion): array
    {
        if (!$suggestion->suggestedStaffId) {
            return ['staff_ref' => null, 'match_available' => false];
        }

        return [
            'staff_ref' => $this->generateStaffRef($suggestion->suggestedStaffId),
            'role_code' => $suggestion->staffRoleCode,
            'role_name' => $suggestion->staffRoleName,
            'employment_type_code' => $suggestion->staffEmploymentTypeCode,
            'employment_type_name' => $suggestion->staffEmploymentTypeName,
            'region_code' => $suggestion->staffRegionCode,
            'region_name' => $suggestion->staffRegionName,
            'hours_available_this_week' => $suggestion->staffRemainingHours !== null
                ? round($suggestion->staffRemainingHours, 1)
                : null,
            'current_utilization_percent' => $suggestion->staffUtilizationPercent !== null
                ? round($suggestion->staffUtilizationPercent)
                : null,
            'estimated_travel_minutes' => $suggestion->estimatedTravelMinutes,
            'previous_visits_to_this_patient' => $suggestion->continuityVisitCount ?? 0,
            'total_patients_served_this_week' => $suggestion->staffPatientCount ?? 0,
            'reliability_score_percent' => $suggestion->staffReliabilityScore !== null
                ? round($suggestion->staffReliabilityScore * 100)
                : null,
            'tenure_months' => $suggestion->staffTenureMonths,
            'has_required_skills' => $suggestion->staffHasRequiredSkills ?? true,
        ];
    }

    /**
     * Build scoring summary section.
     */
    private function buildScoringSummary(AssignmentSuggestionDTO $suggestion): array
    {
        return [
            'total_score' => round($suggestion->confidenceScore, 1),
            'match_quality' => $suggestion->matchStatus,
            'breakdown' => $suggestion->scoringBreakdown ?? [],
        ];
    }

    /**
     * Build alternatives summary section.
     */
    private function buildAlternativesSummary(AssignmentSuggestionDTO $suggestion): array
    {
        return [
            'total_candidates_evaluated' => $suggestion->candidatesEvaluated ?? 0,
            'passed_hard_constraints' => $suggestion->candidatesPassed ?? 0,
            'exclusion_reasons' => $suggestion->exclusionReasons ?? [],
        ];
    }

    /**
     * Generate a non-reversible patient reference.
     *
     * Format: P-{hash} where hash is derived from patient_id + app key
     * This prevents exposure of real patient IDs while maintaining
     * consistency within a session.
     */
    public function generatePatientRef(int $patientId): string
    {
        $salt = config('app.key', 'connected-capacity');
        $hash = substr(hash('sha256', 'patient_' . $patientId . $salt), 0, 4);
        return "P-{$hash}";
    }

    /**
     * Generate a non-reversible staff reference.
     *
     * Format: S-{hash} where hash is derived from user_id + app key
     */
    public function generateStaffRef(int $staffId): string
    {
        $salt = config('app.key', 'connected-capacity');
        $hash = substr(hash('sha256', 'staff_' . $staffId . $salt), 0, 4);
        return "S-{$hash}";
    }

    /**
     * Determine continuity preference label from visit count.
     */
    private function getContinuityPreference(AssignmentSuggestionDTO $suggestion): string
    {
        $visits = $suggestion->continuityVisitCount ?? 0;

        if ($visits >= 5) {
            return 'strong';
        }
        if ($visits >= 2) {
            return 'moderate';
        }
        return 'new_relationship';
    }

    /**
     * Validate that the payload contains no forbidden PHI/PII fields.
     *
     * This is a safety check called before sending to Vertex AI.
     *
     * @throws RuntimeException If PHI/PII is detected
     */
    public function validateNoPhiPii(array $payload): bool
    {
        $json = json_encode($payload);
        $lowerJson = strtolower($json);

        foreach (self::FORBIDDEN_FIELD_PATTERNS as $field) {
            // Check for field names as JSON keys
            if (str_contains($lowerJson, '"' . $field . '"')) {
                throw new RuntimeException(
                    "PHI/PII field '{$field}' detected in prompt payload. " .
                    "This is a safety violation. Review PromptBuilder to ensure " .
                    "no protected information is included."
                );
            }
        }

        // Additional check: ensure no email patterns
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $json)) {
            throw new RuntimeException(
                "Email address pattern detected in prompt payload. " .
                "This is a safety violation."
            );
        }

        // Additional check: ensure no OHIP patterns (10 digits)
        if (preg_match('/\b\d{10}\b/', $json)) {
            throw new RuntimeException(
                "Potential OHIP number (10-digit sequence) detected in prompt payload. " .
                "This is a safety violation."
            );
        }

        return true;
    }

    /**
     * Build a prompt for explaining why no match was found.
     */
    public function buildNoMatchPromptPayload(
        int $patientId,
        int $serviceTypeId,
        string $serviceTypeName,
        array $exclusionReasons,
        int $candidatesEvaluated
    ): array {
        return [
            'system_instruction' => $this->getNoMatchSystemInstruction(),
            'context' => [
                'scenario' => 'no_staff_match',
                'patient_ref' => $this->generatePatientRef($patientId),
                'service_type_name' => $serviceTypeName,
            ],
            'constraints_summary' => [
                'candidates_evaluated' => $candidatesEvaluated,
                'all_excluded' => true,
                'exclusion_reasons' => $exclusionReasons,
            ],
        ];
    }

    /**
     * Get system instruction for no-match explanations.
     */
    private function getNoMatchSystemInstruction(): string
    {
        return <<<'INSTRUCTION'
You are an AI scheduling assistant for Ontario Health at Home care services.
Explain why no staff member could be matched for this care assignment.

Guidelines:
- Be concise and helpful
- Summarize the main constraints that prevented matching
- Suggest what might resolve the situation (e.g., "Consider adjusting time slot" or "May require SSPO assignment")

Output Format:
Return a JSON object with exactly these fields:
{
  "short_explanation": "1-2 sentence summary of why no match was found",
  "detailed_points": ["Constraint 1", "Constraint 2", "Suggestion"],
  "confidence_label": "No Match"
}
INSTRUCTION;
    }
}
