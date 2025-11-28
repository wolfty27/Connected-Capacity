<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add billing rate snapshot fields to service_assignments.
 *
 * These fields capture the billing rate at the time of service assignment creation,
 * ensuring historical accuracy for billing when rates change over time.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_assignments', function (Blueprint $table) {
            // Billing rate snapshot (from ServiceRate at time of assignment)
            $table->unsignedInteger('billing_rate_cents')
                ->nullable()
                ->after('service_type_id')
                ->comment('Snapshot of billing rate in cents at assignment creation');

            $table->string('billing_unit_type', 50)
                ->nullable()
                ->after('billing_rate_cents')
                ->comment('Unit type for billing (hour, visit, month, etc.)');

            // Frequency per week for recurring services
            $table->unsignedSmallInteger('frequency_per_week')
                ->nullable()
                ->after('billing_unit_type')
                ->comment('Number of visits/hours per week');

            // Duration per visit in minutes
            $table->unsignedSmallInteger('duration_minutes')
                ->nullable()
                ->after('frequency_per_week')
                ->comment('Duration per service instance in minutes');

            // Calculated weekly cost (snapshot)
            $table->unsignedInteger('calculated_weekly_cost_cents')
                ->nullable()
                ->after('duration_minutes')
                ->comment('Calculated weekly cost in cents (snapshot)');

            // Index for cost reporting
            $table->index(['billing_rate_cents', 'billing_unit_type'], 'service_assignments_billing_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_assignments', function (Blueprint $table) {
            $table->dropIndex('service_assignments_billing_idx');
            $table->dropColumn([
                'billing_rate_cents',
                'billing_unit_type',
                'frequency_per_week',
                'duration_minutes',
                'calculated_weekly_cost_cents',
            ]);
        });
    }
};
