<?php

namespace App\Services;

use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Models\SspoServiceCapability;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * STAFF-021: SSPO Marketplace Matching Service
 *
 * Matches service assignments to SSPOs based on:
 * - Service type capabilities
 * - Skill requirements
 * - Geographic coverage
 * - Capacity availability
 * - Quality metrics
 * - Patient preferences
 */
class SspoMarketplaceService
{
    /**
     * Find matching SSPOs for a service assignment
     */
    public function findMatchingSSPOs(
        ServiceType $serviceType,
        Patient $patient,
        ?Carbon $requestedStart = null,
        ?float $estimatedHours = null
    ): Collection {
        // Start with active capabilities for this service type
        $query = SspoServiceCapability::active()
            ->forServiceType($serviceType->id)
            ->with(['sspo', 'serviceType']);

        // Filter by capacity if hours specified
        if ($estimatedHours) {
            $query->withAvailableCapacity((int) ceil($estimatedHours));
        }

        // Filter by geographic coverage if patient has postal code
        $postalPrefix = $this->extractPostalPrefix($patient->address ?? '');
        if ($postalPrefix) {
            $query->where(function ($q) use ($postalPrefix) {
                $q->whereJsonContains('service_areas', $postalPrefix)
                  ->orWhereNull('service_areas'); // SSPOs with no area restrictions
            });
        }

        // Filter by time if specified
        if ($requestedStart) {
            $query->availableOnDay($requestedStart->dayOfWeek);
        }

        // Get all matching capabilities
        $capabilities = $query->get();

        // Score and filter based on skill requirements
        $scoredMatches = $capabilities->map(function ($capability) use ($serviceType, $patient, $requestedStart, $estimatedHours) {
            $scores = $this->calculateMatchScores($capability, $serviceType, $patient, $requestedStart);

            return [
                'capability' => $capability,
                'sspo' => $capability->sspo,
                'sspo_id' => $capability->sspo_id,
                'sspo_name' => $capability->sspo->name,
                'service_type' => $serviceType->name,

                // Capacity info
                'available_hours' => $capability->available_hours,
                'utilization_rate' => $capability->utilization_rate,
                'min_notice_hours' => $capability->min_notice_hours,

                // Pricing
                'hourly_rate' => $capability->hourly_rate,
                'visit_rate' => $capability->visit_rate,
                'estimated_cost' => $this->calculateEstimatedCost($capability, $estimatedHours, $requestedStart),

                // Quality metrics
                'quality_score' => $capability->quality_score ?? 0,
                'acceptance_rate' => $capability->acceptance_rate ?? 0,
                'completion_rate' => $capability->completion_rate ?? 0,

                // Special capabilities
                'can_handle_dementia' => $capability->can_handle_dementia,
                'can_handle_palliative' => $capability->can_handle_palliative,
                'can_handle_complex_care' => $capability->can_handle_complex_care,
                'bilingual_french' => $capability->bilingual_french,
                'languages' => $capability->languages_available ?? [],

                // Match scores
                'scores' => $scores,
                'overall_score' => $scores['overall'],
                'is_preferred' => $patient->preferred_sspo_id === $capability->sspo_id,
                'meets_requirements' => $scores['skills_match'] >= 100 && $scores['availability_match'] >= 100,

                // Warnings
                'warnings' => $this->getMatchWarnings($capability, $serviceType, $requestedStart),
            ];
        });

        // Sort by overall score, with preferred SSPO boosted
        return $scoredMatches
            ->filter(fn($m) => $m['meets_requirements'])
            ->sortByDesc(fn($m) => $m['is_preferred'] ? $m['overall_score'] + 100 : $m['overall_score'])
            ->values();
    }

    /**
     * Calculate match scores for an SSPO capability
     */
    protected function calculateMatchScores(
        SspoServiceCapability $capability,
        ServiceType $serviceType,
        Patient $patient,
        ?Carbon $requestedStart
    ): array {
        // Skills match score (0-100)
        $skillsScore = $this->calculateSkillsMatchScore($capability, $serviceType);

        // Quality score (0-100)
        $qualityScore = $capability->capability_score ?? 0;

        // Availability score (0-100)
        $availabilityScore = $this->calculateAvailabilityScore($capability, $requestedStart);

        // Capacity score (0-100) - higher available capacity = higher score
        $capacityScore = min(100, ($capability->available_hours / max(1, $capability->max_weekly_hours ?? 1)) * 100);

        // Geographic score (0-100)
        $geoScore = $this->calculateGeographicScore($capability, $patient);

        // Overall weighted score
        $overall = (
            $skillsScore * 0.30 +
            $qualityScore * 0.25 +
            $availabilityScore * 0.20 +
            $capacityScore * 0.15 +
            $geoScore * 0.10
        );

        return [
            'skills_match' => round($skillsScore, 1),
            'quality' => round($qualityScore, 1),
            'availability_match' => round($availabilityScore, 1),
            'capacity' => round($capacityScore, 1),
            'geographic' => round($geoScore, 1),
            'overall' => round($overall, 1),
        ];
    }

    /**
     * Calculate skills match score
     */
    protected function calculateSkillsMatchScore(SspoServiceCapability $capability, ServiceType $serviceType): float
    {
        $requiredSkills = $serviceType->requiredSkills()->pluck('code')->toArray();

        if (empty($requiredSkills)) {
            return 100; // No skills required
        }

        $sspoQualifications = $capability->staff_qualifications ?? [];

        $matchedSkills = array_intersect($requiredSkills, $sspoQualifications);
        return (count($matchedSkills) / count($requiredSkills)) * 100;
    }

    /**
     * Calculate availability score
     */
    protected function calculateAvailabilityScore(SspoServiceCapability $capability, ?Carbon $requestedStart): float
    {
        if (!$requestedStart) {
            return 100; // No specific time requested
        }

        $score = 100;

        // Check if can service at requested time
        if (!$capability->canServiceAt($requestedStart)) {
            return 0;
        }

        // Check notice requirement
        if (!$capability->meetsNoticeRequirement($requestedStart)) {
            $score -= 30;
        }

        return max(0, $score);
    }

    /**
     * Calculate geographic score
     */
    protected function calculateGeographicScore(SspoServiceCapability $capability, Patient $patient): float
    {
        $postalPrefix = $this->extractPostalPrefix($patient->address ?? '');

        if (!$postalPrefix) {
            return 50; // Unknown location, neutral score
        }

        $serviceAreas = $capability->service_areas ?? [];

        if (empty($serviceAreas)) {
            return 70; // No restrictions, but not explicitly covering area
        }

        if (in_array($postalPrefix, $serviceAreas)) {
            return 100; // Direct coverage
        }

        // Check if any similar prefixes (first 2 chars match)
        $shortPrefix = substr($postalPrefix, 0, 2);
        foreach ($serviceAreas as $area) {
            if (str_starts_with($area, $shortPrefix)) {
                return 80; // Adjacent area
            }
        }

        return 0; // Area not covered
    }

    /**
     * Calculate estimated cost for assignment
     */
    protected function calculateEstimatedCost(
        SspoServiceCapability $capability,
        ?float $hours,
        ?Carbon $requestedStart
    ): ?float {
        if (!$hours) {
            return null;
        }

        $isWeekend = $requestedStart && $requestedStart->isWeekend();
        $rate = $capability->getEffectiveRate($isWeekend);

        if ($capability->visit_rate) {
            return $rate; // Flat visit rate
        }

        return $rate * $hours; // Hourly rate
    }

    /**
     * Get match warnings
     */
    protected function getMatchWarnings(
        SspoServiceCapability $capability,
        ServiceType $serviceType,
        ?Carbon $requestedStart
    ): array {
        $warnings = [];

        // Check utilization
        if ($capability->utilization_rate > 80) {
            $warnings[] = [
                'code' => 'HIGH_UTILIZATION',
                'message' => "SSPO is at {$capability->utilization_rate}% capacity this week",
            ];
        }

        // Check quality metrics
        if ($capability->quality_score && $capability->quality_score < 70) {
            $warnings[] = [
                'code' => 'LOW_QUALITY_SCORE',
                'message' => "Quality score ({$capability->quality_score}) is below recommended threshold",
            ];
        }

        // Check acceptance rate
        if ($capability->acceptance_rate && $capability->acceptance_rate < 70) {
            $warnings[] = [
                'code' => 'LOW_ACCEPTANCE_RATE',
                'message' => "Acceptance rate ({$capability->acceptance_rate}%) may result in declined requests",
            ];
        }

        // Check insurance
        if ($capability->insurance_expiry_date) {
            $daysUntilExpiry = Carbon::today()->diffInDays($capability->insurance_expiry_date, false);
            if ($daysUntilExpiry <= 30 && $daysUntilExpiry > 0) {
                $warnings[] = [
                    'code' => 'INSURANCE_EXPIRING',
                    'message' => "SSPO insurance expires in {$daysUntilExpiry} days",
                ];
            }
        }

        // Check notice requirement
        if ($requestedStart && !$capability->meetsNoticeRequirement($requestedStart)) {
            $warnings[] = [
                'code' => 'SHORT_NOTICE',
                'message' => "Request does not meet {$capability->min_notice_hours}h minimum notice requirement",
            ];
        }

        return $warnings;
    }

    /**
     * Extract postal code prefix from address
     */
    protected function extractPostalPrefix(string $address): ?string
    {
        // Canadian postal code pattern (first 3 characters)
        if (preg_match('/([A-Z]\d[A-Z])\s*\d[A-Z]\d/i', $address, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    /**
     * Get SSPO rankings for a service type
     */
    public function getSspoRankings(int $serviceTypeId): Collection
    {
        return SspoServiceCapability::active()
            ->forServiceType($serviceTypeId)
            ->orderByRanking()
            ->with('sspo')
            ->get()
            ->map(fn($c) => [
                'sspo_id' => $c->sspo_id,
                'sspo_name' => $c->sspo->name,
                'quality_score' => $c->quality_score,
                'acceptance_rate' => $c->acceptance_rate,
                'completion_rate' => $c->completion_rate,
                'capability_score' => $c->capability_score,
                'available_hours' => $c->available_hours,
                'hourly_rate' => $c->hourly_rate,
            ]);
    }

    /**
     * Auto-assign service assignment to best matching SSPO
     */
    public function autoAssignToSspo(ServiceAssignment $assignment): ?array
    {
        $serviceType = $assignment->serviceType;
        $patient = $assignment->carePlan->patient;

        $matches = $this->findMatchingSSPOs(
            $serviceType,
            $patient,
            $assignment->scheduled_start,
            $assignment->estimated_duration_hours
        );

        if ($matches->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No matching SSPOs found',
                'matches' => [],
            ];
        }

        $bestMatch = $matches->first();

        return [
            'success' => true,
            'recommended_sspo_id' => $bestMatch['sspo_id'],
            'recommended_sspo_name' => $bestMatch['sspo_name'],
            'match_score' => $bestMatch['overall_score'],
            'estimated_cost' => $bestMatch['estimated_cost'],
            'warnings' => $bestMatch['warnings'],
            'alternatives' => $matches->slice(1, 3)->values()->toArray(),
        ];
    }
}
