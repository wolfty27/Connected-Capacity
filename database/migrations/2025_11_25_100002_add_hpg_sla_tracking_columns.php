<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SLA-001: Add HPG timestamp tracking to triage_results
 * SLA-003: Add first_service_delivered_at to care_plans
 *
 * OHaH RFS Compliance Requirements:
 * - 15-minute response SLA for HPG referrals
 * - <24 hours to first service delivery
 *
 * These fields enable SLA compliance tracking and reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add HPG response tracking to triage_results
        Schema::table('triage_results', function (Blueprint $table) {
            // When HPG referral was received (starts 15-minute clock)
            if (!Schema::hasColumn('triage_results', 'hpg_received_at')) {
                $table->timestamp('hpg_received_at')->nullable()->after('received_at');
            }

            // When SPO acknowledged/responded to HPG (must be within 15 min)
            if (!Schema::hasColumn('triage_results', 'hpg_responded_at')) {
                $table->timestamp('hpg_responded_at')->nullable()->after('hpg_received_at');
            }

            // Who responded to the HPG referral
            if (!Schema::hasColumn('triage_results', 'hpg_responded_by')) {
                $table->foreignId('hpg_responded_by')->nullable()->after('hpg_responded_at')
                    ->constrained('users')->nullOnDelete();
            }

            // Crisis designation from LTC waitlist (per OHaH RFS)
            if (!Schema::hasColumn('triage_results', 'crisis_designation')) {
                $table->boolean('crisis_designation')->default(false)->after('behavioural_risk');
            }

            // Index for SLA compliance queries
            $table->index(['hpg_received_at', 'hpg_responded_at'], 'triage_hpg_sla_idx');
        });

        // Add first service tracking to care_plans
        Schema::table('care_plans', function (Blueprint $table) {
            // When first service was delivered (<24h SLA)
            if (!Schema::hasColumn('care_plans', 'first_service_delivered_at')) {
                $table->timestamp('first_service_delivered_at')->nullable()->after('approved_at');
            }

            // Index for time-to-first-service reporting
            $table->index(['approved_at', 'first_service_delivered_at'], 'care_plan_first_service_idx');
        });
    }

    public function down(): void
    {
        Schema::table('triage_results', function (Blueprint $table) {
            $table->dropIndex('triage_hpg_sla_idx');

            if (Schema::hasColumn('triage_results', 'hpg_received_at')) {
                $table->dropColumn('hpg_received_at');
            }
            if (Schema::hasColumn('triage_results', 'hpg_responded_at')) {
                $table->dropColumn('hpg_responded_at');
            }
            if (Schema::hasColumn('triage_results', 'hpg_responded_by')) {
                $table->dropForeign(['hpg_responded_by']);
                $table->dropColumn('hpg_responded_by');
            }
            if (Schema::hasColumn('triage_results', 'crisis_designation')) {
                $table->dropColumn('crisis_designation');
            }
        });

        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropIndex('care_plan_first_service_idx');

            if (Schema::hasColumn('care_plans', 'first_service_delivered_at')) {
                $table->dropColumn('first_service_delivered_at');
            }
        });
    }
};
