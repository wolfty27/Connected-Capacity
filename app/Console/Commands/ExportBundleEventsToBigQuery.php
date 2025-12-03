<?php

namespace App\Console\Commands;

use App\Services\BundleEngine\BundleEventLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Export Bundle Engine Events to BigQuery
 *
 * Phase 8: Learning Infrastructure
 *
 * This command exports pending bundle engine events to BigQuery
 * for long-term analytics and learning loop processing.
 *
 * Recommended schedule: Every 15 minutes
 *
 * Usage:
 *   php artisan bundle-engine:export-events
 *   php artisan bundle-engine:export-events --limit=500
 *   php artisan bundle-engine:export-events --dry-run
 */
class ExportBundleEventsToBigQuery extends Command
{
    protected $signature = 'bundle-engine:export-events
                            {--limit=1000 : Maximum events to export per batch}
                            {--dry-run : Preview export without sending to BigQuery}';

    protected $description = 'Export bundle engine events to BigQuery for analytics';

    public function __construct(
        protected BundleEventLogger $eventLogger
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info("Fetching pending events (limit: {$limit})...");

        $events = $this->eventLogger->getEventsForExport($limit);

        if ($events->isEmpty()) {
            $this->info('No pending events to export.');
            return Command::SUCCESS;
        }

        $this->info("Found {$events->count()} events to export.");

        if ($dryRun) {
            $this->warn('DRY RUN - Events would be exported:');
            $this->table(
                ['ID', 'Type', 'Patient Ref', 'Timestamp'],
                $events->map(fn($e) => [
                    Str::limit($e->id, 8),
                    $e->event_type,
                    $e->patient_ref,
                    $e->event_timestamp,
                ])->toArray()
            );
            return Command::SUCCESS;
        }

        // Generate batch ID
        $batchId = Str::uuid()->toString();

        $this->info("Exporting batch: {$batchId}");

        // Group events by type for BigQuery tables
        $eventsByType = $events->groupBy('event_type');

        $exportedCount = 0;
        $failedCount = 0;

        foreach ($eventsByType as $eventType => $typeEvents) {
            $tableName = $this->getTableName($eventType);

            $this->info("Exporting {$typeEvents->count()} events to {$tableName}...");

            try {
                // Transform events for BigQuery schema
                $rows = $typeEvents->map(fn($e) => $this->transformForBigQuery($e))->toArray();

                // Export to BigQuery
                // NOTE: This is a placeholder - implement actual BigQuery client
                if ($this->exportToBigQuery($tableName, $rows)) {
                    $exportedCount += count($rows);
                } else {
                    $failedCount += count($rows);
                }
            } catch (\Exception $e) {
                Log::error("Failed to export events to {$tableName}", [
                    'batch_id' => $batchId,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to export to {$tableName}: {$e->getMessage()}");
                $failedCount += $typeEvents->count();
            }
        }

        // Mark successfully exported events
        if ($exportedCount > 0) {
            $eventIds = $events->take($exportedCount)->pluck('id')->toArray();
            $this->eventLogger->markEventsExported($eventIds, $batchId);
        }

        $this->info("Export complete: {$exportedCount} exported, {$failedCount} failed.");

        return $failedCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Get BigQuery table name for event type.
     */
    protected function getTableName(string $eventType): string
    {
        return match ($eventType) {
            'scenario_generated' => 'bundle_scenarios_generated',
            'scenario_selected' => 'bundle_scenarios_selected',
            'care_plan_published' => 'bundle_care_plans_published',
            'patient_outcome' => 'bundle_patient_outcomes',
            'explanation_requested' => 'bundle_explanation_requests',
            default => 'bundle_events_other',
        };
    }

    /**
     * Transform event for BigQuery schema.
     */
    protected function transformForBigQuery(object $event): array
    {
        $payload = json_decode($event->payload, true) ?? [];

        return array_merge([
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'timestamp' => $event->event_timestamp,
            'patient_ref' => $event->patient_ref,
            'scenario_id' => $event->scenario_id,
            'user_ref' => $event->user_ref,
            'environment' => config('app.env'),
        ], $payload);
    }

    /**
     * Export rows to BigQuery.
     *
     * NOTE: This is a placeholder implementation.
     * Replace with actual BigQuery client when GCP integration is set up.
     */
    protected function exportToBigQuery(string $tableName, array $rows): bool
    {
        // Check if BigQuery is configured
        if (!config('services.bigquery.enabled', false)) {
            Log::info("BigQuery export skipped (not configured)", [
                'table' => $tableName,
                'row_count' => count($rows),
            ]);

            // In development, just log the export
            if (config('app.env') === 'local') {
                $this->warn("BigQuery not configured - logging export preview:");
                $this->line("  Table: {$tableName}");
                $this->line("  Rows: " . count($rows));
            }

            return true; // Consider successful for now
        }

        // TODO: Implement actual BigQuery export
        // Example with google/cloud-bigquery:
        //
        // $bigQuery = new BigQueryClient([
        //     'projectId' => config('services.bigquery.project_id'),
        //     'keyFilePath' => config('services.bigquery.key_file'),
        // ]);
        //
        // $dataset = $bigQuery->dataset(config('services.bigquery.dataset'));
        // $table = $dataset->table($tableName);
        //
        // $insertResponse = $table->insertRows(
        //     array_map(fn($row) => ['data' => $row], $rows)
        // );
        //
        // return $insertResponse->isSuccessful();

        return true;
    }
}

