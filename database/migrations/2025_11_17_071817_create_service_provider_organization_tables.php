<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceProviderOrganizationTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('service_provider_organizations')) {
            Schema::create('service_provider_organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->enum('type', ['se_health', 'partner', 'external'])->default('partner');
                $table->string('contact_email')->nullable();
                $table->string('contact_phone')->nullable();
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('province')->nullable();
                $table->string('postal_code')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['active', 'type'], 'spo_active_type_idx');
            });
        }

        if (!Schema::hasTable('organization_user_roles')) {
            Schema::create('organization_user_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_provider_organization_id')->constrained()->cascadeOnDelete();
                $table->string('organization_role');
                $table->json('default_assignment_scope')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'service_provider_organization_id'], 'org_user_unique');
                $table->index('organization_role');
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
        if (Schema::hasTable('organization_user_roles')) {
            Schema::dropIfExists('organization_user_roles');
        }

        if (Schema::hasTable('service_provider_organizations')) {
            Schema::dropIfExists('service_provider_organizations');
        }
    }
}
