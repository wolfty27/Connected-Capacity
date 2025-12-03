<?php

namespace App\Services\BundleEngine;

use App\Models\Patient;
use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * BundleEventLogger
 *
 * Phase 8: Learning Infrastructure
 *
 * Logs events from the AI Bundle Engine for analytics and learning.
 * Events are stored locally and can be exported to BigQuery.
 *
 * Event Types:
 * - scenario_generated: When scenarios are created
 * - scenario_selected: When coordinator selects a scenario
 * - care_plan_published: When care plan is activated
 * - patient_outcome: Outcome tracking events
 * - explanation_requested: When AI explanation is requested
 */
class BundleEventLogger
{
    /**
     * Log scenario generation event.
     */
    public function logScenarioGenerated(
        Patient $patient,
        PatientNeedsProfile $profile,
        ScenarioBundleDTO $scenario,
        int $generationTimeMs = 0
    ): void {
        $this->logEvent('scenario_generated', $patient->id, null, [
            'scenario_id' => $scenario->scenarioId,
            'primary_axis' => $scenario->primaryAxis->value,
            'secondary_axes' => array_map(fn($a) => $a->value, $scenario->secondaryAxes ?? []),
            'scenario_title' => $scenario->title,
            'scenario_description' => $scenario->description,
            'service_count' => count($scenario->serviceLines),
            'services' => array_map(fn($s) => [
                'code' => $s->serviceCode,
                'hours' => $s->weeklyHours ?? 0,
                'visits' => $s->frequencyCount ?? 0,
            ], $scenario->serviceLines),
            'weekly_hours' => $scenario->totalWeeklyHours,
            'weekly_cost' => $scenario->weeklyEstimatedCost,
            'cost_status' => $scenario->costStatus,
            'rug_group' => $profile->rugGroup,
            'needs_cluster' => $profile->needsCluster,
            'episode_type' => $profile->episodeType,
            'algorithm_scores' => [
                'personal_support' => $profile->personalSupportScore,
                'rehabilitation' => $profile->rehabilitationScore,
                'chess_ca' => $profile->chessCAScore,
                'pain' => $profile->painScore,
                'distressed_mood' => $profile->distressedMoodScore,
                'service_urgency' => $profile->serviceUrgencyScore,
            ],
            'triggered_caps' => array_map(fn($c) => $c['name'] ?? $c, $profile->triggeredCAPs ?? []),
            'confidence_level' => $profile->confidenceLevel,
            'data_completeness' => $profile->dataCompletenessScore,
            'generation_time_ms' => $generationTimeMs,
            'engine_version' => config('bundle_engine.version', '2.2.0'),
        ], $scenario->scenarioId);
    }

    /**
     * Log batch scenario generation (all scenarios at once).
     */
    public function logScenariosGenerated(
        Patient $patient,
        PatientNeedsProfile $profile,
        array $scenarios,
        int $totalGenerationTimeMs = 0
    ): void {
        $perScenarioTime = count($scenarios) > 0
            ? (int) round($totalGenerationTimeMs / count($scenarios))
            : 0;

        foreach ($scenarios as $scenario) {
            $this->logScenarioGenerated($patient, $profile, $scenario, $perScenarioTime);
        }
    }

    /**
     * Log scenario selection event.
     */
    public function logScenarioSelected(
        int $patientId,
        string $scenarioId,
        int $scenariosOfferedCount,
        int $scenarioRank,
        bool $wasRecommended,
        ?int $selectionTimeSeconds = null,
        bool $modificationsMAde = false,
        ?array $modificationSummary = null,
        ?int $userId = null,
        bool $explanationRequested = false,
        ?string $explanationSource = null
    ): void {
        $this->logEvent('scenario_selected', $patientId, $userId, [
            'scenario_id' => $scenarioId,
            'scenarios_offered_count' => $scenariosOfferedCount,
            'scenario_rank' => $scenarioRank,
            'was_recommended' => $wasRecommended,
            'selection_time_seconds' => $selectionTimeSeconds,
            'modifications_made' => $modificationsMAde,
            'modification_summary' => $modificationSummary,
            'explanation_requested' => $explanationRequested,
            'explanation_source' => $explanationSource,
        ], $scenarioId);
    }

    /**
     * Log care plan publication event.
     */
    public function logCarePlanPublished(
        int $patientId,
        int $carePlanId,
        ?string $originalScenarioId,
        int $finalServiceCount,
        float $finalWeeklyHours,
        float $finalWeeklyCost,
        ?array $deviationFromScenario = null,
        bool $isModification = false,
        ?int $userId = null
    ): void {
        $this->logEvent('care_plan_published', $patientId, $userId, [
            'care_plan_id' => $carePlanId,
            'original_scenario_id' => $originalScenarioId,
            'final_service_count' => $finalServiceCount,
            'final_weekly_hours' => $finalWeeklyHours,
            'final_weekly_cost' => $finalWeeklyCost,
            'deviation_from_scenario' => $deviationFromScenario,
            'is_modification' => $isModification,
        ], $originalScenarioId, $carePlanId);
    }

    /**
     * Log patient outcome event.
     */
    public function logPatientOutcome(
        int $patientId,
        ?int $carePlanId,
        string $outcomeType,
        string $outcomeValue,
        ?string $outcomeSeverity = null,
        ?int $daysSincePlanStart = null,
        ?string $assessmentSource = null,
        ?string $originalScenarioId = null,
        ?array $clinicalContext = null
    ): void {
        $this->logEvent('patient_outcome', $patientId, null, [
            'care_plan_id' => $carePlanId,
            'original_scenario_id' => $originalScenarioId,
            'outcome_type' => $outcomeType,
            'outcome_value' => $outcomeValue,
            'outcome_severity' => $outcomeSeverity,
            'days_since_plan_start' => $daysSincePlanStart,
            'assessment_source' => $assessmentSource,
            'clinical_context' => $clinicalContext,
        ], null, $carePlanId);
    }

    /**
     * Log explanation request event.
     */
    public function logExplanationRequested(
        int $patientId,
        string $scenarioId,
        string $explanationSource,
        int $responseTimeMs,
        ?int $userId = null
    ): void {
        $this->logEvent('explanation_requested', $patientId, $userId, [
            'scenario_id' => $scenarioId,
            'explanation_source' => $explanationSource,
            'response_time_ms' => $responseTimeMs,
        ], $scenarioId);
    }

    /**
     * Core event logging method.
     */
    protected function logEvent(
        string $eventType,
        int $patientId,
        ?int $userId,
        array $payload,
        ?string $scenarioId = null,
        ?int $carePlanId = null
    ): void {
        try {
            DB::table('bundle_engine_events')->insert([
                'id' => Str::uuid()->toString(),
                'event_type' => $eventType,
                'event_timestamp' => now(),
                'patient_id' => $patientId,
                'patient_ref' => $this->generatePatientRef($patientId),
                'care_plan_id' => $carePlanId,
                'scenario_id' => $scenarioId,
                'user_id' => $userId,
                'user_ref' => $userId ? $this->generateUserRef($userId) : null,
                'payload' => json_encode($payload),
                'exported_to_bigquery' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            Log::error('Failed to log bundle engine event', [
                'event_type' => $eventType,
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate de-identified patient reference.
     */
    protected function generatePatientRef(int $patientId): string
    {
        $salt = config('app.key', 'connected-capacity');
        $hash = substr(hash('sha256', 'patient_' . $patientId . $salt), 0, 4);
        return "P-{$hash}";
    }

    /**
     * Generate de-identified user reference.
     */
    protected function generateUserRef(int $userId): string
    {
        $salt = config('app.key', 'connected-capacity');
        $hash = substr(hash('sha256', 'user_' . $userId . $salt), 0, 4);
        return "U-{$hash}";
    }

    /**
     * Get events for BigQuery export.
     */
    public function getEventsForExport(int $limit = 1000): \Illuminate\Support\Collection
    {
        return DB::table('bundle_engine_events')
            ->where('exported_to_bigquery', false)
            ->orderBy('event_timestamp')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark events as exported.
     */
    public function markEventsExported(array $eventIds, string $batchId): int
    {
        return DB::table('bundle_engine_events')
            ->whereIn('id', $eventIds)
            ->update([
                'exported_to_bigquery' => true,
                'exported_at' => now(),
                'export_batch_id' => $batchId,
                'updated_at' => now(),
            ]);
    }

    /**
     * Get event statistics for monitoring.
     */
    public function getEventStats(?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table('bundle_engine_events');

        if ($startDate) {
            $query->where('event_timestamp', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('event_timestamp', '<=', $endDate);
        }

        return [
            'total_events' => $query->count(),
            'by_type' => $query->clone()
                ->selectRaw('event_type, COUNT(*) as count')
                ->groupBy('event_type')
                ->pluck('count', 'event_type')
                ->toArray(),
            'pending_export' => $query->clone()
                ->where('exported_to_bigquery', false)
                ->count(),
            'exported' => $query->clone()
                ->where('exported_to_bigquery', true)
                ->count(),
        ];
    }
}

