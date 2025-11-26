<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * STAFF-004: Create staff_availabilities table for recurring availability windows
     */
    public function up(): void
    {
        Schema::create('staff_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Day of week (0 = Sunday, 6 = Saturday) - matches PHP date('w')
            $table->tinyInteger('day_of_week')->unsigned();

            // Time windows
            $table->time('start_time');
            $table->time('end_time');

            // Effective date range (for schedule changes, e.g., summer hours)
            $table->date('effective_from');
            $table->date('effective_until')->nullable(); // null = indefinite

            // Recurring vs one-time availability
            $table->boolean('is_recurring')->default(true);

            // Optional: specific geographic coverage during this window
            $table->json('service_areas')->nullable();

            // Notes for coordinators
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for availability lookups
            $table->index(['user_id', 'day_of_week']);
            $table->index(['user_id', 'effective_from', 'effective_until']);
            $table->index('day_of_week');

            // Constraint: end_time must be after start_time (enforced at application level)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_availabilities');
    }
};
