<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add min_gap_between_visits_minutes to service_types table.
     * This field defines the minimum time (in minutes) that must elapse between
     * two visits of the same service type for the same patient.
     *
     * Example: PSW visits require 120 min gap to prevent bunching visits
     * (e.g., morning, noon, evening instead of all in morning)
     */
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->integer('min_gap_between_visits_minutes')
                ->nullable()
                ->after('default_duration_minutes')
                ->comment('Minimum gap in minutes required between consecutive visits of this service type for the same patient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn('min_gap_between_visits_minutes');
        });
    }
};
