<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SC-001: Add cost_per_visit to service_types
 *
 * This column stores the default cost per visit for each service type,
 * enabling cost estimation in the care bundle builder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            if (!Schema::hasColumn('service_types', 'cost_per_visit')) {
                $table->decimal('cost_per_visit', 10, 2)->nullable()->after('cost_driver');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            if (Schema::hasColumn('service_types', 'cost_per_visit')) {
                $table->dropColumn('cost_per_visit');
            }
        });
    }
};
