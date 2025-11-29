<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_type_id')->constrained()->onDelete('restrict');
            $table->foreignId('care_plan_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedBigInteger('assigned_user_id')->nullable(); // Staff member

            // Scheduling
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->integer('duration_minutes');

            // Status: pending, planned, active, completed, cancelled, missed
            $table->string('status')->default('planned');

            // For fixed-visit services like RPM: which visit is this (e.g., 'Setup', 'Discharge')
            $table->string('visit_label')->nullable();

            // Verification
            $table->string('verification_status')->default('PENDING'); // PENDING, VERIFIED, MISSED
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_method')->nullable(); // staff, coordinator, device

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient queries
            $table->index(['patient_id', 'scheduled_start', 'scheduled_end']);
            $table->index(['patient_id', 'service_type_id', 'scheduled_start']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_assignments');
    }
};
