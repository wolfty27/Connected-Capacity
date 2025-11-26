<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IR-005-01: Create assessment_reassessment_triggers table
 *
 * Tracks requests for InterRAI reassessment due to:
 * - Clinical condition changes
 * - Manual coordinator requests
 * - Clinical events (falls, hospitalizations, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_reassessment_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->onDelete('cascade');

            // Who triggered the reassessment
            $table->foreignId('triggered_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Trigger reason: 'condition_change', 'manual_request', 'clinical_event', 'stale_assessment'
            $table->string('trigger_reason', 50);

            // Additional notes/details about why reassessment is needed
            $table->text('reason_notes')->nullable();

            // Priority: 'low', 'medium', 'high', 'urgent'
            $table->string('priority', 20)->default('medium');

            // Resolution tracking
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->foreignId('resolution_assessment_id')
                ->nullable()
                ->constrained('interrai_assessments')
                ->onDelete('set null');
            $table->text('resolution_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('trigger_reason');
            $table->index('priority');
            $table->index(['patient_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_reassessment_triggers');
    }
};
