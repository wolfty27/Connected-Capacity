<?php

namespace App\Services\BundleEngine\Derivers;

use App\Models\Patient;
use App\Models\Referral;

/**
 * RehabPotentialDeriver
 *
 * Derives the rehabilitation potential score for a patient.
 *
 * Rehab potential is a policy-laden field that affects bundling decisions:
 * - Patients with high rehab potential → RECOVERY_REHAB axis more applicable
 * - Patients with low rehab potential → SAFETY_STABILITY axis more applicable
 *
 * The score is 0-100 points, with hasRehabPotential = (score >= 40).
 *
 * Scoring Factors (per design document Section 2.3.2):
 * - Episode type indicators (+20-30 points)
 * - Therapy recommendations from assessment (+15-20 points)
 * - Functional improvement potential indicators (+10-15 points)
 * - Age and prognosis modifiers (+/- 5-10 points)
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 2.3.2
 */
class RehabPotentialDeriver
{
    /**
     * Threshold score for hasRehabPotential flag.
     */
    public const POTENTIAL_THRESHOLD = 40;

    /**
     * Maximum possible score.
     */
    public const MAX_SCORE = 100;

    /**
     * Derive rehabilitation potential score and flag.
     *
     * @param array $assessmentData Mapped assessment data
     * @param string|null $episodeType Derived episode type
     * @param Referral|null $referral Referral data if available
     *
     * @return array{score: int, hasRehabPotential: bool, factors: array}
     */
    public function derive(array $assessmentData, ?string $episodeType = null, ?Referral $referral = null): array
    {
        $score = 0;
        $factors = [];

        // Factor 1: Episode Type Indicators (max 30 points)
        $episodeScore = $this->scoreEpisodeType($episodeType);
        $score += $episodeScore['points'];
        if ($episodeScore['points'] > 0) {
            $factors[] = $episodeScore['reason'];
        }

        // Factor 2: Therapy Recommendations (max 20 points)
        $therapyScore = $this->scoreTherapyIndicators($assessmentData);
        $score += $therapyScore['points'];
        if ($therapyScore['points'] > 0) {
            $factors[] = $therapyScore['reason'];
        }

        // Factor 3: Functional Improvement Potential (max 20 points)
        $functionalScore = $this->scoreFunctionalPotential($assessmentData);
        $score += $functionalScore['points'];
        if ($functionalScore['points'] > 0) {
            $factors[] = $functionalScore['reason'];
        }

        // Factor 4: ADL/Mobility Status (max 15 points)
        $adlScore = $this->scoreAdlStatus($assessmentData);
        $score += $adlScore['points'];
        if ($adlScore['points'] > 0) {
            $factors[] = $adlScore['reason'];
        }

        // Factor 5: Cognitive Capacity (max 10 points)
        $cognitiveScore = $this->scoreCognitiveCapacity($assessmentData);
        $score += $cognitiveScore['points'];
        if ($cognitiveScore['points'] > 0) {
            $factors[] = $cognitiveScore['reason'];
        }

        // Factor 6: Referral Indicators (max 15 points)
        if ($referral) {
            $referralScore = $this->scoreReferralIndicators($referral);
            $score += $referralScore['points'];
            if ($referralScore['points'] > 0) {
                $factors[] = $referralScore['reason'];
            }
        }

        // Negative Modifiers
        $negativeModifiers = $this->calculateNegativeModifiers($assessmentData);
        $score += $negativeModifiers['points']; // Will be negative
        if ($negativeModifiers['points'] < 0) {
            $factors[] = $negativeModifiers['reason'];
        }

        // Ensure score is within bounds
        $score = max(0, min(self::MAX_SCORE, $score));

        return [
            'score' => $score,
            'hasRehabPotential' => $score >= self::POTENTIAL_THRESHOLD,
            'factors' => $factors,
        ];
    }

    /**
     * Score based on episode type.
     */
    protected function scoreEpisodeType(?string $episodeType): array
    {
        if ($episodeType === null) {
            return ['points' => 0, 'reason' => null];
        }

        return match ($episodeType) {
            'post_acute' => [
                'points' => 30,
                'reason' => 'Post-acute episode with high rehab potential (+30)',
            ],
            'acute_exacerbation' => [
                'points' => 20,
                'reason' => 'Acute exacerbation with recovery potential (+20)',
            ],
            'chronic' => [
                'points' => 10,
                'reason' => 'Chronic maintenance with some improvement potential (+10)',
            ],
            'complex_continuing' => [
                'points' => 5,
                'reason' => 'Complex continuing care with limited rehab focus (+5)',
            ],
            'palliative' => [
                'points' => 0,
                'reason' => 'Palliative focus, rehab not primary goal',
            ],
            default => ['points' => 0, 'reason' => null],
        };
    }

    /**
     * Score based on therapy indicators from assessment.
     */
    protected function scoreTherapyIndicators(array $data): array
    {
        $points = 0;
        $reasons = [];

        // Weekly therapy minutes scheduled
        $therapyMinutes = $data['weeklyTherapyMinutes'] ?? 0;
        if ($therapyMinutes >= 60) {
            $points += 15;
            $reasons[] = "Active therapy plan ({$therapyMinutes}+ min/week)";
        } elseif ($therapyMinutes >= 30) {
            $points += 10;
            $reasons[] = "Moderate therapy plan ({$therapyMinutes} min/week)";
        } elseif ($therapyMinutes > 0) {
            $points += 5;
            $reasons[] = "Light therapy plan ({$therapyMinutes} min/week)";
        }

        // Therapy recommendation in assessment
        if (($data['therapy_recommended'] ?? false) === true) {
            $points += 5;
            $reasons[] = 'Therapy recommended in assessment';
        }

        return [
            'points' => min(20, $points),
            'reason' => $points > 0 ? implode('; ', $reasons) . " (+{$points})" : null,
        ];
    }

    /**
     * Score based on functional improvement potential.
     */
    protected function scoreFunctionalPotential(array $data): array
    {
        $points = 0;
        $reasons = [];

        // Recent functional decline (suggests room for recovery)
        if (($data['recent_decline'] ?? false) === true) {
            $points += 10;
            $reasons[] = 'Recent functional decline (recovery potential)';
        }

        // Not at baseline (suggests improvement possible)
        if (($data['not_at_baseline'] ?? false) === true) {
            $points += 10;
            $reasons[] = 'Below functional baseline';
        }

        // Improvement noted in last assessment
        if (($data['improvement_noted'] ?? false) === true) {
            $points += 10;
            $reasons[] = 'Recent improvement documented';
        }

        // Motivated patient indicator
        if (($data['patient_motivated'] ?? false) === true) {
            $points += 5;
            $reasons[] = 'Patient motivated for rehab';
        }

        return [
            'points' => min(20, $points),
            'reason' => $points > 0 ? implode('; ', $reasons) . " (+{$points})" : null,
        ];
    }

    /**
     * Score based on ADL/mobility status.
     *
     * Moderate impairment suggests more rehab potential than severe.
     */
    protected function scoreAdlStatus(array $data): array
    {
        $adl = $data['adlSupportLevel'] ?? 0;
        $mobility = $data['mobilityComplexity'] ?? 0;

        // Sweet spot: moderate impairment (2-4) has most rehab potential
        // Severe impairment (5-6) or minimal impairment (0-1) have less

        if ($adl >= 2 && $adl <= 4) {
            $points = 15;
            $reason = "Moderate ADL impairment - good rehab candidate (+15)";
        } elseif ($adl >= 5) {
            $points = 5;
            $reason = "Severe ADL impairment - limited but possible (+5)";
        } else {
            $points = 0;
            $reason = null;
        }

        // Mobility bonus
        if ($mobility >= 2 && $mobility <= 4) {
            $points += 5;
            $reason = ($reason ?? '') . "; Moderate mobility impairment (+5)";
        }

        return [
            'points' => min(15, $points),
            'reason' => $reason,
        ];
    }

    /**
     * Score based on cognitive capacity.
     *
     * Better cognition = better participation in rehab.
     */
    protected function scoreCognitiveCapacity(array $data): array
    {
        $cognitive = $data['cognitiveComplexity'] ?? 0;

        if ($cognitive <= 1) {
            return [
                'points' => 10,
                'reason' => 'Intact cognition supports rehab participation (+10)',
            ];
        } elseif ($cognitive <= 2) {
            return [
                'points' => 7,
                'reason' => 'Mild cognitive impairment - can participate (+7)',
            ];
        } elseif ($cognitive <= 3) {
            return [
                'points' => 4,
                'reason' => 'Moderate cognitive impairment - may need adapted approach (+4)',
            ];
        }

        return ['points' => 0, 'reason' => null];
    }

    /**
     * Score based on referral indicators.
     */
    protected function scoreReferralIndicators(Referral $referral): array
    {
        $points = 0;
        $reasons = [];

        // Referral explicitly mentions rehab
        $notes = strtolower($referral->notes ?? '');
        $reason = strtolower($referral->referral_reason ?? '');

        $rehabKeywords = ['rehab', 'rehabilitation', 'therapy', 'recovery', 'restore', 'regain'];
        foreach ($rehabKeywords as $keyword) {
            if (str_contains($notes, $keyword) || str_contains($reason, $keyword)) {
                $points += 10;
                $reasons[] = 'Referral mentions rehabilitation goals';
                break;
            }
        }

        // Post-surgical referral
        if ($referral->surgery_type || $referral->procedure_type) {
            $points += 10;
            $reasons[] = 'Post-surgical recovery expected';
        }

        // Short expected length of stay (suggests acute episode with recovery)
        $expectedLos = $referral->expected_length_of_stay ?? null;
        if ($expectedLos !== null && $expectedLos <= 90) {
            $points += 5;
            $reasons[] = 'Short expected episode (time-limited recovery)';
        }

        return [
            'points' => min(15, $points),
            'reason' => $points > 0 ? implode('; ', $reasons) . " (+{$points})" : null,
        ];
    }

    /**
     * Calculate negative modifiers (reduce score).
     */
    protected function calculateNegativeModifiers(array $data): array
    {
        $points = 0;
        $reasons = [];

        // Very high cognitive impairment
        if (($data['cognitiveComplexity'] ?? 0) >= 5) {
            $points -= 15;
            $reasons[] = 'Severe cognitive impairment (-15)';
        }

        // Very high health instability
        if (($data['healthInstability'] ?? 0) >= 4) {
            $points -= 10;
            $reasons[] = 'High health instability (-10)';
        }

        // Palliative prognosis
        if (($data['prognosis'] ?? 99) <= 2) {
            $points -= 20;
            $reasons[] = 'Poor prognosis (-20)';
        }

        // Total dependence (ADL 6)
        if (($data['adlSupportLevel'] ?? 0) >= 6) {
            $points -= 10;
            $reasons[] = 'Total ADL dependence (-10)';
        }

        // Long-term decline pattern
        if (($data['long_term_decline'] ?? false) === true) {
            $points -= 10;
            $reasons[] = 'Pattern of long-term decline (-10)';
        }

        return [
            'points' => $points,
            'reason' => $points < 0 ? implode('; ', $reasons) : null,
        ];
    }

    /**
     * Get the potential level label.
     */
    public function getPotentialLevel(int $score): string
    {
        return match (true) {
            $score >= 70 => 'high',
            $score >= 40 => 'moderate',
            $score >= 20 => 'low',
            default => 'minimal',
        };
    }

    /**
     * Get the potential level description.
     */
    public function getPotentialDescription(int $score): string
    {
        return match (true) {
            $score >= 70 => 'Strong rehabilitation potential - therapy-intensive care recommended',
            $score >= 40 => 'Moderate rehabilitation potential - balanced approach recommended',
            $score >= 20 => 'Limited rehabilitation potential - focus on maintenance and safety',
            default => 'Minimal rehabilitation potential - comfort and stability focused',
        };
    }
}

