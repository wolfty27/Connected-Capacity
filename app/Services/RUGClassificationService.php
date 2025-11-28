<?php

namespace App\Services;

use App\Models\InterraiAssessment;
use App\Models\RUGClassification;
use Illuminate\Support\Facades\Log;

/**
 * RUGClassificationService
 *
 * Implements the CIHI RUG-III/HC classification algorithm to determine
 * a patient's Resource Utilization Group based on their InterRAI HC assessment.
 *
 * The algorithm evaluates clinical indicators in hierarchy order:
 * 1. Special Rehabilitation (≥120 therapy minutes)
 * 2. Extensive Services (IV, ventilator, etc.)
 * 3. Special Care (complex clinical + high ADL)
 * 4. Clinically Complex (clinical conditions)
 * 5. Impaired Cognition (CPS ≥ 3)
 * 6. Behaviour Problems (responsive behaviours)
 * 7. Reduced Physical Function (catch-all)
 *
 * @see docs/CC21_RUG_Algorithm_Pseudocode.md
 */
class RUGClassificationService
{
    /**
     * Classify a patient based on their InterRAI HC assessment.
     *
     * @param InterraiAssessment $assessment The InterRAI HC assessment
     * @return RUGClassification The computed classification
     */
    public function classify(InterraiAssessment $assessment): RUGClassification
    {
        // Get iCODE data from assessment
        $data = $this->toICodeArray($assessment);

        // Compute core scales
        $cps = $this->computeCPS($data);
        $adl = $this->computeADLSum($data);
        $iadl = $this->computeIADLIndex($data);
        $therapyMinutes = $this->computeTherapyMinutes($data);

        // Compute classification flags
        $flags = [
            'rehab' => $therapyMinutes >= 120,
            'extensive_services' => $this->hasExtensiveServices($data),
            'special_care' => $this->hasSpecialCareIndicators($data, $adl),
            'clinically_complex' => $this->hasClinicallyComplexIndicators($data, $adl),
            'impaired_cognition' => $cps >= 3,
            'behaviour_problems' => $this->hasBehaviourProblems($data),
        ];

        // Compute extensive count for SE group determination
        $extensiveCount = $this->computeExtensiveCount($data, $flags);

        // Determine RUG group using CIHI hierarchy
        $rugGroup = $this->determineRugGroup($data, $adl, $iadl, $cps, $flags, $extensiveCount);

        // Mark any existing current classifications as superseded
        RUGClassification::where('patient_id', $assessment->patient_id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        // Create and return the new classification
        $classification = RUGClassification::create([
            'patient_id' => $assessment->patient_id,
            'assessment_id' => $assessment->id,
            'rug_group' => $rugGroup,
            'rug_category' => RUGClassification::getCategoryForGroup($rugGroup),
            'adl_sum' => $adl,
            'iadl_sum' => $iadl,
            'cps_score' => $cps,
            'flags' => $flags,
            'numeric_rank' => RUGClassification::getRankForGroup($rugGroup),
            'therapy_minutes' => $therapyMinutes,
            'extensive_count' => $extensiveCount,
            'is_current' => true,
            'computation_details' => [
                'computed_at' => now()->toIso8601String(),
                'assessment_date' => $assessment->assessment_date?->toIso8601String(),
                'raw_scores' => [
                    'adl_items' => $this->getADLItems($data),
                    'iadl_items' => $this->getIADLItems($data),
                    'therapy_items' => $this->getTherapyItems($data),
                ],
            ],
        ]);

        Log::info('RUG classification computed', [
            'patient_id' => $assessment->patient_id,
            'assessment_id' => $assessment->id,
            'rug_group' => $rugGroup,
            'rug_category' => $classification->rug_category,
            'adl_sum' => $adl,
            'cps_score' => $cps,
        ]);

        return $classification;
    }

    /**
     * Convert assessment to iCODE array format.
     */
    protected function toICodeArray(InterraiAssessment $assessment): array
    {
        // If raw_items is available, use it directly
        if ($assessment->raw_items && is_array($assessment->raw_items)) {
            return $assessment->raw_items;
        }

        // Otherwise, map from existing assessment fields
        return [
            // Cognitive items
            'iB1' => $assessment->cognitive_performance_scale ?? 0, // Short-term memory
            'iB2a' => 0, // Memory recall ability
            'iB3a' => 0, // Cognitive skills for decision making
            'iC1' => $assessment->communication_scale ?? 0, // Expressive communication
            'iC2' => 0, // Comprehension

            // ADL items (using adl_hierarchy as proxy)
            'iG1aa' => $this->mapADLHierarchyToItem($assessment->adl_hierarchy, 'meal'),
            'iG1ba' => $this->mapADLHierarchyToItem($assessment->adl_hierarchy, 'dress_upper'),
            'iG1ca' => $this->mapADLHierarchyToItem($assessment->adl_hierarchy, 'dress_lower'),
            'iG1da' => $this->mapADLHierarchyToItem($assessment->adl_hierarchy, 'locomotion'),
            'iG1ea' => $this->mapADLHierarchyToItem($assessment->adl_hierarchy, 'toilet'),
            'iG1fa' => $this->mapADLHierarchyToItem($assessment->adl_hierarchy, 'hygiene'),
            'iG1ga' => $this->mapADLHierarchyToItem($assessment->adl_hierarchy, 'bathing'),
            'iG1ha' => $this->mapADLHierarchyToItem($assessment->adl_hierarchy, 'bed_mobility'),
            'iG1ia' => $this->mapADLHierarchyToItem($assessment->adl_hierarchy, 'transfer'),
            'iG1ja' => $this->mapADLHierarchyToItem($assessment->adl_hierarchy, 'eating'),

            // IADL items (using iadl_difficulty as proxy)
            'iG1ab' => $assessment->iadl_difficulty ?? 0, // IADL capacity
            'iG1db' => $assessment->iadl_difficulty ?? 0,
            'iG1eb' => $assessment->iadl_difficulty ?? 0,

            // Clinical indicators
            'iJ1a' => $assessment->pain_scale ?? 0, // Pain frequency
            'iJ1b' => $assessment->pain_scale ?? 0, // Pain intensity
            'iI1a' => 0, // Ulcer stage
            'iK1a' => 0, // Swallowing
            'iK5a' => 0, // Weight loss

            // Behaviour items
            'iE3a' => 0, // Wandering
            'iE3b' => 0, // Verbal abuse
            'iE3c' => 0, // Physical abuse
            'iE3d' => 0, // Socially inappropriate
            'iE3e' => 0, // Resists care
            'iE3f' => 0, // Disruptive

            // Treatment items
            'iN3eb' => 0, // PT minutes
            'iN3fb' => 0, // OT minutes
            'iN3gb' => 0, // SLP minutes
            'iP1aa' => 0, // IV medication
            'iP1ab' => 0, // IV feeding
            'iP1ae' => 0, // Suctioning
            'iP1af' => 0, // Tracheostomy care
            'iP1ag' => 0, // Ventilator
            'iP1ah' => 0, // Oxygen therapy
            'iP1ak' => 0, // Dialysis
            'iP1al' => 0, // Chemotherapy

            // Falls
            'iJ2a' => $assessment->falls_in_last_90_days ? 1 : 0,

            // CHESS/instability indicators
            'chess' => $assessment->chess_score ?? 0,

            // Depression
            'drs' => $assessment->depression_rating_scale ?? 0,

            // Location (for IADL calculation)
            'location' => 'community', // Assume community setting
        ];
    }

    /**
     * Map ADL hierarchy score to individual ADL item score.
     */
    protected function mapADLHierarchyToItem(int $hierarchy, string $item): int
    {
        // ADL hierarchy 0-6 maps to individual item scores
        // Higher hierarchy = more dependent
        return match (true) {
            $hierarchy >= 6 => 4, // Total dependence
            $hierarchy >= 5 => 4,
            $hierarchy >= 4 => 3,
            $hierarchy >= 3 => 2,
            $hierarchy >= 2 => 1,
            $hierarchy >= 1 => 1,
            default => 0,
        };
    }

    /**
     * Compute Cognitive Performance Scale (sCPS).
     * Based on CIHI's create_sCPS_scale macro.
     */
    protected function computeCPS(array $data): int
    {
        // If CPS is directly available, use it
        if (isset($data['cps']) && $data['cps'] !== null) {
            return (int) $data['cps'];
        }

        // Otherwise, compute from cognitive items
        $decisionMaking = $data['iB3a'] ?? 0;
        $shortTermMemory = $data['iB1'] ?? 0;
        $communication = $data['iC1'] ?? 0;
        $eating = $data['iG1ja'] ?? 0;

        // Simplified CPS calculation
        $xcps1 = ($decisionMaking >= 1) + ($shortTermMemory >= 1);
        $xcps2 = ($communication >= 2) + ($eating >= 3);

        if ($xcps2 >= 2) {
            return 6; // Very severe
        }
        if ($xcps1 >= 2 && $xcps2 >= 1) {
            return 5; // Severe
        }
        if ($xcps1 >= 2) {
            return 4; // Moderate-severe
        }
        if ($xcps1 >= 1 && $decisionMaking >= 2) {
            return 3; // Moderate
        }
        if ($xcps1 >= 1) {
            return 2; // Mild
        }
        if ($decisionMaking >= 1 || $shortTermMemory >= 1) {
            return 1; // Borderline
        }

        return 0; // Intact
    }

    /**
     * Compute ADL Sum (x_adlsum).
     * Sum of bed mobility, transfer, toilet use, and eating scores.
     */
    protected function computeADLSum(array $data): int
    {
        // Get ADL items for the 4 key activities
        $bedMobility = $this->convertADLScore($data['iG1ha'] ?? 0);
        $transfer = $this->convertADLScore($data['iG1ia'] ?? 0);
        $toiletUse = $this->convertADLScore($data['iG1ea'] ?? 0);
        $eating = $this->convertADLScore($data['iG1ja'] ?? 0);

        // Sum ranges from 4 (all independent) to 18 (all total dependent)
        $sum = $bedMobility + $transfer + $toiletUse + $eating;

        // Ensure minimum of 4
        return max(4, min(18, $sum));
    }

    /**
     * Convert ADL self-performance score to RUG scoring.
     * CIHI conversion: 0→1, 1→2, 2→3, 3→4, 4→4, 8→4
     */
    protected function convertADLScore(int $score): int
    {
        return match ($score) {
            0 => 1,
            1 => 2,
            2 => 3,
            3 => 4,
            4, 8 => 4, // Total dependence or activity did not occur
            default => 1,
        };
    }

    /**
     * Compute IADL Index (x_iadls).
     * Count of IADL items requiring full help or more.
     */
    protected function computeIADLIndex(array $data): int
    {
        $location = $data['location'] ?? 'community';

        if ($location === 'private_home' || $location === 'community') {
            // Use self-performance items for community setting
            $items = [
                $data['iG1aa'] ?? 0, // Meal prep
                $data['iG1da'] ?? 0, // Ordinary housework
                $data['iG1ea'] ?? 0, // Managing finances
            ];
        } else {
            // Use capacity items for facility setting
            $items = [
                $data['iG1ab'] ?? 0,
                $data['iG1db'] ?? 0,
                $data['iG1eb'] ?? 0,
            ];
        }

        // Count items with score >= 3 (extensive assistance or more)
        return collect($items)->filter(fn($score) => $score >= 3)->count();
    }

    /**
     * Compute total therapy minutes (x_th_min).
     */
    protected function computeTherapyMinutes(array $data): int
    {
        $pt = (int) ($data['iN3eb'] ?? 0);
        $ot = (int) ($data['iN3fb'] ?? 0);
        $slp = (int) ($data['iN3gb'] ?? 0);

        return $pt + $ot + $slp;
    }

    /**
     * Check for extensive services (IV, ventilator, etc.).
     */
    protected function hasExtensiveServices(array $data): bool
    {
        return ($data['iP1aa'] ?? 0) > 0 // IV medication
            || ($data['iP1ab'] ?? 0) > 0 // IV feeding
            || ($data['iP1ae'] ?? 0) > 0 // Suctioning
            || ($data['iP1af'] ?? 0) > 0 // Tracheostomy
            || ($data['iP1ag'] ?? 0) > 0; // Ventilator
    }

    /**
     * Check for special care indicators.
     */
    protected function hasSpecialCareIndicators(array $data, int $adl): bool
    {
        // Stage 3/4 pressure ulcers with turning program
        $severeUlcer = ($data['iI1a'] ?? 0) >= 3;

        // Complex feeding with aphasia
        $feedingIssue = ($data['iK1a'] ?? 0) >= 2;

        // Significant weight loss
        $weightLoss = ($data['iK5a'] ?? 0) >= 1;

        // Special care requires ADL >= 7 for most indicators
        if ($adl >= 7) {
            return $severeUlcer || $feedingIssue || $weightLoss;
        }

        return false;
    }

    /**
     * Check for clinically complex indicators.
     */
    protected function hasClinicallyComplexIndicators(array $data, int $adl): bool
    {
        // CHESS score indicating health instability
        $healthInstability = ($data['chess'] ?? 0) >= 3;

        // End-stage disease indicators
        $endStage = ($data['iP1ak'] ?? 0) > 0 // Dialysis
            || ($data['iP1al'] ?? 0) > 0; // Chemo

        // Oxygen therapy
        $oxygen = ($data['iP1ah'] ?? 0) > 0;

        // Pain issues
        $painIssue = ($data['iJ1a'] ?? 0) >= 2 && ($data['iJ1b'] ?? 0) >= 2;

        return $healthInstability || $endStage || $oxygen || $painIssue;
    }

    /**
     * Check for behaviour problems.
     */
    protected function hasBehaviourProblems(array $data): bool
    {
        $behaviourItems = [
            $data['iE3a'] ?? 0, // Wandering
            $data['iE3b'] ?? 0, // Verbal abuse
            $data['iE3c'] ?? 0, // Physical abuse
            $data['iE3d'] ?? 0, // Socially inappropriate
            $data['iE3e'] ?? 0, // Resists care
            $data['iE3f'] ?? 0, // Disruptive
        ];

        // Has behaviour problems if any item indicates daily occurrence
        return collect($behaviourItems)->contains(fn($score) => $score >= 2);
    }

    /**
     * Compute extensive count for SE group determination.
     */
    protected function computeExtensiveCount(array $data, array $flags): int
    {
        $count = 0;

        if ($flags['special_care']) $count++;
        if ($flags['clinically_complex']) $count++;
        if ($flags['impaired_cognition']) $count++;
        if (($data['iP1ab'] ?? 0) > 0) $count++; // IV feeding
        if (($data['iP1aa'] ?? 0) > 0) $count++; // IV meds

        return $count;
    }

    /**
     * Determine RUG group using CIHI hierarchy.
     */
    protected function determineRugGroup(
        array $data,
        int $adl,
        int $iadl,
        int $cps,
        array $flags,
        int $extensiveCount
    ): string {
        // 1. Special Rehabilitation
        if ($flags['rehab']) {
            if ($adl >= 11) return 'RB0';
            if ($adl >= 4) {
                return $iadl > 1 ? 'RA2' : 'RA1';
            }
        }

        // 2. Extensive Services
        if ($flags['extensive_services'] && $adl >= 7) {
            if ($extensiveCount >= 4) return 'SE3';
            if ($extensiveCount >= 2) return 'SE2';
            return 'SE1';
        }

        // 3. Special Care
        if ($flags['special_care'] || ($flags['extensive_services'] && $adl <= 6)) {
            if ($adl >= 14) return 'SSB';
            return 'SSA';
        }

        // 4. Clinically Complex
        if ($flags['clinically_complex'] || $flags['special_care']) {
            if ($adl >= 11) return 'CC0';
            if ($adl >= 6) return 'CB0';
            return $iadl >= 1 ? 'CA2' : 'CA1';
        }

        // 5. Impaired Cognition
        if ($flags['impaired_cognition'] && $adl <= 10) {
            if ($adl >= 6) return 'IB0';
            return $iadl >= 1 ? 'IA2' : 'IA1';
        }

        // 6. Behaviour Problems
        if ($flags['behaviour_problems'] && $adl <= 10) {
            if ($adl >= 6) return 'BB0';
            return $iadl >= 1 ? 'BA2' : 'BA1';
        }

        // 7. Reduced Physical Function (catch-all)
        if ($adl >= 11) return 'PD0';
        if ($adl >= 9) return 'PC0';
        if ($adl >= 6) return 'PB0';
        return $iadl >= 1 ? 'PA2' : 'PA1';
    }

    /**
     * Get ADL items for computation details.
     */
    protected function getADLItems(array $data): array
    {
        return [
            'bed_mobility' => $data['iG1ha'] ?? 0,
            'transfer' => $data['iG1ia'] ?? 0,
            'toilet_use' => $data['iG1ea'] ?? 0,
            'eating' => $data['iG1ja'] ?? 0,
        ];
    }

    /**
     * Get IADL items for computation details.
     */
    protected function getIADLItems(array $data): array
    {
        return [
            'meal_prep' => $data['iG1aa'] ?? 0,
            'housework' => $data['iG1da'] ?? 0,
            'finances' => $data['iG1eb'] ?? 0,
        ];
    }

    /**
     * Get therapy items for computation details.
     */
    protected function getTherapyItems(array $data): array
    {
        return [
            'pt_minutes' => $data['iN3eb'] ?? 0,
            'ot_minutes' => $data['iN3fb'] ?? 0,
            'slp_minutes' => $data['iN3gb'] ?? 0,
        ];
    }

    /**
     * Reclassify a patient based on their latest assessment.
     */
    public function reclassifyPatient(int $patientId): ?RUGClassification
    {
        $assessment = InterraiAssessment::where('patient_id', $patientId)
            ->where('is_current', true)
            ->orderBy('assessment_date', 'desc')
            ->first();

        if (!$assessment) {
            Log::warning('Cannot reclassify patient - no current assessment', [
                'patient_id' => $patientId,
            ]);
            return null;
        }

        return $this->classify($assessment);
    }
}
