<?php

namespace App\Services\BundleEngine\Derivers;

use App\Models\Patient;
use App\Models\Referral;
use App\Services\BundleEngine\DTOs\PatientNeedsProfile;

/**
 * EpisodeTypeDeriver
 *
 * Derives the episode type for a patient based on available data.
 *
 * Episode types are policy-laden fields - they affect bundling decisions
 * and must be derived via explicit rules, not set randomly.
 *
 * Episode Types:
 * - 'post_acute': Recent hospital discharge, rehab potential, typically front-loaded therapy
 * - 'chronic': Stable long-term condition, maintenance-focused
 * - 'complex_continuing': Long-term with multiple complexities, high care intensity
 * - 'acute_exacerbation': Acute flare-up of chronic condition
 * - 'palliative': End-of-life focused care
 *
 * Priority Order (per design document Section 2.3):
 * 1. Explicit referral type
 * 2. Hospital discharge indicators (days since discharge, surgery type)
 * 3. InterRAI assessment patterns (decline, prognosis, therapy indicators)
 * 4. Default based on profile characteristics
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 2.3.1
 */
class EpisodeTypeDeriver
{
    /**
     * Threshold: days since hospital discharge to consider post-acute.
     */
    protected const POST_ACUTE_DAYS_THRESHOLD = 30;

    /**
     * Derive episode type for a patient.
     *
     * @param Patient $patient The patient
     * @param array $assessmentData Mapped assessment data
     * @param Referral|null $referral The referral if available
     *
     * @return string Episode type: 'post_acute', 'chronic', 'complex_continuing', 'acute_exacerbation', 'palliative'
     */
    public function derive(Patient $patient, array $assessmentData, ?Referral $referral = null): string
    {
        // Priority 1: Explicit referral type
        if ($referral) {
            $episodeFromReferral = $this->deriveFromReferral($referral);
            if ($episodeFromReferral !== null) {
                return $episodeFromReferral;
            }
        }

        // Priority 2: Hospital discharge indicators
        $episodeFromDischarge = $this->deriveFromDischargeData($patient, $referral);
        if ($episodeFromDischarge !== null) {
            return $episodeFromDischarge;
        }

        // Priority 3: InterRAI assessment patterns
        $episodeFromAssessment = $this->deriveFromAssessmentPatterns($assessmentData);
        if ($episodeFromAssessment !== null) {
            return $episodeFromAssessment;
        }

        // Priority 4: Default based on profile characteristics
        return $this->deriveDefaultEpisodeType($assessmentData);
    }

    /**
     * Derive episode type from referral information.
     */
    protected function deriveFromReferral(?Referral $referral): ?string
    {
        if (!$referral) {
            return null;
        }

        // Check for explicit episode type on referral
        $referralType = $referral->referral_type ?? $referral->type ?? null;

        if ($referralType) {
            return match (strtolower($referralType)) {
                'post_acute', 'post-acute', 'hospital_discharge' => 'post_acute',
                'chronic', 'maintenance' => 'chronic',
                'complex', 'complex_continuing' => 'complex_continuing',
                'acute', 'acute_exacerbation', 'flare' => 'acute_exacerbation',
                'palliative', 'end_of_life', 'hospice' => 'palliative',
                default => null,
            };
        }

        // Check referral source
        $source = $referral->source ?? $referral->referral_source ?? null;
        if ($source) {
            $sourceLower = strtolower($source);
            if (str_contains($sourceLower, 'hospital') || str_contains($sourceLower, 'discharge')) {
                return 'post_acute';
            }
        }

        // Check for program indicators
        $program = $referral->program ?? $referral->program_type ?? null;
        if ($program) {
            $programLower = strtolower($program);
            if (str_contains($programLower, 'transitional') || str_contains($programLower, 'ohah')) {
                return 'post_acute';
            }
            if (str_contains($programLower, 'palliative') || str_contains($programLower, 'hospice')) {
                return 'palliative';
            }
        }

        return null;
    }

    /**
     * Derive episode type from hospital discharge data.
     */
    protected function deriveFromDischargeData(Patient $patient, ?Referral $referral): ?string
    {
        // Check days since hospital discharge
        $dischargeDate = $referral?->discharge_date 
            ?? $referral?->hospital_discharge_date 
            ?? $patient->last_discharge_date 
            ?? null;

        if ($dischargeDate) {
            $daysSinceDischarge = now()->diffInDays($dischargeDate);

            if ($daysSinceDischarge <= self::POST_ACUTE_DAYS_THRESHOLD) {
                // Recent discharge = post-acute
                return 'post_acute';
            }
        }

        // Check for surgery indicators
        $surgeryType = $referral?->surgery_type ?? $referral?->procedure_type ?? null;
        if ($surgeryType) {
            // Any recent surgery suggests post-acute
            return 'post_acute';
        }

        return null;
    }

    /**
     * Derive episode type from InterRAI assessment patterns.
     */
    protected function deriveFromAssessmentPatterns(array $assessmentData): ?string
    {
        // Check for palliative indicators
        if ($this->hasPalliativeIndicators($assessmentData)) {
            return 'palliative';
        }

        // Check for acute exacerbation indicators
        if ($this->hasAcuteExacerbationIndicators($assessmentData)) {
            return 'acute_exacerbation';
        }

        // Check for post-acute indicators (high therapy, rehab potential)
        if ($this->hasPostAcuteIndicators($assessmentData)) {
            return 'post_acute';
        }

        // Check for complex continuing indicators
        if ($this->hasComplexContinuingIndicators($assessmentData)) {
            return 'complex_continuing';
        }

        return null;
    }

    /**
     * Check for palliative/end-of-life indicators.
     */
    protected function hasPalliativeIndicators(array $data): bool
    {
        // VERIFY: Check actual field names
        $prognosis = $data['prognosis'] ?? $data['life_expectancy'] ?? null;
        if ($prognosis !== null && (int) $prognosis <= 2) {
            return true; // Poor prognosis
        }

        // End-stage disease indicator
        if (($data['end_stage_disease'] ?? false) === true) {
            return true;
        }

        // Hospice referral
        if (($data['hospice_enrolled'] ?? false) === true) {
            return true;
        }

        return false;
    }

    /**
     * Check for acute exacerbation indicators.
     */
    protected function hasAcuteExacerbationIndicators(array $data): bool
    {
        // High health instability (CHESS 4+)
        if (($data['healthInstability'] ?? 0) >= 4) {
            return true;
        }

        // Recent acute change in status
        if (($data['acute_change'] ?? false) === true) {
            return true;
        }

        // Flare-up of chronic condition
        if (($data['condition_flare'] ?? false) === true) {
            return true;
        }

        return false;
    }

    /**
     * Check for post-acute indicators.
     */
    protected function hasPostAcuteIndicators(array $data): bool
    {
        // High weekly therapy minutes (suggests active rehab)
        if (($data['weeklyTherapyMinutes'] ?? 0) >= 60) {
            return true;
        }

        // Has rehab potential and therapy ordered
        if (($data['hasRehabPotential'] ?? false) && ($data['weeklyTherapyMinutes'] ?? 0) > 0) {
            return true;
        }

        // RUG category is Special Rehabilitation
        if (($data['rugCategory'] ?? '') === 'Special Rehabilitation') {
            return true;
        }

        return false;
    }

    /**
     * Check for complex continuing indicators.
     */
    protected function hasComplexContinuingIndicators(array $data): bool
    {
        // High ADL + cognitive complexity
        $adl = $data['adlSupportLevel'] ?? 0;
        $cognitive = $data['cognitiveComplexity'] ?? 0;
        $behavioural = $data['behaviouralComplexity'] ?? 0;

        if ($adl >= 4 && $cognitive >= 3) {
            return true;
        }

        // High behavioural complexity
        if ($behavioural >= 3) {
            return true;
        }

        // Requires extensive services
        if (($data['requiresExtensiveServices'] ?? false) === true) {
            return true;
        }

        // Multiple active conditions
        if (count($data['activeConditions'] ?? []) >= 4) {
            return true;
        }

        return false;
    }

    /**
     * Derive default episode type based on profile characteristics.
     */
    protected function deriveDefaultEpisodeType(array $data): string
    {
        $adl = $data['adlSupportLevel'] ?? 0;
        $cognitive = $data['cognitiveComplexity'] ?? 0;
        $instability = $data['healthInstability'] ?? 0;

        // High complexity indicators = complex_continuing
        if ($adl >= 4 || $cognitive >= 4 || $instability >= 4) {
            return 'complex_continuing';
        }

        // Default to chronic for stable ongoing care
        return 'chronic';
    }

    /**
     * Get confidence level for the derived episode type.
     *
     * @param string $derivationMethod How the episode type was derived
     * @return string 'high', 'medium', 'low'
     */
    public function getConfidence(string $derivationMethod): string
    {
        return match ($derivationMethod) {
            'explicit_referral' => 'high',
            'discharge_date' => 'high',
            'surgery_type' => 'high',
            'assessment_patterns' => 'medium',
            'default' => 'low',
            default => 'low',
        };
    }

    /**
     * Validate an episode type value.
     */
    public static function isValidEpisodeType(string $type): bool
    {
        return in_array($type, [
            'post_acute',
            'chronic',
            'complex_continuing',
            'acute_exacerbation',
            'palliative',
        ]);
    }

    /**
     * Get all valid episode types.
     */
    public static function getAllEpisodeTypes(): array
    {
        return [
            'post_acute' => [
                'label' => 'Post-Acute',
                'description' => 'Recent hospital discharge, rehabilitation focus',
            ],
            'chronic' => [
                'label' => 'Chronic',
                'description' => 'Stable long-term condition, maintenance care',
            ],
            'complex_continuing' => [
                'label' => 'Complex Continuing',
                'description' => 'Long-term with multiple complexities',
            ],
            'acute_exacerbation' => [
                'label' => 'Acute Exacerbation',
                'description' => 'Acute flare-up of chronic condition',
            ],
            'palliative' => [
                'label' => 'Palliative',
                'description' => 'End-of-life focused care',
            ],
        ];
    }
}

