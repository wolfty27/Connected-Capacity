<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IR-002-01: Add cached interrai_status to patients table
 *
 * This column caches the InterRAI assessment status for efficient
 * queue listing and filtering without complex joins.
 *
 * Status values:
 * - 'current': Assessment <90 days old
 * - 'stale': Assessment >90 days old
 * - 'missing': No assessment on file
 * - 'pending_upload': Assessment created, IAR upload pending
 * - 'upload_failed': IAR upload failed, needs attention
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('interrai_status', 50)->default('missing')->after('rai_cha_score');
            $table->timestamp('interrai_status_updated_at')->nullable()->after('interrai_status');

            $table->index('interrai_status');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['interrai_status']);
            $table->dropColumn(['interrai_status', 'interrai_status_updated_at']);
        });
    }
};
