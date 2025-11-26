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
        Schema::table('service_provider_organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('service_provider_organizations', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('type');
            }

            if (!Schema::hasColumn('service_provider_organizations', 'regions')) {
                $table->json('regions')->nullable()->after('postal_code');
            }

            if (!Schema::hasColumn('service_provider_organizations', 'capabilities')) {
                $table->json('capabilities')->nullable()->after('regions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_provider_organizations', function (Blueprint $table) {
            if (Schema::hasColumn('service_provider_organizations', 'capabilities')) {
                $table->dropColumn('capabilities');
            }

            if (Schema::hasColumn('service_provider_organizations', 'regions')) {
                $table->dropColumn('regions');
            }

            if (Schema::hasColumn('service_provider_organizations', 'contact_name')) {
                $table->dropColumn('contact_name');
            }
        });
    }
};
