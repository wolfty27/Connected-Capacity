<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SC-005: Add versioning support to care bundles
 *
 * This migration adds version tracking to care_bundles table allowing:
 * - Multiple versions of the same bundle template
 * - Tracking which version a care_plan used
 * - Archive/deprecation of old versions
 * - Change history for audit
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add versioning columns to care_bundles
        if (Schema::hasTable('care_bundles')) {
            Schema::table('care_bundles', function (Blueprint $table) {
                // Version info
                if (!Schema::hasColumn('care_bundles', 'version')) {
                    $table->unsignedInteger('version')->default(1)->after('name');
                }

                if (!Schema::hasColumn('care_bundles', 'parent_bundle_id')) {
                    $table->foreignId('parent_bundle_id')->nullable()->after('version')
                        ->constrained('care_bundles')->nullOnDelete();
                }

                if (!Schema::hasColumn('care_bundles', 'is_current_version')) {
                    $table->boolean('is_current_version')->default(true)->after('parent_bundle_id');
                }

                if (!Schema::hasColumn('care_bundles', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('is_current_version');
                }

                if (!Schema::hasColumn('care_bundles', 'deprecated_at')) {
                    $table->timestamp('deprecated_at')->nullable()->after('published_at');
                }

                if (!Schema::hasColumn('care_bundles', 'deprecation_reason')) {
                    $table->string('deprecation_reason', 255)->nullable()->after('deprecated_at');
                }

                if (!Schema::hasColumn('care_bundles', 'version_notes')) {
                    $table->text('version_notes')->nullable()->after('deprecation_reason');
                }
            });

            // Add index for version queries
            Schema::table('care_bundles', function (Blueprint $table) {
                if (!$this->hasIndex('care_bundles', 'care_bundles_version_idx')) {
                    $table->index(['parent_bundle_id', 'version'], 'care_bundles_version_idx');
                }

                if (!$this->hasIndex('care_bundles', 'care_bundles_current_idx')) {
                    $table->index(['is_current_version', 'published_at'], 'care_bundles_current_idx');
                }
            });
        }

        // Add bundle version reference to care_plans
        if (Schema::hasTable('care_plans')) {
            Schema::table('care_plans', function (Blueprint $table) {
                if (!Schema::hasColumn('care_plans', 'bundle_version')) {
                    $table->unsignedInteger('bundle_version')->nullable()->after('bundle_template_id');
                }

                if (!Schema::hasColumn('care_plans', 'bundle_snapshot')) {
                    $table->json('bundle_snapshot')->nullable()->after('bundle_version');
                }
            });
        }

        // Create bundle version history table
        Schema::create('care_bundle_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_bundle_id')->constrained('care_bundles')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('services_snapshot'); // Snapshot of services at this version
            $table->json('configuration_snapshot')->nullable(); // Any other config
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_summary')->nullable();
            $table->timestamps();

            $table->unique(['care_bundle_id', 'version'], 'bundle_version_unique');
            $table->index(['care_bundle_id', 'created_at'], 'bundle_version_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_bundle_versions');

        if (Schema::hasTable('care_plans')) {
            Schema::table('care_plans', function (Blueprint $table) {
                if (Schema::hasColumn('care_plans', 'bundle_snapshot')) {
                    $table->dropColumn('bundle_snapshot');
                }
                if (Schema::hasColumn('care_plans', 'bundle_version')) {
                    $table->dropColumn('bundle_version');
                }
            });
        }

        if (Schema::hasTable('care_bundles')) {
            Schema::table('care_bundles', function (Blueprint $table) {
                if ($this->hasIndex('care_bundles', 'care_bundles_current_idx')) {
                    $table->dropIndex('care_bundles_current_idx');
                }
                if ($this->hasIndex('care_bundles', 'care_bundles_version_idx')) {
                    $table->dropIndex('care_bundles_version_idx');
                }
            });

            Schema::table('care_bundles', function (Blueprint $table) {
                $columnsToDrop = [];

                if (Schema::hasColumn('care_bundles', 'version_notes')) {
                    $columnsToDrop[] = 'version_notes';
                }
                if (Schema::hasColumn('care_bundles', 'deprecation_reason')) {
                    $columnsToDrop[] = 'deprecation_reason';
                }
                if (Schema::hasColumn('care_bundles', 'deprecated_at')) {
                    $columnsToDrop[] = 'deprecated_at';
                }
                if (Schema::hasColumn('care_bundles', 'published_at')) {
                    $columnsToDrop[] = 'published_at';
                }
                if (Schema::hasColumn('care_bundles', 'is_current_version')) {
                    $columnsToDrop[] = 'is_current_version';
                }
                if (Schema::hasColumn('care_bundles', 'version')) {
                    $columnsToDrop[] = 'version';
                }

                if (Schema::hasColumn('care_bundles', 'parent_bundle_id')) {
                    $table->dropConstrainedForeignId('parent_bundle_id');
                }

                if (count($columnsToDrop) > 0) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }

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
