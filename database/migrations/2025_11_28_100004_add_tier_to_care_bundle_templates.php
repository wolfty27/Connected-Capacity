<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add tier field to care_bundle_templates for intensity classification.
 *
 * Tiers classify bundles by intensity level (1-4):
 * - Tier 4: Highest intensity (SE3, SE2, SSB)
 * - Tier 3: High intensity (SE1, SSA, CC0, CB0, IB0, BB0, PD0)
 * - Tier 2: Moderate intensity (RB0, RA2, CA2, IA2, BA2, PC0, PB0)
 * - Tier 1: Lower intensity (RA1, CA1, IA1, BA1, PA2, PA1)
 *
 * @see docs/CC21_RUG_Bundle_Templates.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_bundle_templates', function (Blueprint $table) {
            $table->unsignedTinyInteger('tier')
                ->nullable()
                ->after('rug_category')
                ->comment('Intensity tier (1-4): 4=highest, 1=lowest');

            $table->index('tier');
        });

        // Populate tier values based on RUG group mapping
        $this->seedTiers();
    }

    public function down(): void
    {
        Schema::table('care_bundle_templates', function (Blueprint $table) {
            $table->dropIndex(['tier']);
            $table->dropColumn('tier');
        });
    }

    /**
     * Seed tier values based on RUG → tier mapping.
     */
    protected function seedTiers(): void
    {
        // Tier mappings according to the specification
        $tierMappings = [
            // Tier 4: Highest intensity
            4 => ['SE3', 'SE2', 'SSB'],
            // Tier 3: High intensity
            3 => ['SE1', 'SSA', 'CC0', 'CB0', 'IB0', 'BB0', 'PD0'],
            // Tier 2: Moderate intensity
            2 => ['RB0', 'RA2', 'CA2', 'IA2', 'BA2', 'PC0', 'PB0'],
            // Tier 1: Lower intensity
            1 => ['RA1', 'CA1', 'IA1', 'BA1', 'PA2', 'PA1'],
        ];

        foreach ($tierMappings as $tier => $rugGroups) {
            \DB::table('care_bundle_templates')
                ->whereIn('rug_group', $rugGroups)
                ->update(['tier' => $tier]);
        }
    }
};
