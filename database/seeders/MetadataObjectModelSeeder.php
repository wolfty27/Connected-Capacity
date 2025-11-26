<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * MetadataObjectModelSeeder - Initializes the Workday-style metadata object model
 *
 * Seeds the foundational metadata definitions for:
 * - Object Definitions (Patient, CareBundle, ServiceType, etc.)
 * - Object Attributes (properties of each object)
 * - Object Relationships (how objects relate)
 * - Object Rules (business logic)
 * - Bundle Configuration Rules (auto-configure bundles based on patient context)
 */
class MetadataObjectModelSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedObjectDefinitions();
        $this->seedObjectAttributes();
        $this->seedObjectRelationships();
        $this->seedObjectRules();
        $this->seedBundleConfigurationRules();
    }

    protected function seedObjectDefinitions(): void
    {
        $definitions = [
            [
                'name' => 'Patient',
                'code' => 'PATIENT',
                'display_name' => 'Patient',
                'description' => 'A person receiving care services',
                'category' => 'clinical',
                'base_table' => 'patients',
                'is_active' => true,
                'is_system' => true,
                'config' => json_encode(['model_class' => 'App\\Models\\Patient']),
            ],
            [
                'name' => 'CareBundle',
                'code' => 'CARE_BUNDLE',
                'display_name' => 'Care Bundle',
                'description' => 'A pre-configured package of care services',
                'category' => 'clinical',
                'base_table' => 'care_bundles',
                'is_active' => true,
                'is_system' => true,
                'config' => json_encode(['model_class' => 'App\\Models\\CareBundle']),
            ],
            [
                'name' => 'CarePlan',
                'code' => 'CARE_PLAN',
                'display_name' => 'Care Plan',
                'description' => 'An active care plan for a patient',
                'category' => 'clinical',
                'base_table' => 'care_plans',
                'is_active' => true,
                'is_system' => true,
                'config' => json_encode(['model_class' => 'App\\Models\\CarePlan']),
            ],
            [
                'name' => 'ServiceType',
                'code' => 'SERVICE_TYPE',
                'display_name' => 'Service Type',
                'description' => 'A type of care service',
                'category' => 'operational',
                'base_table' => 'service_types',
                'is_active' => true,
                'is_system' => true,
                'config' => json_encode(['model_class' => 'App\\Models\\ServiceType']),
            ],
            [
                'name' => 'ServiceAssignment',
                'code' => 'SERVICE_ASSIGNMENT',
                'display_name' => 'Service Assignment',
                'description' => 'An assignment of a service to a patient',
                'category' => 'operational',
                'base_table' => 'service_assignments',
                'is_active' => true,
                'is_system' => true,
                'config' => json_encode(['model_class' => 'App\\Models\\ServiceAssignment']),
            ],
            [
                'name' => 'TransitionNeedsProfile',
                'code' => 'TNP',
                'display_name' => 'Transition Needs Profile',
                'description' => 'Patient assessment for care planning',
                'category' => 'clinical',
                'base_table' => 'transition_needs_profiles',
                'is_active' => true,
                'is_system' => true,
                'config' => json_encode(['model_class' => 'App\\Models\\TransitionNeedsProfile']),
            ],
            [
                'name' => 'PatientQueue',
                'code' => 'PATIENT_QUEUE',
                'display_name' => 'Patient Queue',
                'description' => 'Queue entry for patient workflow',
                'category' => 'operational',
                'base_table' => 'patient_queue',
                'is_active' => true,
                'is_system' => true,
                'config' => json_encode(['model_class' => 'App\\Models\\PatientQueue']),
            ],
        ];

        foreach ($definitions as $def) {
            DB::table('object_definitions')->updateOrInsert(
                ['code' => $def['code']],
                array_merge($def, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    protected function seedObjectAttributes(): void
    {
        // Get definition IDs
        $patientId = DB::table('object_definitions')->where('code', 'PATIENT')->value('id');
        $queueId = DB::table('object_definitions')->where('code', 'PATIENT_QUEUE')->value('id');

        if (!$patientId || !$queueId) return;

        $patientAttributes = [
            ['name' => 'status', 'code' => 'status', 'display_name' => 'Status', 'data_type' => 'enum', 'is_required' => true, 'options' => json_encode([['value' => 'Active'], ['value' => 'Inactive'], ['value' => 'Discharged']])],
            ['name' => 'is_in_queue', 'code' => 'is_in_queue', 'display_name' => 'In Queue', 'data_type' => 'boolean', 'default_value' => 'false'],
            ['name' => 'maple_score', 'code' => 'maple_score', 'display_name' => 'MAPLe Score', 'data_type' => 'integer', 'is_searchable' => true],
            ['name' => 'rai_cha_score', 'code' => 'rai_cha_score', 'display_name' => 'RAI-CHA Score', 'data_type' => 'integer', 'is_searchable' => true],
            ['name' => 'risk_flags', 'code' => 'risk_flags', 'display_name' => 'Risk Flags', 'data_type' => 'json'],
        ];

        foreach ($patientAttributes as $i => $attr) {
            DB::table('object_attributes')->updateOrInsert(
                ['object_definition_id' => $patientId, 'code' => $attr['code']],
                array_merge($attr, [
                    'object_definition_id' => $patientId,
                    'sort_order' => $i,
                    'is_required' => $attr['is_required'] ?? false,
                    'is_readonly' => false,
                    'is_searchable' => $attr['is_searchable'] ?? false,
                    'is_indexed' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        $queueAttributes = [
            ['name' => 'queue_status', 'code' => 'queue_status', 'display_name' => 'Queue Status', 'data_type' => 'enum', 'is_required' => true],
            ['name' => 'priority', 'code' => 'priority', 'display_name' => 'Priority', 'data_type' => 'integer', 'default_value' => '5'],
            ['name' => 'entered_queue_at', 'code' => 'entered_queue_at', 'display_name' => 'Entered Queue At', 'data_type' => 'datetime'],
        ];

        foreach ($queueAttributes as $i => $attr) {
            DB::table('object_attributes')->updateOrInsert(
                ['object_definition_id' => $queueId, 'code' => $attr['code']],
                array_merge($attr, [
                    'object_definition_id' => $queueId,
                    'sort_order' => $i,
                    'is_required' => $attr['is_required'] ?? false,
                    'is_readonly' => false,
                    'is_searchable' => false,
                    'is_indexed' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }
    }

    protected function seedObjectRelationships(): void
    {
        $definitions = DB::table('object_definitions')->pluck('id', 'code');

        $relationships = [
            [
                'source_object_id' => $definitions['PATIENT'],
                'target_object_id' => $definitions['CARE_PLAN'],
                'name' => 'Patient Care Plans',
                'code' => 'carePlans',
                'relationship_type' => 'one_to_many',
                'inverse_name' => 'patient',
            ],
            [
                'source_object_id' => $definitions['PATIENT'],
                'target_object_id' => $definitions['PATIENT_QUEUE'],
                'name' => 'Patient Queue Entry',
                'code' => 'queueEntry',
                'relationship_type' => 'one_to_one',
                'inverse_name' => 'patient',
            ],
            [
                'source_object_id' => $definitions['PATIENT'],
                'target_object_id' => $definitions['TNP'],
                'name' => 'Transition Needs Profile',
                'code' => 'transitionNeedsProfile',
                'relationship_type' => 'one_to_one',
                'inverse_name' => 'patient',
            ],
            [
                'source_object_id' => $definitions['CARE_PLAN'],
                'target_object_id' => $definitions['CARE_BUNDLE'],
                'name' => 'Care Bundle',
                'code' => 'careBundle',
                'relationship_type' => 'many_to_one',
                'inverse_name' => 'carePlans',
            ],
            [
                'source_object_id' => $definitions['CARE_PLAN'],
                'target_object_id' => $definitions['SERVICE_ASSIGNMENT'],
                'name' => 'Service Assignments',
                'code' => 'serviceAssignments',
                'relationship_type' => 'one_to_many',
                'inverse_name' => 'carePlan',
            ],
            [
                'source_object_id' => $definitions['CARE_BUNDLE'],
                'target_object_id' => $definitions['SERVICE_TYPE'],
                'name' => 'Service Types',
                'code' => 'serviceTypes',
                'relationship_type' => 'many_to_many',
                'pivot_table' => 'care_bundle_service_type',
                'pivot_attributes' => json_encode(['default_frequency_per_week', 'default_provider_org_id', 'assignment_type', 'role_required']),
            ],
        ];

        foreach ($relationships as $rel) {
            DB::table('object_relationships')->updateOrInsert(
                ['source_object_id' => $rel['source_object_id'], 'code' => $rel['code']],
                array_merge($rel, [
                    'is_required' => false,
                    'cascade_delete' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }
    }

    protected function seedObjectRules(): void
    {
        $patientId = DB::table('object_definitions')->where('code', 'PATIENT')->value('id');
        $queueId = DB::table('object_definitions')->where('code', 'PATIENT_QUEUE')->value('id');

        if (!$patientId || !$queueId) return;

        $rules = [
            [
                'object_definition_id' => $patientId,
                'name' => 'Activate Patient on Bundle Publish',
                'code' => 'PATIENT_ACTIVATE_ON_BUNDLE',
                'rule_type' => 'transition',
                'trigger_event' => 'on_status_change',
                'conditions' => json_encode([
                    ['field' => 'transitioned_from_queue', 'operator' => 'equals', 'value' => true]
                ]),
                'actions' => json_encode([
                    ['type' => 'set_status', 'value' => 'Active']
                ]),
                'priority' => 10,
            ],
            [
                'object_definition_id' => $queueId,
                'name' => 'Validate Queue Transition',
                'code' => 'QUEUE_VALIDATE_TRANSITION',
                'rule_type' => 'validation',
                'trigger_event' => 'on_status_change',
                'conditions' => json_encode([]),
                'actions' => json_encode([
                    ['type' => 'validate_transition', 'message' => 'Invalid queue status transition']
                ]),
                'priority' => 1,
            ],
        ];

        foreach ($rules as $rule) {
            DB::table('object_rules')->updateOrInsert(
                ['code' => $rule['code']],
                array_merge($rule, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    protected function seedBundleConfigurationRules(): void
    {
        // Get bundle IDs if they exist
        $bundles = DB::table('care_bundles')->pluck('id', 'code');

        if ($bundles->isEmpty()) {
            return;
        }

        $configRules = [];

        // Rules for COMPLEX bundle
        if (isset($bundles['COMPLEX'])) {
            $configRules[] = [
                'care_bundle_id' => $bundles['COMPLEX'],
                'rule_name' => 'Increase Nursing for Wound Care',
                'rule_type' => 'frequency_adjustment',
                'conditions' => json_encode([
                    ['field' => 'has_wound_flag', 'operator' => 'equals', 'value' => true]
                ]),
                'actions' => json_encode([
                    ['type' => 'set_frequency', 'params' => ['value' => 3]]
                ]),
                'priority' => 10,
            ];

            $configRules[] = [
                'care_bundle_id' => $bundles['COMPLEX'],
                'rule_name' => 'Add RT for Respiratory',
                'rule_type' => 'inclusion',
                'conditions' => json_encode([
                    ['field' => 'has_respiratory_flag', 'operator' => 'equals', 'value' => true]
                ]),
                'actions' => json_encode([
                    ['type' => 'set_frequency', 'params' => ['value' => 2]],
                    ['type' => 'set_flag', 'params' => ['flag' => 'auto_included', 'value' => true]]
                ]),
                'priority' => 20,
            ];
        }

        // Rules for DEM-SUP bundle
        if (isset($bundles['DEM-SUP'])) {
            $configRules[] = [
                'care_bundle_id' => $bundles['DEM-SUP'],
                'rule_name' => 'Increase PSW for Cognitive',
                'rule_type' => 'frequency_adjustment',
                'conditions' => json_encode([
                    ['field' => 'has_cognitive_flag', 'operator' => 'equals', 'value' => true]
                ]),
                'actions' => json_encode([
                    ['type' => 'adjust_frequency', 'params' => ['adjustment' => 2]]
                ]),
                'priority' => 10,
            ];
        }

        // Rules for PALLIATIVE bundle
        if (isset($bundles['PALLIATIVE'])) {
            $configRules[] = [
                'care_bundle_id' => $bundles['PALLIATIVE'],
                'rule_name' => 'Add NP for Palliative',
                'rule_type' => 'inclusion',
                'conditions' => json_encode([
                    ['field' => 'has_palliative_flag', 'operator' => 'equals', 'value' => true]
                ]),
                'actions' => json_encode([
                    ['type' => 'set_frequency', 'params' => ['value' => 2]],
                    ['type' => 'set_flag', 'params' => ['flag' => 'palliative_support', 'value' => true]]
                ]),
                'priority' => 10,
            ];
        }

        foreach ($configRules as $rule) {
            DB::table('bundle_configuration_rules')->updateOrInsert(
                ['care_bundle_id' => $rule['care_bundle_id'], 'rule_name' => $rule['rule_name']],
                array_merge($rule, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
