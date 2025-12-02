<?php

namespace App\Services\Scheduling;

use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceRoleMapping;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Scheduling\CareBundleAssignmentPlanner;
use App\Services\Scheduling\ContinuityService;
use App\Services\Scheduling\DTOs\AssignmentSuggestionDTO;
use App\Services\Scheduling\SchedulingEngine;
use App\Services\Scheduling\StaffScoringService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AutoAssignEngine
 *
 * Main orchestrator for AI-assisted scheduling.
 *
 * Responsibilities:
 * - Get unscheduled care requirements
 * - Find eligible staff for each requirement
 * - Score staff using StaffScoringService
 * - Generate AssignmentSuggestionDTOs with best matches
 * - Accept suggestions and create actual ServiceAssignments
 *
 * Usage:
 *   $engine = app(AutoAssignEngine::class);
 *   $suggestions = $engine->generateSuggestions($orgId, $weekStart, $weekEnd);
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class AutoAssignEngine
{
    public function __construct(
        private CareBundleAssignmentPlanner $planner,
        private SchedulingEngine $schedulingEngine,
        private StaffScoringService $scoringService,
        private ContinuityService $continuityService
    ) {}

    /**
     * Generate assignment suggestions for all unscheduled care.
     *
     * @param int $organizationId SPO organization ID
     * @param Carbon $weekStart Week start date
     * @param Carbon $weekEnd Week end date
     * @return Collection<AssignmentSuggestionDTO> Suggestions for each patient-service combination
     */
    public function generateSuggestions(
        int $organizationId,
        Carbon $weekStart,
        Carbon $weekEnd
    ): Collection {
        // 1. Get all unscheduled requirements
        $requirements = $this->planner->getUnscheduledRequirements(
            $organizationId,
            $weekStart,
            $weekEnd
        );

        if ($requirements->isEmpty()) {
            return collect();
        }

        $suggestions = collect();

        // 2. Process each patient's requirements
        foreach ($requirements as $requirement) {
            $patientSuggestions = $this->processPatient($requirement, $organizationId, $weekStart, $weekEnd);
            $suggestions = $suggestions->merge($patientSuggestions);
        }

        // 3. Sort by match quality (strong matches first)
        return $suggestions->sortByDesc(function ($s) {
            return match ($s->matchStatus) {
                'strong' => 4,
                'moderate' => 3,
                'weak' => 2,
                default => 1,
            } * 100 + $s->confidenceScore;
        })->values();
    }

    /**
     * Process a single patient's unscheduled requirements.
     */
    private function processPatient(
        $requirement,
        int $organizationId,
        Carbon $weekStart,
        Carbon $weekEnd
    ): Collection {
        $patientId = $requirement->patientId ?? $requirement->patientId ?? null;
        if (!$patientId) {
            return collect();
        }

        $patient = Patient::with(['user'])->find($patientId);
        if (!$patient) {
            return collect();
        }

        $suggestions = collect();
        $services = $requirement->services ?? $requirement->services ?? [];

        foreach ($services as $service) {
            $suggestion = $this->processService($patient, $service, $organizationId, $weekStart, $weekEnd);
            if ($suggestion) {
                $suggestions->push($suggestion);
            }
        }

        return $suggestions;
    }

    /**
     * Process a single service requirement and find best staff match.
     */
    private function processService(
        Patient $patient,
        $service,
        int $organizationId,
        Carbon $weekStart,
        Carbon $weekEnd
    ): ?AssignmentSuggestionDTO {
        $serviceTypeId = $service->serviceTypeId ?? $service->serviceTypeId ?? null;
        if (!$serviceTypeId) {
            return null;
        }

        $serviceType = ServiceType::find($serviceTypeId);
        if (!$serviceType) {
            return null;
        }

        $remaining = $service->getRemaining() ?? 0;
        if ($remaining <= 0) {
            return null; // Already fully scheduled
        }

        $duration = $serviceType->default_duration_minutes ?? 60;

        // Find eligible staff
        $eligibleStaff = $this->findEligibleStaff($serviceType, $organizationId, $weekStart, $weekEnd);
        $candidatesEvaluated = $eligibleStaff->count();

        if ($eligibleStaff->isEmpty()) {
            return $this->createNoMatchSuggestion(
                $patient,
                $serviceType,
                $organizationId,
                $duration,
                $weekStart,
                ['No staff with eligible role found'],
                $candidatesEvaluated
            );
        }

        // Apply hard constraints and filter
        $constraintResults = $this->applyHardConstraints(
            $eligibleStaff,
            $patient,
            $serviceType,
            $weekStart,
            $weekEnd,
            $duration
        );

        $passedStaff = $constraintResults['passed'];
        $exclusionReasons = $constraintResults['exclusion_reasons'];
        $candidatesPassed = $passedStaff->count();

        if ($passedStaff->isEmpty()) {
            return $this->createNoMatchSuggestion(
                $patient,
                $serviceType,
                $organizationId,
                $duration,
                $weekStart,
                $exclusionReasons,
                $candidatesEvaluated
            );
        }

        // Score remaining staff
        $targetTime = $this->determineTargetTime($weekStart);
        $scores = $this->scoringService->scoreMultipleStaff(
            $passedStaff,
            $patient,
            $serviceType,
            $targetTime,
            $duration,
            $weekStart,
            $weekEnd
        );

        // Get best match
        $bestScore = $scores->first();
        if (!$bestScore || $bestScore['match_status'] === 'none') {
            return $this->createNoMatchSuggestion(
                $patient,
                $serviceType,
                $organizationId,
                $duration,
                $weekStart,
                ['No staff met minimum scoring threshold'],
                $candidatesEvaluated
            );
        }

        // Build full suggestion DTO
        $bestStaff = User::with(['staffRole', 'employmentTypeModel'])->find($bestScore['staff_id']);

        return $this->buildSuggestionDTO(
            $patient,
            $serviceType,
            $bestStaff,
            $bestScore,
            $organizationId,
            $duration,
            $weekStart,
            $candidatesEvaluated,
            $candidatesPassed,
            $exclusionReasons
        );
    }

    /**
     * Find staff eligible to provide a service type.
     */
    private function findEligibleStaff(
        ServiceType $serviceType,
        int $organizationId,
        Carbon $weekStart,
        Carbon $weekEnd
    ): Collection {
        // Get role IDs eligible for this service type
        $eligibleRoleIds = ServiceRoleMapping::active()
            ->where('service_type_id', $serviceType->id)
            ->pluck('staff_role_id')
            ->toArray();

        if (empty($eligibleRoleIds)) {
            return collect();
        }

        return User::query()
            ->whereIn('role', [User::ROLE_FIELD_STAFF, User::ROLE_SPO_COORDINATOR])
            ->where('staff_status', User::STAFF_STATUS_ACTIVE)
            ->where('is_scheduling_locked', false)
            ->whereIn('staff_role_id', $eligibleRoleIds)
            ->where('organization_id', $organizationId)
            ->with(['staffRole', 'employmentTypeModel', 'availabilities'])
            ->get();
    }

    /**
     * Apply hard constraints to filter eligible staff.
     */
    private function applyHardConstraints(
        Collection $staff,
        Patient $patient,
        ServiceType $serviceType,
        Carbon $weekStart,
        Carbon $weekEnd,
        int $durationMinutes
    ): array {
        $passed = collect();
        $exclusionReasons = [];
        $exclusionCounts = [
            'unavailable' => 0,
            'capacity' => 0,
            'skills' => 0,
            'conflict' => 0,
        ];

        foreach ($staff as $user) {
            // Check if staff has required skills
            if (!$serviceType->userHasRequiredSkills($user)) {
                $exclusionCounts['skills']++;
                continue;
            }

            // Check capacity
            $scheduledHours = $this->schedulingEngine->getScheduledHoursForWeek($user->id, $weekStart, $weekEnd);
            $maxHours = $user->max_weekly_hours ?? 40;
            $remainingMinutes = ($maxHours * 60) - ($scheduledHours * 60);

            if ($remainingMinutes < $durationMinutes) {
                $exclusionCounts['capacity']++;
                continue;
            }

            // Check if staff is on leave
            if ($user->isOnLeave()) {
                $exclusionCounts['unavailable']++;
                continue;
            }

            $passed->push($user);
        }

        // Build human-readable exclusion reasons
        if ($exclusionCounts['unavailable'] > 0) {
            $exclusionReasons[] = "{$exclusionCounts['unavailable']} staff unavailable (time-off)";
        }
        if ($exclusionCounts['capacity'] > 0) {
            $exclusionReasons[] = "{$exclusionCounts['capacity']} staff over capacity threshold";
        }
        if ($exclusionCounts['skills'] > 0) {
            $exclusionReasons[] = "{$exclusionCounts['skills']} staff missing required skills";
        }

        return [
            'passed' => $passed,
            'exclusion_reasons' => $exclusionReasons,
        ];
    }

    /**
     * Determine target time for scoring (default to Monday 9 AM of the week).
     */
    private function determineTargetTime(Carbon $weekStart): Carbon
    {
        return $weekStart->copy()->setTime(9, 0);
    }

    /**
     * Create a "no match" suggestion DTO.
     */
    private function createNoMatchSuggestion(
        Patient $patient,
        ServiceType $serviceType,
        int $organizationId,
        int $durationMinutes,
        Carbon $weekStart,
        array $exclusionReasons,
        int $candidatesEvaluated
    ): AssignmentSuggestionDTO {
        return AssignmentSuggestionDTO::noMatch(
            patientId: $patient->id,
            serviceTypeId: $serviceType->id,
            serviceTypeCode: $serviceType->code ?? $serviceType->name,
            serviceTypeName: $serviceType->name,
            durationMinutes: $durationMinutes,
            organizationId: $organizationId,
            exclusionReasons: $exclusionReasons,
            candidatesEvaluated: $candidatesEvaluated,
            weekStart: $weekStart
        );
    }

    /**
     * Build a full suggestion DTO with staff match.
     */
    private function buildSuggestionDTO(
        Patient $patient,
        ServiceType $serviceType,
        User $staff,
        array $score,
        int $organizationId,
        int $durationMinutes,
        Carbon $weekStart,
        int $candidatesEvaluated,
        int $candidatesPassed,
        array $exclusionReasons
    ): AssignmentSuggestionDTO {
        return new AssignmentSuggestionDTO(
            patientId: $patient->id,
            serviceTypeId: $serviceType->id,
            suggestedStaffId: $staff->id,
            organizationId: $organizationId,

            // Service context
            serviceTypeCode: $serviceType->code ?? $serviceType->name,
            serviceTypeName: $serviceType->name,
            durationMinutes: $durationMinutes,
            preferredProvider: $serviceType->preferred_provider,
            deliveryMode: $serviceType->delivery_mode,

            // Patient context (de-identified)
            patientRegionCode: $patient->region?->code,
            patientRegionName: $patient->region?->name,
            patientAcuityLevel: $patient->triage_summary['acuity_level'] ?? 'medium',
            patientMapleScore: $patient->maple_score,
            patientRiskFlags: array_keys(array_filter($patient->risk_flags ?? [])),
            daysSinceActivation: $patient->activated_at ? $patient->activated_at->diffInDays(now()) : null,
            previousStaffCount: $this->continuityService->getUniqueStaffCount($patient->id, $organizationId),

            // Staff context (de-identified)
            staffRoleCode: $staff->staffRole?->code,
            staffRoleName: $staff->staffRole?->name,
            staffEmploymentTypeCode: $staff->employmentTypeModel?->code,
            staffEmploymentTypeName: $staff->employmentTypeModel?->name,
            staffRegionCode: null,
            staffRegionName: null,
            staffRemainingHours: $score['remaining_hours'] ?? null,
            staffUtilizationPercent: $score['utilization_percent'] ?? null,
            staffTenureMonths: $staff->hire_date ? $staff->hire_date->diffInMonths(now()) : null,
            staffHasRequiredSkills: true, // Passed constraint check
            staffReliabilityScore: null, // TODO: Integrate reliability metrics

            // Scoring
            confidenceScore: $score['total_score'],
            matchStatus: $score['match_status'],
            scoringBreakdown: $score['breakdown'],
            isPrimaryRole: ($score['breakdown']['role_fit']['score'] ?? 0) >= 10,

            // Travel & Continuity
            estimatedTravelMinutes: $score['travel_minutes'],
            continuityVisitCount: $score['continuity_visits'],

            // Alternatives
            candidatesEvaluated: $candidatesEvaluated,
            candidatesPassed: $candidatesPassed,
            exclusionReasons: $exclusionReasons,

            // Context
            weekStart: $weekStart,
            organizationType: 'spo',
        );
    }

    /**
     * Accept a suggestion and create an actual ServiceAssignment.
     *
     * @param int $patientId
     * @param int $serviceTypeId
     * @param int $staffId
     * @param Carbon $scheduledStart
     * @param Carbon $scheduledEnd
     * @param int $acceptedBy User ID who accepted
     * @param int $organizationId
     * @return array{success: bool, assignment_id?: int, errors?: array}
     */
    public function acceptSuggestion(
        int $patientId,
        int $serviceTypeId,
        int $staffId,
        Carbon $scheduledStart,
        Carbon $scheduledEnd,
        int $acceptedBy,
        int $organizationId
    ): array {
        // Validate the assignment before creating
        $tempAssignment = new ServiceAssignment([
            'patient_id' => $patientId,
            'service_type_id' => $serviceTypeId,
            'assigned_user_id' => $staffId,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'service_provider_organization_id' => $organizationId,
        ]);

        $validation = $this->schedulingEngine->validateAssignment($tempAssignment);

        if (!$validation->isValid()) {
            return [
                'success' => false,
                'errors' => $validation->getErrors(),
            ];
        }

        try {
            $assignment = ServiceAssignment::create([
                'patient_id' => $patientId,
                'service_type_id' => $serviceTypeId,
                'assigned_user_id' => $staffId,
                'scheduled_start' => $scheduledStart,
                'scheduled_end' => $scheduledEnd,
                'service_provider_organization_id' => $organizationId,
                'status' => ServiceAssignment::STATUS_PLANNED,
                'source' => 'auto_assign',
                'created_by' => $acceptedBy,
                'notes' => 'Created via AI-assisted auto-assign',
            ]);

            Log::info('Auto-assign suggestion accepted', [
                'assignment_id' => $assignment->id,
                'patient_id' => $patientId,
                'staff_id' => $staffId,
                'service_type_id' => $serviceTypeId,
                'accepted_by' => $acceptedBy,
            ]);

            return [
                'success' => true,
                'assignment_id' => $assignment->id,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create assignment from auto-assign', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'staff_id' => $staffId,
            ]);

            return [
                'success' => false,
                'errors' => ['Failed to create assignment: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Accept multiple suggestions in a batch.
     *
     * @param array $suggestions Array of suggestion data
     * @param int $acceptedBy User ID
     * @param int $organizationId
     * @return array{successful: array, failed: array}
     */
    public function acceptBatch(array $suggestions, int $acceptedBy, int $organizationId): array
    {
        $successful = [];
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($suggestions as $suggestion) {
                $result = $this->acceptSuggestion(
                    patientId: $suggestion['patient_id'],
                    serviceTypeId: $suggestion['service_type_id'],
                    staffId: $suggestion['staff_id'],
                    scheduledStart: Carbon::parse($suggestion['scheduled_start']),
                    scheduledEnd: Carbon::parse($suggestion['scheduled_end']),
                    acceptedBy: $acceptedBy,
                    organizationId: $organizationId
                );

                if ($result['success']) {
                    $successful[] = [
                        'patient_id' => $suggestion['patient_id'],
                        'service_type_id' => $suggestion['service_type_id'],
                        'assignment_id' => $result['assignment_id'],
                    ];
                } else {
                    $failed[] = [
                        'patient_id' => $suggestion['patient_id'],
                        'service_type_id' => $suggestion['service_type_id'],
                        'errors' => $result['errors'],
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Batch accept failed', ['error' => $e->getMessage()]);

            // Mark all as failed
            return [
                'successful' => [],
                'failed' => array_map(fn($s) => [
                    'patient_id' => $s['patient_id'],
                    'service_type_id' => $s['service_type_id'],
                    'errors' => ['Batch transaction failed'],
                ], $suggestions),
            ];
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Get a specific suggestion for a patient-service combination.
     *
     * Used by the /explain endpoint to reconstruct suggestion for LLM.
     */
    public function getSuggestionForService(
        int $patientId,
        int $serviceTypeId,
        int $staffId,
        Carbon $weekStart,
        int $organizationId
    ): ?AssignmentSuggestionDTO {
        $weekEnd = $weekStart->copy()->endOfWeek();

        $patient = Patient::with(['user'])->find($patientId);
        $serviceType = ServiceType::find($serviceTypeId);
        $staff = User::with(['staffRole', 'employmentTypeModel'])->find($staffId);

        if (!$patient || !$serviceType || !$staff) {
            return null;
        }

        $duration = $serviceType->default_duration_minutes ?? 60;
        $targetTime = $this->determineTargetTime($weekStart);

        // Calculate score for this specific combination
        $score = $this->scoringService->calculateScore(
            $staff,
            $patient,
            $serviceType,
            $targetTime,
            $duration,
            $weekStart,
            $weekEnd
        );

        return $this->buildSuggestionDTO(
            $patient,
            $serviceType,
            $staff,
            $score,
            $organizationId,
            $duration,
            $weekStart,
            1, // Already filtered to this staff
            1,
            []
        );
    }
}
