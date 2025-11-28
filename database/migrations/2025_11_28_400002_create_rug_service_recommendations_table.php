<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RugServiceRecommendation Migration
 *
 * Creates a metadata-driven table for RUG/interRAI-based service recommendations.
 * This supports adding clinically indicated services (e.g., Homemaking, Behavioral Supports)
 * based on RUG category, ADL/IADL scores, or clinical flags.
 *
 * Used by RugServicePlanner to dynamically add services to care bundles
 * when clinical criteria are met, without hard-coding logic in services.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 * @see docs/CC21_RUG_Bundle_Templates.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rug_service_recommendations', function (Blueprint $table) {
            $table->id();

            // RUG matching criteria (any can be null for broader matching)
            $table->string('rug_group', 10)->nullable()->index()
                ->comment('Specific RUG group (e.g., CC0, IB0, BB0)');
            $table->string('rug_category', 50)->nullable()->index()
                ->comment('RUG category (e.g., Impaired Cognition, Behaviour Problems)');

            // Service to recommend
            $table->foreignId('service_type_id')->constrained('service_types')->cascadeOnDelete();

            // Frequency/intensity recommendation
            $table->integer('min_frequency_per_week')->default(0)
                ->comment('Minimum visits per week (0 = optional)');
            $table->integer('max_frequency_per_week')->nullable()
                ->comment('Maximum visits per week');
            $table->integer('default_duration_minutes')->nullable()
                ->comment('Default visit duration');

            // Trigger conditions (JSON for flexibility)
            $table->json('trigger_conditions')->nullable()
                ->comment('Conditions: e.g., {"adl_min": 11, "iadl_min": 1, "flags": ["behaviour_problems"]}');

            // Documentation
            $table->string('justification')->nullable()
                ->comment('Clinical justification for this recommendation');
            $table->text('clinical_notes')->nullable();

            // Priority and status
            $table->integer('priority_weight')->default(50)
                ->comment('Higher weight = higher priority (for budget constraints)');
            $table->boolean('is_required')->default(false)
                ->comment('If true, always include when criteria match');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Index for efficient lookups
            $table->index(['rug_group', 'rug_category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rug_service_recommendations');
    }
};
