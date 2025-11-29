<?php

namespace App\Services\Scheduling;

use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SchedulingEngine
{
    /**
     * Check if a patient has any overlapping visits during the given time range.
     *
     * This implements the patient_non_concurrency feature:
     * - A patient must never have two visits at the same time by default.
     * - PT & OT cannot be scheduled for the same patient at the same time.
     *
     * @param int $patientId The patient to check
     * @param Carbon $start Proposed visit start time
     * @param Carbon $end Proposed visit end time
     * @param int|null $ignoreAssignmentId Assignment ID to exclude (for updates)
     * @return bool True if there's an overlap, false if the time slot is clear
     */
    public function patientHasOverlap(
        int $patientId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreAssignmentId = null
    ): bool {
        $query = ServiceAssignment::query()
            ->forPatient($patientId)
            ->scheduled() // Only check non-cancelled/non-missed
            ->where(function ($q) use ($start, $end) {
                // Two ranges overlap if: start1 < end2 AND start2 < end1
                $q->where('scheduled_start', '<', $end)
                  ->where('scheduled_end', '>', $start);
            });

        // Exclude the assignment we're updating (if any)
        if ($ignoreAssignmentId !== null) {
            $query->where('id', '!=', $ignoreAssignmentId);
        }

        return $query->exists();
    }

    /**
     * Get all overlapping assignments for a patient in a time range.
     *
     * @param int $patientId
     * @param Carbon $start
     * @param Carbon $end
     * @param int|null $ignoreAssignmentId
     * @return Collection
     */
    public function getPatientOverlaps(
        int $patientId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreAssignmentId = null
    ): Collection {
        $query = ServiceAssignment::query()
            ->forPatient($patientId)
            ->scheduled()
            ->where(function ($q) use ($start, $end) {
                $q->where('scheduled_start', '<', $end)
                  ->where('scheduled_end', '>', $start);
            })
            ->with(['serviceType']);

        if ($ignoreAssignmentId !== null) {
            $query->where('id', '!=', $ignoreAssignmentId);
        }

        return $query->get();
    }

    /**
     * Check spacing rule for a service type.
     *
     * This implements the psw_spacing feature:
     * - PSW/personal care visits should be spaced across the day, not bunched.
     * - Enforces min_gap_between_visits_minutes between same-service visits.
     *
     * @param int $patientId
     * @param int $serviceTypeId
     * @param Carbon $start Proposed visit start time
     * @param int|null $ignoreAssignmentId
     * @return string|null Error message if spacing rule violated, null if OK
     */
    public function checkSpacingRule(
        int $patientId,
        int $serviceTypeId,
        Carbon $start,
        ?int $ignoreAssignmentId = null
    ): ?string {
        $serviceType = ServiceType::find($serviceTypeId);

        if (!$serviceType || !$serviceType->hasSpacingRule()) {
            return null; // No spacing rule for this service type
        }

        $minGapMinutes = $serviceType->min_gap_between_visits_minutes;

        // Find the closest previous assignment of the same type on the same day
        $sameDay = $start->copy()->startOfDay();
        $sameDayEnd = $start->copy()->endOfDay();

        $query = ServiceAssignment::query()
            ->forPatient($patientId)
            ->forServiceType($serviceTypeId)
            ->scheduled()
            ->whereDate('scheduled_start', $sameDay)
            ->orderBy('scheduled_end', 'desc');

        if ($ignoreAssignmentId !== null) {
            $query->where('id', '!=', $ignoreAssignmentId);
        }

        // Get assignments that end before the proposed start
        $previousAssignments = $query->where('scheduled_end', '<=', $start)->get();

        if ($previousAssignments->isEmpty()) {
            return null; // No previous assignments, spacing OK
        }

        // Check gap from the most recent previous assignment
        $lastAssignment = $previousAssignments->first();
        $gapMinutes = $lastAssignment->scheduled_end->diffInMinutes($start);

        if ($gapMinutes < $minGapMinutes) {
            return sprintf(
                '%s visits require at least %d minutes between visits. ' .
                'Previous visit ends at %s, proposed visit starts at %s (gap: %d minutes).',
                $serviceType->name,
                $minGapMinutes,
                $lastAssignment->scheduled_end->format('H:i'),
                $start->format('H:i'),
                $gapMinutes
            );
        }

        return null; // Spacing is OK
    }

    /**
     * Validate an assignment creation/update.
     *
     * Returns an array with 'valid' (bool), 'errors' (array), and 'warnings' (array).
     *
     * @param int $patientId
     * @param int $serviceTypeId
     * @param Carbon $start
     * @param Carbon $end
     * @param int|null $ignoreAssignmentId
     * @return array{valid: bool, errors: array, warnings: array}
     */
    public function validateAssignment(
        int $patientId,
        int $serviceTypeId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreAssignmentId = null
    ): array {
        $errors = [];
        $warnings = [];

        // Check 1: Patient non-concurrency (no overlapping visits)
        if ($this->patientHasOverlap($patientId, $start, $end, $ignoreAssignmentId)) {
            $overlaps = $this->getPatientOverlaps($patientId, $start, $end, $ignoreAssignmentId);
            $overlapInfo = $overlaps->map(function ($a) {
                return sprintf(
                    '%s (%s)',
                    $a->serviceType->name ?? 'Unknown',
                    $a->time_range
                );
            })->join(', ');

            $errors[] = "Patient already has overlapping visits during this time: {$overlapInfo}";
        }

        // Check 2: Service type spacing rules
        $spacingError = $this->checkSpacingRule($patientId, $serviceTypeId, $start, $ignoreAssignmentId);
        if ($spacingError !== null) {
            $errors[] = $spacingError;
        }

        // Check 3: Basic validation
        if ($end <= $start) {
            $errors[] = 'End time must be after start time.';
        }

        if ($start < Carbon::now()->subHours(24)) {
            $warnings[] = 'This visit is scheduled more than 24 hours in the past.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get suggested time slots for a patient/service that respect spacing rules.
     *
     * @param int $patientId
     * @param int $serviceTypeId
     * @param Carbon $date
     * @param int $durationMinutes
     * @return array Array of suggested start times
     */
    public function getSuggestedTimeSlots(
        int $patientId,
        int $serviceTypeId,
        Carbon $date,
        int $durationMinutes = 60
    ): array {
        $serviceType = ServiceType::find($serviceTypeId);
        $minGap = $serviceType?->min_gap_between_visits_minutes ?? 0;

        // Get existing assignments for this patient on this date
        $existingAssignments = ServiceAssignment::query()
            ->forPatient($patientId)
            ->scheduled()
            ->whereDate('scheduled_start', $date)
            ->orderBy('scheduled_start')
            ->get();

        // Define time bands (morning, midday, afternoon, evening)
        $bands = [
            ['start' => '08:00', 'end' => '10:30', 'label' => 'Morning'],
            ['start' => '11:00', 'end' => '13:30', 'label' => 'Midday'],
            ['start' => '14:00', 'end' => '16:30', 'label' => 'Afternoon'],
            ['start' => '17:00', 'end' => '19:00', 'label' => 'Evening'],
        ];

        $suggestions = [];

        foreach ($bands as $band) {
            $bandStart = $date->copy()->setTimeFromTimeString($band['start']);
            $bandEnd = $date->copy()->setTimeFromTimeString($band['end']);

            // Check if this band has room
            $canSchedule = true;

            foreach ($existingAssignments as $assignment) {
                $proposedEnd = $bandStart->copy()->addMinutes($durationMinutes);

                // Check for overlap
                if ($this->patientHasOverlap($patientId, $bandStart, $proposedEnd)) {
                    $canSchedule = false;
                    break;
                }

                // Check spacing rule for same service type
                if ($assignment->service_type_id === $serviceTypeId) {
                    $gap = abs($assignment->scheduled_end->diffInMinutes($bandStart));
                    if ($gap < $minGap) {
                        $canSchedule = false;
                        break;
                    }
                }
            }

            if ($canSchedule) {
                $suggestions[] = [
                    'start' => $bandStart->format('H:i'),
                    'end' => $bandStart->copy()->addMinutes($durationMinutes)->format('H:i'),
                    'label' => $band['label'],
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Get the next available time slot for a service type respecting spacing rules.
     *
     * @param int $patientId
     * @param int $serviceTypeId
     * @param Carbon $afterTime
     * @param int $durationMinutes
     * @return Carbon|null
     */
    public function getNextAvailableSlot(
        int $patientId,
        int $serviceTypeId,
        Carbon $afterTime,
        int $durationMinutes = 60
    ): ?Carbon {
        $serviceType = ServiceType::find($serviceTypeId);
        $minGap = $serviceType?->min_gap_between_visits_minutes ?? 0;

        // Find the last assignment of this service type for this patient on the same day
        $lastAssignment = ServiceAssignment::query()
            ->forPatient($patientId)
            ->forServiceType($serviceTypeId)
            ->scheduled()
            ->whereDate('scheduled_start', $afterTime)
            ->orderBy('scheduled_end', 'desc')
            ->first();

        if ($lastAssignment && $minGap > 0) {
            // Must be at least minGap after the last assignment ends
            $earliestStart = $lastAssignment->scheduled_end->copy()->addMinutes($minGap);
            if ($earliestStart > $afterTime) {
                $afterTime = $earliestStart;
            }
        }

        // Now check for patient-level overlaps and find a clear slot
        $proposedEnd = $afterTime->copy()->addMinutes($durationMinutes);

        // If no overlap at proposed time, return it
        if (!$this->patientHasOverlap($patientId, $afterTime, $proposedEnd)) {
            return $afterTime;
        }

        // Find the next clear slot by iterating through existing assignments
        $assignments = ServiceAssignment::query()
            ->forPatient($patientId)
            ->scheduled()
            ->whereDate('scheduled_start', $afterTime)
            ->where('scheduled_start', '>=', $afterTime)
            ->orderBy('scheduled_end')
            ->get();

        foreach ($assignments as $assignment) {
            $proposedStart = $assignment->scheduled_end;
            $proposedEndNew = $proposedStart->copy()->addMinutes($durationMinutes);

            // Check spacing rule
            if ($serviceTypeId === $assignment->service_type_id && $minGap > 0) {
                $proposedStart = $assignment->scheduled_end->copy()->addMinutes($minGap);
                $proposedEndNew = $proposedStart->copy()->addMinutes($durationMinutes);
            }

            if (!$this->patientHasOverlap($patientId, $proposedStart, $proposedEndNew)) {
                return $proposedStart;
            }
        }

        return null; // No available slot found
    }
}
