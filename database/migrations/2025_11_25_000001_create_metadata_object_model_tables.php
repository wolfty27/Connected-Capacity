<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workday-style Metadata Object Model Architecture
     *
     * This migration creates the foundational tables for a metadata-driven
     * architecture similar to Workday's object model approach:
     *
     * - object_definitions: Defines business object types (Worker, Patient, CareBundle)
     * - object_attributes: Defines attributes/properties of each object type
     * - object_relationships: Defines relationships between object types
     * - object_rules: Defines business logic/rules that apply to objects
     * - object_instances: Runtime instances of objects with their metadata
     * - patient_queue: Queue management for patient workflow transitions
     */
    public function up(): void
    {
        // 1. Object Definitions - Defines the "classes" or object types
        Schema::create('object_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();           // e.g., 'Patient', 'CareBundle', 'ServiceType'
            $table->string('code')->unique();           // e.g., 'PATIENT', 'CARE_BUNDLE'
            $table->string('display_name');             // Human-readable name
            $table->text('description')->nullable();
            $table->string('category')->nullable();     // Grouping: 'clinical', 'operational', 'administrative'
            $table->string('base_table')->nullable();   // Maps to existing Eloquent model table
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System objects cannot be deleted
            $table->json('config')->nullable();         // Additional configuration
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Object Attributes - Defines properties/fields of each object type
        Schema::create('object_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_definition_id')->constrained()->cascadeOnDelete();
            $table->string('name');                     // e.g., 'first_name', 'status', 'care_level'
            $table->string('code');                     // Machine-readable code
            $table->string('display_name');             // Human-readable label
            $table->string('data_type');                // string, integer, boolean, date, json, reference
            $table->boolean('is_required')->default(false);
            $table->boolean('is_readonly')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_indexed')->default(false);
            $table->string('default_value')->nullable();
            $table->json('validation_rules')->nullable(); // Laravel-style validation rules
            $table->json('options')->nullable();        // For enum/select types
            $table->integer('sort_order')->default(0);
            $table->string('group')->nullable();        // UI grouping
            $table->timestamps();

            $table->unique(['object_definition_id', 'code']);
        });

        // 3. Object Relationships - Defines how objects relate to each other
        Schema::create('object_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_object_id')->constrained('object_definitions')->cascadeOnDelete();
            $table->foreignId('target_object_id')->constrained('object_definitions')->cascadeOnDelete();
            $table->string('name');                     // e.g., 'patient_care_plans', 'bundle_services'
            $table->string('code');
            $table->enum('relationship_type', [
                'one_to_one',
                'one_to_many',
                'many_to_one',
                'many_to_many'
            ]);
            $table->string('inverse_name')->nullable(); // Name of the inverse relationship
            $table->string('pivot_table')->nullable();  // For many-to-many
            $table->json('pivot_attributes')->nullable(); // Additional pivot columns
            $table->boolean('is_required')->default(false);
            $table->boolean('cascade_delete')->default(false);
            $table->timestamps();

            $table->unique(['source_object_id', 'code']);
        });

        // 4. Object Rules - Business logic/rules applied at runtime
        Schema::create('object_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_definition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('rule_type', [
                'validation',       // Data validation rules
                'calculation',      // Computed/derived values
                'transition',       // State machine transitions
                'trigger',          // Event triggers
                'constraint',       // Business constraints
                'workflow'          // Workflow rules
            ]);
            $table->enum('trigger_event', [
                'on_create',
                'on_update',
                'on_delete',
                'on_status_change',
                'on_relationship_change',
                'scheduled',
                'manual'
            ])->default('on_update');
            $table->json('conditions')->nullable();     // When the rule applies
            $table->json('actions')->nullable();        // What happens when triggered
            $table->text('expression')->nullable();     // For calculations/conditions
            $table->integer('priority')->default(0);    // Execution order
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Object Instances - Runtime storage for metadata-driven object data
        Schema::create('object_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('entity_id');    // ID of the actual record (patient.id, etc.)
            $table->json('metadata')->nullable();       // Extended attributes not in main table
            $table->json('computed_values')->nullable(); // Cached computed values
            $table->string('status')->default('active');
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();

            $table->unique(['object_definition_id', 'entity_id']);
            $table->index(['object_definition_id', 'status']);
        });

        // 6. Patient Queue - Queue management for workflow transitions
        Schema::create('patient_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->enum('queue_status', [
                'pending_intake',       // Just entered system
                'triage_in_progress',   // Being triaged
                'triage_complete',      // Triage done, awaiting TNP
                'tnp_in_progress',      // TNP assessment underway
                'tnp_complete',         // TNP complete, ready for bundle
                'bundle_building',      // Care bundle being built
                'bundle_review',        // Bundle ready for review
                'bundle_approved',      // Bundle approved, ready to activate
                'transitioned'          // Moved to active patient profile
            ])->default('pending_intake');
            $table->foreignId('assigned_coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('priority')->default(5);    // 1=highest, 10=lowest
            $table->timestamp('entered_queue_at');
            $table->timestamp('triage_completed_at')->nullable();
            $table->timestamp('tnp_completed_at')->nullable();
            $table->timestamp('bundle_started_at')->nullable();
            $table->timestamp('bundle_completed_at')->nullable();
            $table->timestamp('transitioned_at')->nullable();
            $table->json('queue_metadata')->nullable(); // Flexible storage for queue-related data
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['queue_status', 'priority']);
            $table->index(['assigned_coordinator_id', 'queue_status']);
        });

        // 7. Queue Transitions - Audit trail for queue status changes
        Schema::create('queue_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_queue_id')->constrained('patient_queue')->cascadeOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->foreignId('transitioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transition_reason')->nullable();
            $table->json('context')->nullable();        // Additional context data
            $table->timestamps();

            $table->index(['patient_queue_id', 'created_at']);
        });

        // 8. Update patients table with queue-related columns
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('is_in_queue')->default(false)->after('status');
            $table->timestamp('activated_at')->nullable()->after('is_in_queue');
            $table->foreignId('activated_by')->nullable()->after('activated_at')->constrained('users')->nullOnDelete();
        });

        // 9. Service Metadata - Enhanced service configuration
        Schema::create('service_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('value_type')->default('string'); // string, integer, boolean, json
            $table->boolean('is_configurable')->default(true);
            $table->timestamps();

            $table->unique(['service_type_id', 'key']);
        });

        // 10. Bundle Configuration Rules - Metadata for bundle building logic
        Schema::create('bundle_configuration_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_bundle_id')->constrained()->cascadeOnDelete();
            $table->string('rule_name');
            $table->enum('rule_type', [
                'inclusion',        // Service should be included
                'exclusion',        // Service should be excluded
                'frequency_adjustment',
                'duration_adjustment',
                'provider_assignment',
                'cost_modifier'
            ]);
            $table->json('conditions');             // TNP flags, patient attributes, etc.
            $table->json('actions');                // What to do when conditions match
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_configuration_rules');
        Schema::dropIfExists('service_metadata');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['activated_by']);
            $table->dropColumn(['is_in_queue', 'activated_at', 'activated_by']);
        });

        Schema::dropIfExists('queue_transitions');
        Schema::dropIfExists('patient_queue');
        Schema::dropIfExists('object_instances');
        Schema::dropIfExists('object_rules');
        Schema::dropIfExists('object_relationships');
        Schema::dropIfExists('object_attributes');
        Schema::dropIfExists('object_definitions');
    }
};
