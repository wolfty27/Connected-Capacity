<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds care_bundle_template_id to support CC2.1 RUG-based bundle selection.
     * This links care plans to the new CareBundleTemplate model while maintaining
     * backward compatibility with the existing care_bundle_id relationship.
     */
    public function up(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->foreignId('care_bundle_template_id')
                ->nullable()
                ->after('care_bundle_id')
                ->constrained('care_bundle_templates')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropForeign(['care_bundle_template_id']);
            $table->dropColumn('care_bundle_template_id');
        });
    }
};
