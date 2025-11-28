<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CareBundleTemplate Table
 *
 * Stores the 23 RUG-III/HC-specific bundle templates that define
 * default service configurations for each RUG group.
 *
 * Templates are selected based on:
 * - RUG group (e.g., CB0, IB0)
 * - RUG category (e.g., Clinically Complex)
 * - ADL/IADL ranges
 * - Clinical flags
 *
 * @see docs/CC21_RUG_Bundle_Templates.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_bundle_templates', function (Blueprint $table) {
            $table->id();

            // Template identification
            $table->string('code', 50)->unique()->comment('e.g., LTC_CB0_STANDARD');
            $table->string('name', 100);
            $table->text('description')->nullable();

            // RUG mapping
            $table->string('rug_group', 10)->nullable()->comment('Specific RUG group (e.g., CB0)');
            $table->string('rug_category', 50)->nullable()->comment('RUG category for broader matching');
            $table->string('funding_stream', 20)->default('LTC')->comment('LTC, ALC, etc.');

            // ADL/IADL eligibility ranges
            $table->integer('min_adl_sum')->default(4);
            $table->integer('max_adl_sum')->default(18);
            $table->integer('min_iadl_sum')->default(0);
            $table->integer('max_iadl_sum')->default(3);

            // Required/excluded flags (JSON)
            $table->json('required_flags')->nullable()->comment('Flags that must be present');
            $table->json('excluded_flags')->nullable()->comment('Flags that must NOT be present');

            // Budget and cost
            $table->integer('weekly_cap_cents')->default(500000)->comment('$5,000 default');
            $table->integer('monthly_cap_cents')->nullable();
            $table->integer('base_cost_cents')->nullable()->comment('Base template cost estimate');

            // Template configuration
            $table->integer('priority_weight')->default(50)->comment('For ranking when multiple match');
            $table->boolean('auto_recommend')->default(true);
            $table->boolean('is_active')->default(true);

            // Versioning
            $table->integer('version')->default(1);
            $table->boolean('is_current_version')->default(true);

            // Metadata
            $table->json('metadata')->nullable()->comment('Additional template configuration');
            $table->text('clinical_notes')->nullable()->comment('Notes for care coordinators');

            $table->timestamps();

            // Indexes
            $table->index('rug_group');
            $table->index('rug_category');
            $table->index('funding_stream');
            $table->index(['is_active', 'is_current_version']);
        });

        // Pivot table for template services
        Schema::create('care_bundle_template_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_bundle_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_type_id')->constrained()->onDelete('cascade');

            // Default service configuration
            $table->integer('default_frequency_per_week')->default(1);
            $table->integer('default_duration_minutes')->default(60);
            $table->integer('default_duration_weeks')->default(12);

            // Cost
            $table->integer('cost_per_visit_cents')->nullable();

            // Flags for conditional inclusion
            $table->boolean('is_required')->default(false)->comment('Always included in bundle');
            $table->boolean('is_conditional')->default(false)->comment('Included based on flags');
            $table->json('condition_flags')->nullable()->comment('Flags that trigger inclusion');

            // Provider preferences
            $table->string('assignment_type', 20)->default('Either')->comment('Internal, External, Either');
            $table->string('role_required', 50)->nullable()->comment('RN, PSW, PT, etc.');

            $table->timestamps();

            $table->unique(['care_bundle_template_id', 'service_type_id'], 'template_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_bundle_template_services');
        Schema::dropIfExists('care_bundle_templates');
    }
};
