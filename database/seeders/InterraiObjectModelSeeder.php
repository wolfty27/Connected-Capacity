<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the InterRAI HC Assessment structure into the Metadata Object Model.
 *
 * This creates:
 * - Object Definition for InterRAI Assessment
 * - Object Attributes for each assessment section and item
 * - Object Rules for score calculations (CPS, ADL, MAPLe, CHESS, etc.)
 */
class InterraiObjectModelSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create InterRAI Assessment object definition
        $assessmentDefId = DB::table('object_definitions')->insertGetId([
            'name' => 'InterraiAssessment',
            'code' => 'INTERRAI_ASSESSMENT',
            'display_name' => 'InterRAI HC Assessment',
            'description' => 'InterRAI Home Care assessment instrument with 17 sections covering cognition, function, health conditions, and social supports.',
            'category' => 'clinical',
            'base_table' => 'interrai_assessments',
            'is_active' => true,
            'is_system' => true,
            'config' => json_encode([
                'sections' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'T'],
                'reassessment_interval_days' => 90,
                'output_scales' => ['CPS', 'ADL', 'IADL', 'MAPLe', 'CHESS', 'DRS', 'Pain'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create attributes for each section
        $this->seedSectionAttributes($assessmentDefId);

        // 3. Create calculation rules for output scales
        $this->seedCalculationRules($assessmentDefId);

        // 4. Create relationship to Patient
        $patientDefId = DB::table('object_definitions')->where('code', 'PATIENT')->value('id');
        if ($patientDefId) {
            DB::table('object_relationships')->insert([
                'source_object_id' => $patientDefId,
                'target_object_id' => $assessmentDefId,
                'name' => 'interrai_assessments',
                'code' => 'patient_interrai_assessments',
                'relationship_type' => 'one_to_many',
                'inverse_name' => 'patient',
                'is_required' => false,
                'cascade_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Seed assessment section attributes.
     */
    private function seedSectionAttributes(int $defId): void
    {
        $sections = [
            // Section C - Cognition (key items for CPS calculation)
            ['code' => 'C1', 'name' => 'cognitive_skills', 'display_name' => 'Cognitive Skills for Daily Decision Making', 'group' => 'C_Cognition', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Modified independence', '2' => 'Minimally impaired', '3' => 'Moderately impaired', '4' => 'Severely impaired', '5' => 'No discernible consciousness']],
            ['code' => 'C2a', 'name' => 'short_term_memory', 'display_name' => 'Short-term Memory OK', 'group' => 'C_Cognition', 'data_type' => 'integer', 'options' => ['0' => 'Yes, memory OK', '1' => 'Memory problem']],
            ['code' => 'C2b', 'name' => 'procedural_memory', 'display_name' => 'Procedural Memory OK', 'group' => 'C_Cognition', 'data_type' => 'integer', 'options' => ['0' => 'Yes, memory OK', '1' => 'Memory problem']],
            ['code' => 'C2c', 'name' => 'situational_memory', 'display_name' => 'Situational Memory OK', 'group' => 'C_Cognition', 'data_type' => 'integer', 'options' => ['0' => 'Yes, memory OK', '1' => 'Memory problem']],
            ['code' => 'C3', 'name' => 'making_self_understood', 'display_name' => 'Making Self Understood', 'group' => 'C_Cognition', 'data_type' => 'integer', 'options' => ['0' => 'Understood', '1' => 'Usually understood', '2' => 'Often understood', '3' => 'Sometimes understood', '4' => 'Rarely/never understood']],

            // Section D - Communication
            ['code' => 'D1', 'name' => 'hearing', 'display_name' => 'Hearing', 'group' => 'D_Communication', 'data_type' => 'integer', 'options' => ['0' => 'Adequate', '1' => 'Minimal difficulty', '2' => 'Moderate difficulty', '3' => 'Severe difficulty', '4' => 'No hearing']],
            ['code' => 'D2', 'name' => 'vision', 'display_name' => 'Vision', 'group' => 'D_Communication', 'data_type' => 'integer', 'options' => ['0' => 'Adequate', '1' => 'Impaired', '2' => 'Moderately impaired', '3' => 'Severely impaired', '4' => 'No vision']],

            // Section E - Mood (for DRS calculation)
            ['code' => 'E1a', 'name' => 'negative_statements', 'display_name' => 'Made negative statements', 'group' => 'E_Mood', 'data_type' => 'integer', 'options' => ['0' => 'Not present', '1' => 'Present 1-2 days', '2' => 'Present 3+ days']],
            ['code' => 'E1b', 'name' => 'persistent_anger', 'display_name' => 'Persistent anger with self or others', 'group' => 'E_Mood', 'data_type' => 'integer', 'options' => ['0' => 'Not present', '1' => 'Present 1-2 days', '2' => 'Present 3+ days']],
            ['code' => 'E1c', 'name' => 'unrealistic_fears', 'display_name' => 'Expressions of unrealistic fears', 'group' => 'E_Mood', 'data_type' => 'integer', 'options' => ['0' => 'Not present', '1' => 'Present 1-2 days', '2' => 'Present 3+ days']],
            ['code' => 'E1d', 'name' => 'repetitive_health_complaints', 'display_name' => 'Repetitive health complaints', 'group' => 'E_Mood', 'data_type' => 'integer', 'options' => ['0' => 'Not present', '1' => 'Present 1-2 days', '2' => 'Present 3+ days']],
            ['code' => 'E1e', 'name' => 'repetitive_anxious_complaints', 'display_name' => 'Repetitive anxious complaints', 'group' => 'E_Mood', 'data_type' => 'integer', 'options' => ['0' => 'Not present', '1' => 'Present 1-2 days', '2' => 'Present 3+ days']],
            ['code' => 'E1f', 'name' => 'sad_pained_worried', 'display_name' => 'Sad, pained, worried facial expressions', 'group' => 'E_Mood', 'data_type' => 'integer', 'options' => ['0' => 'Not present', '1' => 'Present 1-2 days', '2' => 'Present 3+ days']],
            ['code' => 'E1g', 'name' => 'crying_tearfulness', 'display_name' => 'Crying, tearfulness', 'group' => 'E_Mood', 'data_type' => 'integer', 'options' => ['0' => 'Not present', '1' => 'Present 1-2 days', '2' => 'Present 3+ days']],

            // Section G - Functional Status (ADL items)
            ['code' => 'G4a', 'name' => 'iadl_meal_prep', 'display_name' => 'IADL: Meal Preparation', 'group' => 'G_IADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G4b', 'name' => 'iadl_housework', 'display_name' => 'IADL: Ordinary Housework', 'group' => 'G_IADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G4c', 'name' => 'iadl_finances', 'display_name' => 'IADL: Managing Finances', 'group' => 'G_IADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G4d', 'name' => 'iadl_medications', 'display_name' => 'IADL: Managing Medications', 'group' => 'G_IADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G4e', 'name' => 'iadl_phone', 'display_name' => 'IADL: Phone Use', 'group' => 'G_IADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G4f', 'name' => 'iadl_stairs', 'display_name' => 'IADL: Stairs', 'group' => 'G_IADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G4g', 'name' => 'iadl_shopping', 'display_name' => 'IADL: Shopping', 'group' => 'G_IADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G4h', 'name' => 'iadl_transportation', 'display_name' => 'IADL: Transportation', 'group' => 'G_IADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],

            // ADL Self-Performance items
            ['code' => 'G5a', 'name' => 'adl_bathing', 'display_name' => 'ADL: Bathing', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G5b', 'name' => 'adl_bath_transfer', 'display_name' => 'ADL: Bath Transfer', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G5c', 'name' => 'adl_personal_hygiene', 'display_name' => 'ADL: Personal Hygiene', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G5d', 'name' => 'adl_dressing_upper', 'display_name' => 'ADL: Dressing Upper Body', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G5e', 'name' => 'adl_dressing_lower', 'display_name' => 'ADL: Dressing Lower Body', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G5f', 'name' => 'adl_walking', 'display_name' => 'ADL: Walking', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G5g', 'name' => 'adl_locomotion', 'display_name' => 'ADL: Locomotion', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G5h', 'name' => 'adl_transfer_toilet', 'display_name' => 'ADL: Transfer Toilet', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G5i', 'name' => 'adl_toilet_use', 'display_name' => 'ADL: Toilet Use', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G5j', 'name' => 'adl_bed_mobility', 'display_name' => 'ADL: Bed Mobility', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],
            ['code' => 'G5k', 'name' => 'adl_eating', 'display_name' => 'ADL: Eating', 'group' => 'G_ADL', 'data_type' => 'integer', 'options' => ['0' => 'Independent', '1' => 'Setup help only', '2' => 'Supervision', '3' => 'Limited assistance', '4' => 'Extensive assistance', '5' => 'Maximal assistance', '6' => 'Total dependence', '8' => 'Activity did not occur']],

            // Section H - Continence
            ['code' => 'H1', 'name' => 'bladder_continence', 'display_name' => 'Bladder Continence', 'group' => 'H_Continence', 'data_type' => 'integer', 'options' => ['0' => 'Continent', '1' => 'Control with catheter/ostomy', '2' => 'Infrequently incontinent', '3' => 'Occasionally incontinent', '4' => 'Frequently incontinent', '5' => 'Incontinent']],
            ['code' => 'H2', 'name' => 'bowel_continence', 'display_name' => 'Bowel Continence', 'group' => 'H_Continence', 'data_type' => 'integer', 'options' => ['0' => 'Continent', '1' => 'Control with ostomy', '2' => 'Infrequently incontinent', '3' => 'Occasionally incontinent', '4' => 'Frequently incontinent', '5' => 'Incontinent']],

            // Section J - Health Conditions (for CHESS calculation)
            ['code' => 'J1a', 'name' => 'pain_frequency', 'display_name' => 'Pain Frequency', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['0' => 'No pain', '1' => 'Not in last 3 days', '2' => 'Less than daily', '3' => 'Daily']],
            ['code' => 'J1b', 'name' => 'pain_intensity', 'display_name' => 'Pain Intensity', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['1' => 'Mild', '2' => 'Moderate', '3' => 'Severe', '4' => 'Times when pain is horrible']],
            ['code' => 'J2a', 'name' => 'dyspnea', 'display_name' => 'Dyspnea (Shortness of Breath)', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['0' => 'Absent', '1' => 'Present']],
            ['code' => 'J2b', 'name' => 'fatigue', 'display_name' => 'Fatigue', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['0' => 'Absent', '1' => 'Present']],
            ['code' => 'J2c', 'name' => 'edema', 'display_name' => 'Edema', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['0' => 'Absent', '1' => 'Present']],
            ['code' => 'J2d', 'name' => 'dizziness', 'display_name' => 'Dizziness/Vertigo', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['0' => 'Absent', '1' => 'Present']],
            ['code' => 'J2e', 'name' => 'chest_pain', 'display_name' => 'Chest Pain', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['0' => 'Absent', '1' => 'Present']],
            ['code' => 'J3', 'name' => 'fall_history', 'display_name' => 'Fall in Last 90 Days', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['0' => 'No', '1' => 'Yes, no injury', '2' => 'Yes, with injury']],
            ['code' => 'J4', 'name' => 'weight_loss', 'display_name' => 'Unintended Weight Loss', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['0' => 'No', '1' => '5% in 30 days', '2' => '10% in 180 days']],
            ['code' => 'J5', 'name' => 'vomiting', 'display_name' => 'Vomiting', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['0' => 'Absent', '1' => 'Present']],
            ['code' => 'J6', 'name' => 'dehydration', 'display_name' => 'Insufficient Fluid Intake', 'group' => 'J_Health', 'data_type' => 'integer', 'options' => ['0' => 'No', '1' => 'Yes']],

            // Section P - Social Supports
            ['code' => 'P1', 'name' => 'primary_caregiver_lives_with', 'display_name' => 'Primary Caregiver Lives With Client', 'group' => 'P_Social', 'data_type' => 'integer', 'options' => ['0' => 'No', '1' => 'Yes']],
            ['code' => 'P2', 'name' => 'caregiver_stress', 'display_name' => 'Caregiver Unable to Continue', 'group' => 'P_Social', 'data_type' => 'integer', 'options' => ['0' => 'No', '1' => 'Yes']],
        ];

        $sortOrder = 0;
        foreach ($sections as $item) {
            DB::table('object_attributes')->insert([
                'object_definition_id' => $defId,
                'name' => $item['name'],
                'code' => $item['code'],
                'display_name' => $item['display_name'],
                'data_type' => $item['data_type'],
                'is_required' => false,
                'is_readonly' => false,
                'is_searchable' => false,
                'is_indexed' => false,
                'options' => json_encode($item['options']),
                'sort_order' => $sortOrder++,
                'group' => $item['group'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Seed calculation rules for InterRAI output scales.
     */
    private function seedCalculationRules(int $defId): void
    {
        // CPS (Cognitive Performance Scale) calculation rule
        DB::table('object_rules')->insert([
            'object_definition_id' => $defId,
            'name' => 'Calculate CPS Score',
            'code' => 'INTERRAI_CALC_CPS',
            'rule_type' => 'calculation',
            'trigger_event' => 'on_update',
            'conditions' => json_encode(['sections_completed' => ['C_Cognition']]),
            'actions' => json_encode(['set_field' => 'cognitive_performance_scale']),
            'expression' => 'CPS = f(C1, C2a, C3, G5k) per InterRAI algorithm',
            'priority' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ADL Hierarchy calculation rule
        DB::table('object_rules')->insert([
            'object_definition_id' => $defId,
            'name' => 'Calculate ADL Hierarchy Score',
            'code' => 'INTERRAI_CALC_ADL',
            'rule_type' => 'calculation',
            'trigger_event' => 'on_update',
            'conditions' => json_encode(['sections_completed' => ['G_ADL']]),
            'actions' => json_encode(['set_field' => 'adl_hierarchy']),
            'expression' => 'ADL = f(G5c, G5i, G5g, G5k) per InterRAI algorithm',
            'priority' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // CHESS (health instability) calculation rule
        DB::table('object_rules')->insert([
            'object_definition_id' => $defId,
            'name' => 'Calculate CHESS Score',
            'code' => 'INTERRAI_CALC_CHESS',
            'rule_type' => 'calculation',
            'trigger_event' => 'on_update',
            'conditions' => json_encode(['sections_completed' => ['J_Health', 'C_Cognition']]),
            'actions' => json_encode(['set_field' => 'chess_score']),
            'expression' => 'CHESS = f(J2, J4, J5, J6, C1 change, ADL change) per InterRAI algorithm',
            'priority' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // MAPLe (priority level) calculation rule
        DB::table('object_rules')->insert([
            'object_definition_id' => $defId,
            'name' => 'Calculate MAPLe Score',
            'code' => 'INTERRAI_CALC_MAPLE',
            'rule_type' => 'calculation',
            'trigger_event' => 'on_update',
            'conditions' => json_encode(['workflow_status' => 'completed']),
            'actions' => json_encode(['set_field' => 'maple_score']),
            'expression' => 'MAPLe = f(ADL, CPS, Behavior, falls, wandering, caregiver_stress) per InterRAI algorithm',
            'priority' => 20,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reassessment trigger rule
        DB::table('object_rules')->insert([
            'object_definition_id' => $defId,
            'name' => 'Trigger Reassessment After 90 Days',
            'code' => 'INTERRAI_REASSESS_90',
            'rule_type' => 'trigger',
            'trigger_event' => 'scheduled',
            'conditions' => json_encode(['days_since_assessment' => 90]),
            'actions' => json_encode([
                'set_patient_status' => 'interrai_stale',
                'create_reassessment_trigger' => true,
            ]),
            'priority' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
