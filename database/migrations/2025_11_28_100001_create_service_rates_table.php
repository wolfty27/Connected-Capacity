<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create service_rates table for Ontario-aligned rate card system.
 *
 * This table stores billing rates per service type, supporting:
 * - System-wide default rates (organization_id = null)
 * - Organization-specific rate overrides (SPO/SSPO)
 * - Time-bound effective periods for rate changes
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('service_provider_organizations')
                ->cascadeOnDelete()
                ->comment('Null = system default rate; set = SPO/SSPO override');
            $table->string('unit_type', 50)
                ->comment('hour, visit, month, trip, call, service, night, block');
            $table->unsignedInteger('rate_cents')
                ->comment('Billing rate in cents (CAD)');
            $table->date('effective_from')
                ->comment('Date from which this rate is active');
            $table->date('effective_to')
                ->nullable()
                ->comment('Date until which this rate is active (null = indefinitely)');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            // Indexes for efficient lookups
            $table->index(['service_type_id', 'organization_id', 'effective_from'], 'service_rates_lookup_idx');
            $table->index(['organization_id', 'effective_from']);
            $table->index('effective_from');
            $table->index('effective_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_rates');
    }
};
