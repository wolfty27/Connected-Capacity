<?php

namespace App\Jobs;

use App\Models\Patient;
use App\Services\InterraiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SyncInterraiStatusJob
 *
 * IR-009: Hourly job to reconcile patient.interrai_status with actual data.
 *
 * Handles edge cases and ensures cached status stays accurate:
 * - Syncs status after IAR upload success/failure
 * - Catches any status drift
 * - Updates status_updated_at timestamp
 *
 * Schedule: Hourly (configured in Console/Kernel.php)
 */
class SyncInterraiStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(InterraiService $interraiService): void
    {
        Log::info('SyncInterraiStatusJob started');

        $results = $interraiService->syncAllPatientStatuses();

        Log::info('SyncInterraiStatusJob completed', $results);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncInterraiStatusJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
