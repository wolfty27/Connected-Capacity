<?php

namespace App\Services\Scheduling\DTOs;

use Carbon\Carbon;

/**
 * AssignmentSuggestionDTO
 *
 * Data transfer object representing a staff assignment suggestion.
 * Contains all the de-identified metadata needed for:
 * - Displaying suggestions in the UI
 * - Building LLM prompts (no PHI/PII)
 * - Audit logging
 *
 * IMPORTANT: This DTO intentionally excludes:
 * - Patient names, addresses, OHIP numbers
 * - Staff names, emails, phone numbers
 * - Exact coordinates (only travel_minutes derived from them)
 */
class AssignmentSuggestionDTO
{
    public function __construct(
        // === Core Identifiers ===
        public readonly int $patientId,
        public readonly int $serviceTypeId,
        public readonly ?int $suggestedStaffId,
        public readonly int $organizationId,

        // === Service Context (from ServiceType metadata) ===
        public readonly string $serviceTypeCode,
        public readonly string $serviceTypeName,
        public readonly int $durationMinutes,
        public readonly ?string $preferredProvider = null,
        public readonly ?string $deliveryMode = null,
        public readonly ?array $requiredSkillNames = null,

        // === Patient Context (de-identified) ===
        public readonly ?string $patientRegionCode = null,
        public readonly ?string $patientRegionName = null,
        public readonly ?string $patientAcuityLevel = null,
        public readonly ?int $patientMapleScore = null,
        public readonly ?array $patientRiskFlags = null,
        public readonly ?int $daysSinceActivation = null,
        public readonly ?int $previousStaffCount = null,

        // === Staff Context (de-identified, only for suggested staff) ===
        public readonly ?string $staffRoleCode = null,
        public readonly ?string $staffRoleName = null,
        public readonly ?string $staffEmploymentTypeCode = null,
        public readonly ?string $staffEmploymentTypeName = null,
        public readonly ?string $staffRegionCode = null,
        public readonly ?string $staffRegionName = null,
        public readonly ?float $staffRemainingHours = null,
        public readonly ?float $staffUtilizationPercent = null,
        public readonly ?int $staffTenureMonths = null,
        public readonly ?bool $staffHasRequiredSkills = null,
        public readonly ?float $staffReliabilityScore = null,
        public readonly ?int $staffPatientCount = null,

        // === Match Scoring ===
        public readonly float $confidenceScore = 0.0,
        public readonly string $matchStatus = 'none', // 'strong', 'moderate', 'weak', 'none'
        public readonly ?array $scoringBreakdown = null,
        public readonly ?bool $isPrimaryRole = null,

        // === Travel & Continuity ===
        public readonly ?int $estimatedTravelMinutes = null,
        public readonly ?int $continuityVisitCount = null,

        // === Alternatives Summary ===
        public readonly ?int $candidatesEvaluated = null,
        public readonly ?int $candidatesPassed = null,
        public readonly ?array $exclusionReasons = null,

        // === Time Context ===
        public readonly ?Carbon $weekStart = null,
        public readonly ?Carbon $targetSlot = null,
        public readonly ?string $organizationType = 'spo',
    ) {}

    /**
     * Check if there is a suggested staff member.
     */
    public function hasSuggestion(): bool
    {
        return $this->suggestedStaffId !== null && $this->matchStatus !== 'none';
    }

    /**
     * Check if this is a strong match (confidence >= 80).
     */
    public function isStrongMatch(): bool
    {
        return $this->matchStatus === 'strong' || $this->confidenceScore >= 80;
    }

    /**
     * Get a continuity note for display.
     */
    public function getContinuityNote(): ?string
    {
        if (!$this->continuityVisitCount || $this->continuityVisitCount < 1) {
            return null;
        }

        if ($this->continuityVisitCount === 1) {
            return 'Has served this patient once before';
        }

        return "Has served this patient {$this->continuityVisitCount} times";
    }

    /**
     * Convert to array for API responses.
     * Note: This includes IDs which the controller will use to look up display names.
     */
    public function toArray(): array
    {
        return [
            'patient_id' => $this->patientId,
            'service_type_id' => $this->serviceTypeId,
            'service_type_code' => $this->serviceTypeCode,
            'service_type_name' => $this->serviceTypeName,
            'duration_minutes' => $this->durationMinutes,
            'suggested_staff_id' => $this->suggestedStaffId,
            'suggested_staff_role' => $this->staffRoleCode,
            'confidence_score' => round($this->confidenceScore, 1),
            'match_status' => $this->matchStatus,
            'estimated_travel_minutes' => $this->estimatedTravelMinutes,
            'continuity_note' => $this->getContinuityNote(),
            'scoring_breakdown' => $this->scoringBreakdown,
            'exclusion_reasons' => $this->exclusionReasons,
            'candidates_evaluated' => $this->candidatesEvaluated,
            'candidates_passed' => $this->candidatesPassed,
        ];
    }

    /**
     * Create a "no match" suggestion.
     */
    public static function noMatch(
        int $patientId,
        int $serviceTypeId,
        string $serviceTypeCode,
        string $serviceTypeName,
        int $durationMinutes,
        int $organizationId,
        array $exclusionReasons = [],
        ?int $candidatesEvaluated = null,
        ?Carbon $weekStart = null
    ): self {
        return new self(
            patientId: $patientId,
            serviceTypeId: $serviceTypeId,
            suggestedStaffId: null,
            organizationId: $organizationId,
            serviceTypeCode: $serviceTypeCode,
            serviceTypeName: $serviceTypeName,
            durationMinutes: $durationMinutes,
            matchStatus: 'none',
            confidenceScore: 0.0,
            exclusionReasons: $exclusionReasons,
            candidatesEvaluated: $candidatesEvaluated,
            candidatesPassed: 0,
            weekStart: $weekStart,
        );
    }
}
