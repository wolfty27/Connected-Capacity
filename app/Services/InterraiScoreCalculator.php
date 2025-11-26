<?php

namespace App\Services;

/**
 * InterRAI Score Calculator Service
 *
 * Implements the official InterRAI algorithms for calculating output scales:
 * - CPS (Cognitive Performance Scale): 0-6
 * - ADL Hierarchy: 0-6
 * - IADL Difficulty: 0-6
 * - CHESS (Changes in Health, End-stage, Signs & Symptoms): 0-5
 * - DRS (Depression Rating Scale): 0-14
 * - Pain Scale: 0-4
 * - MAPLe (Method for Assigning Priority Levels): 1-5
 */
class InterraiScoreCalculator
{
    /**
     * Calculate all output scales from raw assessment items.
     */
    public function calculateAllScores(array $items): array
    {
        return [
            'cognitive_performance_scale' => $this->calculateCPS($items),
            'adl_hierarchy' => $this->calculateADLHierarchy($items),
            'iadl_difficulty' => $this->calculateIADLDifficulty($items),
            'iadl_capacity' => $this->calculateIADLCapacity($items),
            'chess_score' => $this->calculateCHESS($items),
            'depression_rating_scale' => $this->calculateDRS($items),
            'pain_scale' => $this->calculatePainScale($items),
            'maple_score' => $this->calculateMAPLe($items),
        ];
    }

    /**
     * Calculate CPS (Cognitive Performance Scale).
     *
     * Based on:
     * - C1: Cognitive skills for daily decision making
     * - C2a: Short-term memory
     * - C3: Making self understood (communication)
     * - G5k: Eating ADL
     *
     * Scale: 0 = Intact, 1-2 = Mild, 3-4 = Moderate, 5-6 = Severe
     */
    public function calculateCPS(array $items): ?int
    {
        $c1 = $items['C1'] ?? null;      // Decision making (0-5)
        $c2a = $items['C2a'] ?? null;    // Short-term memory (0-1)
        $c3 = $items['C3'] ?? null;      // Making self understood (0-4)
        $g5k = $items['G5k'] ?? null;    // Eating ADL (0-6)

        if ($c1 === null) {
            return null;
        }

        // CPS Algorithm (simplified version of official InterRAI algorithm)
        // No discernible consciousness
        if ($c1 == 5) {
            return 6;
        }

        // Calculate memory impairment
        $memoryImpaired = ($c2a ?? 0) == 1;

        // Calculate communication impairment level
        $commLevel = min(($c3 ?? 0), 4);

        // Calculate eating dependence
        $eatingDep = in_array($g5k, [4, 5, 6]) ? 1 : 0;

        // CPS calculation
        if ($c1 <= 1 && !$memoryImpaired && $commLevel <= 1) {
            return 0; // Intact
        } elseif ($c1 <= 1) {
            return 1; // Borderline intact
        } elseif ($c1 == 2) {
            return 2; // Mild impairment
        } elseif ($c1 == 3 && $commLevel <= 2) {
            return 3; // Moderate impairment
        } elseif ($c1 == 3 || $c1 == 4) {
            if ($eatingDep) {
                return 5; // Moderately severe impairment
            }
            return 4; // Moderate-severe impairment
        }

        return 4;
    }

    /**
     * Calculate ADL Hierarchy Scale.
     *
     * Based on 4 early-loss ADLs:
     * - G5c: Personal hygiene
     * - G5i: Toilet use
     * - G5g: Locomotion
     * - G5k: Eating
     *
     * Scale: 0-6 where 0 = Independent, 6 = Total dependence
     */
    public function calculateADLHierarchy(array $items): ?int
    {
        $hygiene = $items['G5c'] ?? null;     // Personal hygiene
        $toilet = $items['G5i'] ?? null;      // Toilet use
        $locomotion = $items['G5g'] ?? null;  // Locomotion
        $eating = $items['G5k'] ?? null;      // Eating

        if ($hygiene === null && $toilet === null && $locomotion === null && $eating === null) {
            return null;
        }

        // Convert "activity did not occur" (8) to max dependence for calculation
        $normalize = fn($val) => ($val === 8 || $val === null) ? 0 : min($val, 6);

        $h = $normalize($hygiene);
        $t = $normalize($toilet);
        $l = $normalize($locomotion);
        $e = $normalize($eating);

        // ADL Hierarchy Algorithm
        // Based on the InterRAI algorithm, higher values in early-loss ADLs indicate more impairment

        // All independent
        if ($h <= 1 && $t <= 1 && $l <= 1 && $e <= 1) {
            return 0;
        }

        // Supervision only needed
        if ($h <= 2 && $t <= 2 && $l <= 2 && $e <= 2) {
            return 1;
        }

        // Limited impairment
        if ($h <= 3 && $t <= 3 && $l <= 3 && $e <= 3) {
            return 2;
        }

        // Extensive assistance needed in any
        if (max($h, $t, $l, $e) <= 4) {
            return 3;
        }

        // Maximal assistance in locomotion or eating
        if ($l >= 5 || $e >= 5) {
            if ($e >= 6) {
                return 6; // Total dependence
            }
            return 5; // Dependence
        }

        return 4; // Extensive dependence
    }

    /**
     * Calculate IADL Difficulty Scale.
     *
     * Based on sum of difficulty across IADL items G4a-G4h.
     * Scale: 0-6
     */
    public function calculateIADLDifficulty(array $items): ?int
    {
        $iadlItems = ['G4a', 'G4b', 'G4c', 'G4d', 'G4e', 'G4f', 'G4g', 'G4h'];
        $sum = 0;
        $count = 0;

        foreach ($iadlItems as $key) {
            $val = $items[$key] ?? null;
            if ($val !== null && $val != 8) { // Exclude "activity did not occur"
                $sum += min($val, 6);
                $count++;
            }
        }

        if ($count === 0) {
            return null;
        }

        // Calculate difficulty level (0-6 scale)
        $avg = $sum / $count;

        if ($avg <= 0.5) return 0;
        if ($avg <= 1.5) return 1;
        if ($avg <= 2.5) return 2;
        if ($avg <= 3.5) return 3;
        if ($avg <= 4.5) return 4;
        if ($avg <= 5.5) return 5;
        return 6;
    }

    /**
     * Calculate IADL Capacity (inverted from difficulty).
     */
    public function calculateIADLCapacity(array $items): ?int
    {
        $difficulty = $this->calculateIADLDifficulty($items);
        return $difficulty !== null ? (6 - $difficulty) : null;
    }

    /**
     * Calculate CHESS Score (Changes in Health, End-Stage Disease, Signs and Symptoms).
     *
     * Indicators of health instability:
     * - Vomiting (J5)
     * - Dehydration (J6)
     * - Weight loss (J4)
     * - Shortness of breath (J2a)
     * - Edema (J2c)
     * - Decline in cognition
     * - Decline in ADL
     * - End-stage disease
     *
     * Scale: 0-5 where 0 = Stable, 5 = Unstable
     */
    public function calculateCHESS(array $items): ?int
    {
        $score = 0;

        // Vomiting
        if (($items['J5'] ?? 0) >= 1) {
            $score++;
        }

        // Dehydration
        if (($items['J6'] ?? 0) >= 1) {
            $score++;
        }

        // Weight loss (5% in 30 days or 10% in 180 days)
        if (($items['J4'] ?? 0) >= 1) {
            $score++;
        }

        // Shortness of breath
        if (($items['J2a'] ?? 0) >= 1) {
            $score++;
        }

        // Edema
        if (($items['J2c'] ?? 0) >= 1) {
            $score++;
        }

        return min($score, 5);
    }

    /**
     * Calculate DRS (Depression Rating Scale).
     *
     * Based on mood indicators E1a-E1g (sum of scores).
     * Scale: 0-14 where 0 = No depression indicators, 14 = All indicators present daily
     */
    public function calculateDRS(array $items): ?int
    {
        $moodItems = ['E1a', 'E1b', 'E1c', 'E1d', 'E1e', 'E1f', 'E1g'];
        $sum = 0;
        $hasData = false;

        foreach ($moodItems as $key) {
            $val = $items[$key] ?? null;
            if ($val !== null) {
                $sum += min($val, 2);
                $hasData = true;
            }
        }

        return $hasData ? min($sum, 14) : null;
    }

    /**
     * Calculate Pain Scale.
     *
     * Based on J1a (frequency) and J1b (intensity).
     * Scale: 0-4
     */
    public function calculatePainScale(array $items): ?int
    {
        $frequency = $items['J1a'] ?? null;  // 0=No pain, 1=Not in last 3 days, 2=Less than daily, 3=Daily
        $intensity = $items['J1b'] ?? null;  // 1=Mild, 2=Moderate, 3=Severe, 4=Horrible

        if ($frequency === null || $frequency == 0) {
            return 0; // No pain
        }

        if ($frequency == 1) {
            return 0; // Pain not in last 3 days
        }

        $intensityScore = $intensity ?? 1;

        // Pain Scale algorithm
        if ($frequency == 2) { // Less than daily
            return min($intensityScore, 2);
        }

        // Daily pain
        if ($intensityScore <= 2) {
            return 2; // Moderate
        } elseif ($intensityScore == 3) {
            return 3; // Severe
        }

        return 4; // Excruciating
    }

    /**
     * Calculate MAPLe Score (Method for Assigning Priority Levels).
     *
     * Determines service allocation priority based on:
     * - ADL impairment
     * - Cognitive impairment
     * - Behavior problems
     * - Falls/wandering risk
     * - Caregiver distress
     *
     * Scale: 1-5 where 1 = Low, 5 = Very High
     */
    public function calculateMAPLe(array $items): ?int
    {
        $adl = $this->calculateADLHierarchy($items);
        $cps = $this->calculateCPS($items);

        if ($adl === null && $cps === null) {
            return null;
        }

        $adl = $adl ?? 0;
        $cps = $cps ?? 0;

        // Risk factors
        $falls = ($items['J3'] ?? 0) >= 1;
        $wandering = ($items['wandering'] ?? 0) >= 1;
        $caregiverStress = ($items['P2'] ?? 0) >= 1;

        // MAPLe Algorithm (simplified)
        $score = 1; // Start at low

        // ADL impairment contribution
        if ($adl >= 4) {
            $score = max($score, 4);
        } elseif ($adl >= 2) {
            $score = max($score, 3);
        } elseif ($adl >= 1) {
            $score = max($score, 2);
        }

        // Cognitive impairment contribution
        if ($cps >= 4) {
            $score = max($score, 4);
        } elseif ($cps >= 2) {
            $score = max($score, 3);
        }

        // Risk factors can push to very high
        if ($caregiverStress && ($adl >= 2 || $cps >= 2)) {
            $score = max($score, 4);
        }

        if ($falls && $wandering) {
            $score = max($score, 5);
        } elseif ($falls || $wandering) {
            $score = max($score, 4);
        }

        // Combinations that trigger highest priority
        if ($adl >= 3 && $cps >= 3) {
            $score = 5;
        }

        return $score;
    }

    /**
     * Determine which CAPs (Clinical Assessment Protocols) are triggered.
     */
    public function getTriggeredCAPs(array $items): array
    {
        $caps = [];

        // Falls CAP
        if (($items['J3'] ?? 0) >= 1) {
            $caps[] = ['code' => 'FALLS', 'name' => 'Falls Prevention', 'priority' => 'high'];
        }

        // Pain CAP
        if ($this->calculatePainScale($items) >= 2) {
            $caps[] = ['code' => 'PAIN', 'name' => 'Pain Management', 'priority' => 'medium'];
        }

        // Depression CAP
        if ($this->calculateDRS($items) >= 3) {
            $caps[] = ['code' => 'MOOD', 'name' => 'Mood/Depression', 'priority' => 'medium'];
        }

        // Cognitive CAP
        if ($this->calculateCPS($items) >= 2) {
            $caps[] = ['code' => 'COGNITION', 'name' => 'Cognitive Loss', 'priority' => 'high'];
        }

        // ADL/Rehab CAP
        if ($this->calculateADLHierarchy($items) >= 2) {
            $caps[] = ['code' => 'ADL_REHAB', 'name' => 'ADL/Rehabilitation', 'priority' => 'medium'];
        }

        // Continence CAP
        if (($items['H1'] ?? 0) >= 3 || ($items['H2'] ?? 0) >= 3) {
            $caps[] = ['code' => 'CONTINENCE', 'name' => 'Bladder/Bowel Management', 'priority' => 'medium'];
        }

        // Caregiver Support CAP
        if (($items['P2'] ?? 0) >= 1) {
            $caps[] = ['code' => 'CAREGIVER', 'name' => 'Caregiver Support', 'priority' => 'high'];
        }

        // Nutrition CAP
        if (($items['J4'] ?? 0) >= 1 || ($items['J6'] ?? 0) >= 1) {
            $caps[] = ['code' => 'NUTRITION', 'name' => 'Nutrition/Hydration', 'priority' => 'high'];
        }

        return $caps;
    }

    /**
     * Get recommended PSW hours based on scores.
     */
    public function getRecommendedPswHours(array $scores): float
    {
        $adl = $scores['adl_hierarchy'] ?? 0;
        $maple = $scores['maple_score'] ?? 1;

        // Base hours by MAPLe level
        $baseHours = match ($maple) {
            1 => 3.5,   // Low
            2 => 7.0,   // Mild
            3 => 14.0,  // Moderate
            4 => 21.0,  // High
            5 => 28.0,  // Very High
            default => 7.0,
        };

        // Adjust by ADL
        if ($adl >= 5) {
            $baseHours *= 1.5;
        } elseif ($adl >= 3) {
            $baseHours *= 1.25;
        }

        return min(round($baseHours, 1), 56.0); // Cap at 56 hours/week
    }
}
