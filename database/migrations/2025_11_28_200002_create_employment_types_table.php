<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WORKFORCE-002: Employment Types Metadata Table
 *
 * Creates metadata-driven employment types (Full-Time, Part-Time, Casual, SSPO-Contract)
 * that support the Ontario Health atHome 80% FTE compliance requirement.
 *
 * Per Q&A:
 * - FTE ratio = [Number of active full-time direct staff ÷ Number of active direct staff] × 100%
 * - Full-time aligns with Ontario's Employment Standards Act (typically 40h/week)
 * - SSPO staff do NOT count in FTE ratio (either numerator or denominator)
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Employment type code: FT, PT, CASUAL, SSPO');
            $table->string('name', 100)->comment('Display name: Full-Time, Part-Time, Casual, SSPO Contract');
            $table->text('description')->nullable();

            // Hours and capacity
            $table->decimal('standard_hours_per_week', 5, 2)->default(40.00)->comment('Standard weekly hours for this type');
            $table->decimal('min_hours_per_week', 5, 2)->nullable()->comment('Minimum weekly hours (for PT/Casual)');
            $table->decimal('max_hours_per_week', 5, 2)->nullable()->comment('Maximum weekly hours');

            // FTE ratio calculation flags (per OHaH Q&A)
            $table->boolean('is_direct_staff')->default(true)->comment('True for FT/PT/Casual; False for SSPO - used in FTE denominator');
            $table->boolean('is_full_time')->default(false)->comment('True only for Full-Time - used in FTE numerator');
            $table->boolean('counts_for_capacity')->default(true)->comment('Whether to include in capacity calculations');

            // Benefits and classification
            $table->boolean('benefits_eligible')->default(false)->comment('Whether eligible for benefits');
            $table->decimal('fte_equivalent', 4, 2)->nullable()->comment('FTE equivalent value (1.0 for FT, 0.5-0.8 for PT, etc.)');

            // Display and ordering
            $table->string('badge_color', 20)->nullable()->comment('UI badge color: green, blue, orange, purple');
            $table->integer('sort_order')->default(100);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
            $table->index(['is_direct_staff', 'is_active']);
            $table->index(['is_full_time', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_types');
    }
};
