<?php

namespace Database\Seeders;

use App\Models\Metadata\ObjectAttribute;
use App\Models\Metadata\ObjectDefinition;
use App\Models\Metadata\ObjectRule;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * STAFF-012: Seed staff metadata ObjectDefinition
 *
 * Creates metadata-driven staff attributes and business rules
 * for the Connected Capacity workforce management system.
 */
class StaffMetadataSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // Staff ObjectDefinition
        // ==========================================
        $staffDef = ObjectDefinition::updateOrCreate(
            ['code' => 'STAFF'],
            [
                'name' => 'Staff Member',
                'display_name' => 'Staff Member',
                'category' => 'workforce',
                'base_table' => 'users',
                'config' => [
                    'model_class' => User::class,
                    'filterable_roles' => [
                        User::ROLE_FIELD_STAFF,
                        User::ROLE_SPO_COORDINATOR,
                        User::ROLE_SSPO_COORDINATOR,
                    ],
                    'supports_skills' => true,
                    'supports_availability' => true,
                    'supports_fte_tracking' => true,
                ],
                'is_active' => true,
                'is_system' => true,
            ]
        );

        $this->command->info('Created STAFF ObjectDefinition');

        // ==========================================
        // Staff Custom Attributes
        // ==========================================
        $attributes = [
            [
                'code' => 'preferred_service_areas',
                'name' => 'Preferred Service Areas',
                'data_type' => 'json',
                'is_required' => false,
                'is_searchable' => true,
                'group' => 'preferences',
                'config' => [
                    'description' => 'Geographic areas the staff member prefers to work in',
                    'ui_component' => 'multi-select',
                ],
            ],
            [
                'code' => 'language_proficiencies',
                'name' => 'Language Proficiencies',
                'data_type' => 'json',
                'is_required' => false,
                'is_searchable' => true,
                'group' => 'capabilities',
                'config' => [
                    'description' => 'Languages spoken by the staff member',
                    'options' => [
                        ['value' => 'en', 'label' => 'English'],
                        ['value' => 'fr', 'label' => 'French'],
                        ['value' => 'zh', 'label' => 'Mandarin'],
                        ['value' => 'yue', 'label' => 'Cantonese'],
                        ['value' => 'pa', 'label' => 'Punjabi'],
                        ['value' => 'hi', 'label' => 'Hindi'],
                        ['value' => 'es', 'label' => 'Spanish'],
                        ['value' => 'pt', 'label' => 'Portuguese'],
                        ['value' => 'it', 'label' => 'Italian'],
                        ['value' => 'ar', 'label' => 'Arabic'],
                        ['value' => 'tl', 'label' => 'Filipino/Tagalog'],
                    ],
                    'ui_component' => 'multi-select',
                ],
            ],
            [
                'code' => 'transportation_method',
                'name' => 'Transportation Method',
                'data_type' => 'string',
                'is_required' => false,
                'is_searchable' => true,
                'group' => 'logistics',
                'config' => [
                    'description' => 'How the staff member travels to visits',
                    'options' => [
                        ['value' => 'car', 'label' => 'Personal Vehicle'],
                        ['value' => 'public_transit', 'label' => 'Public Transit'],
                        ['value' => 'bicycle', 'label' => 'Bicycle'],
                        ['value' => 'walking', 'label' => 'Walking'],
                    ],
                    'ui_component' => 'select',
                ],
            ],
            [
                'code' => 'max_travel_distance_km',
                'name' => 'Maximum Travel Distance (km)',
                'data_type' => 'integer',
                'is_required' => false,
                'is_searchable' => true,
                'group' => 'logistics',
                'config' => [
                    'description' => 'Maximum distance willing to travel for visits',
                    'default_value' => 25,
                    'min' => 1,
                    'max' => 100,
                    'ui_component' => 'number-input',
                ],
            ],
            [
                'code' => 'emergency_contact_name',
                'name' => 'Emergency Contact Name',
                'data_type' => 'string',
                'is_required' => false,
                'is_searchable' => false,
                'group' => 'personal',
                'config' => [
                    'description' => 'Emergency contact person',
                    'ui_component' => 'text-input',
                ],
            ],
            [
                'code' => 'emergency_contact_phone',
                'name' => 'Emergency Contact Phone',
                'data_type' => 'string',
                'is_required' => false,
                'is_searchable' => false,
                'group' => 'personal',
                'config' => [
                    'description' => 'Emergency contact phone number',
                    'ui_component' => 'phone-input',
                ],
            ],
            [
                'code' => 'license_number',
                'name' => 'Professional License Number',
                'data_type' => 'string',
                'is_required' => false,
                'is_searchable' => true,
                'group' => 'credentials',
                'config' => [
                    'description' => 'Nursing or professional license number',
                    'ui_component' => 'text-input',
                ],
            ],
            [
                'code' => 'license_expiry',
                'name' => 'License Expiry Date',
                'data_type' => 'date',
                'is_required' => false,
                'is_searchable' => true,
                'group' => 'credentials',
                'config' => [
                    'description' => 'Date when professional license expires',
                    'ui_component' => 'date-picker',
                ],
            ],
            [
                'code' => 'specialty_areas',
                'name' => 'Specialty Areas',
                'data_type' => 'json',
                'is_required' => false,
                'is_searchable' => true,
                'group' => 'capabilities',
                'config' => [
                    'description' => 'Areas of clinical specialty',
                    'options' => [
                        ['value' => 'wound_care', 'label' => 'Wound Care'],
                        ['value' => 'palliative', 'label' => 'Palliative Care'],
                        ['value' => 'dementia', 'label' => 'Dementia Care'],
                        ['value' => 'pediatric', 'label' => 'Pediatric'],
                        ['value' => 'mental_health', 'label' => 'Mental Health'],
                        ['value' => 'diabetes', 'label' => 'Diabetes Management'],
                        ['value' => 'respiratory', 'label' => 'Respiratory Care'],
                    ],
                    'ui_component' => 'multi-select',
                ],
            ],
            [
                'code' => 'performance_rating',
                'name' => 'Performance Rating',
                'data_type' => 'decimal',
                'is_required' => false,
                'is_searchable' => true,
                'group' => 'performance',
                'config' => [
                    'description' => 'Overall performance rating (1-5)',
                    'min' => 1,
                    'max' => 5,
                    'decimal_places' => 1,
                    'ui_component' => 'rating',
                ],
            ],
        ];

        foreach ($attributes as $index => $attrData) {
            ObjectAttribute::updateOrCreate(
                [
                    'object_definition_id' => $staffDef->id,
                    'code' => $attrData['code'],
                ],
                array_merge($attrData, [
                    'object_definition_id' => $staffDef->id,
                    'display_order' => $index + 1,
                    'is_active' => true,
                ])
            );
        }

        $this->command->info('Created ' . count($attributes) . ' staff attributes');

        // ==========================================
        // Staff Business Rules (STAFF-013)
        // ==========================================
        $rules = [
            // Dementia bundle skill requirement
            [
                'code' => 'DEMENTIA_SKILL_REQUIRED',
                'name' => 'Dementia Skill Required for DEM-SUP Bundle',
                'description' => 'Staff assigned to dementia support bundles must have dementia care certification',
                'rule_type' => 'validation',
                'trigger_event' => 'assignment.create',
                'conditions' => [
                    'bundle.code' => ['$in' => ['DEM-SUP', 'DEM-INT']],
                    'assignment.assigned_user_id' => ['$exists' => true],
                ],
                'actions' => [
                    [
                        'type' => 'validate_skill',
                        'skill_code' => 'DEMENTIA_CARE',
                        'message' => 'Staff must have dementia care certification for this bundle',
                    ],
                ],
                'priority' => 10,
                'is_active' => true,
            ],
            // Palliative bundle skill requirement
            [
                'code' => 'PALLIATIVE_SKILL_REQUIRED',
                'name' => 'Palliative Skill Required for PALL-SUP Bundle',
                'description' => 'Staff assigned to palliative support bundles must have palliative care certification',
                'rule_type' => 'validation',
                'trigger_event' => 'assignment.create',
                'conditions' => [
                    'bundle.code' => ['$in' => ['PALL-SUP', 'PALL-INT']],
                    'assignment.assigned_user_id' => ['$exists' => true],
                ],
                'actions' => [
                    [
                        'type' => 'validate_skill',
                        'skill_code' => 'PALLIATIVE_CARE',
                        'message' => 'Staff must have palliative care certification for this bundle',
                    ],
                ],
                'priority' => 10,
                'is_active' => true,
            ],
            // Mental health bundle skill requirement
            [
                'code' => 'MH_SKILL_REQUIRED',
                'name' => 'Mental Health Skill Required for MH Bundle',
                'description' => 'Staff assigned to mental health bundles must have mental health first aid certification',
                'rule_type' => 'validation',
                'trigger_event' => 'assignment.create',
                'conditions' => [
                    'bundle.code' => ['$in' => ['MH-SUP', 'MH-INT']],
                    'assignment.assigned_user_id' => ['$exists' => true],
                ],
                'actions' => [
                    [
                        'type' => 'validate_skill',
                        'skill_code' => 'MH_FIRST_AID',
                        'message' => 'Staff must have mental health first aid certification for this bundle',
                    ],
                ],
                'priority' => 10,
                'is_active' => true,
            ],
            // FTE compliance check
            [
                'code' => 'FTE_80_COMPLIANCE',
                'name' => '80% FTE Compliance Check',
                'description' => 'Alert when SPO FTE ratio falls below 80% target',
                'rule_type' => 'calculation',
                'trigger_event' => 'weekly.huddle',
                'conditions' => [],
                'expression' => '(${internal_staff_hours} / ${total_care_hours}) * 100',
                'actions' => [
                    [
                        'type' => 'alert_if_below',
                        'threshold' => 80,
                        'severity' => 'warning',
                        'message' => 'FTE ratio below 80% target - OHaH compliance at risk',
                    ],
                    [
                        'type' => 'alert_if_below',
                        'threshold' => 75,
                        'severity' => 'critical',
                        'message' => 'FTE ratio critically low - immediate action required',
                    ],
                ],
                'priority' => 5,
                'is_active' => true,
            ],
            // Skill expiry warning
            [
                'code' => 'SKILL_EXPIRY_WARNING',
                'name' => 'Skill Expiry Warning',
                'description' => 'Alert coordinators when staff skills are expiring within 30 days',
                'rule_type' => 'trigger',
                'trigger_event' => 'daily.check',
                'conditions' => [
                    'staff_skills.expires_at' => ['$lte' => '${today + 30 days}'],
                    'staff_skills.expires_at' => ['$gt' => '${today}'],
                ],
                'actions' => [
                    [
                        'type' => 'notify',
                        'recipients' => ['staff', 'coordinator'],
                        'template' => 'skill_expiry_warning',
                        'message' => 'Certification for ${skill.name} expires on ${expires_at}',
                    ],
                ],
                'priority' => 20,
                'is_active' => true,
            ],
            // Availability conflict check
            [
                'code' => 'AVAILABILITY_CONFLICT',
                'name' => 'Availability Conflict Check',
                'description' => 'Prevent assignment when staff is unavailable',
                'rule_type' => 'validation',
                'trigger_event' => 'assignment.create',
                'conditions' => [
                    'assignment.assigned_user_id' => ['$exists' => true],
                    'assignment.scheduled_start' => ['$exists' => true],
                ],
                'actions' => [
                    [
                        'type' => 'validate_availability',
                        'check_unavailabilities' => true,
                        'check_availability_windows' => true,
                        'message' => 'Staff is not available at the scheduled time',
                    ],
                ],
                'priority' => 15,
                'is_active' => true,
            ],
            // Capacity overload warning
            [
                'code' => 'CAPACITY_OVERLOAD',
                'name' => 'Capacity Overload Warning',
                'description' => 'Warn when staff is assigned beyond their weekly capacity',
                'rule_type' => 'validation',
                'trigger_event' => 'assignment.create',
                'conditions' => [
                    'assignment.assigned_user_id' => ['$exists' => true],
                ],
                'actions' => [
                    [
                        'type' => 'validate_capacity',
                        'max_utilization' => 100,
                        'warning_threshold' => 90,
                        'message' => 'Staff weekly capacity would exceed ${utilization}%',
                    ],
                ],
                'priority' => 12,
                'is_active' => true,
            ],
        ];

        foreach ($rules as $ruleData) {
            ObjectRule::updateOrCreate(
                [
                    'object_definition_id' => $staffDef->id,
                    'code' => $ruleData['code'],
                ],
                array_merge($ruleData, [
                    'object_definition_id' => $staffDef->id,
                ])
            );
        }

        $this->command->info('Created ' . count($rules) . ' staff business rules');
    }
}
