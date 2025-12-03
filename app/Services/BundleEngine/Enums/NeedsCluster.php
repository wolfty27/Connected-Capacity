<?php

namespace App\Services\BundleEngine\Enums;

/**
 * NeedsCluster
 *
 * Simplified patient groupings for bundle selection when full RUG classification
 * is unavailable (e.g., CA-only path).
 *
 * These are NOT RUG groups - they are simplified groupings sufficient for
 * first-phase bundling from Contact Assessment data only.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 2.4
 */
enum NeedsCluster: string
{
    // === Physical Function Primary ===

    /** High physical dependency - ADL capacity 4+ */
    case HIGH_ADL = 'HIGH_ADL';

    /** Moderate physical dependency - ADL capacity 2-3 */
    case MODERATE_ADL = 'MODERATE_ADL';

    /** Low physical dependency - ADL capacity 0-1 */
    case LOW_ADL = 'LOW_ADL';

    // === Cognitive Primary ===

    /** Cognitive screen indicates impairment */
    case COGNITIVE_COMPLEX = 'COGNITIVE_COMPLEX';

    /** Mental health/behavioural primary concern */
    case MH_COMPLEX = 'MH_COMPLEX';

    // === Medical Complexity Primary ===

    /** Multiple conditions, high CHESS score */
    case MEDICAL_COMPLEX = 'MEDICAL_COMPLEX';

    /** Hospital discharge, rehabilitation potential */
    case POST_ACUTE = 'POST_ACUTE';

    // === Combined ===

    /** Both high physical dependency and cognitive impairment */
    case HIGH_ADL_COGNITIVE = 'HIGH_ADL_COGNITIVE';

    /** Low complexity, general support needs */
    case GENERAL = 'GENERAL';

    /**
     * Get human-readable label for display.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::HIGH_ADL => 'High Physical Dependency',
            self::MODERATE_ADL => 'Moderate Physical Dependency',
            self::LOW_ADL => 'Low Physical Dependency',
            self::COGNITIVE_COMPLEX => 'Cognitive Complexity',
            self::MH_COMPLEX => 'Mental Health Complexity',
            self::MEDICAL_COMPLEX => 'Medical Complexity',
            self::POST_ACUTE => 'Post-Acute / Rehabilitation',
            self::HIGH_ADL_COGNITIVE => 'High ADL + Cognitive',
            self::GENERAL => 'General Support',
        };
    }

    /**
     * Get description of the cluster.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::HIGH_ADL => 'Patient requires extensive assistance with daily living activities',
            self::MODERATE_ADL => 'Patient needs moderate support with some daily activities',
            self::LOW_ADL => 'Patient is relatively independent in daily activities',
            self::COGNITIVE_COMPLEX => 'Primary needs relate to cognitive impairment and supervision',
            self::MH_COMPLEX => 'Primary needs relate to mental health or behavioural support',
            self::MEDICAL_COMPLEX => 'Multiple medical conditions requiring clinical monitoring',
            self::POST_ACUTE => 'Recent hospital discharge with rehabilitation potential',
            self::HIGH_ADL_COGNITIVE => 'Complex needs: both physical dependency and cognitive impairment',
            self::GENERAL => 'General support needs without specific clinical complexity',
        };
    }

    /**
     * Get approximate RUG categories that map to this cluster.
     *
     * Used for template selection when no HC assessment is available.
     *
     * @return array<string> RUG category names
     */
    public function getApproximateRugCategories(): array
    {
        return match ($this) {
            self::HIGH_ADL => ['Reduced Physical Function', 'Special Care'],
            self::MODERATE_ADL => ['Reduced Physical Function'],
            self::LOW_ADL => ['Reduced Physical Function'],
            self::COGNITIVE_COMPLEX => ['Impaired Cognition'],
            self::MH_COMPLEX => ['Behaviour Problems', 'Impaired Cognition'],
            self::MEDICAL_COMPLEX => ['Clinically Complex', 'Special Care'],
            self::POST_ACUTE => ['Special Rehabilitation', 'Clinically Complex'],
            self::HIGH_ADL_COGNITIVE => ['Impaired Cognition', 'Special Care'],
            self::GENERAL => ['Reduced Physical Function'],
        };
    }

    /**
     * Get the primary focus area for this cluster.
     */
    public function getPrimaryFocus(): string
    {
        return match ($this) {
            self::HIGH_ADL, self::MODERATE_ADL, self::LOW_ADL => 'physical',
            self::COGNITIVE_COMPLEX, self::HIGH_ADL_COGNITIVE => 'cognitive',
            self::MH_COMPLEX => 'mental_health',
            self::MEDICAL_COMPLEX => 'clinical',
            self::POST_ACUTE => 'rehabilitation',
            self::GENERAL => 'general',
        };
    }

    /**
     * Check if this cluster typically needs high-frequency PSW support.
     */
    public function requiresHighPswFrequency(): bool
    {
        return in_array($this, [
            self::HIGH_ADL,
            self::HIGH_ADL_COGNITIVE,
            self::COGNITIVE_COMPLEX,
        ]);
    }

    /**
     * Check if this cluster typically needs enhanced nursing.
     */
    public function requiresEnhancedNursing(): bool
    {
        return in_array($this, [
            self::MEDICAL_COMPLEX,
            self::POST_ACUTE,
            self::HIGH_ADL,
        ]);
    }

    /**
     * Get all clusters as options for a dropdown/select.
     *
     * @return array<array{value: string, label: string, description: string}>
     */
    public static function toSelectOptions(): array
    {
        return array_map(
            fn(self $cluster) => [
                'value' => $cluster->value,
                'label' => $cluster->getLabel(),
                'description' => $cluster->getDescription(),
            ],
            self::cases()
        );
    }
}

