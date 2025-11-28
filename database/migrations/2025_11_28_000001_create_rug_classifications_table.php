<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RUG-III/HC Classification Table
 *
 * Stores the output of CIHI's RUG-III/HC classification algorithm.
 * Each classification is linked to an InterRAI HC assessment and
 * drives bundle template selection in the Bundle Engine.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 * @see docs/CC21_RUG_Algorithm_Pseudocode.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rug_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('assessment_id')->constrained('interrai_assessments')->onDelete('cascade');

            // RUG group code (e.g., 'CB0', 'IB0', 'RB0')
            $table->string('rug_group', 10);

            // RUG category (e.g., 'Clinically Complex', 'Impaired Cognition')
            $table->string('rug_category', 50);

            // Core computed scores
            $table->integer('adl_sum')->comment('x_adlsum: 4-18 range');
            $table->integer('iadl_sum')->comment('x_iadls: IADL impairment count');
            $table->integer('cps_score')->comment('sCPS: Cognitive Performance Scale 0-6');

            // Clinical flags (JSON) - which triggers were activated
            $table->json('flags')->nullable()->comment('Rehab, extensive, special_care, clinically_complex, impaired_cognition, behaviour flags');

            // Numeric rank for ordering (aNR3H equivalent)
            $table->integer('numeric_rank')->comment('CIHI numeric rank for case-mix ordering');

            // Computed therapy minutes (for rehab classification)
            $table->integer('therapy_minutes')->default(0)->comment('x_th_min: Total PT/OT/SLP minutes');

            // Extensive services count
            $table->integer('extensive_count')->default(0)->comment('x_ext_ct: Count of extensive service indicators');

            // Whether this is the current/latest classification for the patient
            $table->boolean('is_current')->default(true);

            // Metadata for audit/debug
            $table->json('computation_details')->nullable()->comment('Intermediate computation values for debugging');

            $table->timestamps();

            // Indexes for common queries
            $table->index(['patient_id', 'is_current']);
            $table->index('rug_group');
            $table->index('rug_category');
            $table->index('numeric_rank');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rug_classifications');
    }
};
