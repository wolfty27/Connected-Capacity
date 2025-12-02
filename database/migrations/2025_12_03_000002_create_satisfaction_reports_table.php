<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create satisfaction_reports table for patient feedback on visits.
     * 
     * Staff satisfaction scores are derived from patient-reported feedback
     * on completed service assignments, NOT self-reported job satisfaction.
     */
    public function up(): void
    {
        Schema::create('satisfaction_reports', function (Blueprint $table) {
            $table->id();
            
            // Link to the service assignment being rated
            $table->foreignId('service_assignment_id')->constrained()->onDelete('cascade');
            
            // Patient who provided feedback
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            
            // Staff member who delivered the service (denormalized for query efficiency)
            $table->foreignId('staff_user_id')->constrained('users')->onDelete('cascade');
            
            // Rating on 1-5 scale (1=Poor, 2=Fair, 3=Good, 4=Very Good, 5=Excellent)
            $table->tinyInteger('rating')->unsigned();
            
            // Optional feedback text
            $table->text('feedback_text')->nullable();
            
            // Specific aspects rated (optional JSON for future expansion)
            $table->json('aspect_ratings')->nullable();
            
            // When the feedback was submitted
            $table->timestamp('reported_at');
            
            // Who submitted the feedback (patient, family member, etc.)
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('reporter_type', ['patient', 'family_member', 'caregiver', 'other'])->default('patient');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for common queries
            $table->index(['staff_user_id', 'reported_at']);
            $table->index(['patient_id', 'reported_at']);
            $table->index(['rating']);
            $table->unique(['service_assignment_id']); // One report per assignment
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('satisfaction_reports');
    }
};
