<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Bundle Engine Events Table
 *
 * Phase 8: Learning Infrastructure
 *
 * Stores events from the bundle engine for analytics and learning.
 * Events are periodically exported to BigQuery for long-term analysis.
 *
 * Event Types:
 * - scenario_generated: When scenarios are created for a patient
 * - scenario_selected: When a coordinator selects a scenario
 * - care_plan_published: When a care plan is activated
 * - patient_outcome: Periodic outcome tracking
 * - explanation_requested: When AI explanation is requested
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_engine_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Event classification
            $table->string('event_type', 50)->index();
            $table->timestamp('event_timestamp');
            
            // Patient context
            $table->unsignedBigInteger('patient_id');
            $table->string('patient_ref', 20)->nullable()->comment('De-identified ref for export (P-XXXX)');
            
            // Care plan context (optional)
            $table->unsignedBigInteger('care_plan_id')->nullable();
            
            // Scenario context (optional)
            $table->string('scenario_id', 100)->nullable();
            
            // User context (de-identified)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_ref', 20)->nullable()->comment('De-identified ref for export (U-XXXX)');
            
            // Event payload (JSON)
            $table->json('payload')->comment('Event-specific data');
            
            // Export tracking
            $table->boolean('exported_to_bigquery')->default(false);
            $table->timestamp('exported_at')->nullable();
            $table->string('export_batch_id', 36)->nullable()->comment('BigQuery export batch identifier');
            
            $table->timestamps();
            
            // Performance indexes
            $table->index(['event_type', 'event_timestamp']);
            $table->index(['patient_id', 'event_timestamp']);
            $table->index(['care_plan_id', 'event_type']);
            $table->index('exported_to_bigquery');
            $table->index(['exported_to_bigquery', 'event_timestamp']);
        });

        // Add comment to table
        DB::statement("COMMENT ON TABLE bundle_engine_events IS 'AI Bundle Engine event log for analytics and learning loop'");
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_engine_events');
    }
};

