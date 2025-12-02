<?php

namespace App\Services;

use App\Models\QinRecord;
use App\Services\CareOps\FteComplianceService;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * QinService - Domain service for Quality Improvement Notice management.
 *
 * This service implements the hybrid QIN model:
 * 1. Active QINs - Officially issued QINs from OHaH (stored in qin_records table)
 * 2. Potential QINs - Auto-calculated based on current metric breaches
 *
 * Per Ontario Health at Home RFP:
 * - QINs are issued when SPOs breach performance band thresholds
 * - SPOs must respond with a QIP within 7 days
 * - Target is 0 Active QINs (compliance)
 */
class QinService
{
    public function __construct(
        protected MissedCareService $missedCareService,
        protected FteComplianceService $fteComplianceService
    ) {}

    /**
     * Get count of officially issued Active QINs (non-closed).
     * This is the official compliance metric shown on dashboards.
     */
    public function getActiveCount(int $organizationId): int
    {
        return QinRecord::where('organization_id', $organizationId)
            ->active()
            ->count();
    }

    /**
     * Get all active (non-closed) QIN records for an organization.
     */
    public function getActiveQinRecords(int $organizationId): Collection
    {
        return QinRecord::where('organization_id', $organizationId)
            ->active()
            ->orderByDesc('issued_date')
            ->get();
    }

    /**
     * Get QIN records by status.
     */
    public function getQinRecordsByStatus(int $organizationId, string $status): Collection
    {
        return QinRecord::where('organization_id', $organizationId)
            ->where('status', $status)
            ->orderByDesc('issued_date')
            ->get();
    }

    /**
     * Get all QIN records for an organization (for history/manager page).
     */
    public function getAllQinRecords(int $organizationId): Collection
    {
        return QinRecord::where('organization_id', $organizationId)
            ->orderByDesc('issued_date')
            ->get();
    }

    /**
     * Calculate potential QINs based on current metric breaches.
     *
     * This evaluates all Schedule 4 indicators and returns the count
     * of metrics currently in Band B or C (potential breach territory).
     *
     * NOTE: This does NOT mean a QIN has been issued - only that
     * the metrics would warrant one if OHaH were to review.
     *
     * @return array{count: int, breaches: array}
     */
    public function calculatePotentialBreaches(int $organizationId): array
    {
        $breaches = [];

        // 1. Check Missed Care Rate
        try {
            $missedCare = $this->missedCareService->calculateForOrg($organizationId);
            $missedCareBand = $missedCare->getComplianceBand();
            if ($missedCareBand !== 'A') {
                $breaches[] = [
                    'indicator' => QinRecord::INDICATORS['missed_care'],
                    'band' => $missedCareBand,
                    'band_breach' => QinRecord::BAND_BREACHES['missed_care'][$missedCareBand] ?? "Band {$missedCareBand}",
                    'current_value' => $missedCare->ratePercent . '%',
                ];
            }
        } catch (\Exception $e) {
            // Log but don't fail if service unavailable
            \Log::warning('QinService: Failed to calculate missed care metrics', ['error' => $e->getMessage()]);
        }

        // 2. Check FTE Compliance
        try {
            $fteMetrics = $this->fteComplianceService->calculateSnapshot($organizationId);
            $fteBand = $fteMetrics['band'] ?? 'GREEN';
            // Map FTE bands to QIN bands: GREEN=A, YELLOW=B, RED=C
            $qinBand = match ($fteBand) {
                'GREEN' => 'A',
                'YELLOW' => 'B',
                'RED' => 'C',
                default => 'A',
            };
            if ($qinBand !== 'A') {
                $breaches[] = [
                    'indicator' => QinRecord::INDICATORS['fte_compliance'],
                    'band' => $qinBand,
                    'band_breach' => QinRecord::BAND_BREACHES['fte_compliance'][$qinBand] ?? "Band {$qinBand}",
                    'current_value' => ($fteMetrics['fte_ratio'] ?? 0) . '%',
                ];
            }
        } catch (\Exception $e) {
            \Log::warning('QinService: Failed to calculate FTE metrics', ['error' => $e->getMessage()]);
        }

        // 3. Referral Acceptance and TFS would be added here once those services exist
        // For now, we'll leave placeholders that can be wired in later

        return [
            'count' => count($breaches),
            'breaches' => $breaches,
        ];
    }

    /**
     * Get comprehensive QIN metrics for dashboard display.
     */
    public function getMetrics(int $organizationId): array
    {
        $activeRecords = $this->getActiveQinRecords($organizationId);
        $potential = $this->calculatePotentialBreaches($organizationId);

        // Count by status
        $openCount = $activeRecords->where('status', QinRecord::STATUS_OPEN)->count();
        $pendingReviewCount = $activeRecords->whereIn('status', [
            QinRecord::STATUS_SUBMITTED,
            QinRecord::STATUS_UNDER_REVIEW,
        ])->count();

        // Closed YTD
        $closedYtd = QinRecord::where('organization_id', $organizationId)
            ->closed()
            ->whereYear('closed_at', now()->year)
            ->count();

        return [
            // Official issued QINs
            'active_count' => $activeRecords->count(),
            'open_count' => $openCount,
            'pending_review_count' => $pendingReviewCount,
            'closed_ytd' => $closedYtd,

            // Potential breaches (informational)
            'potential_count' => $potential['count'],
            'potential_breaches' => $potential['breaches'],

            // Records for display
            'active_records' => $activeRecords->map(fn ($qin) => [
                'id' => $qin->id,
                'qin_number' => $qin->qin_number,
                'indicator' => $qin->indicator,
                'band_breach' => $qin->band_breach,
                'issued_date' => $qin->issued_date->toDateString(),
                'qip_due_date' => $qin->qip_due_date?->toDateString(),
                'status' => $qin->status,
                'status_label' => $qin->status_label,
                'is_overdue' => $qin->isOverdue(),
                'days_until_due' => $qin->daysUntilDue(),
                'ohah_contact' => $qin->ohah_contact,
            ])->toArray(),
        ];
    }

    /**
     * Create a new QIN record (for webhook ingestion or manual entry).
     */
    public function createQinRecord(array $data): QinRecord
    {
        // Generate QIN number if not provided
        if (empty($data['qin_number'])) {
            $data['qin_number'] = QinRecord::generateQinNumber($data['organization_id']);
        }

        // Set default QIP due date (7 days from issue)
        if (empty($data['qip_due_date']) && !empty($data['issued_date'])) {
            $data['qip_due_date'] = Carbon::parse($data['issued_date'])->addDays(7);
        }

        return QinRecord::create($data);
    }

    /**
     * Update QIN status (e.g., when QIP is submitted).
     */
    public function updateStatus(int $qinId, string $newStatus, ?string $notes = null): QinRecord
    {
        $qin = QinRecord::findOrFail($qinId);

        $updateData = ['status' => $newStatus];

        if ($newStatus === QinRecord::STATUS_CLOSED) {
            $updateData['closed_at'] = now();
        }

        if ($notes) {
            $updateData['notes'] = $qin->notes
                ? $qin->notes . "\n\n" . now()->toDateTimeString() . ": " . $notes
                : $notes;
        }

        $qin->update($updateData);

        return $qin->fresh();
    }
}
