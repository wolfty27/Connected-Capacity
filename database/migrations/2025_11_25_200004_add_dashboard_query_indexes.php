<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DQ-003: Add database indexes to optimize dashboard and reporting queries
 *
 * These indexes support the CC2 dashboard requirements:
 * - Patient census by status
 * - Service utilization metrics
 * - Staff workload distribution
 * - SLA compliance tracking
 * - Geographic distribution
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Patient dashboard indexes
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                // Census queries by status and organization
                if (!$this->hasIndex('patients', 'patients_census_idx')) {
                    $columns = ['status'];
                    if (Schema::hasColumn('patients', 'spo_id')) {
                        $columns[] = 'spo_id';
                    }
                    $table->index($columns, 'patients_census_idx');
                }

                // Geographic distribution queries
                if (Schema::hasColumn('patients', 'postal_code') && !$this->hasIndex('patients', 'patients_geo_idx')) {
                    $table->index(['postal_code'], 'patients_geo_idx');
                }

                // Recently updated patients
                if (!$this->hasIndex('patients', 'patients_recent_idx')) {
                    $table->index(['updated_at'], 'patients_recent_idx');
                }
            });
        }

        // 2. Service assignment dashboard indexes
        if (Schema::hasTable('service_assignments')) {
            Schema::table('service_assignments', function (Blueprint $table) {
                // Staff workload queries
                if (Schema::hasColumn('service_assignments', 'assigned_user_id') && !$this->hasIndex('service_assignments', 'sa_staff_workload_idx')) {
                    $table->index(['assigned_user_id', 'scheduled_start', 'status'], 'sa_staff_workload_idx');
                }

                // Service utilization by type
                if (Schema::hasColumn('service_assignments', 'service_type_id') && !$this->hasIndex('service_assignments', 'sa_utilization_idx')) {
                    $table->index(['service_type_id', 'status', 'scheduled_start'], 'sa_utilization_idx');
                }

                // Patient service history
                if (Schema::hasColumn('service_assignments', 'patient_id') && !$this->hasIndex('service_assignments', 'sa_patient_history_idx')) {
                    $table->index(['patient_id', 'scheduled_start'], 'sa_patient_history_idx');
                }

                // SSPO assignment tracking
                if (Schema::hasColumn('service_assignments', 'sspo_id') && !$this->hasIndex('service_assignments', 'sa_sspo_tracking_idx')) {
                    $table->index(['sspo_id', 'status', 'scheduled_start'], 'sa_sspo_tracking_idx');
                }

                // Completion rate queries
                if (!$this->hasIndex('service_assignments', 'sa_completion_idx')) {
                    $table->index(['status', 'actual_end'], 'sa_completion_idx');
                }
            });
        }

        // 3. Care bundle dashboard indexes
        if (Schema::hasTable('care_bundles')) {
            Schema::table('care_bundles', function (Blueprint $table) {
                // Bundle status dashboard - only if status column exists
                if (Schema::hasColumn('care_bundles', 'status') && !$this->hasIndex('care_bundles', 'cb_status_dashboard_idx')) {
                    $table->index(['status', 'created_at'], 'cb_status_dashboard_idx');
                }

                // Bundle by patient lookup - only if both columns exist
                if (Schema::hasColumn('care_bundles', 'patient_id') && Schema::hasColumn('care_bundles', 'status') && !$this->hasIndex('care_bundles', 'cb_patient_lookup_idx')) {
                    $table->index(['patient_id', 'status'], 'cb_patient_lookup_idx');
                }
            });
        }

        // 4. Care plan SLA tracking indexes
        if (Schema::hasTable('care_plans')) {
            Schema::table('care_plans', function (Blueprint $table) {
                // First service SLA tracking
                if (Schema::hasColumn('care_plans', 'first_service_due') && !$this->hasIndex('care_plans', 'cp_first_service_sla_idx')) {
                    $table->index(['first_service_due', 'first_service_completed_at'], 'cp_first_service_sla_idx');
                }

                // Patient care plan lookup
                if (Schema::hasColumn('care_plans', 'patient_id') && !$this->hasIndex('care_plans', 'cp_patient_lookup_idx')) {
                    $table->index(['patient_id', 'status'], 'cp_patient_lookup_idx');
                }
            });
        }

        // 5. Triage SLA dashboard indexes
        if (Schema::hasTable('triage_results')) {
            Schema::table('triage_results', function (Blueprint $table) {
                // Triage completion tracking - only if status column exists
                if (Schema::hasColumn('triage_results', 'status') && !$this->hasIndex('triage_results', 'tr_completion_idx')) {
                    $table->index(['status', 'created_at'], 'tr_completion_idx');
                }

                // Priority distribution - only if both columns exist
                if (Schema::hasColumn('triage_results', 'priority') && Schema::hasColumn('triage_results', 'status') && !$this->hasIndex('triage_results', 'tr_priority_idx')) {
                    $table->index(['priority', 'status'], 'tr_priority_idx');
                }
            });
        }

        // 6. User/staff dashboard indexes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Staff by organization - check both columns exist
                if (Schema::hasColumn('users', 'organization_id') && Schema::hasColumn('users', 'is_active') && !$this->hasIndex('users', 'users_org_idx')) {
                    $table->index(['organization_id', 'is_active'], 'users_org_idx');
                }

                // Staff by role for scheduling - check both columns exist
                if (Schema::hasColumn('users', 'role') && Schema::hasColumn('users', 'is_active') && !$this->hasIndex('users', 'users_role_idx')) {
                    $table->index(['role', 'is_active'], 'users_role_idx');
                }
            });
        }

        // 7. Interdisciplinary notes dashboard indexes
        if (Schema::hasTable('interdisciplinary_notes')) {
            Schema::table('interdisciplinary_notes', function (Blueprint $table) {
                // Notes by patient timeline
                if (Schema::hasColumn('interdisciplinary_notes', 'patient_id') && !$this->hasIndex('interdisciplinary_notes', 'idn_patient_timeline_idx')) {
                    $table->index(['patient_id', 'created_at'], 'idn_patient_timeline_idx');
                }

                // Notes by author for staff activity
                if (Schema::hasColumn('interdisciplinary_notes', 'user_id') && !$this->hasIndex('interdisciplinary_notes', 'idn_author_idx')) {
                    $table->index(['user_id', 'created_at'], 'idn_author_idx');
                }

                // Notes by type for categorization
                if (Schema::hasColumn('interdisciplinary_notes', 'note_type') && !$this->hasIndex('interdisciplinary_notes', 'idn_type_idx')) {
                    $table->index(['note_type', 'created_at'], 'idn_type_idx');
                }
            });
        }

        // 8. Visit tracking indexes
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                // Visit completion dashboard - check columns exist
                if (Schema::hasColumn('visits', 'status') && Schema::hasColumn('visits', 'scheduled_date') && !$this->hasIndex('visits', 'visits_completion_idx')) {
                    $table->index(['status', 'scheduled_date'], 'visits_completion_idx');
                }

                // Patient visit history
                if (Schema::hasColumn('visits', 'patient_id') && Schema::hasColumn('visits', 'scheduled_date') && !$this->hasIndex('visits', 'visits_patient_idx')) {
                    $table->index(['patient_id', 'scheduled_date'], 'visits_patient_idx');
                }
            });
        }

        // 9. Audit log indexes for compliance reporting
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                // User activity queries
                if (Schema::hasColumn('audit_logs', 'user_id') && !$this->hasIndex('audit_logs', 'audit_user_activity_idx')) {
                    $table->index(['user_id', 'created_at'], 'audit_user_activity_idx');
                }

                // Entity audit trail
                if (Schema::hasColumn('audit_logs', 'auditable_type') && !$this->hasIndex('audit_logs', 'audit_entity_idx')) {
                    $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_entity_idx');
                }

                // Action type filtering
                if (Schema::hasColumn('audit_logs', 'event') && !$this->hasIndex('audit_logs', 'audit_event_idx')) {
                    $table->index(['event', 'created_at'], 'audit_event_idx');
                }
            });
        }

        // 10. HPG referral tracking indexes
        if (Schema::hasTable('hpg_referrals')) {
            Schema::table('hpg_referrals', function (Blueprint $table) {
                // Referral status dashboard
                if (!$this->hasIndex('hpg_referrals', 'hpg_status_idx')) {
                    $table->index(['status', 'received_at'], 'hpg_status_idx');
                }

                // SLA compliance tracking
                if (Schema::hasColumn('hpg_referrals', 'response_due_at') && !$this->hasIndex('hpg_referrals', 'hpg_sla_idx')) {
                    $table->index(['response_due_at', 'responded_at'], 'hpg_sla_idx');
                }
            });
        }
    }

    public function down(): void
    {
        $indexesToDrop = [
            'patients' => ['patients_census_idx', 'patients_geo_idx', 'patients_recent_idx'],
            'service_assignments' => ['sa_staff_workload_idx', 'sa_utilization_idx', 'sa_patient_history_idx', 'sa_sspo_tracking_idx', 'sa_completion_idx'],
            'care_bundles' => ['cb_status_dashboard_idx', 'cb_patient_lookup_idx'],
            'care_plans' => ['cp_first_service_sla_idx', 'cp_patient_lookup_idx'],
            'triage_results' => ['tr_completion_idx', 'tr_priority_idx'],
            'users' => ['users_org_idx', 'users_role_idx'],
            'interdisciplinary_notes' => ['idn_patient_timeline_idx', 'idn_author_idx', 'idn_type_idx'],
            'visits' => ['visits_completion_idx', 'visits_patient_idx'],
            'audit_logs' => ['audit_user_activity_idx', 'audit_entity_idx', 'audit_event_idx'],
            'hpg_referrals' => ['hpg_status_idx', 'hpg_sla_idx'],
        ];

        foreach ($indexesToDrop as $table => $indexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($indexes) {
                    foreach ($indexes as $index) {
                        if ($this->hasIndex($table->getTable(), $index)) {
                            $table->dropIndex($index);
                        }
                    }
                });
            }
        }
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
