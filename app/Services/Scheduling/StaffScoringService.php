<?php

namespace App\Services\Scheduling;

use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceRoleMapping;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Scheduling\ContinuityService;
use App\Services\Travel\TravelTimeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * StaffScoringService
 *
 * Calculates weighted match scores for staff-to-service assignments.
 *
 * Scoring Algorithm (0-100 scale):
 * - Capacity Fit:      25% - Available hours vs required
 * - Continuity:        20% - Previous visits with patient
 * - Travel Efficiency: 20% - Estimated travel time
 * - Region Match:      10% - Same geographic area
 * - Role Fit:          10% - Primary vs secondary role
 * - Workload Balance:  10% - Current utilization
 * - Urgency Fit:        5% - High-acuity patient + reliable staff
 *
 * Match Status:
 * - "strong": score >= 80
 * - "moderate": score >= 60
 * - "weak": score >= 40
 * - "none": score < 40 or hard constraint failed
 */
class StaffScoringService
{
    // Scoring weights (must sum to 100)
    private const WEIGHT_CAPACITY = 25;
    private const WEIGHT_CONTINUITY = 20;
    private const WEIGHT_TRAVEL = 20;
    private const WEIGHT_REGION = 10;
    private const WEIGHT_ROLE = 10;
    private const WEIGHT_WORKLOAD = 10;
    private const WEIGHT_URGENCY = 5;

    // Thresholds
    private const STRONG_MATCH_THRESHOLD = 80;
    private const MODERATE_MATCH_THRESHOLD = 60;
    private const WEAK_MATCH_THRESHOLD = 40;

    // Travel time thresholds (minutes)
    private const TRAVEL_EXCELLENT = 15;
    private const TRAVEL_GOOD = 25;
    private const TRAVEL_ACCEPTABLE = 40;

    // Capacity thresholds
    private const CAPACITY_WARNING_PERCENT = 80;

    public function __construct(
        private ContinuityService $continuityService,
        private TravelTimeService $travelTimeService,
        private SchedulingEngine $schedulingEngine
    ) {}

    /**
     * Calculate score for a single staff-patient-service combination.
     *
     * @param User $staff The staff member to score
     * @param Patient $patient The patient needing care
     * @param ServiceType $serviceType The service type required
     * @param Carbon $targetTime The target appointment time
     * @param int $durationMinutes Service duration
     * @param Carbon $weekStart Week start for capacity calculations
     * @param Carbon $weekEnd Week end for capacity calculations
     * @return array Score details including total, breakdown, and match status
     */
    public function calculateScore(
        User $staff,
        Patient $patient,
        ServiceType $serviceType,
        Carbon $targetTime,
        int $durationMinutes,
        Carbon $weekStart,
        Carbon $weekEnd
    ): array {
        $breakdown = [];
        $notes = [];

        // === 1. Capacity Fit (25%) ===
        $capacityScore = $this->scoreCapacity($staff, $durationMinutes, $weekStart, $weekEnd);
        $breakdown['capacity_fit'] = [
            'score' => $capacityScore['score'],
            'max' => self::WEIGHT_CAPACITY,
            'note' => $capacityScore['note'],
        ];

        // === 2. Continuity (20%) ===
        $continuityScore = $this->scoreContinuity($staff->id, $patient->id);
        $breakdown['continuity'] = [
            'score' => $continuityScore['score'],
            'max' => self::WEIGHT_CONTINUITY,
            'note' => $continuityScore['note'],
        ];

        // === 3. Travel Efficiency (20%) ===
        $travelScore = $this->scoreTravel($staff, $patient, $targetTime);
        $breakdown['travel_efficiency'] = [
            'score' => $travelScore['score'],
            'max' => self::WEIGHT_TRAVEL,
            'note' => $travelScore['note'],
        ];

        // === 4. Region Match (10%) ===
        $regionScore = $this->scoreRegion($staff, $patient);
        $breakdown['region_match'] = [
            'score' => $regionScore['score'],
            'max' => self::WEIGHT_REGION,
            'note' => $regionScore['note'],
        ];

        // === 5. Role Fit (10%) ===
        $roleScore = $this->scoreRoleFit($staff, $serviceType);
        $breakdown['role_fit'] = [
            'score' => $roleScore['score'],
            'max' => self::WEIGHT_ROLE,
            'note' => $roleScore['note'],
        ];

        // === 6. Workload Balance (10%) ===
        $workloadScore = $this->scoreWorkloadBalance($staff, $weekStart, $weekEnd);
        $breakdown['workload_balance'] = [
            'score' => $workloadScore['score'],
            'max' => self::WEIGHT_WORKLOAD,
            'note' => $workloadScore['note'],
        ];

        // === 7. Urgency Fit (5%) ===
        $urgencyScore = $this->scoreUrgencyFit($staff, $patient);
        $breakdown['urgency_fit'] = [
            'score' => $urgencyScore['score'],
            'max' => self::WEIGHT_URGENCY,
            'note' => $urgencyScore['note'],
        ];

        // Calculate total
        $totalScore = array_sum(array_column($breakdown, 'score'));
        $matchStatus = $this->determineMatchStatus($totalScore);

        return [
            'total_score' => round($totalScore, 1),
            'match_status' => $matchStatus,
            'breakdown' => $breakdown,
            'staff_id' => $staff->id,
            'patient_id' => $patient->id,
            'service_type_id' => $serviceType->id,
            'travel_minutes' => $travelScore['travel_minutes'] ?? null,
            'continuity_visits' => $continuityScore['visit_count'] ?? 0,
            'remaining_hours' => $capacityScore['remaining_hours'] ?? null,
            'utilization_percent' => $workloadScore['utilization_percent'] ?? null,
        ];
    }

    /**
     * Score capacity fit (0-25).
     */
    private function scoreCapacity(
        User $staff,
        int $durationMinutes,
        Carbon $weekStart,
        Carbon $weekEnd
    ): array {
        $maxWeeklyHours = $staff->max_weekly_hours ?? 40;
        $scheduledHours = $this->schedulingEngine->getScheduledHoursForWeek(
            $staff->id,
            $weekStart,
            $weekEnd
        );
        $remainingHours = $maxWeeklyHours - $scheduledHours;
        $requiredHours = $durationMinutes / 60;

        // Check if staff has capacity
        if ($remainingHours < $requiredHours) {
            return [
                'score' => 0,
                'note' => 'Insufficient capacity',
                'remaining_hours' => $remainingHours,
            ];
        }

        // Score based on how much buffer remains after assignment
        $bufferHours = $remainingHours - $requiredHours;
        $bufferPercent = ($bufferHours / $maxWeeklyHours) * 100;

        if ($bufferPercent >= 30) {
            $score = self::WEIGHT_CAPACITY; // Full score
        } elseif ($bufferPercent >= 20) {
            $score = self::WEIGHT_CAPACITY * 0.9;
        } elseif ($bufferPercent >= 10) {
            $score = self::WEIGHT_CAPACITY * 0.7;
        } else {
            $score = self::WEIGHT_CAPACITY * 0.5;
        }

        return [
            'score' => round($score, 1),
            'note' => round($remainingHours, 1) . 'h remaining',
            'remaining_hours' => round($remainingHours, 1),
        ];
    }

    /**
     * Score continuity of care (0-20).
     */
    private function scoreContinuity(int $staffId, int $patientId): array
    {
        $visitCount = $this->continuityService->getVisitCount($staffId, $patientId);

        if ($visitCount === 0) {
            return [
                'score' => 0,
                'note' => 'New relationship',
                'visit_count' => 0,
            ];
        }

        // Score: 4 points per visit, capped at 20
        $score = min(self::WEIGHT_CONTINUITY, $visitCount * 4);

        $note = $visitCount === 1
            ? '1 previous visit'
            : "{$visitCount} previous visits";

        return [
            'score' => $score,
            'note' => $note,
            'visit_count' => $visitCount,
        ];
    }

    /**
     * Score travel efficiency (0-20).
     */
    private function scoreTravel(User $staff, Patient $patient, Carbon $targetTime): array
    {
        // Check if patient has coordinates
        if (!$patient->hasCoordinates()) {
            return [
                'score' => self::WEIGHT_TRAVEL * 0.5, // Default to middle score
                'note' => 'No patient location data',
                'travel_minutes' => null,
            ];
        }

        // Get staff's previous assignment location (or home base)
        $previousLocation = $this->getStaffPreviousLocation($staff->id, $targetTime);

        if (!$previousLocation) {
            // Assume starting from organization base - give partial credit
            return [
                'score' => self::WEIGHT_TRAVEL * 0.7,
                'note' => 'No prior appointment',
                'travel_minutes' => null,
            ];
        }

        // Calculate travel time
        $travelMinutes = $this->travelTimeService->getTravelMinutes(
            $previousLocation['lat'],
            $previousLocation['lng'],
            $patient->lat,
            $patient->lng,
            $targetTime
        );

        // Score based on travel time thresholds
        if ($travelMinutes <= self::TRAVEL_EXCELLENT) {
            $score = self::WEIGHT_TRAVEL;
        } elseif ($travelMinutes <= self::TRAVEL_GOOD) {
            $score = self::WEIGHT_TRAVEL * 0.8;
        } elseif ($travelMinutes <= self::TRAVEL_ACCEPTABLE) {
            $score = self::WEIGHT_TRAVEL * 0.5;
        } else {
            $score = max(0, self::WEIGHT_TRAVEL * (1 - ($travelMinutes - self::TRAVEL_ACCEPTABLE) / 60));
        }

        return [
            'score' => round($score, 1),
            'note' => "{$travelMinutes} min travel",
            'travel_minutes' => $travelMinutes,
        ];
    }

    /**
     * Score region match (0-10).
     */
    private function scoreRegion(User $staff, Patient $patient): array
    {
        $staffRegion = $staff->region_id;
        $patientRegion = $patient->region_id;

        if (!$staffRegion || !$patientRegion) {
            return [
                'score' => self::WEIGHT_REGION * 0.5,
                'note' => 'Region data incomplete',
            ];
        }

        if ($staffRegion === $patientRegion) {
            return [
                'score' => self::WEIGHT_REGION,
                'note' => 'Same region',
            ];
        }

        return [
            'score' => 0,
            'note' => 'Different region',
        ];
    }

    /**
     * Score role fit (0-10).
     */
    private function scoreRoleFit(User $staff, ServiceType $serviceType): array
    {
        if (!$staff->staff_role_id) {
            return [
                'score' => 0,
                'note' => 'No role assigned',
            ];
        }

        // Check if primary role for this service
        $isPrimary = ServiceRoleMapping::active()
            ->where('staff_role_id', $staff->staff_role_id)
            ->where('service_type_id', $serviceType->id)
            ->where('is_primary', true)
            ->exists();

        if ($isPrimary) {
            return [
                'score' => self::WEIGHT_ROLE,
                'note' => 'Primary role',
            ];
        }

        // Check if eligible at all
        $isEligible = ServiceRoleMapping::active()
            ->where('staff_role_id', $staff->staff_role_id)
            ->where('service_type_id', $serviceType->id)
            ->exists();

        if ($isEligible) {
            return [
                'score' => self::WEIGHT_ROLE * 0.6,
                'note' => 'Secondary role',
            ];
        }

        return [
            'score' => 0,
            'note' => 'Role not eligible',
        ];
    }

    /**
     * Score workload balance (0-10).
     */
    private function scoreWorkloadBalance(User $staff, Carbon $weekStart, Carbon $weekEnd): array
    {
        $maxWeeklyHours = $staff->max_weekly_hours ?? 40;
        $scheduledHours = $this->schedulingEngine->getScheduledHoursForWeek(
            $staff->id,
            $weekStart,
            $weekEnd
        );
        $utilizationPercent = ($scheduledHours / $maxWeeklyHours) * 100;

        // Prefer staff at 50-70% utilization (not overworked, not underutilized)
        if ($utilizationPercent >= 50 && $utilizationPercent <= 70) {
            $score = self::WEIGHT_WORKLOAD;
        } elseif ($utilizationPercent < 50) {
            // Underutilized - slight penalty
            $score = self::WEIGHT_WORKLOAD * 0.8;
        } elseif ($utilizationPercent <= 80) {
            $score = self::WEIGHT_WORKLOAD * 0.7;
        } else {
            // Over 80% - higher risk
            $score = self::WEIGHT_WORKLOAD * 0.4;
        }

        return [
            'score' => round($score, 1),
            'note' => round($utilizationPercent) . '% utilized',
            'utilization_percent' => round($utilizationPercent, 1),
        ];
    }

    /**
     * Score urgency fit (0-5).
     *
     * High-acuity patients should be matched with reliable staff.
     */
    private function scoreUrgencyFit(User $staff, Patient $patient): array
    {
        $mapleScore = $patient->maple_score ?? 0;
        $acuityLevel = $patient->triage_summary['acuity_level'] ?? 'medium';

        // High acuity indicators
        $isHighAcuity = $mapleScore >= 4 || $acuityLevel === 'high';

        if (!$isHighAcuity) {
            // For standard patients, give full score
            return [
                'score' => self::WEIGHT_URGENCY,
                'note' => 'Standard acuity',
            ];
        }

        // For high-acuity, check staff reliability
        // TODO: Integrate with actual reliability metrics when available
        $staffReliability = $this->estimateStaffReliability($staff->id);

        if ($staffReliability >= 0.95) {
            return [
                'score' => self::WEIGHT_URGENCY,
                'note' => 'High acuity + reliable staff',
            ];
        } elseif ($staffReliability >= 0.85) {
            return [
                'score' => self::WEIGHT_URGENCY * 0.6,
                'note' => 'High acuity patient',
            ];
        }

        return [
            'score' => self::WEIGHT_URGENCY * 0.3,
            'note' => 'Consider more reliable staff',
        ];
    }

    /**
     * Estimate staff reliability based on completion rate.
     */
    private function estimateStaffReliability(int $staffId): float
    {
        $windowStart = Carbon::now()->subMonths(3);

        $stats = ServiceAssignment::query()
            ->where('assigned_user_id', $staffId)
            ->where('scheduled_start', '>=', $windowStart)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed
            ', [ServiceAssignment::STATUS_COMPLETED])
            ->first();

        if (!$stats || $stats->total < 5) {
            // Not enough data - assume average
            return 0.90;
        }

        return $stats->completed / $stats->total;
    }

    /**
     * Get staff's previous location on the target day.
     */
    private function getStaffPreviousLocation(int $staffId, Carbon $targetTime): ?array
    {
        $dayStart = $targetTime->copy()->startOfDay();

        $previousAssignment = ServiceAssignment::query()
            ->where('assigned_user_id', $staffId)
            ->whereBetween('scheduled_end', [$dayStart, $targetTime])
            ->whereNotIn('status', [ServiceAssignment::STATUS_CANCELLED])
            ->with('patient')
            ->orderByDesc('scheduled_end')
            ->first();

        if (!$previousAssignment || !$previousAssignment->patient?->hasCoordinates()) {
            return null;
        }

        return [
            'lat' => $previousAssignment->patient->lat,
            'lng' => $previousAssignment->patient->lng,
        ];
    }

    /**
     * Determine match status from total score.
     */
    private function determineMatchStatus(float $score): string
    {
        if ($score >= self::STRONG_MATCH_THRESHOLD) {
            return 'strong';
        }
        if ($score >= self::MODERATE_MATCH_THRESHOLD) {
            return 'moderate';
        }
        if ($score >= self::WEAK_MATCH_THRESHOLD) {
            return 'weak';
        }
        return 'none';
    }

    /**
     * Score multiple staff for a single patient-service (batch operation).
     *
     * @param Collection $eligibleStaff Collection of User models
     * @param Patient $patient
     * @param ServiceType $serviceType
     * @param Carbon $targetTime
     * @param int $durationMinutes
     * @param Carbon $weekStart
     * @param Carbon $weekEnd
     * @return Collection Scores sorted by total_score descending
     */
    public function scoreMultipleStaff(
        Collection $eligibleStaff,
        Patient $patient,
        ServiceType $serviceType,
        Carbon $targetTime,
        int $durationMinutes,
        Carbon $weekStart,
        Carbon $weekEnd
    ): Collection {
        // Pre-fetch continuity data for efficiency
        $staffIds = $eligibleStaff->pluck('id')->toArray();
        $continuityData = $this->continuityService->getBatchVisitCounts($patient->id, $staffIds);

        $scores = $eligibleStaff->map(function ($staff) use (
            $patient,
            $serviceType,
            $targetTime,
            $durationMinutes,
            $weekStart,
            $weekEnd
        ) {
            return $this->calculateScore(
                $staff,
                $patient,
                $serviceType,
                $targetTime,
                $durationMinutes,
                $weekStart,
                $weekEnd
            );
        });

        return $scores->sortByDesc('total_score')->values();
    }
}
