<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fetch all patients with relevant data
        $patients = DB::table('patients')
            ->whereNotNull('triage_summary')
            ->orWhereNotNull('risk_flags')
            ->get();

        foreach ($patients as $patient) {
            // Check if a profile already exists to ensure idempotency
            $exists = DB::table('transition_needs_profiles')
                ->where('patient_id', $patient->id)
                ->exists();

            if (!$exists) {
                DB::table('transition_needs_profiles')->insert([
                    'patient_id' => $patient->id,
                    'clinical_flags' => $patient->risk_flags, // Already JSON in DB
                    'narrative_summary' => $patient->triage_summary, // Already JSON/Text in DB, moving as is to text column (might need decode/encode if structure changes, but assuming direct move for now)
                    'status' => 'completed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data migration reversal is risky/complex.
        // We generally don't delete the migrated data in down() unless strictly required during dev.
    }
};