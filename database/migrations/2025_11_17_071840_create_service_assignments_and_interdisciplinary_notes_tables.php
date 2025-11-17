<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceAssignmentsAndInterdisciplinaryNotesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('service_assignments')) {
            Schema::create('service_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('care_plan_id')->constrained('care_plans')->cascadeOnDelete();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('service_provider_organization_id')->constrained('service_provider_organizations')->cascadeOnDelete();
                $table->foreignId('service_type_id')->constrained('service_types')->cascadeOnDelete();
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled', 'missed', 'escalated'])->default('planned');
                $table->timestamp('scheduled_start')->nullable();
                $table->timestamp('scheduled_end')->nullable();
                $table->timestamp('actual_start')->nullable();
                $table->timestamp('actual_end')->nullable();
                $table->string('frequency_rule')->nullable();
                $table->text('notes')->nullable();
                $table->enum('source', ['manual', 'triage', 'rpm_alert', 'api'])->default('manual');
                $table->unsignedBigInteger('rpm_alert_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['service_provider_organization_id', 'status'], 'service_assignments_org_status_idx');
                $table->index(['assigned_user_id', 'scheduled_start'], 'service_assignments_user_start_idx');
                $table->index(['patient_id', 'status'], 'service_assignments_patient_status_idx');
                $table->index('rpm_alert_id');
            });
        }

        if (!Schema::hasTable('interdisciplinary_notes')) {
            Schema::create('interdisciplinary_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('service_assignment_id')->nullable()->constrained('service_assignments')->nullOnDelete();
                $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
                $table->string('author_role');
                $table->enum('note_type', ['clinical', 'psw', 'mh', 'rpm', 'escalation'])->default('clinical');
                $table->longText('content');
                $table->json('visible_to_orgs')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['patient_id', 'created_at'], 'interdisciplinary_notes_patient_idx');
                $table->index(['service_assignment_id', 'created_at'], 'interdisciplinary_notes_assignment_idx');
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
        if (Schema::hasTable('interdisciplinary_notes')) {
            Schema::dropIfExists('interdisciplinary_notes');
        }

        if (Schema::hasTable('service_assignments')) {
            Schema::dropIfExists('service_assignments');
        }
    }
}
