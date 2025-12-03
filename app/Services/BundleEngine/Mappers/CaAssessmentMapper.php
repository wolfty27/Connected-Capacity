<?php

namespace App\Services\BundleEngine\Mappers;

use App\Models\InterraiAssessment;
use App\Services\BundleEngine\Contracts\AssessmentMapperInterface;
use App\Services\BundleEngine\Enums\NeedsCluster;

/**
 * CaAssessmentMapper
 *
 * Maps InterRAI Contact Assessment (CA) data to PatientNeedsProfile fields.
 *
 * CA is a shorter, rapid home-care intake assessment. It provides:
 * - Basic ADL/IADL capacity indicators
 * - Cognitive screening (short-term memory, decision-making)
 * - Basic falls and instability indicators
 * - Enough data for first-phase bundling when HC is unavailable
 *
 * Since CA doesn't provide full RUG classification, this mapper derives
 * a NeedsCluster for template selection.
 *
 * IMPORTANT: Field names must be verified against actual InterraiAssessment
 * model and raw_items JSON structure for CA instruments.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 2.2.2
 */
class CaAssessmentMapper implements AssessmentMapperInterface
{
    /**
     * CA assessment type identifier.
     */
    public function getAssessmentType(): string
    {
        return 'ca';
    }

    /**
     * CA has medium confidence weight.
     */
    public function getConfidenceWeight(): float
    {
        return 0.7;
    }

    /**
     * CA does NOT support full RUG classification.
     * It derives NeedsCluster instead.
     */
    public function supportsRugClassification(): bool
    {
        return false;
    }

    /**
     * Map CA assessment to profile fields.
     *
     * @param InterraiAssessment $assessment
     * @return array<string, mixed>
     */
    public function mapToProfileFields(InterraiAssessment $assessment): array
    {
        $rawItems = $assessment->raw_items ?? [];

        // Derive needs cluster since we can't do full RUG
        $needsCluster = $this->deriveNeedsCluster($assessment);

        return [
            // Data source tracking
            'hasCaAssessment' => true,
            'primaryAssessmentType' => 'ca',
            'primaryAssessmentDate' => $assessment->assessment_date,

            // Case classification (no RUG, use NeedsCluster)
            'needsCluster' => $needsCluster?->value,

            // Functional needs
            'adlSupportLevel' => $this->extractAdlSupportLevel($assessment),
            'iadlSupportLevel' => $this->extractIadlSupportLevel($assessment),
            'mobilityComplexity' => $this->extractMobilityComplexity($assessment),
            'specificAdlNeeds' => $this->extractSpecificAdlNeeds($rawItems),

            // Cognitive (from CA cognitive screen items)
            'cognitiveComplexity' => $this->extractCognitiveComplexity($assessment),
            'behaviouralComplexity' => $this->extractBehaviouralComplexity($assessment),

            // Clinical risk profile
            'fallsRiskLevel' => $this->extractFallsRiskLevel($assessment),
            'healthInstability' => $this->extractHealthInstability($assessment),

            // Support context
            'livesAlone' => $this->extractLivesAlone($rawItems),
            'caregiverAvailabilityScore' => $this->extractCaregiverAvailability($rawItems),
        ];
    }

    /**
     * Derive NeedsCluster from CA assessment items.
     *
     * This provides a simplified grouping when full RUG isn't available.
     */
    public function deriveNeedsCluster(InterraiAssessment $assessment): ?NeedsCluster
    {
        $adl = $this->extractAdlSupportLevel($assessment);
        $cognitive = $this->extractCognitiveComplexity($assessment);
        $health = $this->extractHealthInstability($assessment);
        $behavioural = $this->extractBehaviouralComplexity($assessment);

        // Priority-based derivation (per design document Section 2.4)

        // 1. Combined high complexity
        if ($adl >= 4 && $cognitive >= 3) {
            return NeedsCluster::HIGH_ADL_COGNITIVE;
        }

        // 2. High ADL dependency
        if ($adl >= 4) {
            return NeedsCluster::HIGH_ADL;
        }

        // 3. Cognitive complexity primary
        if ($cognitive >= 3) {
            return NeedsCluster::COGNITIVE_COMPLEX;
        }

        // 4. Mental health / behavioural complexity
        if ($behavioural >= 3) {
            return NeedsCluster::MH_COMPLEX;
        }

        // 5. Medical complexity (high instability)
        if ($health >= 3) {
            return NeedsCluster::MEDICAL_COMPLEX;
        }

        // 6. Moderate ADL
        if ($adl >= 2) {
            return NeedsCluster::MODERATE_ADL;
        }

        // 7. Low ADL
        if ($adl >= 1) {
            return NeedsCluster::LOW_ADL;
        }

        // 8. Default - general support
        return NeedsCluster::GENERAL;
    }

    /**
     * Extract ADL capacity from CA items.
     *
     * CA uses different items than HC - typically capacity-based questions.
     * VERIFY: Check actual CA field names.
     */
    public function extractAdlSupportLevel(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        // CA may have ADL capacity items instead of ADL hierarchy
        // VERIFY: Check actual field names for CA instrument

        // Try ADL capacity score first
        if (isset($rawItems['adl_capacity_score'])) {
            return $this->normalizeScale($rawItems['adl_capacity_score'], 0, 6);
        }

        // Calculate from individual CA ADL items
        $items = [
            $rawItems['ca_bathing'] ?? $rawItems['bathing_capacity'] ?? 0,
            $rawItems['ca_dressing'] ?? $rawItems['dressing_capacity'] ?? 0,
            $rawItems['ca_toileting'] ?? $rawItems['toilet_capacity'] ?? 0,
            $rawItems['ca_locomotion'] ?? $rawItems['locomotion_capacity'] ?? 0,
            $rawItems['ca_eating'] ?? $rawItems['eating_capacity'] ?? 0,
        ];

        // Sum and normalize
        $sum = array_sum(array_map('intval', $items));

        // Rough mapping: sum of 5 items (each 0-4) -> 0-6 scale
        return min(6, (int) round($sum / 3));
    }

    /**
     * Extract IADL capacity from CA items.
     *
     * VERIFY: Check actual CA field names.
     */
    public function extractIadlSupportLevel(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        // Try IADL capacity score first
        if (isset($rawItems['iadl_capacity_score'])) {
            return $this->normalizeScale($rawItems['iadl_capacity_score'], 0, 6);
        }

        // Calculate from individual CA IADL items
        $items = [
            $rawItems['ca_meals'] ?? $rawItems['meal_prep_capacity'] ?? 0,
            $rawItems['ca_housework'] ?? $rawItems['housework_capacity'] ?? 0,
            $rawItems['ca_finances'] ?? $rawItems['finances_capacity'] ?? 0,
            $rawItems['ca_medications'] ?? $rawItems['medication_capacity'] ?? 0,
            $rawItems['ca_transportation'] ?? $rawItems['transport_capacity'] ?? 0,
        ];

        $sum = array_sum(array_map('intval', $items));

        return min(6, (int) round($sum / 3));
    }

    /**
     * Extract mobility complexity from CA items.
     */
    public function extractMobilityComplexity(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        // VERIFY: Check actual CA field names
        $locomotion = $rawItems['ca_locomotion'] ?? $rawItems['locomotion_capacity'] ?? 0;
        $stairs = $rawItems['ca_stairs'] ?? $rawItems['stair_capacity'] ?? 0;

        $max = max((int) $locomotion, (int) $stairs);

        return $this->normalizeScale($max, 0, 6);
    }

    /**
     * Extract cognitive complexity from CA cognitive screen.
     *
     * CA typically has short-term memory and decision-making items.
     * VERIFY: Check actual CA cognitive screen field names.
     */
    public function extractCognitiveComplexity(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        // CA cognitive screen items
        // VERIFY: Actual field names may differ

        $shortTermMemory = $rawItems['ca_short_term_memory'] ?? $rawItems['stm_problem'] ?? 0;
        $decisionMaking = $rawItems['ca_decision_making'] ?? $rawItems['decision_making'] ?? 0;
        $orientation = $rawItems['ca_orientation'] ?? 0;

        // Simple scoring: 0-2 for each item -> 0-6 total
        $total = (int) $shortTermMemory + (int) $decisionMaking + (int) $orientation;

        return min(6, $total);
    }

    /**
     * Extract behavioural complexity from CA.
     *
     * CA may have limited behavioural items compared to HC.
     */
    public function extractBehaviouralComplexity(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        // VERIFY: Check actual CA behavioural screen field names
        $aggression = $rawItems['ca_aggression'] ?? 0;
        $wandering = $rawItems['ca_wandering'] ?? 0;
        $resists = $rawItems['ca_resists_care'] ?? 0;

        $count = 0;
        if ((int) $aggression > 0) $count++;
        if ((int) $wandering > 0) $count++;
        if ((int) $resists > 0) $count++;

        return min(4, $count);
    }

    /**
     * Extract health instability from CA.
     *
     * CA may derive simplified instability from acute conditions/changes.
     */
    public function extractHealthInstability(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        // VERIFY: Check actual CA instability indicators
        // CA may not have full CHESS, but has acute change indicators

        $acuteChange = $rawItems['ca_acute_change'] ?? $rawItems['acute_change'] ?? 0;
        $unstable = $rawItems['ca_unstable_condition'] ?? 0;
        $hospitalRecent = $rawItems['ca_recent_hospital'] ?? 0;

        // Simple scoring
        $score = 0;
        if ((int) $acuteChange > 0) $score += 2;
        if ((int) $unstable > 0) $score += 2;
        if ((int) $hospitalRecent > 0) $score += 1;

        return min(5, $score);
    }

    /**
     * Extract falls risk level from CA.
     */
    public function extractFallsRiskLevel(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        // VERIFY: Check actual CA falls items
        $fallHistory = $rawItems['ca_fall_history'] ?? $rawItems['fall_any'] ?? 0;
        $unsteady = $rawItems['ca_unsteady'] ?? 0;

        if ((int) $fallHistory > 1 || (int) $unsteady > 1) {
            return 2; // High
        }
        if ((int) $fallHistory > 0 || (int) $unsteady > 0) {
            return 1; // Moderate
        }
        return 0; // Low
    }

    /**
     * Extract specific ADL needs from CA.
     */
    protected function extractSpecificAdlNeeds(array $rawItems): array
    {
        $needs = [];

        // VERIFY: Check actual CA field names and thresholds
        if ((int) ($rawItems['ca_bathing'] ?? 0) >= 2) {
            $needs[] = 'bathing';
        }
        if ((int) ($rawItems['ca_dressing'] ?? 0) >= 2) {
            $needs[] = 'dressing';
        }
        if ((int) ($rawItems['ca_toileting'] ?? 0) >= 2) {
            $needs[] = 'toileting';
        }
        if ((int) ($rawItems['ca_locomotion'] ?? 0) >= 2) {
            $needs[] = 'mobility';
        }

        return $needs;
    }

    /**
     * Check if patient lives alone.
     */
    protected function extractLivesAlone(array $rawItems): bool
    {
        // VERIFY: Check actual CA field name
        return ((int) ($rawItems['ca_lives_alone'] ?? $rawItems['lives_alone'] ?? 0)) > 0;
    }

    /**
     * Extract caregiver availability.
     */
    protected function extractCaregiverAvailability(array $rawItems): int
    {
        // VERIFY: Check actual CA field name
        $hasCaregiver = (int) ($rawItems['ca_caregiver_present'] ?? $rawItems['informal_support'] ?? 0);

        if ($hasCaregiver > 0) {
            return 3; // Has caregiver
        }
        return 0; // No caregiver
    }

    /**
     * Get list of fields this mapper can populate.
     */
    public function getPopulatableFields(): array
    {
        return [
            'hasCaAssessment',
            'primaryAssessmentType',
            'primaryAssessmentDate',
            'needsCluster',
            'adlSupportLevel',
            'iadlSupportLevel',
            'mobilityComplexity',
            'specificAdlNeeds',
            'cognitiveComplexity',
            'behaviouralComplexity',
            'fallsRiskLevel',
            'healthInstability',
            'livesAlone',
            'caregiverAvailabilityScore',
        ];
    }

    /**
     * Normalize a value to a scale range.
     */
    protected function normalizeScale(mixed $value, int $min, int $max): int
    {
        if ($value === null) {
            return $min;
        }

        $intValue = (int) $value;

        return max($min, min($max, $intValue));
    }
}

