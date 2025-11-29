<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds address and geographic fields to patients table.
 *
 * These fields enable:
 * - Travel time calculations between patient locations
 * - Region-based staff assignment
 * - Google Maps integration for route planning
 * - FSA-based region auto-assignment
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Address fields
            $table->string('address', 255)->nullable()->after('status')->comment('Street address');
            $table->string('city', 100)->nullable()->after('address')->comment('City name');
            $table->string('postal_code', 10)->nullable()->after('city')->comment('Canadian postal code (e.g., M5G 1X8)');

            // Geographic coordinates for travel time calculations
            $table->decimal('lat', 10, 7)->nullable()->after('postal_code')->comment('Latitude');
            $table->decimal('lng', 11, 7)->nullable()->after('lat')->comment('Longitude');

            // Region assignment (auto-populated via RegionService)
            $table->foreignId('region_id')->nullable()->after('lng')->constrained('regions')->nullOnDelete();

            // Indexes for geographic queries
            $table->index('postal_code');
            $table->index('region_id');
            $table->index(['lat', 'lng'], 'patients_coordinates_index');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropIndex('patients_coordinates_index');
            $table->dropIndex(['postal_code']);
            $table->dropIndex(['region_id']);
            $table->dropColumn(['address', 'city', 'postal_code', 'lat', 'lng', 'region_id']);
        });
    }
};
