<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STAFF-019: Create SSPO Service Capabilities table
 *
 * This table stores the service capabilities for each SSPO,
 * including capacity, pricing, and quality metrics for marketplace matching.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sspo_service_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sspo_id')->constrained('service_provider_organizations')->onDelete('cascade');
            $table->foreignId('service_type_id')->constrained('service_types')->onDelete('cascade');
            $table->boolean('is_active')->default(true);

            // Capacity
            $table->integer('max_weekly_hours')->nullable()->comment('Maximum hours this SSPO can provide per week');
            $table->integer('current_utilization_hours')->default(0)->comment('Currently committed hours this week');
            $table->integer('min_notice_hours')->default(24)->comment('Minimum notice required for bookings');

            // Pricing
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('visit_rate', 10, 2)->nullable()->comment('Flat rate per visit if applicable');
            $table->json('rate_modifiers')->nullable()->comment('Modifiers for weekend/holiday/overtime');

            // Geographic Coverage
            $table->json('service_areas')->nullable()->comment('Array of postal code prefixes served');

            // Availability Schedule
            $table->json('available_days')->nullable()->comment('Array of day numbers (0=Sun, 6=Sat)');
            $table->time('earliest_start_time')->nullable();
            $table->time('latest_end_time')->nullable();

            // Quality Metrics
            $table->decimal('acceptance_rate', 5, 2)->nullable()->comment('Percentage of requests accepted');
            $table->decimal('completion_rate', 5, 2)->nullable()->comment('Percentage of visits completed as scheduled');
            $table->decimal('quality_score', 5, 2)->nullable()->comment('Overall quality score (0-100)');

            // Staff Qualifications
            $table->json('staff_qualifications')->nullable()->comment('Array of skill codes available');
            $table->integer('available_staff_count')->nullable();

            // Special Capabilities
            $table->boolean('can_handle_complex_care')->default(false);
            $table->boolean('can_handle_dementia')->default(false);
            $table->boolean('can_handle_palliative')->default(false);
            $table->boolean('bilingual_french')->default(false);
            $table->json('languages_available')->nullable();

            // Validity Period
            $table->date('capability_effective_date')->nullable();
            $table->date('capability_expiry_date')->nullable();

            // Insurance/Compliance
            $table->boolean('insurance_verified')->default(false);
            $table->date('insurance_expiry_date')->nullable();

            $table->timestamps();

            // Unique constraint: one capability record per SSPO per service type
            $table->unique(['sspo_id', 'service_type_id'], 'sspo_service_type_unique');

            // Indexes for common queries
            $table->index(['service_type_id', 'is_active'], 'capability_service_active');
            $table->index(['is_active', 'quality_score'], 'capability_active_quality');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sspo_service_capabilities');
    }
};
