<?php

namespace App\Services\BundleEngine\Enums;

/**
 * ScenarioAxis
 *
 * Patient-experience orientations for bundle scenarios.
 * Each axis represents a different emphasis for the care bundle.
 *
 * Patients typically have 2-4 applicable axes based on their profile.
 * The ScenarioAxisSelector determines which axes apply.
 *
 * DESIGN PRINCIPLE: These are patient-experience orientations,
 * NOT clinical/budget dichotomies. We frame around recovery, safety,
 * convenience, and caregiver support.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 4.1
 */
enum ScenarioAxis: string
{
    // === Primary Axes ===

    /**
     * Recovery-Focused / Rehabilitation-Heavy
     *
     * Emphasis: Therapy intensity, mobility goals, function restoration
     * Suitable for: Post-acute, rehab potential, therapy minutes > 0
     * Services: Heavy PT/OT/SLP, goal-focused nursing, activation
     */
    case RECOVERY_REHAB = 'recovery_rehab';

    /**
     * Safety & Stability
     *
     * Emphasis: Fall prevention, crisis avoidance, daily functioning
     * Suitable for: Fall risk, health instability, cognitive impairment
     * Services: Daily PSW, nursing monitoring, PERS, safety assessments
     */
    case SAFETY_STABILITY = 'safety_stability';

    /**
     * Tech-Enabled / Remote-Support
     *
     * Emphasis: Remote monitoring, telehealth, reduced in-person visits
     * Suitable for: Tech-ready, stable condition, caregiver available
     * Services: RPM, virtual check-ins, telehealth nursing, PERS
     */
    case TECH_ENABLED = 'tech_enabled';

    /**
     * Caregiver-Relief / Support-Emphasis
     *
     * Emphasis: Respite, homemaking, family coaching, adult day programs
     * Suitable for: High caregiver stress, caregiver-dependent patient
     * Services: Respite hours, homemaking, day programs, caregiver education
     */
    case CAREGIVER_RELIEF = 'caregiver_relief';

    // === Secondary/Hybrid Axes ===

    /**
     * Medical Intensive
     *
     * Emphasis: High nursing frequency, clinical treatments
     * Suitable for: Extensive services, wound care, IV therapy
     * Services: Shift nursing, wound care, respiratory therapy
     */
    case MEDICAL_INTENSIVE = 'medical_intensive';

    /**
     * Cognitive Support
     *
     * Emphasis: Cognitive stimulation, behavioural support, structure
     * Suitable for: CPS 3+, behavioural issues, dementia
     * Services: Behavioural PSW, activation, structured routines
     */
    case COGNITIVE_SUPPORT = 'cognitive_support';

    /**
     * Community Integrated
     *
     * Emphasis: Social engagement, transportation, community programs
     * Suitable for: Socially isolated, IADL-focused needs
     * Services: Adult day, transportation, meal programs, social visits
     */
    case COMMUNITY_INTEGRATED = 'community_integrated';

    /**
     * Balanced
     *
     * Emphasis: Balanced mix across all areas
     * Default/baseline for all patients
     * Services: Mix of nursing, PSW, CSS as appropriate
     */
    case BALANCED = 'balanced';

    /**
     * Get human-readable label for UI display.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::RECOVERY_REHAB => 'Recovery-Focused Care',
            self::SAFETY_STABILITY => 'Safety & Stability',
            self::TECH_ENABLED => 'Tech-Enabled Care',
            self::CAREGIVER_RELIEF => 'Caregiver Relief',
            self::MEDICAL_INTENSIVE => 'Medical Intensive',
            self::COGNITIVE_SUPPORT => 'Cognitive Support',
            self::COMMUNITY_INTEGRATED => 'Community Integrated',
            self::BALANCED => 'Balanced Care',
        };
    }

    /**
     * Get 1-2 sentence description for UI.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::RECOVERY_REHAB =>
                'Prioritizes therapy and function restoration with intensive PT/OT services to support recovery goals.',
            self::SAFETY_STABILITY =>
                'Maximizes daily functioning and fall prevention with consistent PSW support and nursing monitoring.',
            self::TECH_ENABLED =>
                'Leverages remote monitoring and telehealth for continuous oversight with targeted in-person visits.',
            self::CAREGIVER_RELIEF =>
                'Supports both patient and family caregiver with respite hours, homemaking, and family support services.',
            self::MEDICAL_INTENSIVE =>
                'Provides intensive clinical care with high nursing frequency for complex medical needs.',
            self::COGNITIVE_SUPPORT =>
                'Focuses on cognitive stimulation and behavioural support with structured routines and supervision.',
            self::COMMUNITY_INTEGRATED =>
                'Emphasizes social engagement and community connections through day programs and social services.',
            self::BALANCED =>
                'Provides a balanced mix of services across all care domains based on assessed needs.',
        };
    }

    /**
     * Get emoji icon for UI display.
     */
    public function getEmoji(): string
    {
        return match ($this) {
            self::RECOVERY_REHAB => '🔄',
            self::SAFETY_STABILITY => '🛡️',
            self::TECH_ENABLED => '📱',
            self::CAREGIVER_RELIEF => '🤝',
            self::MEDICAL_INTENSIVE => '🏥',
            self::COGNITIVE_SUPPORT => '🧠',
            self::COMMUNITY_INTEGRATED => '🏘️',
            self::BALANCED => '⚖️',
        };
    }

    /**
     * Get service categories emphasized by this axis.
     *
     * @return array<string>
     */
    public function getEmphasizedServiceCategories(): array
    {
        return match ($this) {
            self::RECOVERY_REHAB => ['therapy', 'activation', 'nursing'],
            self::SAFETY_STABILITY => ['nursing', 'psw', 'remote_monitoring'],
            self::TECH_ENABLED => ['remote_monitoring', 'telehealth'],
            self::CAREGIVER_RELIEF => ['respite', 'homemaking', 'day_program', 'caregiver_education'],
            self::MEDICAL_INTENSIVE => ['nursing', 'wound_care', 'respiratory'],
            self::COGNITIVE_SUPPORT => ['behavioural_psw', 'activation', 'psw'],
            self::COMMUNITY_INTEGRATED => ['day_program', 'transportation', 'meals', 'social'],
            self::BALANCED => ['nursing', 'psw', 'therapy', 'css'],
        };
    }

    /**
     * Get service modifiers for this axis.
     *
     * Modifiers adjust base template services:
     * - multiplier: frequency multiplier (1.5 = +50%)
     * - priority: 'core', 'recommended', 'optional'
     *
     * @return array<string, array{multiplier: float, priority: string}>
     */
    public function getServiceModifiers(): array
    {
        return match ($this) {
            self::RECOVERY_REHAB => [
                'therapy' => ['multiplier' => 1.5, 'priority' => 'core'],
                'activation' => ['multiplier' => 1.3, 'priority' => 'recommended'],
                'nursing' => ['multiplier' => 1.0, 'priority' => 'core'],
                'psw' => ['multiplier' => 0.9, 'priority' => 'core'],
            ],
            self::SAFETY_STABILITY => [
                'nursing' => ['multiplier' => 1.3, 'priority' => 'core'],
                'psw' => ['multiplier' => 1.2, 'priority' => 'core'],
                'remote_monitoring' => ['multiplier' => 1.5, 'priority' => 'recommended'],
                'therapy' => ['multiplier' => 0.8, 'priority' => 'recommended'],
            ],
            self::TECH_ENABLED => [
                'remote_monitoring' => ['multiplier' => 2.0, 'priority' => 'core'],
                'telehealth' => ['multiplier' => 1.5, 'priority' => 'core'],
                'nursing' => ['multiplier' => 0.7, 'priority' => 'recommended'],
                'psw' => ['multiplier' => 0.8, 'priority' => 'recommended'],
            ],
            self::CAREGIVER_RELIEF => [
                'respite' => ['multiplier' => 2.0, 'priority' => 'core'],
                'homemaking' => ['multiplier' => 1.5, 'priority' => 'core'],
                'day_program' => ['multiplier' => 1.5, 'priority' => 'recommended'],
                'caregiver_education' => ['multiplier' => 1.0, 'priority' => 'core'],
            ],
            self::MEDICAL_INTENSIVE => [
                'nursing' => ['multiplier' => 2.0, 'priority' => 'core'],
                'wound_care' => ['multiplier' => 1.5, 'priority' => 'core'],
                'respiratory' => ['multiplier' => 1.5, 'priority' => 'recommended'],
                'psw' => ['multiplier' => 1.0, 'priority' => 'core'],
            ],
            self::COGNITIVE_SUPPORT => [
                'behavioural_psw' => ['multiplier' => 1.5, 'priority' => 'core'],
                'activation' => ['multiplier' => 1.5, 'priority' => 'core'],
                'psw' => ['multiplier' => 1.3, 'priority' => 'core'],
                'nursing' => ['multiplier' => 0.8, 'priority' => 'recommended'],
            ],
            self::COMMUNITY_INTEGRATED => [
                'day_program' => ['multiplier' => 1.5, 'priority' => 'core'],
                'transportation' => ['multiplier' => 1.5, 'priority' => 'core'],
                'meals' => ['multiplier' => 1.3, 'priority' => 'recommended'],
                'psw' => ['multiplier' => 0.8, 'priority' => 'recommended'],
            ],
            self::BALANCED => [], // Uses template defaults
        };
    }

    /**
     * Get patient goals emphasized by this axis.
     *
     * @return array<string>
     */
    public function getEmphasizedGoals(): array
    {
        return match ($this) {
            self::RECOVERY_REHAB => ['mobility', 'independence', 'strength', 'function_restoration'],
            self::SAFETY_STABILITY => ['fall_prevention', 'daily_functioning', 'crisis_avoidance', 'stability'],
            self::TECH_ENABLED => ['continuous_monitoring', 'convenience', 'efficiency', 'connectivity'],
            self::CAREGIVER_RELIEF => ['caregiver_wellbeing', 'respite', 'family_support', 'sustainability'],
            self::MEDICAL_INTENSIVE => ['clinical_stability', 'symptom_management', 'treatment_adherence'],
            self::COGNITIVE_SUPPORT => ['cognitive_engagement', 'behavioural_stability', 'routine', 'supervision'],
            self::COMMUNITY_INTEGRATED => ['social_engagement', 'independence', 'community_connection'],
            self::BALANCED => ['overall_wellbeing', 'comprehensive_support', 'holistic_care'],
        };
    }

    /**
     * Get trade-off annotations for this axis.
     *
     * These explain what the scenario EMPHASIZES, not what it's "missing".
     *
     * @return array{emphasis: string, approach: string, consideration: string}
     */
    public function getTradeOffs(): array
    {
        return match ($this) {
            self::RECOVERY_REHAB => [
                'emphasis' => 'Prioritizes recovery and function restoration',
                'approach' => 'More therapy sessions to accelerate progress',
                'consideration' => 'Best for patients with clear rehab goals and potential',
            ],
            self::SAFETY_STABILITY => [
                'emphasis' => 'Prioritizes daily safety and crisis prevention',
                'approach' => 'Consistent daily support and monitoring',
                'consideration' => 'Best for patients at risk of falls or health instability',
            ],
            self::TECH_ENABLED => [
                'emphasis' => 'Leverages technology for continuous oversight',
                'approach' => 'Remote monitoring with targeted in-person visits',
                'consideration' => 'Best for tech-comfortable patients with reliable connectivity',
            ],
            self::CAREGIVER_RELIEF => [
                'emphasis' => 'Supports both patient and family caregiver',
                'approach' => 'Includes respite and family support services',
                'consideration' => 'Best when family caregiver is integral to care plan',
            ],
            self::MEDICAL_INTENSIVE => [
                'emphasis' => 'Intensive clinical monitoring and treatment',
                'approach' => 'High nursing frequency with specialized care',
                'consideration' => 'Best for patients with complex medical needs',
            ],
            self::COGNITIVE_SUPPORT => [
                'emphasis' => 'Cognitive engagement and behavioural support',
                'approach' => 'Structured routines with supervision',
                'consideration' => 'Best for patients with dementia or cognitive impairment',
            ],
            self::COMMUNITY_INTEGRATED => [
                'emphasis' => 'Social connection and community engagement',
                'approach' => 'Day programs and social services',
                'consideration' => 'Best for socially isolated patients who can participate',
            ],
            self::BALANCED => [
                'emphasis' => 'Comprehensive coverage across all domains',
                'approach' => 'Balanced allocation based on assessment',
                'consideration' => 'Suitable baseline for most patients',
            ],
        };
    }

    /**
     * Check if this is a primary axis (commonly selected).
     */
    public function isPrimary(): bool
    {
        return in_array($this, [
            self::RECOVERY_REHAB,
            self::SAFETY_STABILITY,
            self::TECH_ENABLED,
            self::CAREGIVER_RELIEF,
        ]);
    }

    /**
     * Get all axes as options for a dropdown/select.
     *
     * @return array<array{value: string, label: string, description: string, emoji: string}>
     */
    public static function toSelectOptions(): array
    {
        return array_map(
            fn(self $axis) => [
                'value' => $axis->value,
                'label' => $axis->getLabel(),
                'description' => $axis->getDescription(),
                'emoji' => $axis->getEmoji(),
                'is_primary' => $axis->isPrimary(),
            ],
            self::cases()
        );
    }

    /**
     * Get primary axes only.
     *
     * @return array<self>
     */
    public static function primaryAxes(): array
    {
        return array_filter(self::cases(), fn(self $axis) => $axis->isPrimary());
    }
}

