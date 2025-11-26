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
        // 1. Add InterRAI-related queue statuses to patient_queue enum
        // Note: For PostgreSQL, we need to alter the enum type
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TYPE patient_queue_queue_status_check ADD VALUE IF NOT EXISTS 'interrai_required'");
            DB::statement("ALTER TYPE patient_queue_queue_status_check ADD VALUE IF NOT EXISTS 'interrai_in_progress'");
            DB::statement("ALTER TYPE patient_queue_queue_status_check ADD VALUE IF NOT EXISTS 'interrai_complete'");
        }

        // 2. Enhance interrai_assessments table for re-assessment support
        Schema::table('interrai_assessments', function (Blueprint $table) {
            // Assessment versioning - each patient can have multiple assessments
            $table->integer('version')->default(1)->after('patient_id');
            $table->boolean('is_current')->default(true)->after('version');

            // Link to previous assessment for tracking changes
            $table->foreignId('previous_assessment_id')->nullable()->after('is_current')
                ->constrained('interrai_assessments')->nullOnDelete();

            // Re-assessment trigger reason
            $table->string('reassessment_reason')->nullable()->after('previous_assessment_id');

            // Assessment workflow status
            $table->enum('workflow_status', [
                'draft',
                'in_progress',
                'pending_review',
                'completed',
                'locked'
            ])->default('draft')->after('reassessment_reason');

            // Section completion tracking (JSON array of completed section codes)
            $table->json('sections_completed')->nullable()->after('workflow_status');

            // Raw assessment items stored in object_instances, reference here
            $table->foreignId('object_instance_id')->nullable()->after('sections_completed')
                ->constrained('object_instances')->nullOnDelete();

            // Time tracking
            $table->timestamp('started_at')->nullable()->after('object_instance_id');
            $table->timestamp('submitted_at')->nullable()->after('started_at');
            $table->integer('time_spent_minutes')->nullable()->after('submitted_at');

            // Review workflow
            $table->foreignId('reviewed_by')->nullable()->after('time_spent_minutes')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');

            // Clinical scales calculated from raw items
            $table->tinyInteger('iadl_capacity')->nullable()->after('iadl_difficulty');
            $table->tinyInteger('communication_scale')->nullable()->after('iadl_capacity');
            $table->tinyInteger('social_engagement')->nullable()->after('communication_scale');
            $table->tinyInteger('self_reliance')->nullable()->after('social_engagement');

            // CAPs triggered (stored as JSON array)
            $table->json('caps_triggered')->nullable()->after('self_reliance');

            // Indexes for finding assessments by status
            $table->index(['patient_id', 'is_current']);
            $table->index(['workflow_status']);
        });

        // 3. Add reassessment scheduling to patients
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'next_interrai_due')) {
                $table->date('next_interrai_due')->nullable()->after('interrai_status');
            }
            if (!Schema::hasColumn('patients', 'interrai_reassessment_reason')) {
                $table->string('interrai_reassessment_reason')->nullable()->after('next_interrai_due');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['next_interrai_due', 'interrai_reassessment_reason']);
        });

        Schema::table('interrai_assessments', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'is_current']);
            $table->dropIndex(['workflow_status']);

            $table->dropForeign(['previous_assessment_id']);
            $table->dropForeign(['object_instance_id']);
            $table->dropForeign(['reviewed_by']);

            $table->dropColumn([
                'version',
                'is_current',
                'previous_assessment_id',
                'reassessment_reason',
                'workflow_status',
                'sections_completed',
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
                'caps_triggered',
            ]);
        });
    }
};
