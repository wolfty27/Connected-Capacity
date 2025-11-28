<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WORKFORCE-003: Add Staff Role and Employment Type FKs to Users
 *
 * Adds metadata-driven foreign keys for staff role and employment type,
 * plus additional staff-specific fields for job satisfaction tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Staff role FK (RN, RPN, PSW, OT, PT, SLP, SW, etc.)
            $table->foreignId('staff_role_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('staff_roles')
                ->nullOnDelete();

            // Employment type FK (replaces string employment_type)
            $table->foreignId('employment_type_id')
                ->nullable()
                ->after('staff_role_id')
                ->constrained('employment_types')
                ->nullOnDelete();

            // Staff satisfaction tracking (per RFP: > 95% target)
            $table->enum('job_satisfaction', ['excellent', 'good', 'neutral', 'poor'])
                ->nullable()
                ->after('staff_status')
                ->comment('Last recorded job satisfaction level');

            $table->date('job_satisfaction_recorded_at')
                ->nullable()
                ->after('job_satisfaction')
                ->comment('When satisfaction was last recorded');

            // External system reference (for AlayaCare integration)
            $table->string('external_staff_id', 50)
                ->nullable()
                ->after('external_id')
                ->comment('External system staff ID (e.g., AlayaCare employee ID)');

            // Add indexes for workforce queries
            $table->index('staff_role_id');
            $table->index('employment_type_id');
            $table->index(['organization_id', 'staff_status', 'staff_role_id']);
            $table->index(['organization_id', 'employment_type_id', 'staff_status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['staff_role_id']);
            $table->dropForeign(['employment_type_id']);

            $table->dropIndex(['staff_role_id']);
            $table->dropIndex(['employment_type_id']);
            $table->dropIndex(['organization_id', 'staff_status', 'staff_role_id']);
            $table->dropIndex(['organization_id', 'employment_type_id', 'staff_status']);

            $table->dropColumn([
                'staff_role_id',
                'employment_type_id',
                'job_satisfaction',
                'job_satisfaction_recorded_at',
                'external_staff_id',
            ]);
        });
    }
};
