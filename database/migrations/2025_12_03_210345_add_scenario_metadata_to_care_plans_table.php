<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add scenario_metadata column to care_plans table.
 *
 * This stores v2.3 AI Bundle Engine scenario information:
 * - scenario_id: The generated scenario ID
 * - title: The scenario title (e.g., "Safety & Stability Focus")
 * - axis: The primary axis (e.g., "safety_stability")
 * - source: The generation source (e.g., "category_composition_v2.3")
 * - services_snapshot: Optional snapshot of v2.3 generated services
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->json('scenario_metadata')->nullable()->after('care_bundle_template_id')
                ->comment('v2.3 AI Bundle Engine scenario information: title, axis, source');
            
            $table->string('scenario_title', 100)->nullable()->after('scenario_metadata')
                ->comment('Display title from v2.3 scenario (denormalized for easy access)');
            
            $table->string('scenario_axis', 50)->nullable()->after('scenario_title')
                ->comment('Primary axis from v2.3 scenario (e.g., safety_stability)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropColumn(['scenario_metadata', 'scenario_title', 'scenario_axis']);
        });
    }
};
