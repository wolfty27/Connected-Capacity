<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add scheduling lock flag to users table.
     * 
     * When is_scheduling_locked = true:
     * - Staff excluded from SchedulingEngine assignment flows
     * - Staff excluded from "available staff" dropdowns
     * - Staff excluded from WorkforceCapacityService "available hours"
     * - Staff still visible in profiles and historical data
     * - Staff can still log in (separate from staff_status)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_scheduling_locked')->default(false)->after('staff_status');
            $table->timestamp('scheduling_locked_at')->nullable()->after('is_scheduling_locked');
            $table->string('scheduling_locked_reason')->nullable()->after('scheduling_locked_at');
            
            // Index for filtering in scheduling queries
            $table->index(['organization_id', 'is_scheduling_locked', 'staff_status']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'is_scheduling_locked', 'staff_status']);
            $table->dropColumn([
                'is_scheduling_locked',
                'scheduling_locked_at',
                'scheduling_locked_reason',
            ]);
        });
    }
};
