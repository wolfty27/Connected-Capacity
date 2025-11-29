<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // PSW, PT, OT, NUR, RPM, etc.
            $table->string('name');
            $table->string('category'); // personal_care, therapy, nursing, monitoring
            $table->string('color', 7)->default('#6366f1'); // Hex color for UI
            $table->integer('default_duration_minutes')->default(60);

            // Scheduling mode: 'weekly' (hours/week) or 'fixed_visits' (set # of visits per care plan)
            $table->string('scheduling_mode')->default('weekly');

            // For fixed_visits mode: how many visits per care plan
            $table->integer('fixed_visits_per_plan')->nullable();

            // Labels for fixed visits (e.g., ['Setup', 'Discharge'] for RPM)
            $table->json('fixed_visit_labels')->nullable();

            // Minimum gap between same-service visits for same patient (PSW = 120, NUR = 60, etc.)
            $table->integer('min_gap_between_visits_minutes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
