<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create LLM Explanation Logs Table
 *
 * This table stores audit records of all LLM explanation requests.
 *
 * IMPORTANT: This table intentionally does NOT store prompts or responses
 * as they may contain derived PHI/PII information. Only IDs and metadata
 * are logged for audit and debugging purposes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_explanation_logs', function (Blueprint $table) {
            $table->id();

            // Foreign keys to related entities
            $table->foreignId('patient_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('staff_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->foreignId('service_type_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('organization_id')
                ->constrained('service_provider_organizations')
                ->onDelete('cascade');

            // User who triggered the explanation request
            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Source of the explanation
            // 'vertex_ai' = LLM generated
            // 'fallback' = Rules-based fallback
            $table->string('source', 20);

            // Status of the request
            // 'success' = Explanation generated successfully
            // 'timeout' = Request timed out
            // 'rate_limited' = Rate limit exceeded
            // 'auth_error' = Authentication failed
            // 'error' = Other error
            // 'vertex_ai_disabled' = Feature disabled in config
            // 'no_match_case' = No staff match found (expected)
            $table->string('status', 50);

            // Scoring metadata (safe to log)
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('match_status', 20)->nullable();

            // Request metadata
            $table->integer('candidates_evaluated')->nullable();
            $table->integer('candidates_passed')->nullable();

            // Performance metrics
            $table->integer('response_time_ms')->nullable();

            // DO NOT add prompt or response columns - they may contain PHI
            // Only log IDs and summary metadata for audit purposes

            $table->timestamp('created_at')->useCurrent();

            // Indexes for common queries
            $table->index(['organization_id', 'created_at'], 'llm_logs_org_date_idx');
            $table->index(['source', 'status'], 'llm_logs_source_status_idx');
            $table->index(['patient_id', 'created_at'], 'llm_logs_patient_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_explanation_logs');
    }
};
