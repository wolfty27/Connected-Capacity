<?php

namespace Database\Seeders;

use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Services\RUGClassificationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * DemoAssessmentsSeeder - Creates InterRAI HC assessments and RUG classifications
 *
 * Creates assessments ONLY for patients that should have them:
 * - 3 READY queue patients (assessment_complete status)
 * - 10 Active patients (transitioned status)
 *
 * Does NOT create assessments for the 2 NOT READY queue patients
 * (Q04, Q05) who are still awaiting InterRAI HC assessment.
 *
 * Uses DemoInterraiPayloadFactory to generate iCODE payloads that produce
 * specific RUG classifications through the RUGClassificationService.
 *
 * Target RUG distribution:
 * - Special Rehabilitation: RB0, RA2
 * - Extensive Services: SE3, SE1
 * - Special Care: SSB, SSA
 * - Clinically Complex: CC0 (x3)
 * - Impaired Cognition: IB0 (x3)
 * - Behaviour Problems: BB0 (x3)
 * - Reduced Physical Function: PD0
 *
 * @see DemoInterraiPayloadFactory
 * @see docs/CC21_RUG_Algorithm_Pseudocode.md
 */
class DemoAssessmentsSeeder extends Seeder
{
    protected RUGClassificationService $rugService;

    public function __construct()
    {
        $this->rugService = new RUGClassificationService();
    }

    public function run(): void
    {
        $this->command->info('Creating InterRAI assessments and RUG classifications...');

        $targetRugGroups = DemoPatientsSeeder::getTargetRugGroups();
        $notReadyPatients = DemoPatientsSeeder::getNotReadyPatients();

        $expectedCount = count($targetRugGroups); // 13 patients (3 ready queue + 10 active)
        $this->command->info("  Expected: {$expectedCount} patients with assessments (3 ready queue + 10 active)");
        $this->command->info("  Skipping: " . count($notReadyPatients) . " NOT READY queue patients (no assessment yet)");

        $patients = Patient::whereIn('ohip', array_keys($targetRugGroups))->get();

        if ($patients->isEmpty()) {
            $this->command->error('No demo patients found. Run DemoPatientsSeeder first.');
            return;
        }

        $createdCount = 0;
        $mismatches = [];

        foreach ($patients as $patient) {
            // Skip patients that should NOT have assessments
            if (in_array($patient->ohip, $notReadyPatients)) {
                $this->command->warn("  Skipping {$patient->user->name} (NOT READY - no assessment)");
                continue;
            }

            $targetRug = $targetRugGroups[$patient->ohip] ?? 'PA1';

            // Get iCODE payload from factory
            $rawItems = DemoInterraiPayloadFactory::forRug($targetRug);

            // Create InterRAI assessment with proper iCODE raw_items
            $assessment = InterraiAssessment::create([
                'patient_id' => $patient->id,
                'assessment_type' => 'hc',
                'assessment_date' => now()->subDays(rand(5, 30)),
                'source' => 'OHAH',
                'workflow_status' => 'completed',
                'is_current' => true,
                'version' => 1,
                'maple_score' => $this->deriveMapleScore($rawItems),
                'adl_hierarchy' => $this->deriveAdlHierarchy($rawItems),
                'iadl_difficulty' => $this->deriveIadlDifficulty($rawItems),
                'cognitive_performance_scale' => $rawItems['cps'] ?? 0,
                'chess_score' => $rawItems['chess'] ?? 0,
                'depression_rating_scale' => $rawItems['drs'] ?? 0,
                'pain_scale' => max($rawItems['iJ1a'] ?? 0, $rawItems['iJ1b'] ?? 0),
                'falls_in_last_90_days' => ($rawItems['iJ2a'] ?? 0) > 0,
                'wandering_flag' => ($rawItems['iE3a'] ?? 0) > 0,
                'primary_diagnosis_icd10' => $this->getDiagnosisForRug($targetRug),
                'raw_items' => $rawItems,
                'iar_upload_status' => 'uploaded',
                'chris_sync_status' => 'synced',
            ]);

            // Generate RUG classification using the service
            $rug = $this->rugService->classify($assessment);

            // Update patient with derived scores
            $patient->update([
                'maple_score' => $this->deriveMapleScore($rawItems),
                'rai_cha_score' => $this->deriveAdlHierarchy($rawItems),
            ]);

            $createdCount++;

            // Check for mismatch
            if ($rug->rug_group !== $targetRug) {
                $mismatches[] = [
                    'patient' => $patient->user->name,
                    'expected' => $targetRug,
                    'actual' => $rug->rug_group,
                ];
                Log::warning('Demo seed RUG mismatch', [
                    'patient' => $patient->id,
                    'patient_name' => $patient->user->name,
                    'expected' => $targetRug,
                    'actual' => $rug->rug_group,
                    'adl_sum' => $rug->adl_sum,
                    'iadl_sum' => $rug->iadl_sum,
                    'cps_score' => $rug->cps_score,
                    'flags' => $rug->flags,
                ]);
                $this->command->warn("  {$patient->user->name}: InterRAI -> RUG {$rug->rug_group} (expected {$targetRug}) ⚠️");
            } else {
                $this->command->info("  {$patient->user->name}: InterRAI -> RUG {$rug->rug_group} ({$rug->rug_category}) ✓");
            }
        }

        $this->command->info("Assessments and RUG classifications created for {$createdCount} patients.");
        $this->command->info("  - 3 READY queue patients: will show 'InterRAI HC Assessment Complete - Ready for Bundle'");
        $this->command->info("  - 10 Active patients: already transitioned with care plans");
        $this->command->info("  - 2 NOT READY queue patients: will show 'InterRAI HC Assessment Pending'");

        if (count($mismatches) > 0) {
            $this->command->warn("⚠️ " . count($mismatches) . " RUG mismatches detected. Check logs for details.");
        } else {
            $this->command->info("✓ All RUG classifications match their targets!");
        }
    }

    /**
     * Derive MAPLe score from raw items (simplified).
     */
    protected function deriveMapleScore(array $rawItems): string
    {
        // Use ADL + cognitive indicators to derive MAPLE
        $adlSum = ($rawItems['iG1ha'] ?? 0) + ($rawItems['iG1ia'] ?? 0) +
                  ($rawItems['iG1ea'] ?? 0) + ($rawItems['iG1ja'] ?? 0);
        $cps = $rawItems['cps'] ?? 0;

        if ($adlSum >= 8 || $cps >= 4) return '5';
        if ($adlSum >= 6 || $cps >= 3) return '4';
        if ($adlSum >= 4 || $cps >= 2) return '3';
        if ($adlSum >= 2) return '2';
        return '1';
    }

    /**
     * Derive ADL hierarchy from raw items.
     */
    protected function deriveAdlHierarchy(array $rawItems): int
    {
        // Get the max ADL item score
        $scores = [
            $rawItems['iG1ha'] ?? 0,
            $rawItems['iG1ia'] ?? 0,
            $rawItems['iG1ea'] ?? 0,
            $rawItems['iG1ja'] ?? 0,
        ];
        return min(6, max($scores));
    }

    /**
     * Derive IADL difficulty from raw items.
     */
    protected function deriveIadlDifficulty(array $rawItems): int
    {
        // Get the max IADL item score
        $scores = [
            $rawItems['iG1aa'] ?? 0,
            $rawItems['iG1da'] ?? 0,
            $rawItems['iG1eb'] ?? 0,
        ];
        return min(6, max($scores));
    }

    /**
     * Get a typical diagnosis ICD-10 code for a RUG category.
     */
    protected function getDiagnosisForRug(string $rugGroup): string
    {
        // Return diagnosis codes that align with RUG categories
        return match (true) {
            str_starts_with($rugGroup, 'R') => 'I63.9',  // Stroke (Special Rehab)
            str_starts_with($rugGroup, 'SE') => 'N18.6', // ESRD (Extensive Services)
            str_starts_with($rugGroup, 'SS') => 'G35',   // MS (Special Care)
            str_starts_with($rugGroup, 'C') => 'J44.1',  // COPD (Clinically Complex)
            str_starts_with($rugGroup, 'I') => 'G30.9',  // Alzheimer's (Impaired Cognition)
            str_starts_with($rugGroup, 'B') => 'F03.90', // Dementia with behavioral (Behaviour)
            str_starts_with($rugGroup, 'P') => 'G20',    // Parkinson's (Reduced Physical Function)
            default => 'M17.9', // Osteoarthritis (default)
        };
    }
}
