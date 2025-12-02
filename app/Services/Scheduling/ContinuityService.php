<?php

namespace App\Services\Scheduling;

use App\Models\ServiceAssignment;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ContinuityService
 *
 * Provides historical assignment data for continuity-of-care scoring.
 *
 * Continuity of care is a key factor in home care quality:
 * - Patients prefer familiar caregivers
 * - Staff who know the patient can provide better care
 * - Reduces anxiety for patients with cognitive impairment
 *
 * This service queries ServiceAssignment history to support:
 * - Staff-patient visit counts
 * - Previous staff for a patient
 * - Patient familiarity scoring
 */
class ContinuityService
{
    /**
     * Time window for continuity calculations (months).
     * Assignments older than this are not counted.
     */
    private const CONTINUITY_WINDOW_MONTHS = 6;

    /**
     * Get the number of completed visits a staff member has had with a patient.
     *
     * @param int $staffId User ID of the staff member
     * @param int $patientId Patient ID
     * @param Carbon|null $beforeDate Only count visits before this date (default: now)
     * @return int Number of completed visits
     */
    public function getVisitCount(int $staffId, int $patientId, ?Carbon $beforeDate = null): int
    {
        $beforeDate = $beforeDate ?? Carbon::now();
        $windowStart = $beforeDate->copy()->subMonths(self::CONTINUITY_WINDOW_MONTHS);

        return ServiceAssignment::query()
            ->where('assigned_user_id', $staffId)
            ->where('patient_id', $patientId)
            ->where('status', ServiceAssignment::STATUS_COMPLETED)
            ->whereBetween('scheduled_start', [$windowStart, $beforeDate])
            ->count();
    }

    /**
     * Get all staff who have previously served a patient.
     *
     * @param int $patientId Patient ID
     * @param int|null $organizationId Filter by organization
     * @return Collection Collection of [user_id, visit_count, last_visit_at]
     */
    public function getPreviousStaffForPatient(int $patientId, ?int $organizationId = null): Collection
    {
        $windowStart = Carbon::now()->subMonths(self::CONTINUITY_WINDOW_MONTHS);

        $query = ServiceAssignment::query()
            ->select('assigned_user_id')
            ->selectRaw('COUNT(*) as visit_count')
            ->selectRaw('MAX(scheduled_start) as last_visit_at')
            ->where('patient_id', $patientId)
            ->where('status', ServiceAssignment::STATUS_COMPLETED)
            ->where('scheduled_start', '>=', $windowStart)
            ->whereNotNull('assigned_user_id')
            ->groupBy('assigned_user_id')
            ->orderByDesc('visit_count');

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        return $query->get()->map(fn($row) => [
            'user_id' => $row->assigned_user_id,
            'visit_count' => $row->visit_count,
            'last_visit_at' => $row->last_visit_at ? Carbon::parse($row->last_visit_at) : null,
        ]);
    }

    /**
     * Get continuity score for a staff-patient pair (0-100).
     *
     * Scoring:
     * - 0 visits: 0 points
     * - 1-2 visits: 10 points per visit
     * - 3-5 visits: 15 points per visit (capped at 60)
     * - 6+ visits: 60 points + 5 per additional (capped at 100)
     *
     * @param int $staffId User ID
     * @param int $patientId Patient ID
     * @return int Score from 0-100
     */
    public function getContinuityScore(int $staffId, int $patientId): int
    {
        $visits = $this->getVisitCount($staffId, $patientId);

        if ($visits === 0) {
            return 0;
        }

        if ($visits <= 2) {
            return $visits * 10; // 10, 20
        }

        if ($visits <= 5) {
            return min(60, 20 + ($visits - 2) * 15); // 35, 50, 65 -> capped at 60
        }

        // 6+ visits
        return min(100, 60 + ($visits - 5) * 5);
    }

    /**
     * Get the count of unique staff who have served a patient.
     *
     * Lower counts indicate better continuity (fewer different caregivers).
     *
     * @param int $patientId Patient ID
     * @param int|null $organizationId Filter by organization
     * @return int Number of unique staff
     */
    public function getUniqueStaffCount(int $patientId, ?int $organizationId = null): int
    {
        $windowStart = Carbon::now()->subMonths(self::CONTINUITY_WINDOW_MONTHS);

        $query = ServiceAssignment::query()
            ->where('patient_id', $patientId)
            ->where('status', ServiceAssignment::STATUS_COMPLETED)
            ->where('scheduled_start', '>=', $windowStart)
            ->whereNotNull('assigned_user_id');

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        return $query->distinct('assigned_user_id')->count('assigned_user_id');
    }

    /**
     * Get batch continuity data for multiple staff-patient combinations.
     *
     * More efficient than calling getVisitCount() multiple times.
     *
     * @param int $patientId Patient ID
     * @param array $staffIds Array of User IDs to check
     * @return array Associative array [staff_id => visit_count]
     */
    public function getBatchVisitCounts(int $patientId, array $staffIds): array
    {
        if (empty($staffIds)) {
            return [];
        }

        $windowStart = Carbon::now()->subMonths(self::CONTINUITY_WINDOW_MONTHS);

        $results = ServiceAssignment::query()
            ->select('assigned_user_id')
            ->selectRaw('COUNT(*) as visit_count')
            ->where('patient_id', $patientId)
            ->whereIn('assigned_user_id', $staffIds)
            ->where('status', ServiceAssignment::STATUS_COMPLETED)
            ->where('scheduled_start', '>=', $windowStart)
            ->groupBy('assigned_user_id')
            ->pluck('visit_count', 'assigned_user_id')
            ->toArray();

        // Fill in zeros for staff with no visits
        $output = [];
        foreach ($staffIds as $staffId) {
            $output[$staffId] = $results[$staffId] ?? 0;
        }

        return $output;
    }

    /**
     * Check if a staff member is one of the patient's regular caregivers.
     *
     * A "regular" caregiver has 3+ visits in the continuity window.
     *
     * @param int $staffId User ID
     * @param int $patientId Patient ID
     * @return bool True if regular caregiver
     */
    public function isRegularCaregiver(int $staffId, int $patientId): bool
    {
        return $this->getVisitCount($staffId, $patientId) >= 3;
    }

    /**
     * Get the most recent visit between a staff member and patient.
     *
     * @param int $staffId User ID
     * @param int $patientId Patient ID
     * @return Carbon|null Date of last visit, or null if never visited
     */
    public function getLastVisitDate(int $staffId, int $patientId): ?Carbon
    {
        $lastVisit = ServiceAssignment::query()
            ->where('assigned_user_id', $staffId)
            ->where('patient_id', $patientId)
            ->where('status', ServiceAssignment::STATUS_COMPLETED)
            ->orderByDesc('scheduled_start')
            ->value('scheduled_start');

        return $lastVisit ? Carbon::parse($lastVisit) : null;
    }
}
