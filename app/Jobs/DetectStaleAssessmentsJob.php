<?php

namespace App\Jobs;

use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Models\ReassessmentTrigger;
use App\Services\InterraiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * DetectStaleAssessmentsJob
 *
 * IR-009-01: Scheduled job to detect stale InterRAI assessments.
 *
 * Runs daily to:
 * - Identify assessments approaching 90-day staleness
 * - Update patient interrai_status cache
 * - Create reassessment triggers for stale assessments
 * - Log compliance metrics
 *
 * Schedule: Daily at 6:00 AM (configured in Console/Kernel.php)
 */
class DetectStaleAssessmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(InterraiService $interraiService): void
    {
        Log::info('DetectStaleAssessmentsJob started');

        $results = [
            'patients_checked' => 0,
            'statuses_updated' => 0,
            'newly_stale' => 0,
            'approaching_stale' => 0,
            'triggers_created' => 0,
        ];

        // Get all patients in queue
        $patients = Patient::where('is_in_queue', true)
            ->with('latestInterraiAssessment')
            ->get();

        foreach ($patients as $patient) {
            $results['patients_checked']++;

            // Sync status
            $oldStatus = $patient->interrai_status;
            $newStatus = $interraiService->syncPatientInterraiStatus($patient);

            if ($oldStatus !== $newStatus) {
                $results['statuses_updated']++;
            }

            // Check for newly stale assessments
            $assessment = $patient->latestInterraiAssessment;
            if ($assessment) {
                $daysOld = $assessment->assessment_date->diffInDays(now());

                // Newly stale (just crossed 90 days)
                if ($newStatus === Patient::INTERRAI_STATUS_STALE && $oldStatus !== Patient::INTERRAI_STATUS_STALE) {
                    $results['newly_stale']++;

                    // Create reassessment trigger if none exists
                    if (!$patient->hasPendingReassessment()) {
                        ReassessmentTrigger::create([
                            'patient_id' => $patient->id,
                            'trigger_reason' => ReassessmentTrigger::REASON_STALE_ASSESSMENT,
                            'reason_notes' => "Assessment is {$daysOld} days old (>90 days). Reassessment required per OHaH RFS.",
                            'priority' => ReassessmentTrigger::PRIORITY_MEDIUM,
                        ]);
                        $results['triggers_created']++;
                    }
                }

                // Approaching stale (75-89 days old) - for notifications
                if ($daysOld >= 75 && $daysOld < 90) {
                    $results['approaching_stale']++;
                }
            }
        }

        // Log summary
        Log::info('DetectStaleAssessmentsJob completed', $results);

        // Could dispatch notification events here
        // if ($results['newly_stale'] > 0) {
        //     event(new StaleAssessmentsDetected($results));
        // }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DetectStaleAssessmentsJob failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
