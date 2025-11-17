<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceTypesAndCareBundlesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('service_types')) {
            Schema::create('service_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code')->nullable()->unique();
                $table->string('category');
                $table->integer('default_duration_minutes')->nullable();
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index(['active', 'category'], 'service_types_active_category_idx');
            });
        }

        if (!Schema::hasTable('care_bundles')) {
            Schema::create('care_bundles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->text('default_notes')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('care_bundle_service_type')) {
            Schema::create('care_bundle_service_type', function (Blueprint $table) {
                $table->id();
                $table->foreignId('care_bundle_id')->constrained('care_bundles')->cascadeOnDelete();
                $table->foreignId('service_type_id')->constrained('service_types')->cascadeOnDelete();
                $table->integer('default_frequency_per_week')->nullable();
                $table->foreignId('default_provider_org_id')->nullable()->constrained('service_provider_organizations')->nullOnDelete();
                $table->timestamps();

                $table->unique(['care_bundle_id', 'service_type_id'], 'care_bundle_service_type_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('care_bundle_service_type')) {
            Schema::dropIfExists('care_bundle_service_type');
        }

        if (Schema::hasTable('care_bundles')) {
            Schema::dropIfExists('care_bundles');
        }

        if (Schema::hasTable('service_types')) {
            Schema::dropIfExists('service_types');
        }
    }
}
