<?php

namespace App\Services;

use App\DTOs\TfsMetricsDTO;
use App\Models\PatientQueue;
use App\Models\ServiceAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * TfsMetricsService - Calculates Time-to-First-Service metrics per OHaH requirements
 *
 * OHaH RFP (Appendix 1) mandates <24h Time-to-First-Service target:
 * "Time from referral acceptance to first completed visit"
 *
 * Band thresholds:
 * - Band A: <24 hours (Meets Target)
 * - Band B: 24-48 hours (Below Standard)
 * - Band C: >48 hours (Action Required)
 *
 * Calculation:
 * - Start: PatientQueue.accepted_at (when SPO accepted the referral)
 * - End: First ServiceAssignment with status 'completed' or verification_status 'verified'
 * - Only includes patients who have received first service
 * - Returns average and median across all applicable patients in the period
 */
class TfsMetricsService
{
    /**
     * Default reporting window in days.
     */
    public const DEFAULT_REPORTING_DAYS = 28;

    /**
     * Calculate TFS metrics for an organization.
     *
     * @param int|null $organizationId Filter by SPO (null = all)
     * @param Carbon|null $startDate Period start (default: 28 days ago)
     * @param Carbon|null $endDate Period end (default: now)
     * @return TfsMetricsDTO
     */
    public function calculate(?int $organizationId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): TfsMetricsDTO
    {
        $startDate = $startDate ?? now()->subDays(self::DEFAULT_REPORTING_DAYS);
        $endDate = $endDate ?? now();

        // Get all queue entries with accepted_at in period
        $queueEntries = PatientQueue::query()
            ->whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$startDate, $endDate])
            ->with('patient')
            ->get();

        $totalPatients = $queueEntries->count();
        $tfsTimes = collect();

        foreach ($queueEntries as $queueEntry) {
            $patientId = $queueEntry->patient_id;
            $acceptedAt = $queueEntry->accepted_at;

            // Find first completed service assignment for this patient
            $firstService = ServiceAssignment::where('patient_id', $patientId)
                ->where(function ($q) {
                    $q->where('status', 'completed')
                      ->orWhere('verification_status', 'verified');
                })
                ->where('scheduled_start', '>=', $acceptedAt)
                ->orderBy('scheduled_start', 'asc')
                ->first();

            if ($firstService) {
                // Calculate hours between acceptance and first service
                $hoursToFirstService = $acceptedAt->diffInHours($firstService->scheduled_start, true);
                $tfsTimes->push($hoursToFirstService);
            }
        }

        $patientsWithFirstService = $tfsTimes->count();

        // Calculate average and median
        $averageHours = $patientsWithFirstService > 0 
            ? $tfsTimes->avg() 
            : 0.0;

        $medianHours = $patientsWithFirstService > 0 
            ? $this->calculateMedian($tfsTimes) 
            : null;

        return TfsMetricsDTO::fromCalculation(
            averageHours: $averageHours,
            medianHours: $medianHours,
            patientsWithFirstService: $patientsWithFirstService,
            patientsTotal: $totalPatients,
            startDate: $startDate,
            endDate: $endDate
        );
    }

    /**
     * Get TFS average in hours (for dashboard cards).
     */
    public function getAverageHours(?int $organizationId = null): float
    {
        return $this->calculate($organizationId)->averageHours;
    }

    /**
     * Get the current compliance band.
     */
    public function getComplianceBand(?int $organizationId = null): string
    {
        return $this->calculate($organizationId)->band;
    }

    /**
     * Get patients currently waiting for first service (beyond SLA).
     */
    public function getPatientsWaitingBeyondSla(?int $organizationId = null): Collection
    {
        $slaHours = 24; // Target is <24 hours

        return PatientQueue::query()
            ->whereNotNull('accepted_at')
            ->whereNull('transitioned_at') // Still in queue (not yet active)
            ->where('accepted_at', '<', now()->subHours($slaHours))
            ->whereDoesntHave('patient.serviceAssignments', function ($q) {
                $q->where('status', 'completed')
                  ->orWhere('verification_status', 'verified');
            })
            ->with('patient.user')
            ->get();
    }

    /**
     * Calculate median of a collection of values.
     */
    protected function calculateMedian(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return 0.0;
        }

        $middleIndex = (int) floor(($count - 1) / 2);

        if ($count % 2 === 0) {
            return ($sorted[$middleIndex] + $sorted[$middleIndex + 1]) / 2;
        }

        return $sorted[$middleIndex];
    }

    /**
     * Get detailed patient data for TFS calculation.
     * Returns all patients with their acceptance and first service times.
     * For patients awaiting first service, also includes scheduled (upcoming) first visit.
     */
    public function getPatientDetails(?int $organizationId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(self::DEFAULT_REPORTING_DAYS);
        $endDate = $endDate ?? now();

        // Get all queue entries with accepted_at in period
        $queueEntries = PatientQueue::query()
            ->whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$startDate, $endDate])
            ->with(['patient.user'])
            ->get();

        $patients = [];

        foreach ($queueEntries as $queueEntry) {
            $patientId = $queueEntry->patient_id;
            $patient = $queueEntry->patient;
            $acceptedAt = $queueEntry->accepted_at;

            // Find first completed service assignment for this patient
            $firstCompletedService = ServiceAssignment::where('patient_id', $patientId)
                ->where(function ($q) {
                    $q->where('status', 'completed')
                      ->orWhere('verification_status', 'verified');
                })
                ->where('scheduled_start', '>=', $acceptedAt)
                ->orderBy('scheduled_start', 'asc')
                ->with('serviceType')
                ->first();

            $hoursToFirstService = null;
            $firstServiceDate = null;
            $serviceTypeName = null;
            $status = 'awaiting_first_service';
            
            // For awaiting patients, check if they have a scheduled (future) first visit
            $scheduledFirstService = null;
            $scheduledFirstServiceDate = null;
            $scheduledFirstServiceType = null;
            $hoursUntilScheduled = null;

            if ($firstCompletedService) {
                // Patient has completed first service
                $hoursToFirstService = round($acceptedAt->diffInHours($firstCompletedService->scheduled_start, true), 1);
                $firstServiceDate = $firstCompletedService->scheduled_start;
                $serviceTypeName = $firstCompletedService->serviceType?->name ?? 'Unknown';
                
                // Determine status based on TFS - using descriptive labels
                if ($hoursToFirstService < 24) {
                    $status = 'within_target';
                } elseif ($hoursToFirstService <= 48) {
                    $status = 'below_standard';
                } else {
                    $status = 'exceeded_target'; // Changed from 'action_required' - more descriptive
                }
            } else {
                // Patient is awaiting first service - check for scheduled visits
                $scheduledFirstService = ServiceAssignment::where('patient_id', $patientId)
                    ->whereIn('status', ['scheduled', 'confirmed', 'pending'])
                    ->where('scheduled_start', '>=', now())
                    ->orderBy('scheduled_start', 'asc')
                    ->with('serviceType')
                    ->first();

                if ($scheduledFirstService) {
                    $scheduledFirstServiceDate = $scheduledFirstService->scheduled_start;
                    $scheduledFirstServiceType = $scheduledFirstService->serviceType?->name ?? 'Unknown';
                    $hoursUntilScheduled = round(now()->diffInHours($scheduledFirstServiceDate, false), 1);
                }
            }

            $patients[] = [
                'id' => $patient->id,
                'name' => $patient->user?->name ?? 'Unknown',
                'ohip' => $patient->ohip,
                'queue_status' => $queueEntry->queue_status,
                'accepted_at' => $acceptedAt->toIso8601String(),
                'accepted_at_formatted' => $acceptedAt->format('M j, Y g:i A'),
                'first_service_at' => $firstServiceDate?->toIso8601String(),
                'first_service_at_formatted' => $firstServiceDate?->format('M j, Y g:i A'),
                'first_service_type' => $serviceTypeName,
                'hours_to_first_service' => $hoursToFirstService,
                'hours_formatted' => $hoursToFirstService !== null ? number_format($hoursToFirstService, 1) . 'h' : 'N/A',
                'status' => $status,
                'has_first_service' => $firstCompletedService !== null,
                // Scheduled first service info (for awaiting patients)
                'scheduled_first_service_at' => $scheduledFirstServiceDate?->toIso8601String(),
                'scheduled_first_service_at_formatted' => $scheduledFirstServiceDate?->format('M j, Y g:i A'),
                'scheduled_first_service_type' => $scheduledFirstServiceType,
                'hours_until_scheduled' => $hoursUntilScheduled,
                'has_scheduled_service' => $scheduledFirstService !== null,
            ];
        }

        // Sort by hours_to_first_service (nulls last)
        usort($patients, function ($a, $b) {
            if ($a['hours_to_first_service'] === null) return 1;
            if ($b['hours_to_first_service'] === null) return -1;
            return $b['hours_to_first_service'] <=> $a['hours_to_first_service']; // Highest first (worst performers)
        });

        return $patients;
    }
}
