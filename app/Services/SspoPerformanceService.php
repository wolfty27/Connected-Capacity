<?php

namespace App\Services;

use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SspoPerformanceService - Calculate SSPO partner performance metrics
 *
 * Per OHaH RFS, SPO can subcontract to SSPO partners but retains
 * full liability. This service tracks SSPO performance:
 * - Assignment acceptance rate
 * - Average response time to accept/decline
 * - Missed visit rate
 * - Service delivery metrics
 */
class SspoPerformanceService
{
    protected MissedCareService $missedCareService;

    public function __construct(MissedCareService $missedCareService)
    {
        $this->missedCareService = $missedCareService;
    }

    /**
     * Get comprehensive performance metrics for an SSPO.
     */
    public function getPerformanceMetrics(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $organization = ServiceProviderOrganization::find($organizationId);

        if (!$organization) {
            return ['error' => 'Organization not found'];
        }

        return [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'type' => $organization->type,
            ],
            'acceptance' => $this->getAcceptanceMetrics($organizationId, $startDate, $endDate),
            'response_time' => $this->getResponseTimeMetrics($organizationId, $startDate, $endDate),
            'service_delivery' => $this->getServiceDeliveryMetrics($organizationId, $startDate, $endDate),
            'missed_care' => $this->missedCareService->calculate($organizationId, $startDate, $endDate),
            'ranking' => $this->getOrganizationRanking($organizationId, $startDate, $endDate),
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
            ],
        ];
    }

    /**
     * Get acceptance metrics for an SSPO.
     */
    public function getAcceptanceMetrics(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $query = ServiceAssignment::query()
            ->where('service_provider_organization_id', $organizationId)
            ->where('sspo_acceptance_status', '!=', ServiceAssignment::SSPO_NOT_APPLICABLE)
            ->whereBetween('sspo_notified_at', [$startDate, $endDate]);

        $total = (clone $query)->count();
        $accepted = (clone $query)->where('sspo_acceptance_status', ServiceAssignment::SSPO_ACCEPTED)->count();
        $declined = (clone $query)->where('sspo_acceptance_status', ServiceAssignment::SSPO_DECLINED)->count();
        $pending = (clone $query)->where('sspo_acceptance_status', ServiceAssignment::SSPO_PENDING)->count();

        $responded = $accepted + $declined;
        $acceptanceRate = $responded > 0 ? round(($accepted / $responded) * 100, 1) : null;

        return [
            'total_received' => $total,
            'accepted' => $accepted,
            'declined' => $declined,
            'pending' => $pending,
            'responded' => $responded,
            'acceptance_rate' => $acceptanceRate,
            'pending_rate' => $total > 0 ? round(($pending / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get response time metrics for an SSPO.
     */
    public function getResponseTimeMetrics(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $responseTimes = ServiceAssignment::query()
            ->where('service_provider_organization_id', $organizationId)
            ->whereNotNull('sspo_notified_at')
            ->whereNotNull('sspo_responded_at')
            ->whereBetween('sspo_notified_at', [$startDate, $endDate])
            ->selectRaw('TIMESTAMPDIFF(MINUTE, sspo_notified_at, sspo_responded_at) as response_minutes')
            ->pluck('response_minutes');

        if ($responseTimes->isEmpty()) {
            return [
                'average_minutes' => null,
                'median_minutes' => null,
                'min_minutes' => null,
                'max_minutes' => null,
                'within_1_hour' => null,
                'within_4_hours' => null,
                'within_24_hours' => null,
            ];
        }

        $sorted = $responseTimes->sort()->values();
        $count = $sorted->count();
        $median = $count % 2 === 0
            ? ($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2
            : $sorted[intval($count / 2)];

        return [
            'average_minutes' => round($responseTimes->avg(), 1),
            'median_minutes' => round($median, 1),
            'min_minutes' => $responseTimes->min(),
            'max_minutes' => $responseTimes->max(),
            'within_1_hour' => round(($responseTimes->filter(fn($m) => $m <= 60)->count() / $count) * 100, 1),
            'within_4_hours' => round(($responseTimes->filter(fn($m) => $m <= 240)->count() / $count) * 100, 1),
            'within_24_hours' => round(($responseTimes->filter(fn($m) => $m <= 1440)->count() / $count) * 100, 1),
            'response_count' => $count,
        ];
    }

    /**
     * Get service delivery metrics for an SSPO.
     */
    public function getServiceDeliveryMetrics(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $query = ServiceAssignment::query()
            ->where('service_provider_organization_id', $organizationId)
            ->whereBetween('scheduled_start', [$startDate, $endDate]);

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', ServiceAssignment::STATUS_COMPLETED)->count();
        $inProgress = (clone $query)->where('status', ServiceAssignment::STATUS_IN_PROGRESS)->count();
        $pending = (clone $query)->where('status', ServiceAssignment::STATUS_PENDING)->count();
        $missed = (clone $query)->where('status', ServiceAssignment::STATUS_MISSED)->count();
        $cancelled = (clone $query)->where('status', ServiceAssignment::STATUS_CANCELLED)->count();

        // Calculate on-time delivery (within scheduled window)
        $onTime = ServiceAssignment::query()
            ->where('service_provider_organization_id', $organizationId)
            ->where('status', ServiceAssignment::STATUS_COMPLETED)
            ->whereNotNull('actual_start')
            ->whereNotNull('scheduled_start')
            ->whereBetween('scheduled_start', [$startDate, $endDate])
            ->whereRaw('actual_start <= DATE_ADD(scheduled_start, INTERVAL 30 MINUTE)')
            ->count();

        $onTimeRate = $completed > 0 ? round(($onTime / $completed) * 100, 1) : null;
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : null;

        return [
            'total_assigned' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'pending' => $pending,
            'missed' => $missed,
            'cancelled' => $cancelled,
            'completion_rate' => $completionRate,
            'on_time_deliveries' => $onTime,
            'on_time_rate' => $onTimeRate,
        ];
    }

    /**
     * Get all SSPO performance rankings.
     */
    public function getAllSspoRankings(?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $sspos = ServiceProviderOrganization::where('type', 'partner')
            ->where('active', true)
            ->get();

        return $sspos->map(function ($org) use ($startDate, $endDate) {
            $acceptance = $this->getAcceptanceMetrics($org->id, $startDate, $endDate);
            $responseTime = $this->getResponseTimeMetrics($org->id, $startDate, $endDate);
            $delivery = $this->getServiceDeliveryMetrics($org->id, $startDate, $endDate);
            $missed = $this->missedCareService->calculate($org->id, $startDate, $endDate);

            // Calculate composite score (0-100)
            $score = $this->calculatePerformanceScore($acceptance, $responseTime, $delivery, $missed);

            return [
                'organization_id' => $org->id,
                'organization_name' => $org->name,
                'acceptance_rate' => $acceptance['acceptance_rate'],
                'avg_response_minutes' => $responseTime['average_minutes'],
                'completion_rate' => $delivery['completion_rate'],
                'missed_care_rate' => $missed['missed_rate'],
                'total_assignments' => $delivery['total_assigned'],
                'performance_score' => $score,
            ];
        })
            ->sortByDesc('performance_score')
            ->values()
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            });
    }

    /**
     * Get ranking position for a specific organization.
     */
    public function getOrganizationRanking(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $rankings = $this->getAllSspoRankings($startDate, $endDate);

        $orgRanking = $rankings->firstWhere('organization_id', $organizationId);

        if (!$orgRanking) {
            return [
                'rank' => null,
                'total_sspos' => $rankings->count(),
                'performance_score' => null,
            ];
        }

        return [
            'rank' => $orgRanking['rank'],
            'total_sspos' => $rankings->count(),
            'performance_score' => $orgRanking['performance_score'],
            'percentile' => round((1 - ($orgRanking['rank'] - 1) / max($rankings->count(), 1)) * 100, 1),
        ];
    }

    /**
     * Get daily performance trend for an SSPO.
     */
    public function getDailyTrend(int $organizationId, int $days = 14): Collection
    {
        $startDate = now()->subDays($days);

        return ServiceAssignment::query()
            ->where('service_provider_organization_id', $organizationId)
            ->where('scheduled_start', '>=', $startDate)
            ->selectRaw('DATE(scheduled_start) as date')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [ServiceAssignment::STATUS_COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as missed', [ServiceAssignment::STATUS_MISSED])
            ->groupBy(DB::raw('DATE(scheduled_start)'))
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->date,
                    'total' => (int) $row->total,
                    'completed' => (int) $row->completed,
                    'missed' => (int) $row->missed,
                    'completion_rate' => $row->total > 0
                        ? round(($row->completed / $row->total) * 100, 1)
                        : null,
                ];
            });
    }

    /**
     * Get service type breakdown for an SSPO.
     */
    public function getServiceTypeBreakdown(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return ServiceAssignment::query()
            ->where('service_provider_organization_id', $organizationId)
            ->whereBetween('scheduled_start', [$startDate, $endDate])
            ->select('service_type_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [ServiceAssignment::STATUS_COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as missed', [ServiceAssignment::STATUS_MISSED])
            ->groupBy('service_type_id')
            ->with('serviceType:id,name,code,category')
            ->get()
            ->map(function ($row) {
                return [
                    'service_type_id' => $row->service_type_id,
                    'service_type' => $row->serviceType ? [
                        'id' => $row->serviceType->id,
                        'name' => $row->serviceType->name,
                        'code' => $row->serviceType->code,
                        'category' => $row->serviceType->category,
                    ] : null,
                    'total' => (int) $row->total,
                    'completed' => (int) $row->completed,
                    'missed' => (int) $row->missed,
                    'completion_rate' => $row->total > 0
                        ? round(($row->completed / $row->total) * 100, 1)
                        : null,
                ];
            });
    }

    /**
     * Get decline reasons breakdown for an SSPO.
     */
    public function getDeclineReasons(
        int $organizationId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return ServiceAssignment::query()
            ->where('service_provider_organization_id', $organizationId)
            ->where('sspo_acceptance_status', ServiceAssignment::SSPO_DECLINED)
            ->whereNotNull('sspo_decline_reason')
            ->whereBetween('sspo_responded_at', [$startDate, $endDate])
            ->select('sspo_decline_reason')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('sspo_decline_reason')
            ->orderByDesc('count')
            ->get()
            ->map(fn($row) => [
                'reason' => $row->sspo_decline_reason,
                'count' => (int) $row->count,
            ]);
    }

    /**
     * Calculate composite performance score (0-100).
     */
    protected function calculatePerformanceScore(
        array $acceptance,
        array $responseTime,
        array $delivery,
        array $missed
    ): float {
        $score = 100.0;

        // Acceptance rate weight: 25%
        if ($acceptance['acceptance_rate'] !== null) {
            $score -= (100 - $acceptance['acceptance_rate']) * 0.25;
        }

        // Response time weight: 15% (target: within 4 hours = 240 min)
        if ($responseTime['average_minutes'] !== null) {
            $responseScore = max(0, 100 - ($responseTime['average_minutes'] / 240) * 100);
            $score -= (100 - $responseScore) * 0.15;
        }

        // Completion rate weight: 30%
        if ($delivery['completion_rate'] !== null) {
            $score -= (100 - $delivery['completion_rate']) * 0.30;
        }

        // Missed care weight: 30% (most important)
        if ($missed['missed_rate'] > 0) {
            $missedScore = max(0, 100 - $missed['missed_rate'] * 20); // 5% missed = 0 score
            $score -= (100 - $missedScore) * 0.30;
        }

        return max(0, round($score, 1));
    }
}
