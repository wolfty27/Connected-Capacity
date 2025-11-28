<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add source_organization_id to interrai_assessments table.
 *
 * Tracks which SPO organization provided the assessment when source is 'spo_completed'.
 * This allows displaying the organization name (e.g., "SE Health") instead of generic "SPO Completed".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interrai_assessments', function (Blueprint $table) {
            $table->foreignId('source_organization_id')
                ->nullable()
                ->after('source')
                ->constrained('service_provider_organizations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('interrai_assessments', function (Blueprint $table) {
            $table->dropForeign(['source_organization_id']);
            $table->dropColumn('source_organization_id');
        });
    }
};
