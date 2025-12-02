<?php

namespace App\Services\Llm\Fallback;

use App\Services\Llm\Contracts\ExplanationProviderInterface;
use App\Services\Llm\DTOs\ExplanationResponseDTO;
use App\Services\Scheduling\DTOs\AssignmentSuggestionDTO;
use Carbon\Carbon;

/**
 * RulesBasedExplanationProvider
 *
 * Fallback explanation provider that generates explanations using
 * deterministic rules instead of LLM calls.
 *
 * This is used when:
 * - Vertex AI is disabled
 * - Vertex AI is unavailable (timeout, rate limit, error)
 * - For testing without LLM dependencies
 *
 * The rules-based approach ensures explanations are always available,
 * even if they are less nuanced than LLM-generated ones.
 */
class RulesBasedExplanationProvider implements ExplanationProviderInterface
{
    /**
     * @inheritDoc
     */
    public function generateExplanation(AssignmentSuggestionDTO $suggestion): ExplanationResponseDTO
    {
        $startTime = microtime(true);

        $points = [];
        $shortParts = [];

        // === Continuity Factor (highest priority) ===
        $visits = $suggestion->continuityVisitCount ?? 0;
        if ($visits >= 5) {
            $points[] = "Strong continuity of care ({$visits} previous visits with this patient)";
            $shortParts[] = 'established care relationship';
        } elseif ($visits >= 2) {
            $points[] = "Some care history ({$visits} previous visits)";
            $shortParts[] = 'prior care history';
        }

        // === Travel Efficiency ===
        $travel = $suggestion->estimatedTravelMinutes;
        if ($travel !== null) {
            if ($travel <= 15) {
                $points[] = "Efficient travel time ({$travel} minutes from prior appointment)";
                $shortParts[] = 'minimal travel';
            } elseif ($travel <= 25) {
                $points[] = "Reasonable travel time ({$travel} minutes)";
            } elseif ($travel > 30) {
                $points[] = "Higher travel time of {$travel} minutes (consider alternatives if available)";
            }
        }

        // === Capacity Fit ===
        $remaining = $suggestion->staffRemainingHours;
        $utilization = $suggestion->staffUtilizationPercent;
        if ($remaining !== null) {
            if ($remaining >= 8) {
                $points[] = "Good capacity availability ({$remaining}h remaining this week)";
                $shortParts[] = 'good availability';
            } elseif ($remaining >= 4) {
                $points[] = "Moderate capacity ({$remaining}h remaining this week)";
            } else {
                $points[] = "Limited remaining capacity ({$remaining}h this week)";
            }
        }

        // === Region Match ===
        if ($suggestion->staffRegionCode && $suggestion->patientRegionCode) {
            if ($suggestion->staffRegionCode === $suggestion->patientRegionCode) {
                $points[] = "Serves the same region ({$suggestion->staffRegionName})";
                if (empty($shortParts)) {
                    $shortParts[] = 'local to patient';
                }
            }
        }

        // === Role Fit ===
        if ($suggestion->isPrimaryRole === true) {
            $points[] = "Primary role for this service type";
        }

        // === Skills Match ===
        if ($suggestion->staffHasRequiredSkills === true) {
            $points[] = "Has all required skills for this service";
        } elseif ($suggestion->staffHasRequiredSkills === false) {
            $points[] = "Missing some preferred skills (may require supervision)";
        }

        // === Reliability ===
        $reliability = $suggestion->staffReliabilityScore;
        if ($reliability !== null && $reliability >= 0.95) {
            $points[] = "High reliability record (" . round($reliability * 100) . "% completion rate)";
        }

        // === Build Short Explanation ===
        $shortExplanation = $this->buildShortExplanation($suggestion, $shortParts);

        // === Determine Confidence Label ===
        $confidenceLabel = $this->determineConfidenceLabel($suggestion);

        $responseTime = (int) round((microtime(true) - $startTime) * 1000);

        return new ExplanationResponseDTO(
            shortExplanation: $shortExplanation,
            detailedPoints: array_slice($points, 0, 4), // Max 4 points
            confidenceLabel: $confidenceLabel,
            source: 'rules_based',
            generatedAt: Carbon::now(),
            responseTimeMs: $responseTime,
        );
    }

    /**
     * @inheritDoc
     */
    public function generateNoMatchExplanation(array $exclusionReasons): ExplanationResponseDTO
    {
        $startTime = microtime(true);

        $points = [];

        // Process exclusion reasons
        foreach ($exclusionReasons as $reason) {
            if (is_string($reason)) {
                $points[] = $reason;
            }
        }

        if (empty($points)) {
            $points[] = 'No staff members passed all required constraints for this time slot';
        }

        // Generate suggestions based on common exclusion patterns
        $suggestions = $this->generateSuggestionsFromExclusions($exclusionReasons);
        $points = array_merge($points, $suggestions);

        // Build short explanation
        $primaryReason = $points[0] ?? 'No available staff match all requirements';
        $shortExplanation = "No available staff match. {$primaryReason}";

        $responseTime = (int) round((microtime(true) - $startTime) * 1000);

        return new ExplanationResponseDTO(
            shortExplanation: $shortExplanation,
            detailedPoints: array_slice($points, 0, 5),
            confidenceLabel: 'No Match',
            source: 'rules_based',
            generatedAt: Carbon::now(),
            responseTimeMs: $responseTime,
        );
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        // Rules-based provider is always available
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getProviderName(): string
    {
        return 'rules_based';
    }

    /**
     * Build a short explanation from the suggestion and identified factors.
     */
    private function buildShortExplanation(AssignmentSuggestionDTO $suggestion, array $parts): string
    {
        $roleName = $suggestion->staffRoleName ?? 'Staff member';

        if (empty($parts)) {
            return "{$roleName} is the best available match based on role eligibility and schedule availability.";
        }

        $factorList = implode(' and ', array_slice($parts, 0, 2));
        return "{$roleName} recommended due to {$factorList}.";
    }

    /**
     * Determine the confidence label based on the suggestion's score and factors.
     */
    private function determineConfidenceLabel(AssignmentSuggestionDTO $suggestion): string
    {
        $score = $suggestion->confidenceScore;

        if ($score >= 80) {
            return 'High Match';
        }
        if ($score >= 60) {
            return 'Good Match';
        }
        if ($score >= 40) {
            return 'Acceptable';
        }
        if ($score > 0) {
            return 'Limited Options';
        }
        return 'No Match';
    }

    /**
     * Generate actionable suggestions based on exclusion patterns.
     */
    private function generateSuggestionsFromExclusions(array $exclusionReasons): array
    {
        $suggestions = [];
        $reasonsLower = array_map('strtolower', array_filter($exclusionReasons, 'is_string'));
        $reasonsText = implode(' ', $reasonsLower);

        // Pattern matching for common exclusion types
        if (str_contains($reasonsText, 'unavailab') || str_contains($reasonsText, 'time-off')) {
            $suggestions[] = 'Consider adjusting the scheduled time slot';
        }

        if (str_contains($reasonsText, 'capacity') || str_contains($reasonsText, 'hours')) {
            $suggestions[] = 'Consider splitting care across multiple staff members';
        }

        if (str_contains($reasonsText, 'role') || str_contains($reasonsText, 'eligib')) {
            $suggestions[] = 'May require staff with different qualifications';
        }

        if (str_contains($reasonsText, 'sspo') || str_contains($reasonsText, 'nursing')) {
            $suggestions[] = 'Consider SSPO marketplace for specialized services';
        }

        return $suggestions;
    }
}
