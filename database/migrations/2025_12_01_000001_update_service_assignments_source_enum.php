<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Update service_assignments.source enum to include INTERNAL and SSPO values.
 *
 * The original enum only had: manual, triage, rpm_alert, api
 * This adds: INTERNAL (SPO direct staff), SSPO (subcontracted SSPO staff)
 */
return new class extends Migration
{
    public function up(): void
    {
        // For PostgreSQL, we need to alter the enum type
        if (config('database.default') === 'pgsql') {
            // Add new values to the enum type
            DB::statement("ALTER TABLE service_assignments DROP CONSTRAINT IF EXISTS service_assignments_source_check");
            DB::statement("ALTER TABLE service_assignments ALTER COLUMN source TYPE VARCHAR(50)");
        } else {
            // For MySQL/SQLite, modify the column
            Schema::table('service_assignments', function ($table) {
                $table->string('source', 50)->default('manual')->change();
            });
        }
    }

    public function down(): void
    {
        // Revert would require converting data which is complex
        // Keep the wider column type
    }
};

