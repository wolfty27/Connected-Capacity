<?php

namespace Database\Seeders;

/**
 * DemoInterraiPayloadFactory - Generates iCODE payloads for specific RUG classifications
 *
 * This factory creates raw_items arrays with proper iCODE keys that the
 * RUGClassificationService expects. Each method produces a payload designed
 * to classify into a specific RUG group.
 *
 * iCODE Key Reference:
 * - ADL (4-item sum): iG1ha (bed mobility), iG1ia (transfer), iG1ea (toilet), iG1ja (eating)
 * - IADL (count >=3): iG1aa (meal prep), iG1da (housework), iG1eb (finances)
 * - Therapy minutes: iN3eb (PT), iN3fb (OT), iN3gb (SLP)
 * - Extensive: iP1aa (IV meds), iP1ab (IV feed), iP1ae (suction), iP1af (trach), iP1ag (vent)
 * - Special Care: iI1a (ulcer stage), iK1a (swallowing), iK5a (weight loss)
 * - Clinical: chess, iP1ak (dialysis), iP1al (chemo), iP1ah (oxygen), iJ1a/iJ1b (pain)
 * - Behaviour: iE3a-f (wandering, verbal, physical, inappropriate, resists, disruptive)
 * - Cognition: cps (direct), iB3a (decision), iB1 (memory), iC1 (communication)
 *
 * ADL Score Conversion (CIHI): raw 0→1, 1→2, 2→3, 3/4→4
 * So ADL sum = sum of converted scores (range 4-18)
 *
 * @see app/Services/RUGClassificationService.php
 * @see docs/CC21_RUG_Algorithm_Pseudocode.md
 */
class DemoInterraiPayloadFactory
{
    /**
     * Get a baseline payload with all iCODE keys initialized to safe defaults.
     * Also includes human-readable keys for the UI display sections.
     */
    protected static function baseline(): array
    {
        return [
            // ADL items (self-performance scores 0-4) - iCODE keys for RUG classification
            'iG1ha' => 0, // Bed mobility
            'iG1ia' => 0, // Transfer
            'iG1ea' => 0, // Toilet use
            'iG1ja' => 0, // Eating
            'iG1aa' => 0, // IADL - Meal prep
            'iG1ba' => 0, // IADL - Dress upper
            'iG1ca' => 0, // IADL - Dress lower
            'iG1da' => 0, // IADL - Ordinary housework
            'iG1eb' => 0, // IADL - Managing finances (capacity)
            'iG1fa' => 0, // Personal hygiene
            'iG1ga' => 0, // Bathing

            // Cognitive items
            'iB1' => 0,   // Short-term memory (0=OK, 1=problem)
            'iB2a' => 0,  // Memory recall ability
            'iB3a' => 0,  // Cognitive skills for decision making (0-6)
            'iC1' => 0,   // Making self understood (0-5)
            'iC2' => 0,   // Comprehension
            'cps' => null, // CPS score - if set, used directly

            // Behaviour items (0=not present, 1=present but not in last 3 days, 2=1-2 of last 3 days, 3=daily)
            'iE3a' => 0, // Wandering
            'iE3b' => 0, // Verbal abuse
            'iE3c' => 0, // Physical abuse
            'iE3d' => 0, // Socially inappropriate
            'iE3e' => 0, // Resists care
            'iE3f' => 0, // Disruptive

            // Clinical indicators
            'iI1a' => 0,  // Pressure ulcer stage (0-4)
            'iK1a' => 0,  // Swallowing (0-3)
            'iK5a' => 0,  // Weight loss
            'iJ1a' => 0,  // Pain frequency (0-3)
            'iJ1b' => 0,  // Pain intensity (0-4)
            'iJ2a' => 0,  // Falls

            // Health stability
            'chess' => 0, // CHESS score (0-5)
            'drs' => 0,   // Depression rating scale

            // Therapy minutes (per 7-day period)
            'iN3eb' => 0, // PT minutes
            'iN3fb' => 0, // OT minutes
            'iN3gb' => 0, // SLP minutes

            // Extensive services (0=no, 1=yes)
            'iP1aa' => 0, // IV medication
            'iP1ab' => 0, // IV/parenteral feeding
            'iP1ae' => 0, // Suctioning
            'iP1af' => 0, // Tracheostomy care
            'iP1ag' => 0, // Ventilator/respirator
            'iP1ah' => 0, // Oxygen therapy
            'iP1ak' => 0, // Dialysis
            'iP1al' => 0, // Chemotherapy

            // Location
            'location' => 'community',

            // Human-readable keys for UI display (getAssessmentSections)
            // Cognition Section
            'cps_decision_making' => 0,
            'cps_short_term_memory' => 0,
            'cps_communication' => 0,

            // Communication Section
            'hearing' => 0,
            'vision' => 0,

            // Mood Section
            'mood_negative_statements' => 0,
            'mood_persistent_anger' => 0,
            'mood_unrealistic_fears' => 0,
            'mood_sad_expressions' => 0,
            'mood_crying' => 0,

            // ADL Section (UI keys)
            'adl_bathing' => 0,
            'adl_hygiene' => 0,
            'adl_dressing_upper' => 0,
            'adl_dressing_lower' => 0,
            'adl_locomotion' => 0,
            'adl_transfer' => 0,
            'adl_toilet_use' => 0,
            'adl_bed_mobility' => 0,
            'adl_eating' => 0,

            // IADL Section (UI keys)
            'iadl_meal_prep' => 0,
            'iadl_housework' => 0,
            'iadl_finances' => 0,
            'iadl_medications' => 0,
            'iadl_phone' => 0,
            'iadl_shopping' => 0,
            'iadl_transportation' => 0,

            // Continence Section
            'bladder_continence' => 0,
            'bowel_continence' => 0,

            // Health Conditions Section
            'pain_frequency' => 0,
            'pain_intensity' => 1,
            'dyspnea' => false,
            'fatigue' => false,
            'edema' => false,
            'dizziness' => false,
            'chest_pain' => false,
            'fall_history' => 0,
            'weight_loss' => false,
            'dehydration' => false,
            'vomiting' => false,

            // Diseases Section
            'special_ms' => false,
            'special_quadriplegia' => false,
            'special_coma' => false,
            'clinical_chf' => false,
            'clinical_copd' => false,
            'clinical_pneumonia' => false,
            'clinical_diabetes' => false,
            'clinical_wound' => false,
            'special_burns' => false,

            // Treatments Section
            'extensive_dialysis' => false,
            'extensive_iv' => false,
            'extensive_ventilator' => false,
            'extensive_trach' => false,
            'clinical_oxygen' => false,
            'clinical_tube_feeding' => false,
            'clinical_dialysis' => false,
            'therapy_pt' => 0,
            'therapy_ot' => 0,
            'therapy_slp' => 0,

            // Social Supports Section
            'caregiver_lives_with' => false,
            'caregiver_stress' => false,
        ];
    }

    /**
     * Synchronize iCODE values to human-readable UI keys.
     */
    protected static function syncUiKeys(array $data): array
    {
        // Sync ADL values (iCODE -> UI keys)
        $data['adl_bed_mobility'] = $data['iG1ha'];
        $data['adl_transfer'] = $data['iG1ia'];
        $data['adl_toilet_use'] = $data['iG1ea'];
        $data['adl_eating'] = $data['iG1ja'];
        $data['adl_hygiene'] = $data['iG1fa'];
        $data['adl_bathing'] = $data['iG1ga'];
        $data['adl_dressing_upper'] = $data['iG1ba'];
        $data['adl_dressing_lower'] = $data['iG1ca'];
        $data['adl_locomotion'] = $data['iG1ha']; // Use bed mobility as proxy

        // Sync IADL values
        $data['iadl_meal_prep'] = $data['iG1aa'];
        $data['iadl_housework'] = $data['iG1da'];
        $data['iadl_finances'] = $data['iG1eb'];
        $data['iadl_medications'] = max($data['iG1aa'], $data['iG1da']);
        $data['iadl_phone'] = $data['iG1eb'];
        $data['iadl_shopping'] = $data['iG1da'];
        $data['iadl_transportation'] = $data['iG1da'];

        // Sync cognition values
        $data['cps_decision_making'] = $data['iB3a'];
        $data['cps_short_term_memory'] = $data['iB1'];
        $data['cps_communication'] = $data['iC1'];

        // Sync pain values
        $data['pain_frequency'] = $data['iJ1a'];
        $data['pain_intensity'] = max(1, $data['iJ1b']);

        // Sync fall history
        $data['fall_history'] = $data['iJ2a'];

        // Sync therapy minutes
        $data['therapy_pt'] = $data['iN3eb'];
        $data['therapy_ot'] = $data['iN3fb'];
        $data['therapy_slp'] = $data['iN3gb'];

        // Sync extensive services
        $data['extensive_iv'] = (bool) $data['iP1aa'];
        $data['extensive_ventilator'] = (bool) $data['iP1ag'];
        $data['extensive_trach'] = (bool) $data['iP1af'];
        $data['extensive_dialysis'] = (bool) $data['iP1ak'];
        $data['clinical_oxygen'] = (bool) $data['iP1ah'];

        // Sync mood/behaviour items
        $data['mood_negative_statements'] = min($data['iE3b'], 2);
        $data['mood_persistent_anger'] = min($data['iE3b'], 2);

        // Sync clinical indicators
        $data['clinical_wound'] = ($data['iI1a'] >= 2);
        $data['clinical_chf'] = ($data['chess'] >= 3);

        return $data;
    }

    /**
     * RB0 - Special Rehabilitation, HIGH ADL (11-18)
     *
     * Requirements:
     * - Therapy minutes >= 120
     * - ADL sum 11-18
     * - No extensive services, special care, or clinically complex triggers
     */
    public static function forRugRb0(): array
    {
        return array_merge(self::baseline(), [
            // High therapy: 180 PT + 120 OT + 60 SLP = 360 minutes
            'iN3eb' => 180,
            'iN3fb' => 120,
            'iN3gb' => 60,

            // ADL sum = 12: raw (2,2,2,2) → converted (3+3+3+3=12)
            // Need ADL >= 11 for RB0
            'iG1ha' => 2, // Bed mobility → 3
            'iG1ia' => 2, // Transfer → 3
            'iG1ea' => 2, // Toilet use → 3
            'iG1ja' => 2, // Eating → 3

            // No extensive/special care/clinical triggers
            'chess' => 1, // Low health instability
            'cps' => 1,   // Mild cognitive
        ]);
    }

    /**
     * RA2 - Special Rehabilitation, lower ADL (4-10), IADL >= 2
     *
     * Requirements:
     * - Therapy minutes >= 120
     * - ADL sum 4-10
     * - IADL index >= 2 (count of IADL items with score >= 3)
     */
    public static function forRugRa2(): array
    {
        return array_merge(self::baseline(), [
            // Therapy: 150 PT + 90 OT = 240 minutes
            'iN3eb' => 150,
            'iN3fb' => 90,
            'iN3gb' => 0,

            // ADL sum = 8: raw (1,1,1,1) → converted (2+2+2+2=8)
            'iG1ha' => 1,
            'iG1ia' => 1,
            'iG1ea' => 1,
            'iG1ja' => 1,

            // IADL >= 2: need 2+ items with score >= 3
            'iG1aa' => 4, // Meal prep - extensive help
            'iG1da' => 4, // Housework - extensive help
            'iG1eb' => 2, // Finances - moderate

            'chess' => 1,
            'cps' => 1,
        ]);
    }

    /**
     * SE3 - Extensive Services, highest complexity (extensive count >= 4)
     *
     * Requirements:
     * - Has extensive services (IV, vent, etc.)
     * - ADL >= 7
     * - Extensive count >= 4
     */
    public static function forRugSe3(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 14: raw (3,3,2,2) → converted (4+4+3+3=14)
            'iG1ha' => 3,
            'iG1ia' => 3,
            'iG1ea' => 2,
            'iG1ja' => 2,

            // Extensive services - multiple to get high count
            'iP1aa' => 1, // IV medication
            'iP1ab' => 1, // IV feeding
            'iP1af' => 1, // Tracheostomy
            'iP1ag' => 1, // Ventilator
            'iP1ah' => 1, // Oxygen

            // Additional flags for extensive count
            'chess' => 4,
            'cps' => 4,

            // Special care indicators
            'iI1a' => 3, // Stage 3 pressure ulcer
        ]);
    }

    /**
     * SE1 - Extensive Services, lower complexity (extensive count 1)
     *
     * Requirements:
     * - Has extensive services (IV, vent, trach, suction, IV feed)
     * - ADL >= 7
     * - Extensive count = 1
     *
     * Note: Dialysis (iP1ak) is NOT in hasExtensiveServices() - it's clinically complex.
     * Must use: iP1aa (IV meds), iP1ab (IV feed), iP1ae (suction), iP1af (trach), iP1ag (vent)
     */
    public static function forRugSe1(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 10: raw (2,2,1,1) → converted (3+3+2+2=10)
            'iG1ha' => 2,
            'iG1ia' => 2,
            'iG1ea' => 1,
            'iG1ja' => 1,

            // Single extensive service (IV medication - actual extensive service)
            'iP1aa' => 1, // IV medication

            // Keep other flags low to avoid higher categories
            'chess' => 1, // Low to avoid clinically complex
            'cps' => 1,
        ]);
    }

    /**
     * SE2 - Extensive Services, moderate complexity (extensive count 2-3)
     *
     * Requirements:
     * - Has extensive services
     * - ADL >= 7
     * - Extensive count 2-3
     */
    public static function forRugSe2(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 12: raw (2,2,2,2) → converted (3+3+3+3=12)
            'iG1ha' => 2,
            'iG1ia' => 2,
            'iG1ea' => 2,
            'iG1ja' => 2,

            // Two extensive services
            'iP1aa' => 1, // IV medication
            'iP1ak' => 1, // Dialysis

            'chess' => 3,
            'cps' => 2,
        ]);
    }

    /**
     * SSB - Special Care, HIGH ADL (14-18)
     *
     * Requirements:
     * - Special care indicators (ulcer stage 3+, swallowing, weight loss)
     * - ADL >= 14
     * - No extensive services with ADL >= 7
     */
    public static function forRugSsb(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 15: raw (3,3,3,2) → converted (4+4+4+3=15)
            'iG1ha' => 3,
            'iG1ia' => 3,
            'iG1ea' => 3,
            'iG1ja' => 2,

            // Special care triggers
            'iI1a' => 3,  // Stage 3 pressure ulcer
            'iK1a' => 2,  // Swallowing problem
            'iK5a' => 1,  // Weight loss

            // No extensive services
            'chess' => 3,
            'cps' => 3,
        ]);
    }

    /**
     * SSA - Special Care, lower ADL (4-13)
     *
     * Requirements:
     * - Special care indicators
     * - ADL 4-13 (or extensive services with ADL <= 6)
     */
    public static function forRugSsa(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 10: raw (2,2,1,1) → converted (3+3+2+2=10)
            'iG1ha' => 2,
            'iG1ia' => 2,
            'iG1ea' => 1,
            'iG1ja' => 1,

            // Special care triggers
            'iI1a' => 3,  // Stage 3 pressure ulcer
            'iK1a' => 2,  // Swallowing problem

            'chess' => 3,
            'cps' => 2,
        ]);
    }

    /**
     * CC0 - Clinically Complex, HIGH ADL (11-18)
     *
     * Requirements:
     * - Clinically complex indicators (CHESS >= 3, dialysis, chemo, oxygen, pain)
     * - ADL >= 11
     * - No extensive services, no special care
     */
    public static function forRugCc0(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 12: raw (2,2,2,2) → converted (3+3+3+3=12)
            'iG1ha' => 2,
            'iG1ia' => 2,
            'iG1ea' => 2,
            'iG1ja' => 2,

            // Clinically complex triggers
            'chess' => 4,  // High health instability
            'iP1ah' => 1,  // Oxygen therapy
            'iJ1a' => 3,   // Pain frequency - daily
            'iJ1b' => 3,   // Pain intensity - severe

            // Low cognition to avoid impaired cognition category
            'cps' => 1,
        ]);
    }

    /**
     * CB0 - Clinically Complex, moderate ADL (6-10)
     *
     * Requirements:
     * - Clinically complex indicators
     * - ADL 6-10
     */
    public static function forRugCb0(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 8: raw (1,1,1,1) → converted (2+2+2+2=8)
            'iG1ha' => 1,
            'iG1ia' => 1,
            'iG1ea' => 1,
            'iG1ja' => 1,

            // Clinically complex triggers
            'chess' => 3,
            'iP1ah' => 1, // Oxygen
            'iJ1a' => 2,
            'iJ1b' => 2,

            'cps' => 1,
        ]);
    }

    /**
     * CA2 - Clinically Complex, lower ADL (4-5), IADL >= 1
     *
     * Requirements:
     * - Clinically complex indicators
     * - ADL 4-5
     * - IADL >= 1
     */
    public static function forRugCa2(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 5: raw (1,0,0,0) → converted (2+1+1+1=5)
            'iG1ha' => 1,
            'iG1ia' => 0,
            'iG1ea' => 0,
            'iG1ja' => 0,

            // IADL >= 1
            'iG1aa' => 3, // Meal prep - needs help

            // Clinically complex
            'chess' => 3,
            'iJ1a' => 2,
            'iJ1b' => 2,

            'cps' => 1,
        ]);
    }

    /**
     * IB0 - Impaired Cognition, moderate ADL (6-10)
     *
     * Requirements:
     * - CPS >= 3
     * - ADL 6-10
     * - No rehab, extensive, special care, or clinically complex triggers
     */
    public static function forRugIb0(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 8: raw (1,1,1,1) → converted (2+2+2+2=8)
            'iG1ha' => 1,
            'iG1ia' => 1,
            'iG1ea' => 1,
            'iG1ja' => 1,

            // High CPS for impaired cognition
            'cps' => 4,
            'iB3a' => 3, // Moderately impaired decision making
            'iB1' => 1,  // Short-term memory problem
            'iC1' => 2,  // Sometimes understood

            // No clinically complex triggers
            'chess' => 1,
            'iJ1a' => 0,
            'iJ1b' => 0,
        ]);
    }

    /**
     * IA2 - Impaired Cognition, lower ADL (4-5), IADL >= 1
     *
     * Requirements:
     * - CPS >= 3
     * - ADL 4-5
     * - IADL >= 1
     */
    public static function forRugIa2(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 5: raw (1,0,0,0) → converted (2+1+1+1=5)
            'iG1ha' => 1,
            'iG1ia' => 0,
            'iG1ea' => 0,
            'iG1ja' => 0,

            // IADL >= 1
            'iG1aa' => 4, // Meal prep - dependent

            // Cognitive impairment
            'cps' => 3,
            'iB3a' => 2,
            'iB1' => 1,

            'chess' => 1,
        ]);
    }

    /**
     * BB0 - Behaviour Problems, moderate ADL (6-10)
     *
     * Requirements:
     * - Behaviour indicators with score >= 2 (daily occurrence)
     * - ADL 6-10
     * - CPS < 3 (or it would classify as Impaired Cognition first)
     * - No rehab, extensive, special care, or clinically complex triggers
     */
    public static function forRugBb0(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 8: raw (1,1,1,1) → converted (2+2+2+2=8)
            'iG1ha' => 1,
            'iG1ia' => 1,
            'iG1ea' => 1,
            'iG1ja' => 1,

            // Behaviour problems - need score >= 2
            'iE3b' => 2, // Verbal abuse - daily
            'iE3e' => 2, // Resists care - daily
            'iE3c' => 1, // Physical abuse - present

            // Low CPS to stay in behaviour category (not impaired cognition)
            'cps' => 2,
            'iB3a' => 1,

            'chess' => 1,
        ]);
    }

    /**
     * BA2 - Behaviour Problems, lower ADL (4-5), IADL >= 1
     *
     * Requirements:
     * - Behaviour indicators with score >= 2
     * - ADL 4-5
     * - IADL >= 1
     * - CPS < 3
     */
    public static function forRugBa2(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 5: raw (1,0,0,0) → converted (2+1+1+1=5)
            'iG1ha' => 1,
            'iG1ia' => 0,
            'iG1ea' => 0,
            'iG1ja' => 0,

            // IADL >= 1
            'iG1aa' => 3,

            // Behaviour problems
            'iE3b' => 2, // Verbal abuse
            'iE3d' => 2, // Socially inappropriate

            'cps' => 2,
            'chess' => 1,
        ]);
    }

    /**
     * PD0 - Reduced Physical Function, highest ADL (11+)
     *
     * Requirements:
     * - ADL >= 11
     * - No rehab, extensive, special care, clinically complex, impaired cognition, or behaviour triggers
     */
    public static function forRugPd0(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 12: raw (2,2,2,2) → converted (3+3+3+3=12)
            'iG1ha' => 2,
            'iG1ia' => 2,
            'iG1ea' => 2,
            'iG1ja' => 2,

            // No triggers for other categories
            'chess' => 1,
            'cps' => 1,
            'iJ1a' => 1,
            'iJ1b' => 1,
        ]);
    }

    /**
     * PC0 - Reduced Physical Function, ADL 9-10
     *
     * Requirements:
     * - ADL 9-10
     * - No higher category triggers
     */
    public static function forRugPc0(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 9: raw (2,2,1,0) → converted (3+3+2+1=9)
            'iG1ha' => 2,
            'iG1ia' => 2,
            'iG1ea' => 1,
            'iG1ja' => 0,

            'chess' => 1,
            'cps' => 1,
        ]);
    }

    /**
     * PB0 - Reduced Physical Function, ADL 6-8
     *
     * Requirements:
     * - ADL 6-8
     * - No higher category triggers
     */
    public static function forRugPb0(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 7: raw (1,1,1,0) → converted (2+2+2+1=7)
            'iG1ha' => 1,
            'iG1ia' => 1,
            'iG1ea' => 1,
            'iG1ja' => 0,

            'chess' => 1,
            'cps' => 1,
        ]);
    }

    /**
     * PA2 - Reduced Physical Function, low ADL (4-5), IADL >= 1
     *
     * Requirements:
     * - ADL 4-5
     * - IADL >= 1
     * - No higher category triggers
     */
    public static function forRugPa2(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 5: raw (1,0,0,0) → converted (2+1+1+1=5)
            'iG1ha' => 1,
            'iG1ia' => 0,
            'iG1ea' => 0,
            'iG1ja' => 0,

            // IADL >= 1
            'iG1aa' => 3,

            'chess' => 1,
            'cps' => 0,
        ]);
    }

    /**
     * PA1 - Reduced Physical Function, low ADL (4-5), IADL = 0 (default/fallback)
     *
     * Requirements:
     * - ADL 4-5
     * - IADL = 0
     * - No higher category triggers
     */
    public static function forRugPa1(): array
    {
        return array_merge(self::baseline(), [
            // ADL sum = 4: raw (0,0,0,0) → converted (1+1+1+1=4)
            'iG1ha' => 0,
            'iG1ia' => 0,
            'iG1ea' => 0,
            'iG1ja' => 0,

            // IADL = 0
            'iG1aa' => 0,
            'iG1da' => 0,
            'iG1eb' => 0,

            'chess' => 0,
            'cps' => 0,
        ]);
    }

    /**
     * Get payload for a specific RUG group.
     * Automatically syncs iCODE values to human-readable UI keys.
     */
    public static function forRug(string $rugGroup): array
    {
        $data = match ($rugGroup) {
            'RB0' => self::forRugRb0(),
            'RA2' => self::forRugRa2(),
            'SE3' => self::forRugSe3(),
            'SE2' => self::forRugSe2(),
            'SE1' => self::forRugSe1(),
            'SSB' => self::forRugSsb(),
            'SSA' => self::forRugSsa(),
            'CC0' => self::forRugCc0(),
            'CB0' => self::forRugCb0(),
            'CA2' => self::forRugCa2(),
            'IB0' => self::forRugIb0(),
            'IA2' => self::forRugIa2(),
            'BB0' => self::forRugBb0(),
            'BA2' => self::forRugBa2(),
            'PD0' => self::forRugPd0(),
            'PC0' => self::forRugPc0(),
            'PB0' => self::forRugPb0(),
            'PA2' => self::forRugPa2(),
            default => self::forRugPa1(),
        };

        // Sync iCODE values to human-readable UI keys
        return self::syncUiKeys($data);
    }
}
