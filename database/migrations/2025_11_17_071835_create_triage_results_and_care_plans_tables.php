<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTriageResultsAndCarePlansTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('triage_results')) {
            Schema::create('triage_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->timestamp('received_at');
                $table->timestamp('triaged_at')->nullable();
                $table->enum('acuity_level', ['low', 'medium', 'high', 'critical'])->default('low');
                $table->boolean('dementia_flag')->default(false);
                $table->boolean('mh_flag')->default(false);
                $table->boolean('rpm_required')->default(false);
                $table->boolean('fall_risk')->default(false);
                $table->boolean('behavioural_risk')->default(false);
                $table->text('notes')->nullable();
                $table->json('raw_referral_payload')->nullable();
                $table->foreignId('triaged_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('patient_id');
                $table->index(['acuity_level', 'rpm_required'], 'triage_results_acuity_rpm_idx');
            });
        }

        if (!Schema::hasTable('care_plans')) {
            Schema::create('care_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('care_bundle_id')->nullable()->constrained('care_bundles')->nullOnDelete();
                $table->integer('version')->default(1);
                $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
                $table->json('goals')->nullable();
                $table->json('risks')->nullable();
                $table->json('interventions')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['patient_id', 'version'], 'care_plan_patient_version_unique');
                $table->index(['status', 'care_bundle_id'], 'care_plan_status_bundle_idx');
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
        if (Schema::hasTable('care_plans')) {
            Schema::dropIfExists('care_plans');
        }

        if (Schema::hasTable('triage_results')) {
            Schema::dropIfExists('triage_results');
        }
    }
}
