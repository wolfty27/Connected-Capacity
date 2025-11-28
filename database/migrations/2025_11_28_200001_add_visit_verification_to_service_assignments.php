<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add visit verification fields to service_assignments table.
 *
 * Per OHaH contract requirements:
 * - All visits must be verified to track missed care
 * - Verification can be manual (staff), automated (SSPO system), or device-based
 * - Grace period is configurable (default 24 hours for overdue detection)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_assignments', function (Blueprint $table) {
            // Verification status: PENDING (default), VERIFIED, MISSED
            $table->string('verification_status', 20)->default('PENDING')->after('status');

            // When the visit was verified
            $table->timestamp('verified_at')->nullable()->after('verification_status');

            // Who/what verified the visit: staff_manual, sspo_system, device, coordinator_override
            $table->string('verification_source', 50)->nullable()->after('verified_at');

            // User who performed manual verification (nullable for system verifications)
            $table->foreignId('verified_by_user_id')
                ->nullable()
                ->after('verification_source')
                ->constrained('users')
                ->nullOnDelete();

            // Add index for efficient jeopardy board queries
            $table->index(['verification_status', 'scheduled_start'], 'idx_verification_jeopardy');
        });
    }

    public function down(): void
    {
        Schema::table('service_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_verification_jeopardy');
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn([
                'verification_status',
                'verified_at',
                'verification_source',
            ]);
        });
    }
};
