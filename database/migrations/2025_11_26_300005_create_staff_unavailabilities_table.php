<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * STAFF-005: Create staff_unavailabilities table for time-off and absence tracking
     */
    public function up(): void
    {
        Schema::create('staff_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Type of unavailability
            $table->enum('unavailability_type', [
                'vacation',     // Planned time off
                'sick',         // Illness
                'personal',     // Personal day
                'training',     // Professional development
                'jury_duty',    // Legal obligation
                'bereavement',  // Family death
                'maternity',    // Parental leave
                'paternity',    // Parental leave
                'medical',      // Medical appointment/procedure
                'other'         // Catch-all
            ]);

            // Time period
            $table->datetime('start_datetime');
            $table->datetime('end_datetime');

            // All day flag (simplifies UI)
            $table->boolean('is_all_day')->default(false);

            // Details
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();

            // Approval workflow
            $table->enum('approval_status', [
                'pending',
                'approved',
                'denied',
                'cancelled'
            ])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Request tracking
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('requested_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for availability checks
            $table->index(['user_id', 'start_datetime', 'end_datetime']);
            $table->index(['user_id', 'approval_status']);
            $table->index('unavailability_type');
            $table->index(['start_datetime', 'end_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_unavailabilities');
    }
};
