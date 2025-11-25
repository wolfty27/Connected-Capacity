<?php

namespace App\Services;

use App\Events\SlaComplianceAlert;
use App\Models\InterraiAssessment;
use App\Models\Patient;
use App\Models\TriageResult;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ReferralIntakeService - Handles HPG referral intake with OHaH compliance
 *
 * Per OHaH RFS requirements:
 * - Process HPG referrals within 15-minute SLA
 * - Extract and validate InterRAI HC data
 * - Flag when InterRAI is missing or stale (>3 months)
 * - Track HPG response timestamps for SLA compliance
 */
class ReferralIntakeService
{
    protected InterraiService $interraiService;
    protected HpgResponseService $hpgResponseService;

    public function __construct(
        InterraiService $interraiService,
        HpgResponseService $hpgResponseService
    ) {
        $this->interraiService = $interraiService;
        $this->hpgResponseService = $hpgResponseService;
    }

    /**
     * Process an incoming HPG referral.
     *
     * @param array $hpgPayload Raw referral data from HPG
     * @param User|null $receivedBy User who received the referral
     * @return array{patient: Patient, triage: TriageResult, interrai: ?InterraiAssessment, flags: array}
     */
    public function processReferral(array $hpgPayload, ?User $receivedBy = null): array
    {
        return DB::transaction(function () use ($hpgPayload, $receivedBy) {
            $receivedAt = now();

            Log::info('Processing HPG referral', [
                'received_at' => $receivedAt->toIso8601String(),
                'received_by' => $receivedBy?->id,
            ]);

            // Step 1: Create or find patient
            $patient = $this->findOrCreatePatient($hpgPayload);

            // Step 2: Create triage result with HPG timestamps
            $triageResult = $this->createTriageResult($patient, $hpgPayload, $receivedAt);

            // Step 3: Extract InterRAI from HPG payload (if present)
            $interraiAssessment = $this->interraiService->extractFromHpgPayload($patient, $hpgPayload);

            // Step 4: Check InterRAI status and generate flags
            $interraiStatus = $this->checkInterraiStatus($patient, $interraiAssessment, $hpgPayload);

            // Step 5: Generate compliance flags
            $flags = $this->generateComplianceFlags($patient, $triageResult, $interraiStatus);

            // Log intake summary
            Log::info('HPG referral processed', [
                'patient_id' => $patient->id,
                'triage_id' => $triageResult->id,
                'interrai_assessment_id' => $interraiAssessment?->id,
                'interrai_status' => $interraiStatus['status'],
                'flags' => array_keys(array_filter($flags)),
            ]);

            return [
                'patient' => $patient,
                'triage' => $triageResult,
                'interrai' => $interraiAssessment,
                'interrai_status' => $interraiStatus,
                'flags' => $flags,
                'received_at' => $receivedAt->toIso8601String(),
            ];
        });
    }

    /**
     * Check InterRAI assessment status for referral.
     *
     * Per OHaH RFS: SPO must complete InterRAI HC if:
     * - Missing from referral
     * - >3 months old
     * - Clinical condition has significantly changed
     *
     * @return array{status: string, requires_completion: bool, reason: string, message: string, assessment?: InterraiAssessment}
     */
    public function checkInterraiStatus(Patient $patient, ?InterraiAssessment $newAssessment, array $hpgPayload): array
    {
        // If we just extracted a new assessment from HPG
        if ($newAssessment) {
            // Check if the HPG-provided assessment is stale
            if ($newAssessment->isStale()) {
                return [
                    'status' => 'stale_from_hpg',
                    'requires_completion' => true,
                    'reason' => 'hpg_assessment_stale',
                    'message' => "InterRAI HC from HPG is {$newAssessment->assessment_date->diffInDays(now())} days old (>90 days). SPO must complete new assessment.",
                    'assessment' => $newAssessment,
                    'assessment_date' => $newAssessment->assessment_date->toIso8601String(),
                    'days_old' => $newAssessment->assessment_date->diffInDays(now()),
                ];
            }

            // Check for clinical change indicators
            if ($this->detectClinicalChange($patient, $newAssessment, $hpgPayload)) {
                return [
                    'status' => 'clinical_change',
                    'requires_completion' => true,
                    'reason' => 'clinical_change_detected',
                    'message' => 'Significant clinical change detected. SPO should consider reassessment.',
                    'assessment' => $newAssessment,
                ];
            }

            return [
                'status' => 'current',
                'requires_completion' => false,
                'reason' => 'current_from_hpg',
                'message' => 'Current InterRAI HC assessment received from HPG.',
                'assessment' => $newAssessment,
                'assessment_date' => $newAssessment->assessment_date->toIso8601String(),
                'days_until_stale' => $newAssessment->days_until_stale,
            ];
        }

        // No new assessment from HPG - check existing patient assessments
        $existingAssessment = $patient->latestInterraiAssessment;

        if (!$existingAssessment) {
            return [
                'status' => 'missing',
                'requires_completion' => true,
                'reason' => 'no_assessment_on_file',
                'message' => 'No InterRAI HC assessment on file. SPO must complete assessment within 14 days.',
                'assessment' => null,
            ];
        }

        if ($existingAssessment->isStale()) {
            return [
                'status' => 'stale',
                'requires_completion' => true,
                'reason' => 'existing_assessment_stale',
                'message' => "Existing InterRAI HC is {$existingAssessment->assessment_date->diffInDays(now())} days old (>90 days). SPO must complete new assessment.",
                'assessment' => $existingAssessment,
                'assessment_date' => $existingAssessment->assessment_date->toIso8601String(),
                'days_old' => $existingAssessment->assessment_date->diffInDays(now()),
            ];
        }

        return [
            'status' => 'current_on_file',
            'requires_completion' => false,
            'reason' => 'existing_assessment_current',
            'message' => 'Current InterRAI HC assessment exists on file.',
            'assessment' => $existingAssessment,
            'assessment_date' => $existingAssessment->assessment_date->toIso8601String(),
            'days_until_stale' => $existingAssessment->days_until_stale,
        ];
    }

    /**
     * Mark referral as responded (for HPG SLA tracking).
     */
    public function markResponded(TriageResult $triageResult, User $respondedBy): TriageResult
    {
        $triageResult->markHpgResponded($respondedBy);

        // Check if SLA was met
        if ($triageResult->isHpgSlaBreached()) {
            $minutesElapsed = $triageResult->hpg_response_time_minutes;

            event(SlaComplianceAlert::hpgResponseBreach($triageResult, $minutesElapsed));

            Log::warning('HPG response SLA breached', [
                'triage_id' => $triageResult->id,
                'patient_id' => $triageResult->patient_id,
                'response_time_minutes' => $minutesElapsed,
            ]);
        } else {
            Log::info('HPG response completed within SLA', [
                'triage_id' => $triageResult->id,
                'response_time_minutes' => $triageResult->hpg_response_time_minutes,
            ]);
        }

        return $triageResult;
    }

    /**
     * Get pending referrals that need InterRAI completion.
     */
    public function getReferralsNeedingInterrai(int $limit = 50): \Illuminate\Support\Collection
    {
        return TriageResult::query()
            ->whereNotNull('hpg_received_at')
            ->whereHas('patient', function ($q) {
                // No InterRAI or stale InterRAI
                $q->where(function ($query) {
                    $query->whereDoesntHave('interraiAssessments')
                        ->orWhereDoesntHave('interraiAssessments', function ($q2) {
                            $q2->where('assessment_date', '>=', now()->subMonths(InterraiAssessment::STALENESS_MONTHS));
                        });
                });
            })
            ->with([
                'patient.user:id,name,email',
                'patient.latestInterraiAssessment',
            ])
            ->orderBy('hpg_received_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($triage) {
                $interraiStatus = $this->interraiService->requiresCompletion($triage->patient);
                return [
                    'triage_id' => $triage->id,
                    'patient_id' => $triage->patient_id,
                    'patient_name' => $triage->patient?->user?->name,
                    'received_at' => $triage->hpg_received_at?->toIso8601String(),
                    'acuity_level' => $triage->acuity_level,
                    'crisis_designation' => $triage->crisis_designation,
                    'interrai_status' => $interraiStatus,
                ];
            });
    }

    /**
     * Get intake metrics for dashboard.
     */
    public function getIntakeMetrics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(7);
        $endDate = $endDate ?? now();

        $totalReferrals = TriageResult::whereBetween('hpg_received_at', [$startDate, $endDate])->count();

        $withInterrai = TriageResult::whereBetween('hpg_received_at', [$startDate, $endDate])
            ->whereHas('patient.interraiAssessments', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->count();

        $needingInterrai = TriageResult::whereBetween('hpg_received_at', [$startDate, $endDate])
            ->whereHas('patient', function ($q) {
                $q->where(function ($query) {
                    $query->whereDoesntHave('interraiAssessments')
                        ->orWhereDoesntHave('interraiAssessments', function ($q2) {
                            $q2->where('assessment_date', '>=', now()->subMonths(InterraiAssessment::STALENESS_MONTHS));
                        });
                });
            })
            ->count();

        $hpgMetrics = $this->hpgResponseService->getComplianceMetrics($startDate, $endDate);

        return [
            'total_referrals' => $totalReferrals,
            'with_interrai_from_hpg' => $withInterrai,
            'needing_interrai_completion' => $needingInterrai,
            'interrai_completion_rate' => $totalReferrals > 0
                ? round((($totalReferrals - $needingInterrai) / $totalReferrals) * 100, 1)
                : 100,
            'hpg_response' => [
                'total' => $hpgMetrics['total'],
                'compliant' => $hpgMetrics['compliant'],
                'breached' => $hpgMetrics['breached'],
                'compliance_rate' => $hpgMetrics['compliance_rate'],
                'average_response_minutes' => $hpgMetrics['average_response_minutes'],
            ],
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
            ],
        ];
    }

    /**
     * Find or create patient from HPG payload.
     */
    protected function findOrCreatePatient(array $hpgPayload): Patient
    {
        $patientData = $hpgPayload['patient'] ?? $hpgPayload;

        // Try to find existing patient by health card number or other identifier
        $patient = null;

        if (!empty($patientData['health_card_number'])) {
            $patient = Patient::where('health_card_number', $patientData['health_card_number'])->first();
        }

        if (!$patient && !empty($patientData['email'])) {
            $patient = Patient::whereHas('user', fn($q) => $q->where('email', $patientData['email']))->first();
        }

        if ($patient) {
            // Update existing patient with any new data
            $patient->update([
                'is_in_queue' => true,
            ]);
            return $patient;
        }

        // Create new patient (simplified - real implementation would create user too)
        return Patient::create([
            'user_id' => $patientData['user_id'] ?? null, // Would need to create user
            'health_card_number' => $patientData['health_card_number'] ?? null,
            'date_of_birth' => $patientData['date_of_birth'] ?? null,
            'is_in_queue' => true,
            'triage_summary' => $patientData,
        ]);
    }

    /**
     * Create triage result from HPG payload.
     */
    protected function createTriageResult(Patient $patient, array $hpgPayload, Carbon $receivedAt): TriageResult
    {
        $triageData = $hpgPayload['triage'] ?? $hpgPayload;

        return TriageResult::create([
            'patient_id' => $patient->id,
            'received_at' => $receivedAt,
            'hpg_received_at' => $receivedAt,
            'acuity_level' => $triageData['acuity_level'] ?? $triageData['priority'] ?? 'medium',
            'dementia_flag' => (bool) ($triageData['dementia_flag'] ?? $triageData['dementia'] ?? false),
            'mh_flag' => (bool) ($triageData['mh_flag'] ?? $triageData['mental_health'] ?? false),
            'rpm_required' => (bool) ($triageData['rpm_required'] ?? $triageData['rpm'] ?? false),
            'fall_risk' => (bool) ($triageData['fall_risk'] ?? $triageData['falls'] ?? false),
            'behavioural_risk' => (bool) ($triageData['behavioural_risk'] ?? $triageData['behaviour'] ?? false),
            'crisis_designation' => (bool) ($triageData['crisis_designation'] ?? $triageData['crisis'] ?? false),
            'raw_referral_payload' => $hpgPayload,
            'notes' => $triageData['notes'] ?? null,
        ]);
    }

    /**
     * Detect significant clinical changes that warrant reassessment.
     */
    protected function detectClinicalChange(Patient $patient, InterraiAssessment $newAssessment, array $hpgPayload): bool
    {
        // Get previous assessment
        $previousAssessment = $patient->interraiAssessments()
            ->where('id', '!=', $newAssessment->id)
            ->orderBy('assessment_date', 'desc')
            ->first();

        if (!$previousAssessment) {
            return false;
        }

        // Check for significant score changes
        $significantChanges = [];

        // MAPLe score change
        if ($newAssessment->maple_score && $previousAssessment->maple_score) {
            if ($newAssessment->maple_score !== $previousAssessment->maple_score) {
                $significantChanges[] = 'maple_score';
            }
        }

        // CHESS score increase (health instability)
        if ($newAssessment->chess_score !== null && $previousAssessment->chess_score !== null) {
            if ($newAssessment->chess_score > $previousAssessment->chess_score + 1) {
                $significantChanges[] = 'chess_increase';
            }
        }

        // ADL decline
        if ($newAssessment->adl_hierarchy !== null && $previousAssessment->adl_hierarchy !== null) {
            if ($newAssessment->adl_hierarchy > $previousAssessment->adl_hierarchy + 1) {
                $significantChanges[] = 'adl_decline';
            }
        }

        // CPS decline
        if ($newAssessment->cognitive_performance_scale !== null && $previousAssessment->cognitive_performance_scale !== null) {
            if ($newAssessment->cognitive_performance_scale > $previousAssessment->cognitive_performance_scale + 1) {
                $significantChanges[] = 'cps_decline';
            }
        }

        // Check HPG payload for clinical change flags
        if (!empty($hpgPayload['clinical_change']) || !empty($hpgPayload['significant_change'])) {
            $significantChanges[] = 'hpg_flagged';
        }

        return count($significantChanges) > 0;
    }

    /**
     * Generate compliance flags for the referral.
     */
    protected function generateComplianceFlags(Patient $patient, TriageResult $triageResult, array $interraiStatus): array
    {
        return [
            'interrai_missing' => $interraiStatus['status'] === 'missing',
            'interrai_stale' => in_array($interraiStatus['status'], ['stale', 'stale_from_hpg']),
            'interrai_requires_completion' => $interraiStatus['requires_completion'],
            'crisis_patient' => $triageResult->crisis_designation,
            'high_acuity' => in_array($triageResult->acuity_level, ['high', 'critical']),
            'dementia_care' => $triageResult->dementia_flag,
            'mental_health' => $triageResult->mh_flag,
            'rpm_required' => $triageResult->rpm_required,
            'fall_risk' => $triageResult->fall_risk,
            'behavioural_risk' => $triageResult->behavioural_risk,
        ];
    }
}
