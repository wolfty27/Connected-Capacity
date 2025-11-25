<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SSPO-001: Add SSPO acceptance status to service_assignments
 *
 * Per OHaH RFS, when SPO subcontracts to SSPO partners:
 * - SSPO must explicitly accept or decline assignments
 * - SPO retains full liability regardless of SSPO acceptance
 * - Declined assignments must be reassigned or handled by SPO
 *
 * This enables tracking of SSPO acceptance workflow and response times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_assignments', function (Blueprint $table) {
            // SSPO acceptance workflow
            if (!Schema::hasColumn('service_assignments', 'sspo_acceptance_status')) {
                $table->enum('sspo_acceptance_status', ['pending', 'accepted', 'declined', 'not_applicable'])
                    ->default('not_applicable')
                    ->after('source');
            }

            // When SSPO responded to assignment request
            if (!Schema::hasColumn('service_assignments', 'sspo_responded_at')) {
                $table->timestamp('sspo_responded_at')->nullable()->after('sspo_acceptance_status');
            }

            // User who accepted/declined on behalf of SSPO
            if (!Schema::hasColumn('service_assignments', 'sspo_responded_by')) {
                $table->foreignId('sspo_responded_by')->nullable()->after('sspo_responded_at')
                    ->constrained('users')->nullOnDelete();
            }

            // Reason for decline (required when declining)
            if (!Schema::hasColumn('service_assignments', 'sspo_decline_reason')) {
                $table->text('sspo_decline_reason')->nullable()->after('sspo_responded_by');
            }

            // When assignment was sent to SSPO (for response time tracking)
            if (!Schema::hasColumn('service_assignments', 'sspo_notified_at')) {
                $table->timestamp('sspo_notified_at')->nullable()->after('sspo_decline_reason');
            }

            // Index for SSPO acceptance queries
            $table->index(['service_provider_organization_id', 'sspo_acceptance_status'], 'service_assignments_sspo_acceptance_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_assignments', function (Blueprint $table) {
            $table->dropIndex('service_assignments_sspo_acceptance_idx');

            if (Schema::hasColumn('service_assignments', 'sspo_acceptance_status')) {
                $table->dropColumn('sspo_acceptance_status');
            }
            if (Schema::hasColumn('service_assignments', 'sspo_responded_at')) {
                $table->dropColumn('sspo_responded_at');
            }
            if (Schema::hasColumn('service_assignments', 'sspo_responded_by')) {
                $table->dropForeign(['sspo_responded_by']);
                $table->dropColumn('sspo_responded_by');
            }
            if (Schema::hasColumn('service_assignments', 'sspo_decline_reason')) {
                $table->dropColumn('sspo_decline_reason');
            }
            if (Schema::hasColumn('service_assignments', 'sspo_notified_at')) {
                $table->dropColumn('sspo_notified_at');
            }
        });
    }
};
