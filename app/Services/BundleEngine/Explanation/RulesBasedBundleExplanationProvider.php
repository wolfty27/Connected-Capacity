<?php

namespace App\Services\BundleEngine\Explanation;

use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;
use App\Services\BundleEngine\Enums\ScenarioAxis;
use App\Services\Llm\DTOs\ExplanationResponseDTO;
use Carbon\Carbon;

/**
 * RulesBasedBundleExplanationProvider
 *
 * Generates deterministic bundle explanations when Vertex AI is unavailable.
 * Uses algorithm scores, CAP triggers, and scenario axis to construct explanations.
 *
 * This provider ensures explanations are always available, even without AI.
 */
class RulesBasedBundleExplanationProvider
{
    /**
     * Generate explanation for a bundle scenario.
     *
     * @param PatientNeedsProfile $profile
     * @param ScenarioBundleDTO $scenario
     * @return ExplanationResponseDTO
     */
    public function generateExplanation(
        PatientNeedsProfile $profile,
        ScenarioBundleDTO $scenario
    ): ExplanationResponseDTO {
        $startTime = microtime(true);

        // Build explanation components
        $shortExplanation = $this->buildShortExplanation($profile, $scenario);
        $detailedPoints = $this->buildDetailedPoints($profile, $scenario);
        $confidenceLabel = $this->determineConfidenceLabel($profile, $scenario);

        $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        return new ExplanationResponseDTO(
            shortExplanation: $shortExplanation,
            detailedPoints: $detailedPoints,
            confidenceLabel: $confidenceLabel,
            source: 'rules_based',
            generatedAt: Carbon::now(),
            responseTimeMs: $responseTimeMs,
        );
    }

    /**
     * Build the short explanation (2-3 sentences).
     */
    private function buildShortExplanation(PatientNeedsProfile $profile, ScenarioBundleDTO $scenario): string
    {
        $axis = $scenario->primaryAxis;
        $parts = [];

        // Opening based on axis
        $parts[] = match ($axis) {
            ScenarioAxis::RECOVERY_REHAB => "This bundle prioritizes rehabilitation and functional recovery.",
            ScenarioAxis::SAFETY_STABILITY => "This bundle focuses on maintaining safety and preventing decline.",
            ScenarioAxis::TECH_ENABLED => "This bundle leverages remote monitoring to provide continuous oversight.",
            ScenarioAxis::CAREGIVER_RELIEF => "This bundle is designed to support caregivers and prevent burnout.",
            ScenarioAxis::COMMUNITY_INTEGRATED => "This bundle integrates community resources for holistic care.",
            ScenarioAxis::BALANCED => "This bundle provides balanced coverage across all care domains.",
            default => "This bundle addresses the patient's identified care needs.",
        };

        // Add clinical justification
        $clinicalNote = $this->getClinicalJustification($profile, $axis);
        if ($clinicalNote) {
            $parts[] = $clinicalNote;
        }

        // Add CAP note if any triggered
        $capNote = $this->getCAPNote($profile);
        if ($capNote) {
            $parts[] = $capNote;
        }

        return implode(' ', $parts);
    }

    /**
     * Get clinical justification based on algorithm scores.
     */
    private function getClinicalJustification(PatientNeedsProfile $profile, ScenarioAxis $axis): ?string
    {
        return match ($axis) {
            ScenarioAxis::RECOVERY_REHAB => $profile->rehabilitationScore >= 3
                ? "Rehabilitation Algorithm score ({$profile->rehabilitationScore}/5) indicates strong potential for functional improvement."
                : null,
            
            ScenarioAxis::SAFETY_STABILITY => $profile->chessCAScore >= 2 || $profile->fallsRiskLevel >= 2
                ? "Clinical indicators suggest elevated risk requiring daily monitoring and stability support."
                : null,
            
            ScenarioAxis::CAREGIVER_RELIEF => $profile->caregiverStressLevel >= 2
                ? "Caregiver stress assessment indicates respite support would benefit care sustainability."
                : null,
            
            ScenarioAxis::TECH_ENABLED => $profile->technologyReadiness >= 2
                ? "Patient profile indicates suitability for remote monitoring technologies."
                : null,
            
            default => $profile->personalSupportScore >= 3
                ? "Personal Support Algorithm ({$profile->personalSupportScore}/6) guides service intensity."
                : null,
        };
    }

    /**
     * Get CAP-related note if any are triggered.
     */
    private function getCAPNote(PatientNeedsProfile $profile): ?string
    {
        $caps = $profile->triggeredCAPs ?? [];
        $improveCaps = [];

        foreach ($caps as $capName => $capResult) {
            if ($capResult['level'] === 'IMPROVE') {
                $improveCaps[] = ucfirst(str_replace('_', ' ', $capName));
            }
        }

        if (empty($improveCaps)) {
            return null;
        }

        if (count($improveCaps) === 1) {
            return "The {$improveCaps[0]} CAP indicates active intervention is recommended.";
        }

        $last = array_pop($improveCaps);
        return "Multiple CAPs (" . implode(', ', $improveCaps) . " and {$last}) indicate areas for active intervention.";
    }

    /**
     * Build detailed explanation points.
     */
    private function buildDetailedPoints(PatientNeedsProfile $profile, ScenarioBundleDTO $scenario): array
    {
        $points = [];

        // Add axis-specific point
        $points[] = $this->getAxisPoint($scenario->primaryAxis);

        // Add algorithm-driven points
        if ($profile->rehabilitationScore >= 3) {
            $points[] = "Rehabilitation potential supports intensive therapy services (Rehab score: {$profile->rehabilitationScore}/5)";
        }

        if ($profile->personalSupportScore >= 3) {
            $points[] = "Personal support needs guide PSW service intensity (PSA score: {$profile->personalSupportScore}/6)";
        }

        if ($profile->chessCAScore >= 2) {
            $points[] = "Health instability indicators warrant nursing oversight (CHESS: {$profile->chessCAScore}/5)";
        }

        if ($profile->painScore >= 3) {
            $points[] = "Pain management is prioritized in nursing care plan (Pain: {$profile->painScore}/4)";
        }

        if ($profile->distressedMoodScore >= 3) {
            $points[] = "Mood support services included based on DMS assessment ({$profile->distressedMoodScore}/9)";
        }

        // Add CAP-driven points
        $caps = $profile->triggeredCAPs ?? [];
        foreach ($caps as $capName => $capResult) {
            if ($capResult['level'] === 'IMPROVE' || $capResult['level'] === 'FACILITATE') {
                $points[] = ucfirst(str_replace('_', ' ', $capName)) . " CAP triggered - " . 
                    ($capResult['description'] ?? 'clinical intervention recommended');
            }
        }

        // Add risk mitigation point if applicable
        $risks = $scenario->risksAddressed ?? [];
        if (!empty($risks)) {
            $points[] = "Bundle addresses identified risks: " . implode(', ', array_slice($risks, 0, 3));
        }

        // Limit to 5 most relevant points
        return array_slice($points, 0, 5);
    }

    /**
     * Get point based on scenario axis.
     */
    private function getAxisPoint(ScenarioAxis $axis): string
    {
        return match ($axis) {
            ScenarioAxis::RECOVERY_REHAB => "Emphasizes PT/OT therapy to maximize functional recovery",
            ScenarioAxis::SAFETY_STABILITY => "Prioritizes consistent monitoring and fall prevention",
            ScenarioAxis::TECH_ENABLED => "Utilizes RPM and telehealth for efficient continuous care",
            ScenarioAxis::CAREGIVER_RELIEF => "Includes respite and support services for family caregivers",
            ScenarioAxis::COMMUNITY_INTEGRATED => "Connects patient to community resources and day programs",
            ScenarioAxis::BALANCED => "Provides comprehensive coverage balancing all care domains",
            default => "Tailored to patient's specific clinical profile",
        };
    }

    /**
     * Determine confidence label based on data quality.
     */
    private function determineConfidenceLabel(PatientNeedsProfile $profile, ScenarioBundleDTO $scenario): string
    {
        // High confidence if we have HC assessment and matched RUG
        if ($profile->hasFullHcAssessment && $profile->rugGroup) {
            return "High Confidence - Full HC Assessment";
        }

        // Medium confidence if we have CA or RUG
        if ($profile->hasCaAssessment || $profile->rugCategory) {
            return "Good Confidence - Standardized Assessment";
        }

        // Lower confidence for referral-only
        if ($profile->confidenceLevel === 'low') {
            return "Preliminary - Limited Assessment Data";
        }

        return "Standard Confidence";
    }
}

