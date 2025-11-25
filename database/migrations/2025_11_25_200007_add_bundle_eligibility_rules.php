<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SC-006: Add bundle eligibility rules
 *
 * This migration adds eligibility rule support to care bundles:
 * - JSON schema for eligibility criteria
 * - Rule evaluation based on patient attributes and assessments
 * - Priority scoring for bundle recommendations
 *
 * Rule Schema Example:
 * {
 *   "operator": "AND",
 *   "conditions": [
 *     { "field": "maple_score", "operator": ">=", "value": 4 },
 *     { "field": "diagnosis_flags", "operator": "contains", "value": "dementia" },
 *     { "field": "adl_hierarchy", "operator": ">=", "value": 3 }
 *   ]
 * }
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add eligibility rules to care_bundles
        if (Schema::hasTable('care_bundles')) {
            Schema::table('care_bundles', function (Blueprint $table) {
                if (!Schema::hasColumn('care_bundles', 'eligibility_rules')) {
                    $table->json('eligibility_rules')->nullable()->after('description');
                }

                if (!Schema::hasColumn('care_bundles', 'priority_weight')) {
                    $table->integer('priority_weight')->default(100)->after('eligibility_rules');
                }

                if (!Schema::hasColumn('care_bundles', 'auto_recommend')) {
                    $table->boolean('auto_recommend')->default(true)->after('priority_weight');
                }

                if (!Schema::hasColumn('care_bundles', 'requires_approval')) {
                    $table->boolean('requires_approval')->default(true)->after('auto_recommend');
                }

                if (!Schema::hasColumn('care_bundles', 'approval_role')) {
                    $table->string('approval_role', 50)->nullable()->after('requires_approval');
                }
            });
        }

        // Create eligibility rule definitions table
        Schema::create('bundle_eligibility_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_bundle_id')->constrained('care_bundles')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('rule_definition'); // The actual rule logic
            $table->integer('priority')->default(0); // Higher = evaluated first
            $table->boolean('is_required')->default(false); // Must pass for bundle
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['care_bundle_id', 'is_active'], 'eligibility_bundle_active_idx');
            $table->index(['priority'], 'eligibility_priority_idx');
        });

        // Create bundle recommendation log for tracking why bundles were recommended
        Schema::create('bundle_recommendation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('care_bundle_id')->constrained('care_bundles')->cascadeOnDelete();
            $table->json('evaluation_results'); // Which rules passed/failed
            $table->decimal('match_score', 5, 2); // 0-100 match percentage
            $table->boolean('was_selected')->default(false);
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();
            $table->text('override_reason')->nullable(); // If not selected, why
            $table->timestamps();

            $table->index(['patient_id', 'created_at'], 'recommendation_patient_idx');
            $table->index(['care_bundle_id', 'was_selected'], 'recommendation_bundle_idx');
        });

        // Seed default eligibility rules for OHaH bundles
        $this->seedDefaultRules();
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_recommendation_logs');
        Schema::dropIfExists('bundle_eligibility_rules');

        if (Schema::hasTable('care_bundles')) {
            Schema::table('care_bundles', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('care_bundles', 'approval_role')) $columns[] = 'approval_role';
                if (Schema::hasColumn('care_bundles', 'requires_approval')) $columns[] = 'requires_approval';
                if (Schema::hasColumn('care_bundles', 'auto_recommend')) $columns[] = 'auto_recommend';
                if (Schema::hasColumn('care_bundles', 'priority_weight')) $columns[] = 'priority_weight';
                if (Schema::hasColumn('care_bundles', 'eligibility_rules')) $columns[] = 'eligibility_rules';

                if (count($columns) > 0) {
                    $table->dropColumn($columns);
                }
            });
        }
    }

    /**
     * Seed default eligibility rules for OHaH bundles.
     */
    protected function seedDefaultRules(): void
    {
        $bundles = DB::table('care_bundles')->get();

        foreach ($bundles as $bundle) {
            $rules = $this->getRulesForBundle($bundle->name);

            if ($rules) {
                DB::table('care_bundles')
                    ->where('id', $bundle->id)
                    ->update([
                        'eligibility_rules' => json_encode($rules['combined']),
                        'priority_weight' => $rules['priority_weight'] ?? 100,
                        'auto_recommend' => $rules['auto_recommend'] ?? true,
                    ]);

                // Add individual rules
                foreach ($rules['individual'] ?? [] as $rule) {
                    DB::table('bundle_eligibility_rules')->insert([
                        'care_bundle_id' => $bundle->id,
                        'name' => $rule['name'],
                        'description' => $rule['description'] ?? null,
                        'rule_definition' => json_encode($rule['definition']),
                        'priority' => $rule['priority'] ?? 0,
                        'is_required' => $rule['is_required'] ?? false,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Get eligibility rules for specific bundle types.
     */
    protected function getRulesForBundle(string $bundleName): ?array
    {
        $bundleRules = [
            'High Intensity Home Care' => [
                'priority_weight' => 150,
                'auto_recommend' => true,
                'combined' => [
                    'operator' => 'AND',
                    'conditions' => [
                        ['field' => 'maple_score', 'operator' => '>=', 'value' => 4],
                        ['field' => 'adl_hierarchy', 'operator' => '>=', 'value' => 3],
                    ],
                ],
                'individual' => [
                    [
                        'name' => 'High MAPLe Score',
                        'description' => 'Patient has MAPLe score of 4 or 5',
                        'definition' => ['field' => 'maple_score', 'operator' => '>=', 'value' => 4],
                        'priority' => 100,
                        'is_required' => true,
                    ],
                    [
                        'name' => 'Significant ADL Needs',
                        'description' => 'ADL Hierarchy score indicates significant support needs',
                        'definition' => ['field' => 'adl_hierarchy', 'operator' => '>=', 'value' => 3],
                        'priority' => 90,
                        'is_required' => true,
                    ],
                ],
            ],
            'Dementia Care Bundle' => [
                'priority_weight' => 140,
                'auto_recommend' => true,
                'combined' => [
                    'operator' => 'AND',
                    'conditions' => [
                        ['field' => 'cps', 'operator' => '>=', 'value' => 3],
                        [
                            'operator' => 'OR',
                            'conditions' => [
                                ['field' => 'diagnosis_flags', 'operator' => 'contains', 'value' => 'dementia'],
                                ['field' => 'diagnosis_flags', 'operator' => 'contains', 'value' => 'alzheimers'],
                            ],
                        ],
                    ],
                ],
                'individual' => [
                    [
                        'name' => 'Cognitive Impairment',
                        'description' => 'CPS score indicates moderate to severe cognitive impairment',
                        'definition' => ['field' => 'cps', 'operator' => '>=', 'value' => 3],
                        'priority' => 100,
                        'is_required' => true,
                    ],
                    [
                        'name' => 'Dementia Diagnosis',
                        'description' => 'Patient has documented dementia or Alzheimer\'s diagnosis',
                        'definition' => [
                            'operator' => 'OR',
                            'conditions' => [
                                ['field' => 'diagnosis_flags', 'operator' => 'contains', 'value' => 'dementia'],
                                ['field' => 'diagnosis_flags', 'operator' => 'contains', 'value' => 'alzheimers'],
                            ],
                        ],
                        'priority' => 95,
                        'is_required' => false,
                    ],
                ],
            ],
            'Standard Home Care' => [
                'priority_weight' => 100,
                'auto_recommend' => true,
                'combined' => [
                    'operator' => 'AND',
                    'conditions' => [
                        ['field' => 'maple_score', 'operator' => '>=', 'value' => 2],
                        ['field' => 'maple_score', 'operator' => '<', 'value' => 4],
                    ],
                ],
                'individual' => [
                    [
                        'name' => 'Moderate Care Needs',
                        'description' => 'MAPLe score indicates moderate care needs',
                        'definition' => [
                            'operator' => 'AND',
                            'conditions' => [
                                ['field' => 'maple_score', 'operator' => '>=', 'value' => 2],
                                ['field' => 'maple_score', 'operator' => '<', 'value' => 4],
                            ],
                        ],
                        'priority' => 100,
                        'is_required' => true,
                    ],
                ],
            ],
        ];

        // Normalize bundle name for matching
        foreach ($bundleRules as $name => $rules) {
            if (stripos($bundleName, str_replace(' Bundle', '', str_replace(' Home Care', '', $name))) !== false) {
                return $rules;
            }
        }

        return null;
    }
};
