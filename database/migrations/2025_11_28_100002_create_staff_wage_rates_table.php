<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create staff_wage_rates table for true cost calculation.
 *
 * This table stores actual wage rates per staff member or role,
 * used to calculate true underlying costs (vs billing rates).
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_wage_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Specific staff member (null = role-based default)');
            $table->foreignId('organization_id')
                ->constrained('service_provider_organizations')
                ->cascadeOnDelete();
            $table->string('role', 50)
                ->nullable()
                ->comment('Role code (PSW, RN, RPN, PT, OT, etc.) for role-based rates');
            $table->foreignId('service_type_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Specific service type if rate varies by service');
            $table->unsignedInteger('wage_cents_per_hour')
                ->comment('Base hourly wage in cents (CAD)');
            $table->decimal('benefits_multiplier', 4, 2)
                ->default(1.00)
                ->comment('Multiplier for benefits (e.g., 1.15 for 15% benefits)');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            // Indexes for efficient lookups
            $table->index(['organization_id', 'role', 'effective_from'], 'wage_rates_role_lookup_idx');
            $table->index(['organization_id', 'user_id', 'effective_from'], 'wage_rates_user_lookup_idx');
            $table->index('effective_from');
            $table->index('effective_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_wage_rates');
    }
};
