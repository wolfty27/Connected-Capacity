<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the region_areas table for FSA (Forward Sortation Area) to region mapping.
 *
 * FSA is the first 3 characters of a Canadian postal code.
 * This table enables metadata-driven region assignment without hardcoding:
 * - Primary lookup: FSA prefix matching (e.g., "M5G" → Toronto Central)
 * - Secondary fallback: Lat/lng bounding box for edge cases
 *
 * All region logic uses this metadata - no hardcoded region assignments.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('region_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->onDelete('cascade');
            $table->string('fsa_prefix', 3)->comment('First 3 characters of postal code (e.g., M5G)');

            // Optional bounding box for lat/lng fallback matching
            $table->decimal('min_lat', 10, 7)->nullable()->comment('Minimum latitude for bounding box');
            $table->decimal('max_lat', 10, 7)->nullable()->comment('Maximum latitude for bounding box');
            $table->decimal('min_lng', 11, 7)->nullable()->comment('Minimum longitude for bounding box');
            $table->decimal('max_lng', 11, 7)->nullable()->comment('Maximum longitude for bounding box');

            $table->timestamps();

            // FSA should be unique - each postal code prefix maps to one region
            $table->unique('fsa_prefix');
            $table->index('region_id');

            // Index for bounding box queries
            $table->index(['min_lat', 'max_lat', 'min_lng', 'max_lng'], 'region_areas_bounding_box_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_areas');
    }
};
