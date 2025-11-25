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
        // 1. Service Categories (skip if already created by earlier migration)
        if (!Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 2. Enhance Service Types
        Schema::table('service_types', function (Blueprint $table) {
            if (!Schema::hasColumn('service_types', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('category')->constrained('service_categories')->nullOnDelete();
            }
        });

        // 3. Enhance Care Bundles (Templates)
        Schema::table('care_bundles', function (Blueprint $table) {
            if (!Schema::hasColumn('care_bundles', 'version')) {
                $table->integer('version')->default(1)->after('code');
            }
        });

        // 4. Enhance Pivot (Template Items)
        Schema::table('care_bundle_service_type', function (Blueprint $table) {
            if (!Schema::hasColumn('care_bundle_service_type', 'assignment_type')) {
                $table->enum('assignment_type', ['Internal', 'External', 'Either'])->default('Either')->after('default_frequency_per_week');
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
        Schema::table('care_bundle_service_type', function (Blueprint $table) {
            $table->dropColumn(['assignment_type', 'role_required']);
        });

        Schema::table('care_bundles', function (Blueprint $table) {
            $table->dropColumn(['version']);
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('service_categories');
    }
};