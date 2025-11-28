<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add InterRAI HC Assessment to the Metadata Object Model.
 *
 * This migration:
 * 1. Adds InterRAI queue statuses to patient workflow
 * 2. Enhances interrai_assessments for re-assessment tracking
 * 3. Integrates with object_definitions for metadata-driven configuration
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add InterRAI-related queue statuses to patient_queue
        // Laravel uses CHECK constraints for enum columns in PostgreSQL, not native enum types
        // We need to drop the old constraint and create a new one with additional values
        // NOTE: Status names updated to use assessment_* instead of tnp_*
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE patient_queue DROP CONSTRAINT IF EXISTS patient_queue_queue_status_check");
            DB::statement("ALTER TABLE patient_queue ADD CONSTRAINT patient_queue_queue_status_check CHECK (queue_status::text = ANY (ARRAY[
                'pending_intake', 'triage_in_progress', 'triage_complete',
                'assessment_in_progress', 'assessment_complete', 'bundle_building',
                'bundle_review', 'bundle_approved', 'transitioned',
                'interrai_required', 'interrai_in_progress', 'interrai_complete'
            ]::text[]))");
        }

        // 2. Enhance interrai_assessments table for re-assessment support
        // Use hasColumn checks to avoid duplicate column errors
        Schema::table('interrai_assessments', function (Blueprint $table) {
            // Assessment versioning - each patient can have multiple assessments
            if (!Schema::hasColumn('interrai_assessments', 'version')) {
                $table->integer('version')->default(1)->after('patient_id');
            }
            if (!Schema::hasColumn('interrai_assessments', 'is_current')) {
                $table->boolean('is_current')->default(true)->after('patient_id');
            }
        });

        Schema::table('interrai_assessments', function (Blueprint $table) {
            // Link to previous assessment for tracking changes
            if (!Schema::hasColumn('interrai_assessments', 'previous_assessment_id')) {
                $table->unsignedBigInteger('previous_assessment_id')->nullable();
                $table->foreign('previous_assessment_id')
                    ->references('id')->on('interrai_assessments')
                    ->nullOnDelete();
            }

            // Re-assessment trigger reason
            if (!Schema::hasColumn('interrai_assessments', 'reassessment_reason')) {
                $table->string('reassessment_reason')->nullable();
            }

            // Assessment workflow status (use string for SQLite compatibility)
            if (!Schema::hasColumn('interrai_assessments', 'workflow_status')) {
                $table->string('workflow_status', 20)->default('draft');
            }

            // Section completion tracking (JSON array of completed section codes)
            if (!Schema::hasColumn('interrai_assessments', 'sections_completed')) {
                $table->json('sections_completed')->nullable();
            }

            // Raw assessment items (JSON storage for form data)
            if (!Schema::hasColumn('interrai_assessments', 'raw_items')) {
                $table->json('raw_items')->nullable();
            }
        });

        Schema::table('interrai_assessments', function (Blueprint $table) {
            // Raw assessment items stored in object_instances, reference here
            if (!Schema::hasColumn('interrai_assessments', 'object_instance_id')) {
                $table->unsignedBigInteger('object_instance_id')->nullable();
                if (Schema::hasTable('object_instances')) {
                    $table->foreign('object_instance_id')
                        ->references('id')->on('object_instances')
                        ->nullOnDelete();
                }
            }

            // Time tracking
            if (!Schema::hasColumn('interrai_assessments', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            if (!Schema::hasColumn('interrai_assessments', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
            if (!Schema::hasColumn('interrai_assessments', 'time_spent_minutes')) {
                $table->integer('time_spent_minutes')->nullable();
            }
        });

        Schema::table('interrai_assessments', function (Blueprint $table) {
            // Review workflow
            if (!Schema::hasColumn('interrai_assessments', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->foreign('reviewed_by')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('interrai_assessments', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
            if (!Schema::hasColumn('interrai_assessments', 'review_notes')) {
                $table->text('review_notes')->nullable();
            }

            // Clinical scales calculated from raw items
            if (!Schema::hasColumn('interrai_assessments', 'iadl_capacity')) {
                $table->tinyInteger('iadl_capacity')->nullable();
            }
            if (!Schema::hasColumn('interrai_assessments', 'communication_scale')) {
                $table->tinyInteger('communication_scale')->nullable();
            }
            if (!Schema::hasColumn('interrai_assessments', 'social_engagement')) {
                $table->tinyInteger('social_engagement')->nullable();
            }
            if (!Schema::hasColumn('interrai_assessments', 'self_reliance')) {
                $table->tinyInteger('self_reliance')->nullable();
            }

            // Description fields for scores
            if (!Schema::hasColumn('interrai_assessments', 'maple_description')) {
                $table->string('maple_description', 50)->nullable();
            }
            if (!Schema::hasColumn('interrai_assessments', 'adl_description')) {
                $table->string('adl_description', 50)->nullable();
            }
            if (!Schema::hasColumn('interrai_assessments', 'cps_description')) {
                $table->string('cps_description', 50)->nullable();
            }

            // Notes field
            if (!Schema::hasColumn('interrai_assessments', 'notes')) {
                $table->text('notes')->nullable();
            }
        });

        // 3. Add reassessment scheduling to patients
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'next_interrai_due')) {
                $table->date('next_interrai_due')->nullable();
            }
            if (!Schema::hasColumn('patients', 'interrai_reassessment_reason')) {
                $table->string('interrai_reassessment_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'next_interrai_due')) {
                $table->dropColumn('next_interrai_due');
            }
            if (Schema::hasColumn('patients', 'interrai_reassessment_reason')) {
                $table->dropColumn('interrai_reassessment_reason');
            }
        });

        Schema::table('interrai_assessments', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['previous_assessment_id']);
            $table->dropForeign(['object_instance_id']);
            $table->dropForeign(['reviewed_by']);
        });

        Schema::table('interrai_assessments', function (Blueprint $table) {
            $columns = [
                'version',
                'is_current',
                'previous_assessment_id',
                'reassessment_reason',
                'workflow_status',
                'sections_completed',
                'raw_items',
                'object_instance_id',
                'started_at',
                'submitted_at',
                'time_spent_minutes',
                'reviewed_by',
                'reviewed_at',
                'review_notes',
                'iadl_capacity',
                'communication_scale',
                'social_engagement',
                'self_reliance',
                'maple_description',
                'adl_description',
                'cps_description',
                'notes',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('interrai_assessments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
