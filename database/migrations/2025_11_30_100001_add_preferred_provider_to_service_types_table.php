<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add preferred_provider column to service_types table.
 *
 * This field indicates which organization type (SSPO or SPO) is primarily
 * responsible for providing/scheduling this service type.
 *
 * Values:
 * - 'sspo': Service is typically provided by SSPO staff (e.g., Nursing, Allied Health)
 * - 'spo': Service is typically provided by SPO staff (e.g., PSW, Homemaking)
 * - null: No preference (either can provide)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->string('preferred_provider', 10)->nullable()->after('active')
                ->comment('sspo or spo - which org type owns this service');
        });
    }

    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn('preferred_provider');
        });
    }
};
