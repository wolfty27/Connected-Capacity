<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SSPO-005: Add SSPO service capabilities
 *
 * This migration creates the pivot table linking SSPO organizations
 * to the service types they can provide, along with capacity and
 * rate information for service matching.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Create SSPO service capabilities pivot table
        Schema::create('sspo_service_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sspo_id')->constrained('service_provider_organizations')->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained('service_types')->cascadeOnDelete();

            // Capacity information
            $table->boolean('is_active')->default(true);
            $table->integer('max_weekly_hours')->nullable(); // Max hours SSPO can provide per week
            $table->integer('current_utilization_hours')->default(0); // Current committed hours
            $table->integer('min_notice_hours')->default(24); // Minimum lead time for bookings

            // Pricing
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->decimal('visit_rate', 8, 2)->nullable();
            $table->json('rate_modifiers')->nullable(); // Weekend/holiday modifiers

            // Coverage
            $table->json('service_areas')->nullable(); // Array of postal code prefixes or regions
            $table->json('available_days')->nullable(); // Days of week available
            $table->time('earliest_start_time')->nullable();
            $table->time('latest_end_time')->nullable();

            // Quality metrics
            $table->decimal('acceptance_rate', 5, 2)->nullable(); // % of requests accepted
            $table->decimal('completion_rate', 5, 2)->nullable(); // % of accepted completed
            $table->decimal('quality_score', 5, 2)->nullable(); // Based on feedback

            // Staff qualifications
            $table->json('staff_qualifications')->nullable(); // Required certifications staff have
            $table->integer('available_staff_count')->default(0);

            // Special capabilities
            $table->boolean('can_handle_complex_care')->default(false);
            $table->boolean('can_handle_dementia')->default(false);
            $table->boolean('can_handle_palliative')->default(false);
            $table->boolean('bilingual_french')->default(false);
            $table->json('languages_available')->nullable();

            // Contract/compliance
            $table->date('capability_effective_date')->nullable();
            $table->date('capability_expiry_date')->nullable();
            $table->boolean('insurance_verified')->default(false);
            $table->date('insurance_expiry_date')->nullable();

            $table->timestamps();

            // Unique constraint - one record per SSPO/service combo
            $table->unique(['sspo_id', 'service_type_id'], 'sspo_service_unique');

            // Indexes for matching queries
            $table->index(['service_type_id', 'is_active'], 'sspo_capability_lookup_idx');
            $table->index(['quality_score', 'acceptance_rate'], 'sspo_capability_quality_idx');
        });

        // Create SSPO geographic coverage table for more detailed area mapping
        Schema::create('sspo_geographic_coverage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sspo_id')->constrained('service_provider_organizations')->cascadeOnDelete();
            $table->string('coverage_type', 20); // postal_prefix, lhin, municipality, custom
            $table->string('coverage_value', 50); // The actual value (e.g., "K1A", "Champlain")
            $table->boolean('is_primary')->default(false);
            $table->integer('priority_level')->default(0); // Higher = preferred area
            $table->decimal('travel_time_buffer_minutes', 5, 2)->default(0); // Extra time for this area
            $table->timestamps();

            $table->index(['coverage_type', 'coverage_value'], 'sspo_geo_lookup_idx');
            $table->index(['sspo_id', 'is_primary'], 'sspo_geo_primary_idx');
        });

        // Create SSPO capacity log for tracking utilization over time
        Schema::create('sspo_capacity_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sspo_id')->constrained('service_provider_organizations')->cascadeOnDelete();
            $table->foreignId('service_type_id')->nullable()->constrained('service_types')->nullOnDelete();
            $table->date('snapshot_date');
            $table->integer('total_capacity_hours');
            $table->integer('committed_hours');
            $table->integer('completed_hours');
            $table->integer('cancelled_hours');
            $table->decimal('utilization_rate', 5, 2);
            $table->timestamps();

            $table->index(['sspo_id', 'snapshot_date'], 'sspo_capacity_date_idx');
            $table->unique(['sspo_id', 'service_type_id', 'snapshot_date'], 'sspo_capacity_unique');
        });

        // Add SSPO preferences to patients for matching
        if (Schema::hasTable('patients') && !Schema::hasColumn('patients', 'preferred_sspo_id')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->foreignId('preferred_sspo_id')->nullable()->after('spo_id')
                    ->constrained('service_provider_organizations')->nullOnDelete();
                $table->json('sspo_preferences')->nullable()->after('preferred_sspo_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                if (Schema::hasColumn('patients', 'sspo_preferences')) {
                    $table->dropColumn('sspo_preferences');
                }
                if (Schema::hasColumn('patients', 'preferred_sspo_id')) {
                    $table->dropConstrainedForeignId('preferred_sspo_id');
                }
            });
        }

        Schema::dropIfExists('sspo_capacity_snapshots');
        Schema::dropIfExists('sspo_geographic_coverage');
        Schema::dropIfExists('sspo_service_capabilities');
    }
};
