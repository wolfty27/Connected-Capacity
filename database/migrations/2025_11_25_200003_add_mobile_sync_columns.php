<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add columns to support mobile offline sync functionality
 *
 * MOB-004: Adds client_note_id for deduplication during offline sync
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add client_note_id to interdisciplinary_notes for offline sync
        if (Schema::hasTable('interdisciplinary_notes') && !Schema::hasColumn('interdisciplinary_notes', 'client_note_id')) {
            Schema::table('interdisciplinary_notes', function (Blueprint $table) {
                $table->string('client_note_id', 100)->nullable()->after('id');
                $table->index('client_note_id', 'interdisciplinary_notes_client_id_idx');
            });
        }

        // Add location tracking columns to service_assignments for clock in/out
        if (Schema::hasTable('service_assignments')) {
            Schema::table('service_assignments', function (Blueprint $table) {
                if (!Schema::hasColumn('service_assignments', 'clock_in_lat')) {
                    $table->decimal('clock_in_lat', 10, 7)->nullable()->after('actual_start');
                }
                if (!Schema::hasColumn('service_assignments', 'clock_in_lng')) {
                    $table->decimal('clock_in_lng', 10, 7)->nullable()->after('clock_in_lat');
                }
                if (!Schema::hasColumn('service_assignments', 'clock_out_lat')) {
                    $table->decimal('clock_out_lat', 10, 7)->nullable()->after('actual_end');
                }
                if (!Schema::hasColumn('service_assignments', 'clock_out_lng')) {
                    $table->decimal('clock_out_lng', 10, 7)->nullable()->after('clock_out_lat');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_assignments')) {
            Schema::table('service_assignments', function (Blueprint $table) {
                $table->dropColumn(['clock_in_lat', 'clock_in_lng', 'clock_out_lat', 'clock_out_lng']);
            });
        }

        if (Schema::hasTable('interdisciplinary_notes') && Schema::hasColumn('interdisciplinary_notes', 'client_note_id')) {
            Schema::table('interdisciplinary_notes', function (Blueprint $table) {
                $table->dropIndex('interdisciplinary_notes_client_id_idx');
                $table->dropColumn('client_note_id');
            });
        }
    }
};
