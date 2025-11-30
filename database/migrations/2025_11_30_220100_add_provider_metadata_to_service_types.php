<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            // Provider type metadata
            if (!Schema::hasColumn('service_types', 'allowed_provider_types')) {
                $table->json('allowed_provider_types')->nullable()->after('preferred_provider');
            }

            if (!Schema::hasColumn('service_types', 'delivery_mode')) {
                $table->string('delivery_mode')->default('in_person')->after('allowed_provider_types');
            }

            // Add index for provider filtering
            $table->index(['preferred_provider', 'delivery_mode'], 'service_type_provider_mode_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropIndex('service_type_provider_mode_idx');

            if (Schema::hasColumn('service_types', 'delivery_mode')) {
                $table->dropColumn('delivery_mode');
            }

            if (Schema::hasColumn('service_types', 'allowed_provider_types')) {
                $table->dropColumn('allowed_provider_types');
            }
        });
    }
};
