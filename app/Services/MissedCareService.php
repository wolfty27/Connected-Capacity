<?php

namespace App\Services;

use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * MissedCareService - Calculates missed care metrics per OHaH requirements
 *
 * OHaH RFS mandates 0% missed care target. This service aggregates:
 * - Missed visits (status = 'missed' or 'cancelled' without reschedule)
 * - Delivered visits (status = 'completed')
 * - Missed care rate = missed / (delivered + missed)
 */
class MissedCareService
{
    /**
     * Statuses that count as "delivered" care.
     */
    protected const DELIVERED_STATUSES = [
        ServiceAssignment::STATUS_COMPLETED,
        ServiceAssignment::STATUS_IN_PROGRESS,
    ];

    /**
     * Statuses that count as "missed" care.
     */
    protected const MISSED_STATUSES = [
        ServiceAssignment::STATUS_MISSED,
        ServiceAssignment::STATUS_CANCELLED,
    ];

    /**
     * Calculate missed care metrics for an organization over a period.
     *
     * @param int|null $organizationId Filter by SPO/SSPO (null = all)
     * @param Carbon|null $startDate Period start (default: 7 days ago)
     * @param Carbon|null $endDate Period end (default: now)
     * @return array{delivered: int, missed: int, total: int, missed_rate: float, compliance: bool}
     */
    public function calculate(?int $organizationId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(7);
        $endDate = $endDate ?? now();

        $query = ServiceAssignment::query()
            ->whereBetween('scheduled_start', [$startDate, $endDate]);

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        $delivered = (clone $query)->whereIn('status', self::DELIVERED_STATUSES)->count();
        $missed = (clone $query)->whereIn('status', self::MISSED_STATUSES)->count();
        $total = $delivered + $missed;

        $missedRate = $total > 0 ? round(($missed / $total) * 100, 2) : 0.0;

        return [
            'delivered' => $delivered,
            'missed' => $missed,
            'total' => $total,
            'missed_rate' => $missedRate,
            'compliance' => $missedRate === 0.0, // OHaH target is 0%
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
            ],
        ];
    }

    /**
     * Get missed care breakdown by service type.
     */
    public function calculateByServiceType(?int $organizationId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->subDays(7);
        $endDate = $endDate ?? now();

        $query = ServiceAssignment::query()
            ->select('service_type_id')
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as delivered', self::DELIVERED_STATUSES)
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as missed', self::MISSED_STATUSES)
            ->whereBetween('scheduled_start', [$startDate, $endDate])
            ->groupBy('service_type_id')
            ->with('serviceType:id,name,code,category');

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        return $query->get()->map(function ($row) {
            $total = $row->delivered + $row->missed;
            return [
                'service_type_id' => $row->service_type_id,
                'service_type' => $row->serviceType ? [
                    'id' => $row->serviceType->id,
                    'name' => $row->serviceType->name,
                    'code' => $row->serviceType->code,
                    'category' => $row->serviceType->category,
                ] : null,
                'delivered' => (int) $row->delivered,
                'missed' => (int) $row->missed,
                'total' => $total,
                'missed_rate' => $total > 0 ? round(($row->missed / $total) * 100, 2) : 0.0,
            ];
        });
    }

    /**
     * Get missed care breakdown by SSPO organization.
     */
    public function calculateBySspo(?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->subDays(7);
        $endDate = $endDate ?? now();

        return ServiceAssignment::query()
            ->select('service_provider_organization_id')
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as delivered', self::DELIVERED_STATUSES)
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as missed', self::MISSED_STATUSES)
            ->whereBetween('scheduled_start', [$startDate, $endDate])
            ->whereNotNull('service_provider_organization_id')
            ->groupBy('service_provider_organization_id')
            ->get()
            ->map(function ($row) {
                $org = ServiceProviderOrganization::find($row->service_provider_organization_id);
                $total = $row->delivered + $row->missed;
                return [
                    'organization_id' => $row->service_provider_organization_id,
                    'organization' => $org ? [
                        'id' => $org->id,
                        'name' => $org->name,
                        'type' => $org->type,
                    ] : null,
                    'delivered' => (int) $row->delivered,
                    'missed' => (int) $row->missed,
                    'total' => $total,
                    'missed_rate' => $total > 0 ? round(($row->missed / $total) * 100, 2) : 0.0,
                ];
            });
    }

    /**
     * Get daily missed care trend for charting.
     */
    public function getDailyTrend(?int $organizationId = null, int $days = 14): Collection
    {
        $startDate = now()->subDays($days);

        $query = ServiceAssignment::query()
            ->selectRaw('DATE(scheduled_start) as date')
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as delivered', self::DELIVERED_STATUSES)
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as missed', self::MISSED_STATUSES)
            ->where('scheduled_start', '>=', $startDate)
            ->groupBy(DB::raw('DATE(scheduled_start)'))
            ->orderBy('date');

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        return $query->get()->map(function ($row) {
            $total = $row->delivered + $row->missed;
            return [
                'date' => $row->date,
                'delivered' => (int) $row->delivered,
                'missed' => (int) $row->missed,
                'total' => $total,
                'missed_rate' => $total > 0 ? round(($row->missed / $total) * 100, 2) : 0.0,
            ];
        });
    }

    /**
     * Get assignments with missed status for review.
     */
    public function getMissedAssignments(?int $organizationId = null, ?Carbon $startDate = null, ?Carbon $endDate = null, int $limit = 50): Collection
    {
        $startDate = $startDate ?? now()->subDays(7);
        $endDate = $endDate ?? now();

        $query = ServiceAssignment::query()
            ->whereIn('status', self::MISSED_STATUSES)
            ->whereBetween('scheduled_start', [$startDate, $endDate])
            ->with([
                'patient:id,user_id',
                'patient.user:id,name',
                'serviceType:id,name,code',
                'serviceProviderOrganization:id,name,type',
                'assignedUser:id,name',
            ])
            ->orderBy('scheduled_start', 'desc')
            ->limit($limit);

        if ($organizationId) {
            $query->where('service_provider_organization_id', $organizationId);
        }

        return $query->get()->map(fn($a) => [
            'id' => $a->id,
            'patient' => $a->patient ? [
                'id' => $a->patient->id,
                'name' => $a->patient->user?->name,
            ] : null,
            'service_type' => $a->serviceType ? [
                'id' => $a->serviceType->id,
                'name' => $a->serviceType->name,
                'code' => $a->serviceType->code,
            ] : null,
            'organization' => $a->serviceProviderOrganization ? [
                'id' => $a->serviceProviderOrganization->id,
                'name' => $a->serviceProviderOrganization->name,
            ] : null,
            'assigned_to' => $a->assignedUser?->name,
            'status' => $a->status,
            'scheduled_start' => $a->scheduled_start?->toIso8601String(),
            'notes' => $a->notes,
        ]);
    }

    /**
     * Check if organization is at risk of missing SLA target.
     *
     * @return array{at_risk: bool, current_rate: float, threshold: float, message: string}
     */
    public function checkComplianceRisk(?int $organizationId = null): array
    {
        // Warning threshold: anything above 0% is at risk for OHaH
        $warningThreshold = 0.5; // Alert if >0.5% missed

        $metrics = $this->calculate($organizationId, now()->subDays(7));

        $atRisk = $metrics['missed_rate'] > $warningThreshold;

        $message = match (true) {
            $metrics['missed_rate'] === 0.0 => 'Fully compliant - 0% missed care',
            $metrics['missed_rate'] <= $warningThreshold => 'Minor variance detected',
            $metrics['missed_rate'] <= 2.0 => 'Warning: Missed care rate approaching risk threshold',
            default => 'Critical: Significant missed care - immediate action required',
        };

        return [
            'at_risk' => $atRisk,
            'current_rate' => $metrics['missed_rate'],
            'threshold' => $warningThreshold,
            'message' => $message,
            'missed_count' => $metrics['missed'],
            'period_days' => 7,
        ];
    }
}
