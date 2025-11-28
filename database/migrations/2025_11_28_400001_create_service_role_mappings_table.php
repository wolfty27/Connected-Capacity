<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ServiceRoleMapping Migration
 *
 * Creates a metadata-driven mapping of which staff roles can deliver which services.
 * This supports the CC2.1 architecture requirement that all business rules
 * are controlled via metadata tables rather than hard-coded logic.
 *
 * Used for:
 * - Validating staff can only deliver services matching their role
 * - Seeding assignments with appropriate service_type_id per staff role
 * - Future scheduling validation
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_role_id')->constrained('staff_roles')->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained('service_types')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false)->comment('Primary role for this service');
            $table->boolean('requires_delegation')->default(false)->comment('Requires RN delegation (e.g., delegated acts)');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique constraint on role + service
            $table->unique(['staff_role_id', 'service_type_id'], 'role_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_role_mappings');
    }
};
