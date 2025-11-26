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
        // Add new fields to service_types table
        Schema::table('service_types', function (Blueprint $table) {
            if (!Schema::hasColumn('service_types', 'cost_code')) {
                $table->string('cost_code')->nullable()->after('description');
            }
            if (!Schema::hasColumn('service_types', 'cost_driver')) {
                $table->string('cost_driver')->nullable()->after('cost_code');
            }
            if (!Schema::hasColumn('service_types', 'source')) {
                $table->string('source')->nullable()->after('cost_driver');
            }
            if (!Schema::hasColumn('service_types', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('category')->constrained('service_categories')->nullOnDelete();
            }
        });

        // Add assignment_type to pivot table
        Schema::table('care_bundle_service_type', function (Blueprint $table) {
            if (!Schema::hasColumn('care_bundle_service_type', 'assignment_type')) {
                $table->string('assignment_type')->default('Either')->after('default_frequency_per_week');
            }
            if (!Schema::hasColumn('care_bundle_service_type', 'role_required')) {
                $table->string('role_required')->nullable()->after('assignment_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn(['cost_code', 'cost_driver', 'source']);
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('care_bundle_service_type', function (Blueprint $table) {
            $table->dropColumn(['assignment_type', 'role_required']);
        });
    }
};
