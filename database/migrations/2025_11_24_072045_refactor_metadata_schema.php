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
        // 1. Service Categories
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Enhance Service Types
        Schema::table('service_types', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('service_categories')->nullOnDelete();
            // We keep 'category' string for now as fallback or migrate data later
        });

        // 3. Enhance Care Bundles (Templates)
        Schema::table('care_bundles', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('code');
            // $table->boolean('is_active') already exists as 'active'
        });

        // 4. Enhance Pivot (Template Items)
        Schema::table('care_bundle_service_type', function (Blueprint $table) {
            $table->enum('assignment_type', ['Internal', 'External', 'Either'])->default('Either')->after('default_frequency_per_week');
            $table->string('role_required')->nullable()->after('assignment_type');
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