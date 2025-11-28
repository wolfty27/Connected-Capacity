<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create organization_cost_profiles table for overhead/travel cost factors.
 *
 * This table stores cost profile settings per organization,
 * used to calculate true underlying costs including overhead and travel.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_cost_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->unique()
                ->constrained('service_provider_organizations')
                ->cascadeOnDelete();
            $table->decimal('overhead_multiplier', 4, 2)
                ->default(1.40)
                ->comment('Multiplier for overhead costs (e.g., 1.40 = 40% overhead)');
            $table->unsignedInteger('travel_flat_cents_per_visit')
                ->default(500)
                ->comment('Flat travel cost per visit in cents (e.g., 500 = $5.00)');
            $table->unsignedInteger('travel_cents_per_km')
                ->default(60)
                ->comment('Variable travel cost per km in cents (e.g., 60 = $0.60/km)');
            $table->decimal('travel_average_distance_km', 6, 2)
                ->default(10.00)
                ->comment('Average distance per visit in km for estimation');
            $table->decimal('admin_overhead_percent', 5, 2)
                ->default(15.00)
                ->comment('Administrative overhead percentage');
            $table->decimal('supplies_percent', 5, 2)
                ->default(5.00)
                ->comment('Medical supplies/consumables percentage');
            $table->boolean('use_actual_travel')->default(false)
                ->comment('If true, use actual distance; if false, use average');
            $table->date('effective_from')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_cost_profiles');
    }
};
