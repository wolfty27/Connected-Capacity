<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add service_requirements JSON field to care_plans table.
 *
 * This field stores the customized service requirements for a care plan,
 * separate from ServiceAssignments. This supports the "plan vs schedule"
 * separation where:
 * - service_requirements = what care is needed (plan)
 * - ServiceAssignments = when/who delivers that care (schedule)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            // JSON field to store customized service requirements
            // Format: [{ service_type_id, frequency_per_week, duration_minutes, duration_weeks, provider_preference }, ...]
            $table->json('service_requirements')->nullable()->after('interventions');
        });
    }

    public function down(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropColumn('service_requirements');
        });
    }
};
