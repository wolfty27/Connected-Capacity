<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_assignments', function (Blueprint $table) {
            $table->decimal('estimated_hours_per_week', 5, 2)->nullable()->after('frequency_rule');
            $table->decimal('estimated_total_hours', 6, 2)->nullable()->after('estimated_hours_per_week');
            $table->decimal('estimated_travel_km_per_week', 5, 2)->nullable()->after('estimated_total_hours');
            $table->boolean('after_hours_required')->default(false)->after('estimated_travel_km_per_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_hours_per_week',
                'estimated_total_hours',
                'estimated_travel_km_per_week',
                'after_hours_required'
            ]);
        });
    }
};
