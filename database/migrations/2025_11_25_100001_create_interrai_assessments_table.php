<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IR-001: Create interrai_assessments table
 *
 * This table stores InterRAI Home Care (HC) assessment data required for OHaH compliance.
 * Per OHaH RFS Section 3.2.1:
 * - SPO must store InterRAI HC if provided by HPG
 * - SPO must complete InterRAI HC if missing or >3 months old
 * - SPO must upload to IAR in real-time
 * - SPO must sync to CHRIS
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interrai_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();

            // Assessment metadata
            $table->enum('assessment_type', ['hc', 'cha', 'contact'])->default('hc');
            $table->timestamp('assessment_date');
            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assessor_role', 50)->nullable(); // RN, Care Coordinator, etc.
            $table->enum('source', ['hpg_referral', 'spo_completed', 'ohah_provided'])->default('spo_completed');

            // InterRAI HC Output Scores
            $table->string('maple_score', 10)->nullable(); // MAPLe 1-5 scale
            $table->string('rai_cha_score', 10)->nullable(); // CHA Algorithm output
            $table->tinyInteger('adl_hierarchy')->nullable(); // 0-6 scale
            $table->tinyInteger('iadl_difficulty')->nullable(); // 0-6 scale
            $table->tinyInteger('cognitive_performance_scale')->nullable(); // 0-6 CPS
            $table->tinyInteger('depression_rating_scale')->nullable(); // 0-14 DRS
            $table->tinyInteger('pain_scale')->nullable(); // 0-3 scale
            $table->tinyInteger('chess_score')->nullable(); // 0-5 health instability
            $table->string('method_for_locomotion', 100)->nullable(); // Mobility status
            $table->boolean('falls_in_last_90_days')->default(false);
            $table->boolean('wandering_flag')->default(false); // Elopement risk

            // Clinical Diagnosis (CAPs - Clinical Assessment Protocols)
            $table->json('caps_triggered')->nullable(); // Array of triggered CAPs
            $table->string('primary_diagnosis_icd10', 10)->nullable(); // ICD-10 code
            $table->json('secondary_diagnoses')->nullable(); // Array of ICD-10 codes

            // IAR Integration (Integrated Assessment Record)
            $table->enum('iar_upload_status', ['pending', 'uploaded', 'failed', 'not_required'])->default('pending');
            $table->timestamp('iar_upload_timestamp')->nullable();
            $table->string('iar_confirmation_id', 100)->nullable(); // IAR system reference
            $table->enum('chris_sync_status', ['pending', 'synced', 'failed', 'not_required'])->default('pending');
            $table->timestamp('chris_sync_timestamp')->nullable();

            // Raw assessment data storage
            $table->longText('raw_assessment_data')->nullable(); // Full InterRAI instrument JSON

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['patient_id', 'assessment_date'], 'interrai_patient_date_idx');
            $table->index(['iar_upload_status', 'created_at'], 'interrai_iar_pending_idx');
            $table->index(['chris_sync_status', 'created_at'], 'interrai_chris_pending_idx');
            $table->index('assessment_type', 'interrai_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interrai_assessments');
    }
};
