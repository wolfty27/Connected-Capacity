<?php

namespace App\Services\BundleEngine;

use App\Services\BundleEngine\Engines\DecisionTreeEngine;
use App\Services\BundleEngine\Engines\CAPTriggerEngine;
use Illuminate\Support\Facades\Log;

/**
 * AlgorithmEvaluator
 *
 * Computes all CA algorithm scores and CAP triggers for a patient profile.
 * This service bridges the raw assessment items to algorithm scores.
 *
 * @see docs/ALGORITHM_DSL.md
 * @see docs/DATA_AVAILABILITY_AUDIT.md
 */
class AlgorithmEvaluator
{
    /**
     * CA item code to HC raw_items key mapping.
     * Based on DATA_AVAILABILITY_AUDIT.md analysis.
     */
    private const CA_TO_HC_MAP = [
        // Section C - Preliminary Screener
        'C1'  => 'iB3a',           // Decision making (using CPS short-term memory)
        'C2a' => 'adl_bathing',    // Bathing
        'C2b' => 'adl_transfer',   // Bath transfer (from transfer)
        'C2c' => 'adl_hygiene',    // Personal hygiene
        'C2d' => 'adl_dressing_lower', // Dressing lower body
        'C2e' => 'adl_bed_mobility', // Locomotion (from bed mobility)
        'C3'  => 'dyspnea',        // Dyspnea
        'C4'  => null,             // Self-reported health (NOT AVAILABLE - default 1)
        'C5a' => 'mood_sad_expressions', // Loss of interest
        'C5b' => 'mood_unrealistic_fears', // Anxious/restless
        'C5c' => 'mood_crying',    // Sad/depressed/hopeless
        'C6a' => null,             // Unstable conditions (NOT AVAILABLE - derive from chess)

        // Section D - Extended Evaluation
        'D1'  => null,             // Change in decision making (NOT AVAILABLE)
        'D3a' => 'iadl_meal_prep', // Meal preparation
        'D3b' => 'iadl_housework', // Ordinary housework
        'D3c' => 'iadl_medications', // Medication management
        'D3d' => null,             // Stair use (NOT AVAILABLE)
        'D4'  => null,             // ADL decline (NOT AVAILABLE)
        'D7c' => 'edema',          // Peripheral edema
        'D7d' => 'vomiting',       // Vomiting
        'D8a' => 'pain_frequency', // Pain frequency (iJ1a)
        'D8b' => 'pain_intensity', // Pain intensity (iJ1b)
        'D10a' => null,            // Decreased intake (NOT AVAILABLE)
        'D10b' => 'weight_loss',   // Weight loss
        'D14b' => 'extensive_iv',  // IV therapy (iP1aa)
        'D14e' => 'clinical_wound', // Wound care (iI1a)
        'D15' => null,             // Last hospital stay (NOT AVAILABLE)
        'D16' => null,             // ED visits (NOT AVAILABLE)
        'D19b' => 'caregiver_stress', // Family overwhelmed

        // Additional mappings
        'B2c' => null,             // Palliative referral (derive from referral_type)
    ];

    public function __construct(
        private DecisionTreeEngine $decisionTreeEngine,
        private CAPTriggerEngine $capTriggerEngine
    ) {}

    /**
     * Evaluate all CA algorithms for given raw assessment items.
     *
     * @param array $rawItems Raw items from InterraiAssessment->raw_items
     * @param array $additionalContext Additional context (e.g., referral_type, recent events)
     * @return array<string, int|bool> Algorithm scores keyed by algorithm name
     */
    public function evaluateAllAlgorithms(array $rawItems, array $additionalContext = []): array
    {
        // Map raw_items to CA item codes
        $caInput = $this->mapToCAInput($rawItems, $additionalContext);

        $scores = [];

        // Evaluate each algorithm
        try {
            $scores['self_reliance_index'] = (bool) $this->decisionTreeEngine->evaluate('self_reliance_index', $caInput);
        } catch (\Exception $e) {
            Log::warning("SRI evaluation failed: " . $e->getMessage());
            $scores['self_reliance_index'] = false;
        }

        try {
            $scores['assessment_urgency'] = (int) $this->decisionTreeEngine->evaluate('assessment_urgency', $caInput);
        } catch (\Exception $e) {
            Log::warning("AUA evaluation failed: " . $e->getMessage());
            $scores['assessment_urgency'] = 1;
        }

        try {
            $scores['service_urgency'] = (int) $this->decisionTreeEngine->evaluate('service_urgency', $caInput);
        } catch (\Exception $e) {
            Log::warning("SUA evaluation failed: " . $e->getMessage());
            $scores['service_urgency'] = 1;
        }

        try {
            $scores['rehabilitation'] = (int) $this->decisionTreeEngine->evaluate('rehabilitation', $caInput);
        } catch (\Exception $e) {
            Log::warning("Rehab evaluation failed: " . $e->getMessage());
            $scores['rehabilitation'] = 1;
        }

        try {
            $scores['personal_support'] = (int) $this->decisionTreeEngine->evaluate('personal_support', $caInput);
        } catch (\Exception $e) {
            Log::warning("PSA evaluation failed: " . $e->getMessage());
            $scores['personal_support'] = 1;
        }

        try {
            $scores['distressed_mood'] = (int) $this->decisionTreeEngine->evaluate('distressed_mood', $caInput);
        } catch (\Exception $e) {
            Log::warning("DMS evaluation failed: " . $e->getMessage());
            $scores['distressed_mood'] = 0;
        }

        try {
            $scores['pain'] = (int) $this->decisionTreeEngine->evaluate('pain_scale', $caInput);
        } catch (\Exception $e) {
            Log::warning("Pain evaluation failed: " . $e->getMessage());
            $scores['pain'] = 0;
        }

        try {
            $scores['chess_ca'] = (int) $this->decisionTreeEngine->evaluate('chess_ca', $caInput);
        } catch (\Exception $e) {
            Log::warning("CHESS-CA evaluation failed: " . $e->getMessage());
            $scores['chess_ca'] = 0;
        }

        return $scores;
    }

    /**
     * Evaluate all CAPs for a profile's CAP input.
     *
     * @param array $capInput Output of PatientNeedsProfile::toCAPInput()
     * @return array<string, array> Triggered CAPs with their results
     */
    public function evaluateAllCAPs(array $capInput): array
    {
        return $this->capTriggerEngine->evaluateAll($capInput);
    }

    /**
     * Map raw assessment items to CA item codes.
     *
     * @param array $rawItems Raw items from InterraiAssessment->raw_items
     * @param array $additionalContext Additional context for derivations
     * @return array<string, mixed> CA item codes with values
     */
    public function mapToCAInput(array $rawItems, array $additionalContext = []): array
    {
        $caInput = [];

        foreach (self::CA_TO_HC_MAP as $caCode => $hcKey) {
            if ($hcKey === null) {
                // Handle items that need derivation or have defaults
                $caInput[$caCode] = $this->deriveUnavailableItem($caCode, $rawItems, $additionalContext);
            } else {
                $caInput[$caCode] = $rawItems[$hcKey] ?? 0;
            }
        }

        return $caInput;
    }

    /**
     * Derive values for unavailable CA items from other data.
     */
    private function deriveUnavailableItem(string $caCode, array $rawItems, array $additionalContext): mixed
    {
        return match ($caCode) {
            // Self-reported health: derive from CHESS (inverse correlation)
            'C4' => isset($rawItems['chess']) && $rawItems['chess'] >= 3 ? 3 : 1,

            // Unstable conditions: derive from CHESS score
            'C6a' => isset($rawItems['chess']) && $rawItems['chess'] >= 3 ? 1 : 0,

            // Change in decision making: not available, default 0
            'D1' => 0,

            // Stair use: not available, default 0
            'D3d' => 0,

            // ADL decline: not available, default 0
            'D4' => 0,

            // Decreased intake: not available, default 0
            'D10a' => 0,

            // Recent hospital stay: from additional context
            'D15' => ($additionalContext['has_recent_hospital_stay'] ?? false) ? 1 : 0,

            // ED visits: from additional context
            'D16' => ($additionalContext['has_recent_er_visit'] ?? false) ? 1 : 0,

            // Palliative referral: from additional context
            'B2c' => ($additionalContext['is_palliative'] ?? false) ? 1 : 0,

            // Default
            default => 0,
        };
    }

    /**
     * Get the CA to HC mapping for documentation/debugging.
     */
    public function getItemMapping(): array
    {
        return self::CA_TO_HC_MAP;
    }

    /**
     * Get available algorithms.
     */
    public function getAvailableAlgorithms(): array
    {
        return $this->decisionTreeEngine->getAvailableAlgorithms();
    }

    /**
     * Get available CAPs.
     */
    public function getAvailableCAPs(): array
    {
        return $this->capTriggerEngine->getAvailableCAPs();
    }
}

