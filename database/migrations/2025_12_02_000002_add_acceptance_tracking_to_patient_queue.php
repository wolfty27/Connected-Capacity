<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds referral acceptance tracking to patient_queue table.
 *
 * Per OHaH RFP (Appendix 1):
 * - Referral Acceptance Rate = (accepted referrals / total referrals) × 100%
 * - Target: 100% (SPO must accept all referrals in their service area)
 * - Band A: 100%, Band B: 95-99.9%, Band C: <95%
 *
 * This migration adds:
 * - accepted_at: Timestamp when SPO formally accepted the referral
 * - is_accepted: Boolean flag for acceptance status
 * - rejection_reason: If rejected, the reason (rare but possible)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_queue', function (Blueprint $table) {
            // Add acceptance tracking fields after entered_queue_at
            $table->timestamp('accepted_at')->nullable()->after('entered_queue_at')
                  ->comment('When SPO formally accepted the referral for care services');
            
            $table->boolean('is_accepted')->default(false)->after('accepted_at')
                  ->comment('Whether the referral has been formally accepted by SPO');
            
            $table->string('rejection_reason')->nullable()->after('is_accepted')
                  ->comment('If referral was rejected/declined, the reason');
            
            // Index for efficient acceptance rate queries
            $table->index(['is_accepted', 'entered_queue_at'], 'idx_acceptance_rate');
        });
    }

    public function down(): void
    {
        Schema::table('patient_queue', function (Blueprint $table) {
            $table->dropIndex('idx_acceptance_rate');
            $table->dropColumn(['accepted_at', 'is_accepted', 'rejection_reason']);
        });
    }
};
