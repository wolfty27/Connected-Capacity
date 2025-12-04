<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create AI Suggestion Logs table for learning loop tracking
 * 
 * This table logs all AI scheduling suggestions and their outcomes to:
 * 1. Track acceptance/rejection rates by suggestion type
 * 2. Identify patterns in user modifications (learning signal)
 * 3. Feed into future model improvements
 * 4. Support BigQuery export for analytics
 * 
 * @see docs/CC21 Scheduler 2.0 prelim – Design & Implementation Spec.txt
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_suggestion_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('suggestion_uuid')->unique(); // Unique ID for tracking across systems
            
            // Context
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_type_id')->constrained()->onDelete('cascade');
            $table->date('week_start'); // ISO week start (Monday)
            
            // Suggestion details
            $table->foreignId('suggested_staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('match_status', 20); // strong, moderate, weak, none
            $table->decimal('confidence_score', 5, 2)->nullable(); // 0.00-100.00
            $table->json('scoring_factors')->nullable(); // Breakdown of what influenced the score
            
            // AI explanation (from LLM service)
            $table->text('explanation_text')->nullable();
            $table->string('explanation_model', 50)->nullable(); // e.g., 'gemini-2.0-flash-001'
            
            // Outcome tracking
            $table->enum('outcome', [
                'pending',      // Not yet acted upon
                'accepted',     // User accepted as-is
                'modified',     // User accepted with modifications
                'rejected',     // User explicitly rejected
                'expired',      // Suggestion expired without action
            ])->default('pending');
            $table->timestamp('outcome_at')->nullable();
            $table->foreignId('outcome_user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // If modified, what changed
            $table->foreignId('final_staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('final_scheduled_start')->nullable();
            $table->dateTime('final_scheduled_end')->nullable();
            $table->json('modifications')->nullable(); // What user changed
            
            // Learning signals
            $table->text('rejection_reason')->nullable(); // Free-text or code
            $table->integer('time_to_decision_seconds')->nullable(); // How long user took to decide
            
            // Assignment created (if accepted/modified)
            $table->foreignId('created_assignment_id')->nullable();
            
            // Metadata
            $table->string('source', 50)->default('auto_assign'); // auto_assign, batch_suggest, etc.
            $table->string('session_id', 100)->nullable(); // Frontend session for grouping
            $table->timestamps();
            
            // Indexes for analytics
            $table->index(['organization_id', 'week_start']);
            $table->index(['outcome', 'created_at']);
            $table->index(['match_status', 'outcome']);
            $table->index('patient_id');
            $table->index('suggested_staff_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_suggestion_logs');
    }
};
