<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds scheduling mode fields to service_types table.
 *
 * Supports different scheduling patterns:
 * - 'weekly' (default): Services are scheduled based on frequency per week
 * - 'fixed_visits': Services have a fixed number of visits per care plan
 *   (e.g., RPM has exactly 2 visits: Setup and Discharge)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            // Scheduling mode: 'weekly' (default) or 'fixed_visits'
            $table->string('scheduling_mode', 20)->default('weekly')->after('active');

            // Number of fixed visits per care plan (only used when scheduling_mode = 'fixed_visits')
            $table->unsignedTinyInteger('fixed_visits_per_plan')->nullable()->after('scheduling_mode');

            // Optional: labels for each fixed visit (e.g., ["Setup", "Discharge"])
            // Stored as JSON array
            $table->json('fixed_visit_labels')->nullable()->after('fixed_visits_per_plan');

            // Index for filtering by scheduling mode
            $table->index('scheduling_mode');
        });
    }

    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropIndex(['scheduling_mode']);
            $table->dropColumn(['scheduling_mode', 'fixed_visits_per_plan', 'fixed_visit_labels']);
        });
    }
};
