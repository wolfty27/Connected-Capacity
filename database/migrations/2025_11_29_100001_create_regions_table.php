<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the regions table for metadata-driven geographic area management.
 *
 * Regions are used for:
 * - Patient geographic assignment
 * - Staff service area matching
 * - Travel time optimization
 * - OHAH (Ontario Health at Home) integration
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Unique region code, e.g., TORONTO_CENTRAL');
            $table->string('name', 100)->comment('Human-readable region name');
            $table->string('ohah_code', 20)->nullable()->comment('OHAH region code for integration');
            $table->boolean('is_active')->default(true)->comment('Whether region is currently active');
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
