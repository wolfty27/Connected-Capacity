<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * STAFF-003: Add external_id, status, hire_date, max_weekly_hours to users table
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // External system integration ID (HPG, IAR, AlayaCare)
            $table->string('external_id')->nullable()->after('id');

            // Staff status for workforce management
            $table->enum('staff_status', [
                'active',       // Currently working
                'inactive',     // Not currently available
                'on_leave',     // Temporary absence
                'terminated'    // No longer employed
            ])->default('active')->after('role');

            // Employment dates
            $table->date('hire_date')->nullable()->after('staff_status');
            $table->date('termination_date')->nullable()->after('hire_date');

            // Capacity planning
            $table->decimal('max_weekly_hours', 5, 2)->default(40.00)->after('fte_value');

            // Indexes for common queries
            $table->index('external_id');
            $table->index('staff_status');
            $table->index(['organization_id', 'staff_status']);
            $table->index(['role', 'staff_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropIndex(['staff_status']);
            $table->dropIndex(['organization_id', 'staff_status']);
            $table->dropIndex(['role', 'staff_status']);

            $table->dropColumn([
                'external_id',
                'staff_status',
                'hire_date',
                'termination_date',
                'max_weekly_hours'
            ]);
        });
    }
};
