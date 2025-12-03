<?php

namespace App\Services\BundleEngine\Mappers;

use App\Models\InterraiAssessment;
use App\Services\BundleEngine\Contracts\AssessmentMapperInterface;

/**
 * HcAssessmentMapper
 *
 * Maps InterRAI Home Care (HC) assessment data to PatientNeedsProfile fields.
 *
 * HC is the most comprehensive assessment and provides:
 * - Full RUG-III/HC classification
 * - All clinical scales (ADL, IADL, CPS, CHESS, etc.)
 * - Complete behavioural and therapy data
 *
 * IMPORTANT: Field names in this mapper must be verified against
 * the actual InterraiAssessment model and raw_items JSON structure.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 2.2.1
 */
class HcAssessmentMapper implements AssessmentMapperInterface
{
    /**
     * HC assessment type identifier.
     */
    public function getAssessmentType(): string
    {
        return 'hc';
    }

    /**
     * HC has highest confidence weight.
     */
    public function getConfidenceWeight(): float
    {
        return 1.0;
    }

    /**
     * HC supports full RUG classification.
     */
    public function supportsRugClassification(): bool
    {
        return true;
    }

    /**
     * Map HC assessment to profile fields.
     *
     * @param InterraiAssessment $assessment
     * @return array<string, mixed>
     */
    public function mapToProfileFields(InterraiAssessment $assessment): array
    {
        $rawItems = $assessment->raw_items ?? [];

        return [
            // Data source tracking
            'hasFullHcAssessment' => true,
            'primaryAssessmentType' => 'hc',
            'primaryAssessmentDate' => $assessment->assessment_date,

            // Case classification
            'rugGroup' => $this->extractRugGroup($assessment),
            'rugCategory' => $this->extractRugCategory($assessment),
            'rugNumericRank' => $this->extractRugNumericRank($assessment),

            // Functional needs
            'adlSupportLevel' => $this->extractAdlSupportLevel($assessment),
            'iadlSupportLevel' => $this->extractIadlSupportLevel($assessment),
            'mobilityComplexity' => $this->extractMobilityComplexity($assessment),
            'specificAdlNeeds' => $this->extractSpecificAdlNeeds($rawItems),

            // Cognitive & Behavioural
            'cognitiveComplexity' => $this->extractCognitiveComplexity($assessment),
            'behaviouralComplexity' => $this->extractBehaviouralComplexity($assessment),
            'hasWanderingRisk' => $this->extractWanderingRisk($rawItems),
            'hasAggressionRisk' => $this->extractAggressionRisk($rawItems),
            'behaviouralFlags' => $this->extractBehaviouralFlags($rawItems),

            // Clinical risk profile
            'fallsRiskLevel' => $this->extractFallsRiskLevel($assessment),
            'skinIntegrityRisk' => $this->extractSkinIntegrityRisk($rawItems),
            'painManagementNeed' => $this->extractPainLevel($rawItems),
            'continenceSupport' => $this->extractContinenceSupport($rawItems),
            'healthInstability' => $this->extractHealthInstability($assessment),
            'clinicalRiskFlags' => $this->extractClinicalRiskFlags($rawItems),

            // Treatment context
            'requiresExtensiveServices' => $this->extractRequiresExtensiveServices($rawItems),
            'extensiveServices' => $this->extractExtensiveServices($rawItems),
            'weeklyTherapyMinutes' => $this->extractWeeklyTherapyMinutes($rawItems),

            // Support context
            'caregiverAvailabilityScore' => $this->extractCaregiverAvailability($rawItems),
            'caregiverStressLevel' => $this->extractCaregiverStress($rawItems),
            'livesAlone' => $this->extractLivesAlone($rawItems),
            'caregiverRequiresRelief' => $this->extractCaregiverRequiresRelief($rawItems),
        ];
    }

    /**
     * Extract RUG-III/HC group code.
     *
     * RUG classification is stored in a separate RUGClassification model
     * linked via the latestRugClassification relationship.
     */
    protected function extractRugGroup(InterraiAssessment $assessment): ?string
    {
        // RUG group is in the related RUGClassification model
        $rugClassification = $assessment->latestRugClassification;
        if ($rugClassification) {
            return $rugClassification->rug_group;
        }
        
        // Fallback to raw_items if present
        return $assessment->raw_items['rug_group'] ?? null;
    }

    /**
     * Extract RUG category name.
     */
    protected function extractRugCategory(InterraiAssessment $assessment): ?string
    {
        // TODO: Verify actual field name or derive from RUG group
        $rugGroup = $this->extractRugGroup($assessment);
        if (!$rugGroup) {
            return null;
        }

        // Map RUG group prefix to category
        $prefix = substr($rugGroup, 0, 2);
        return match ($prefix) {
            'SE', 'SR' => 'Special Rehabilitation',
            'ES' => 'Extensive Services',
            'SC' => 'Special Care',
            'CC' => 'Clinically Complex',
            'IB', 'IA' => 'Impaired Cognition',
            'BB', 'BA' => 'Behaviour Problems',
            'PB', 'PA', 'PC', 'PD', 'PE' => 'Reduced Physical Function',
            default => 'Unknown',
        };
    }

    /**
     * Extract numeric rank for RUG comparison.
     */
    protected function extractRugNumericRank(InterraiAssessment $assessment): ?int
    {
        // RUG numeric rank is in the related RUGClassification model
        $rugClassification = $assessment->latestRugClassification;
        if ($rugClassification && $rugClassification->numeric_rank !== null) {
            return $rugClassification->numeric_rank;
        }
        
        return $assessment->raw_items['rug_numeric_rank'] ?? null;
    }

    /**
     * Extract ADL Hierarchy scale (0-6).
     *
     * VERIFY: Check actual field name - could be 'adl_hierarchy', 'adl_h', etc.
     */
    public function extractAdlSupportLevel(InterraiAssessment $assessment): int
    {
        // Try multiple possible field names
        $rawItems = $assessment->raw_items ?? [];

        $value = $rawItems['adl_hierarchy'] 
            ?? $rawItems['adl_h'] 
            ?? $rawItems['ADL_HIERARCHY'] 
            ?? $assessment->adl_hierarchy
            ?? null;

        return $this->normalizeScale($value, 0, 6);
    }

    /**
     * Extract IADL capacity scale (0-6).
     *
     * VERIFY: Check actual field name - could be 'iadl_capacity', 'iadl_c', etc.
     */
    public function extractIadlSupportLevel(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        $value = $rawItems['iadl_capacity'] 
            ?? $rawItems['iadl_summary_score'] 
            ?? $rawItems['IADL_CAPACITY'] 
            ?? null;

        return $this->normalizeScale($value, 0, 6);
    }

    /**
     * Extract mobility complexity from locomotion and transfer items.
     */
    public function extractMobilityComplexity(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        // VERIFY: Check actual field names
        $locomotion = $rawItems['locomotion'] ?? $rawItems['G2a'] ?? 0;
        $transfer = $rawItems['transfer'] ?? $rawItems['G1a'] ?? 0;

        // Take the higher of the two
        $max = max((int) $locomotion, (int) $transfer);

        return $this->normalizeScale($max, 0, 6);
    }

    /**
     * Extract CPS (Cognitive Performance Scale) as cognitive complexity.
     *
     * VERIFY: Check actual field name - 'cps', 'CPS', 'cognitive_performance_scale'.
     */
    public function extractCognitiveComplexity(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        $value = $rawItems['cps'] 
            ?? $rawItems['CPS'] 
            ?? $rawItems['cognitive_performance_scale'] 
            ?? $assessment->cps
            ?? null;

        return $this->normalizeScale($value, 0, 6);
    }

    /**
     * Extract behavioural complexity from behavioural items.
     *
     * Composite of: verbal aggression, physical aggression, resists care, wandering.
     */
    public function extractBehaviouralComplexity(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        // VERIFY: Check actual field names
        $items = [
            $rawItems['verbal_abuse'] ?? $rawItems['E1a'] ?? 0,
            $rawItems['physical_abuse'] ?? $rawItems['E1b'] ?? 0,
            $rawItems['resists_care'] ?? $rawItems['E1c'] ?? 0,
            $rawItems['wandering'] ?? $rawItems['E4'] ?? 0,
        ];

        // Count how many are present (>0)
        $count = count(array_filter($items, fn($v) => (int) $v > 0));

        return min($count, 4); // Scale 0-4
    }

    /**
     * Extract CHESS scale as health instability.
     *
     * VERIFY: Check actual field name - 'chess', 'CHESS', 'chess_score'.
     */
    public function extractHealthInstability(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        $value = $rawItems['chess'] 
            ?? $rawItems['CHESS'] 
            ?? $rawItems['chess_score'] 
            ?? $assessment->chess
            ?? null;

        return $this->normalizeScale($value, 0, 5);
    }

    /**
     * Extract falls risk level.
     *
     * Based on fall history and fall risk indicators.
     */
    public function extractFallsRiskLevel(InterraiAssessment $assessment): int
    {
        $rawItems = $assessment->raw_items ?? [];

        // VERIFY: Check actual field names
        $fallHistory = $rawItems['fall_history'] ?? $rawItems['J1h'] ?? 0;
        $fallsLast90 = $rawItems['falls_last_90'] ?? $rawItems['J1i'] ?? 0;

        if ((int) $fallsLast90 > 1) {
            return 2; // High
        }
        if ((int) $fallHistory > 0 || (int) $fallsLast90 > 0) {
            return 1; // Moderate
        }
        return 0; // Low
    }

    /**
     * Extract wandering risk flag.
     */
    protected function extractWanderingRisk(array $rawItems): bool
    {
        // VERIFY: Check actual field name
        return ((int) ($rawItems['wandering'] ?? $rawItems['E4'] ?? 0)) > 0;
    }

    /**
     * Extract aggression risk flag.
     */
    protected function extractAggressionRisk(array $rawItems): bool
    {
        // VERIFY: Check actual field names
        $verbal = (int) ($rawItems['verbal_abuse'] ?? $rawItems['E1a'] ?? 0);
        $physical = (int) ($rawItems['physical_abuse'] ?? $rawItems['E1b'] ?? 0);

        return $verbal > 1 || $physical > 0;
    }

    /**
     * Extract specific ADL needs as array.
     */
    protected function extractSpecificAdlNeeds(array $rawItems): array
    {
        $needs = [];

        // VERIFY: Check actual field names and thresholds
        if ((int) ($rawItems['bathing'] ?? $rawItems['G1l'] ?? 0) >= 3) {
            $needs[] = 'bathing';
        }
        if ((int) ($rawItems['dressing'] ?? $rawItems['G1e'] ?? 0) >= 3) {
            $needs[] = 'dressing';
        }
        if ((int) ($rawItems['eating'] ?? $rawItems['G1h'] ?? 0) >= 3) {
            $needs[] = 'eating';
        }
        if ((int) ($rawItems['toilet_use'] ?? $rawItems['G1i'] ?? 0) >= 3) {
            $needs[] = 'toileting';
        }
        if ((int) ($rawItems['transfer'] ?? $rawItems['G1a'] ?? 0) >= 3) {
            $needs[] = 'transfers';
        }

        return $needs;
    }

    /**
     * Extract behavioural flags as array.
     */
    protected function extractBehaviouralFlags(array $rawItems): array
    {
        $flags = [];

        // VERIFY: Check actual field names
        if ((int) ($rawItems['verbal_abuse'] ?? 0) > 0) {
            $flags[] = 'verbal_aggression';
        }
        if ((int) ($rawItems['physical_abuse'] ?? 0) > 0) {
            $flags[] = 'physical_aggression';
        }
        if ((int) ($rawItems['resists_care'] ?? 0) > 0) {
            $flags[] = 'resists_care';
        }
        if ((int) ($rawItems['wandering'] ?? 0) > 0) {
            $flags[] = 'wandering';
        }
        if ((int) ($rawItems['socially_inappropriate'] ?? 0) > 0) {
            $flags[] = 'socially_inappropriate';
        }

        return $flags;
    }

    /**
     * Extract skin integrity risk.
     */
    protected function extractSkinIntegrityRisk(array $rawItems): int
    {
        // VERIFY: Check actual field names
        $pressureUlcer = (int) ($rawItems['pressure_ulcer'] ?? $rawItems['M2a'] ?? 0);
        $skinCondition = (int) ($rawItems['skin_tears'] ?? $rawItems['M5'] ?? 0);

        if ($pressureUlcer >= 2) {
            return 2; // High
        }
        if ($pressureUlcer > 0 || $skinCondition > 0) {
            return 1; // Moderate
        }
        return 0; // Low
    }

    /**
     * Extract pain level (0-3).
     */
    protected function extractPainLevel(array $rawItems): int
    {
        // VERIFY: Check actual field name
        $painScale = $rawItems['pain_scale'] ?? $rawItems['J2a'] ?? 0;

        return $this->normalizeScale($painScale, 0, 3);
    }

    /**
     * Extract continence support level.
     */
    protected function extractContinenceSupport(array $rawItems): int
    {
        // VERIFY: Check actual field names
        $bladder = (int) ($rawItems['bladder_continence'] ?? $rawItems['H1a'] ?? 0);
        $bowel = (int) ($rawItems['bowel_continence'] ?? $rawItems['H2a'] ?? 0);

        return max($bladder, $bowel);
    }

    /**
     * Extract clinical risk flags.
     */
    protected function extractClinicalRiskFlags(array $rawItems): array
    {
        $flags = [];

        // VERIFY: Check actual field names
        if ((int) ($rawItems['pressure_ulcer'] ?? 0) > 0) {
            $flags[] = 'pressure_ulcer';
        }
        if ((int) ($rawItems['falls_last_90'] ?? 0) > 0) {
            $flags[] = 'recent_fall';
        }
        if ((int) ($rawItems['dehydration_risk'] ?? 0) > 0) {
            $flags[] = 'dehydration_risk';
        }
        if ((int) ($rawItems['weight_loss'] ?? 0) > 0) {
            $flags[] = 'weight_loss';
        }

        return $flags;
    }

    /**
     * Check if patient requires extensive services.
     */
    protected function extractRequiresExtensiveServices(array $rawItems): bool
    {
        // VERIFY: Check actual field names for extensive service indicators
        return (
            (int) ($rawItems['iv_therapy'] ?? 0) > 0 ||
            (int) ($rawItems['tracheostomy'] ?? 0) > 0 ||
            (int) ($rawItems['ventilator'] ?? 0) > 0 ||
            (int) ($rawItems['dialysis'] ?? 0) > 0 ||
            (int) ($rawItems['radiation'] ?? 0) > 0
        );
    }

    /**
     * Extract list of extensive services needed.
     */
    protected function extractExtensiveServices(array $rawItems): array
    {
        $services = [];

        // VERIFY: Check actual field names
        if ((int) ($rawItems['iv_therapy'] ?? 0) > 0) {
            $services[] = 'iv_therapy';
        }
        if ((int) ($rawItems['tracheostomy'] ?? 0) > 0) {
            $services[] = 'tracheostomy';
        }
        if ((int) ($rawItems['ventilator'] ?? 0) > 0) {
            $services[] = 'ventilator';
        }
        if ((int) ($rawItems['wound_care'] ?? 0) > 0) {
            $services[] = 'wound_care';
        }
        if ((int) ($rawItems['oxygen_therapy'] ?? 0) > 0) {
            $services[] = 'oxygen_therapy';
        }

        return $services;
    }

    /**
     * Extract weekly therapy minutes.
     */
    protected function extractWeeklyTherapyMinutes(array $rawItems): int
    {
        // VERIFY: Check actual field names
        $ptMinutes = (int) ($rawItems['pt_minutes'] ?? $rawItems['P1ba'] ?? 0);
        $otMinutes = (int) ($rawItems['ot_minutes'] ?? $rawItems['P1bb'] ?? 0);
        $slpMinutes = (int) ($rawItems['slp_minutes'] ?? $rawItems['P1bc'] ?? 0);

        return $ptMinutes + $otMinutes + $slpMinutes;
    }

    /**
     * Extract caregiver availability score.
     */
    protected function extractCaregiverAvailability(array $rawItems): int
    {
        // VERIFY: Check actual field name
        $hasInformalHelper = (int) ($rawItems['informal_helper'] ?? $rawItems['G3'] ?? 0);
        $helperLivesWith = (int) ($rawItems['helper_lives_with'] ?? 0);

        if ($hasInformalHelper > 0 && $helperLivesWith > 0) {
            return 5; // 24/7 available
        }
        if ($hasInformalHelper > 0) {
            return 3; // Available but not live-in
        }
        return 0; // No caregiver
    }

    /**
     * Extract caregiver stress level.
     */
    protected function extractCaregiverStress(array $rawItems): int
    {
        // VERIFY: Check actual field name
        $distress = $rawItems['caregiver_distress'] ?? $rawItems['G4'] ?? 0;

        return $this->normalizeScale($distress, 0, 4);
    }

    /**
     * Check if patient lives alone.
     */
    protected function extractLivesAlone(array $rawItems): bool
    {
        // VERIFY: Check actual field name
        return ((int) ($rawItems['lives_alone'] ?? $rawItems['A5'] ?? 0)) > 0;
    }

    /**
     * Check if caregiver requires relief.
     */
    protected function extractCaregiverRequiresRelief(array $rawItems): bool
    {
        // VERIFY: Check actual field name
        $distress = (int) ($rawItems['caregiver_distress'] ?? $rawItems['G4'] ?? 0);

        return $distress >= 3;
    }

    /**
     * Get list of fields this mapper can populate.
     */
    public function getPopulatableFields(): array
    {
        return [
            'hasFullHcAssessment',
            'primaryAssessmentType',
            'primaryAssessmentDate',
            'rugGroup',
            'rugCategory',
            'rugNumericRank',
            'adlSupportLevel',
            'iadlSupportLevel',
            'mobilityComplexity',
            'specificAdlNeeds',
            'cognitiveComplexity',
            'behaviouralComplexity',
            'hasWanderingRisk',
            'hasAggressionRisk',
            'behaviouralFlags',
            'fallsRiskLevel',
            'skinIntegrityRisk',
            'painManagementNeed',
            'continenceSupport',
            'healthInstability',
            'clinicalRiskFlags',
            'requiresExtensiveServices',
            'extensiveServices',
            'weeklyTherapyMinutes',
            'caregiverAvailabilityScore',
            'caregiverStressLevel',
            'livesAlone',
            'caregiverRequiresRelief',
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

