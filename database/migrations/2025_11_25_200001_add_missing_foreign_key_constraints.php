<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DQ-001: Add missing foreign key constraints to improve referential integrity
 *
 * This migration adds FK constraints to columns that reference other tables
 * but were created without explicit constraints in earlier migrations.
 * Also adds useful indexes for query performance.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add FK for bundle_recommendation_id in transition_needs_profiles
        if (Schema::hasTable('transition_needs_profiles') && Schema::hasColumn('transition_needs_profiles', 'bundle_recommendation_id')) {
            Schema::table('transition_needs_profiles', function (Blueprint $table) {
                // Check if care_bundles exists before adding constraint
                if (Schema::hasTable('care_bundles')) {
                    $table->foreign('bundle_recommendation_id')
                        ->references('id')
                        ->on('care_bundles')
                        ->nullOnDelete();
                }

                // Add index for status lookups
                if (!$this->hasIndex('transition_needs_profiles', 'tnp_patient_status_idx')) {
                    $table->index(['patient_id', 'status'], 'tnp_patient_status_idx');
                }
            });
        }

        // 2. Add FK for created_by/updated_by on care_bundles if they exist
        if (Schema::hasTable('care_bundles')) {
            Schema::table('care_bundles', function (Blueprint $table) {
                if (Schema::hasColumn('care_bundles', 'created_by') && !$this->hasForeignKey('care_bundles', 'care_bundles_created_by_foreign')) {
                    $table->foreign('created_by')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }

                if (Schema::hasColumn('care_bundles', 'updated_by') && !$this->hasForeignKey('care_bundles', 'care_bundles_updated_by_foreign')) {
                    $table->foreign('updated_by')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            });
        }

        // 3. Add FK for organization relationships if missing
        if (Schema::hasTable('sspo_organizations') && Schema::hasTable('service_provider_organizations')) {
            Schema::table('sspo_organizations', function (Blueprint $table) {
                if (Schema::hasColumn('sspo_organizations', 'parent_spo_id') && !$this->hasForeignKey('sspo_organizations', 'sspo_organizations_parent_spo_id_foreign')) {
                    $table->foreign('parent_spo_id')
                        ->references('id')
                        ->on('service_provider_organizations')
                        ->cascadeOnDelete();
                }
            });
        }

        // 4. Add composite indexes for common query patterns
        if (Schema::hasTable('service_assignments')) {
            Schema::table('service_assignments', function (Blueprint $table) {
                // Index for date range queries
                if (!$this->hasIndex('service_assignments', 'service_assignments_date_range_idx')) {
                    $table->index(['scheduled_start', 'scheduled_end'], 'service_assignments_date_range_idx');
                }

                // Index for SSPO acceptance workflow
                if (Schema::hasColumn('service_assignments', 'sspo_status')) {
                    if (!$this->hasIndex('service_assignments', 'service_assignments_sspo_workflow_idx')) {
                        $table->index(['sspo_status', 'sspo_notified_at'], 'service_assignments_sspo_workflow_idx');
                    }
                }
            });
        }

        // 5. Add indexes for InterRAI assessments workflow
        if (Schema::hasTable('interrai_assessments')) {
            Schema::table('interrai_assessments', function (Blueprint $table) {
                // Index for stale assessment detection
                if (!$this->hasIndex('interrai_assessments', 'interrai_staleness_idx')) {
                    $table->index(['source', 'assessment_date'], 'interrai_staleness_idx');
                }

                // Index for MAPLe score queries
                if (!$this->hasIndex('interrai_assessments', 'interrai_maple_idx')) {
                    $table->index(['maple_score', 'assessment_date'], 'interrai_maple_idx');
                }
            });
        }

        // 6. Add indexes for triage performance tracking
        if (Schema::hasTable('triage_results')) {
            Schema::table('triage_results', function (Blueprint $table) {
                // Index for HPG SLA monitoring
                if (Schema::hasColumn('triage_results', 'hpg_received_at') && !$this->hasIndex('triage_results', 'triage_hpg_sla_idx')) {
                    $table->index(['hpg_received_at', 'hpg_responded_at'], 'triage_hpg_sla_idx');
                }
            });
        }

        // 7. Add indexes for care plan approvals
        if (Schema::hasTable('care_plans')) {
            Schema::table('care_plans', function (Blueprint $table) {
                // Index for first service SLA
                if (!$this->hasIndex('care_plans', 'care_plans_approval_idx')) {
                    $table->index(['status', 'approved_at'], 'care_plans_approval_idx');
                }
            });
        }

        // 8. Add FK for visits.assigned_staff_id if column exists
        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'assigned_staff_id')) {
            Schema::table('visits', function (Blueprint $table) {
                if (!$this->hasForeignKey('visits', 'visits_assigned_staff_id_foreign')) {
                    $table->foreign('assigned_staff_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        // Remove FKs in reverse order
        if (Schema::hasTable('visits') && $this->hasForeignKey('visits', 'visits_assigned_staff_id_foreign')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->dropForeign(['assigned_staff_id']);
            });
        }

        if (Schema::hasTable('care_plans') && $this->hasIndex('care_plans', 'care_plans_approval_idx')) {
            Schema::table('care_plans', function (Blueprint $table) {
                $table->dropIndex('care_plans_approval_idx');
            });
        }

        if (Schema::hasTable('triage_results') && $this->hasIndex('triage_results', 'triage_hpg_sla_idx')) {
            Schema::table('triage_results', function (Blueprint $table) {
                $table->dropIndex('triage_hpg_sla_idx');
            });
        }

        if (Schema::hasTable('interrai_assessments')) {
            Schema::table('interrai_assessments', function (Blueprint $table) {
                if ($this->hasIndex('interrai_assessments', 'interrai_staleness_idx')) {
                    $table->dropIndex('interrai_staleness_idx');
                }
                if ($this->hasIndex('interrai_assessments', 'interrai_maple_idx')) {
                    $table->dropIndex('interrai_maple_idx');
                }
            });
        }

        if (Schema::hasTable('service_assignments')) {
            Schema::table('service_assignments', function (Blueprint $table) {
                if ($this->hasIndex('service_assignments', 'service_assignments_date_range_idx')) {
                    $table->dropIndex('service_assignments_date_range_idx');
                }
                if ($this->hasIndex('service_assignments', 'service_assignments_sspo_workflow_idx')) {
                    $table->dropIndex('service_assignments_sspo_workflow_idx');
                }
            });
        }

        if (Schema::hasTable('sspo_organizations') && $this->hasForeignKey('sspo_organizations', 'sspo_organizations_parent_spo_id_foreign')) {
            Schema::table('sspo_organizations', function (Blueprint $table) {
                $table->dropForeign(['parent_spo_id']);
            });
        }

        if (Schema::hasTable('care_bundles')) {
            Schema::table('care_bundles', function (Blueprint $table) {
                if ($this->hasForeignKey('care_bundles', 'care_bundles_created_by_foreign')) {
                    $table->dropForeign(['created_by']);
                }
                if ($this->hasForeignKey('care_bundles', 'care_bundles_updated_by_foreign')) {
                    $table->dropForeign(['updated_by']);
                }
            });
        }

        if (Schema::hasTable('transition_needs_profiles')) {
            Schema::table('transition_needs_profiles', function (Blueprint $table) {
                if ($this->hasForeignKey('transition_needs_profiles', 'transition_needs_profiles_bundle_recommendation_id_foreign')) {
                    $table->dropForeign(['bundle_recommendation_id']);
                }
                if ($this->hasIndex('transition_needs_profiles', 'tnp_patient_status_idx')) {
                    $table->dropIndex('tnp_patient_status_idx');
                }
            });
        }
    }

    /**
     * Check if a foreign key exists on a table.
     */
    protected function hasForeignKey(string $table, string $foreignKey): bool
    {
        $foreignKeys = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableForeignKeys($table);

        foreach ($foreignKeys as $fk) {
            if ($fk->getName() === $foreignKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an index exists on a table.
     */
    protected function hasIndex(string $table, string $index): bool
    {
        try {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                $result = Schema::getConnection()->select(
                    "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$table, $index]
                );
                return count($result) > 0;
            } elseif ($driver === 'sqlite') {
                $result = Schema::getConnection()->select(
                    "SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?",
                    [$index]
                );
                return count($result) > 0;
            } else {
                $result = Schema::getConnection()->select(
                    "SHOW INDEX FROM {$table} WHERE Key_name = ?",
                    [$index]
                );
                return count($result) > 0;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
};
