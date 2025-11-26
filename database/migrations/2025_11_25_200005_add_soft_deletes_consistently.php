<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DQ-004: Add soft deletes consistently across tables
 *
 * OHaH regulatory requirements mandate data retention for audit purposes.
 * This migration adds soft delete support to tables that should preserve
 * historical records rather than hard deleting data.
 *
 * Tables receiving soft deletes:
 * - patients: Patient records must be retained for regulatory compliance
 * - care_plans: Historical care plans needed for continuity
 * - care_bundles: Bundle history for service analysis
 * - service_assignments: Service history for billing/audit
 * - interdisciplinary_notes: Clinical notes must be retained
 * - interrai_assessments: Assessment history for clinical tracking
 * - visits: Visit history for service verification
 * - triage_results: Triage history for intake tracking
 * - users: Staff records for audit trail
 *
 * Tables NOT receiving soft deletes (transient/system data):
 * - sessions, cache, jobs, failed_jobs
 * - password_resets, personal_access_tokens
 * - migrations, settings
 */
return new class extends Migration
{
    /**
     * Tables that should have soft deletes for data retention.
     */
    protected array $tablesForSoftDeletes = [
        'patients',
        'care_plans',
        'care_bundles',
        'service_assignments',
        'interdisciplinary_notes',
        'interrai_assessments',
        'visits',
        'triage_results',
        'users',
        'transition_needs_profiles',
        'hpg_referrals',
        'service_provider_organizations',
        'sspo_organizations',
    ];

    public function up(): void
    {
        foreach ($this->tablesForSoftDeletes as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->softDeletes();
                });

                // Add index for soft delete queries (exclude deleted records efficiently)
                $this->addSoftDeleteIndex($table);
            }
        }

        // Add deleted_by tracking for audit purposes on key tables
        $tablesWithDeletedBy = ['patients', 'care_plans', 'care_bundles', 'service_assignments'];

        foreach ($tablesWithDeletedBy as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_by')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreignId('deleted_by')->nullable()->after('deleted_at')
                        ->constrained('users')->nullOnDelete();
                });
            }
        }

        // Add deletion reason for compliance on critical tables
        $tablesWithDeletionReason = ['patients', 'care_plans'];

        foreach ($tablesWithDeletionReason as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deletion_reason')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('deletion_reason', 255)->nullable()->after('deleted_by');
                });
            }
        }
    }

    public function down(): void
    {
        // Remove deletion_reason columns
        $tablesWithDeletionReason = ['patients', 'care_plans'];

        foreach ($tablesWithDeletionReason as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deletion_reason')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('deletion_reason');
                });
            }
        }

        // Remove deleted_by columns
        $tablesWithDeletedBy = ['patients', 'care_plans', 'care_bundles', 'service_assignments'];

        foreach ($tablesWithDeletedBy as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_by')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropConstrainedForeignId('deleted_by');
                });
            }
        }

        // Remove soft delete columns
        foreach ($this->tablesForSoftDeletes as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                // Drop index first
                $this->dropSoftDeleteIndex($table);

                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropSoftDeletes();
                });
            }
        }
    }

    /**
     * Add an index optimized for soft delete queries.
     */
    protected function addSoftDeleteIndex(string $table): void
    {
        $indexName = "{$table}_soft_delete_idx";

        try {
            if (!$this->hasIndex($table, $indexName)) {
                Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                    $blueprint->index(['deleted_at'], $indexName);
                });
            }
        } catch (\Exception $e) {
            // Index may already exist under different name
        }
    }

    /**
     * Drop the soft delete index.
     */
    protected function dropSoftDeleteIndex(string $table): void
    {
        $indexName = "{$table}_soft_delete_idx";

        try {
            if ($this->hasIndex($table, $indexName)) {
                Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                    $blueprint->dropIndex($indexName);
                });
            }
        } catch (\Exception $e) {
            // Index may not exist
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
