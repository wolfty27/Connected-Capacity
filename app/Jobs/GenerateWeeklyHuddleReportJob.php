<?php

namespace App\Jobs;

use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\TriageResult;
use App\Services\HpgResponseService;
use App\Services\MissedCareService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * GenerateWeeklyHuddleReportJob - Generate OHaH weekly huddle report
 *
 * Per OHaH RFS, SPOs participate in weekly care coordination huddles.
 * This job generates a comprehensive report including:
 * - SLA compliance metrics
 * - Patient census and transitions
 * - Missed care analysis
 * - SSPO performance summary
 * - Action items and escalations
 */
class GenerateWeeklyHuddleReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Carbon $weekStart;
    protected Carbon $weekEnd;
    protected ?int $organizationId;

    public function __construct(?int $organizationId = null, ?Carbon $weekStart = null)
    {
        $this->organizationId = $organizationId;
        $this->weekStart = $weekStart ?? now()->startOfWeek();
        $this->weekEnd = $this->weekStart->copy()->endOfWeek();
    }

    public function handle(
        HpgResponseService $hpgService,
        MissedCareService $missedCareService
    ): void {
        Log::info('Generating weekly huddle report', [
            'week_start' => $this->weekStart->toDateString(),
            'week_end' => $this->weekEnd->toDateString(),
            'organization_id' => $this->organizationId,
        ]);

        $report = $this->generateReport($hpgService, $missedCareService);

        // Store report as JSON (could also generate PDF/Excel)
        $filename = sprintf(
            'huddle-reports/weekly_%s_%s.json',
            $this->weekStart->format('Y-m-d'),
            $this->organizationId ?? 'all'
        );

        Storage::put($filename, json_encode($report, JSON_PRETTY_PRINT));

        Log::info('Weekly huddle report generated', [
            'filename' => $filename,
            'patient_count' => $report['census']['total_active'],
        ]);
    }

    protected function generateReport(
        HpgResponseService $hpgService,
        MissedCareService $missedCareService
    ): array {
        return [
            'report_metadata' => [
                'generated_at' => now()->toIso8601String(),
                'week_start' => $this->weekStart->toIso8601String(),
                'week_end' => $this->weekEnd->toIso8601String(),
                'organization_id' => $this->organizationId,
                'report_type' => 'weekly_huddle',
            ],
            'executive_summary' => $this->generateExecutiveSummary($hpgService, $missedCareService),
            'sla_compliance' => $this->generateSlaSection($hpgService, $missedCareService),
            'census' => $this->generateCensusSection(),
            'service_delivery' => $this->generateServiceDeliverySection(),
            'sspo_performance' => $this->generateSspoSection($missedCareService),
            'escalations' => $this->generateEscalationsSection(),
            'action_items' => $this->generateActionItems($hpgService, $missedCareService),
        ];
    }

    protected function generateExecutiveSummary(
        HpgResponseService $hpgService,
        MissedCareService $missedCareService
    ): array {
        $hpgMetrics = $hpgService->getComplianceMetrics($this->weekStart, $this->weekEnd, $this->organizationId);
        $missedMetrics = $missedCareService->calculate($this->organizationId, $this->weekStart, $this->weekEnd);

        $newReferrals = TriageResult::whereBetween('received_at', [$this->weekStart, $this->weekEnd])->count();
        $carePlansCreated = CarePlan::whereBetween('created_at', [$this->weekStart, $this->weekEnd])->count();

        return [
            'week_highlights' => [
                'new_referrals' => $newReferrals,
                'care_plans_created' => $carePlansCreated,
                'hpg_compliance_rate' => $hpgMetrics['compliance_rate'],
                'missed_care_rate' => $missedMetrics['missed_rate'],
            ],
            'compliance_status' => [
                'hpg_response' => $hpgMetrics['breached'] === 0 ? 'COMPLIANT' : 'BREACHED',
                'missed_care' => $missedMetrics['compliance'] ? 'COMPLIANT' : 'AT_RISK',
            ],
            'overall_health' => $this->calculateOverallHealth($hpgMetrics, $missedMetrics),
        ];
    }

    protected function generateSlaSection(
        HpgResponseService $hpgService,
        MissedCareService $missedCareService
    ): array {
        $hpgMetrics = $hpgService->getComplianceMetrics($this->weekStart, $this->weekEnd, $this->organizationId);
        $hpgDaily = $hpgService->getDailyStats($this->weekStart, $this->weekEnd, $this->organizationId);
        $missedMetrics = $missedCareService->calculate($this->organizationId, $this->weekStart, $this->weekEnd);
        $missedTrend = $missedCareService->getDailyTrend($this->organizationId, 7);

        // First service SLA
        $firstServiceQuery = CarePlan::query()
            ->whereNotNull('approved_at')
            ->whereBetween('approved_at', [$this->weekStart, $this->weekEnd]);

        $firstServiceTotal = (clone $firstServiceQuery)->count();
        $firstServiceWithin24h = (clone $firstServiceQuery)
            ->whereNotNull('first_service_delivered_at')
            ->whereRaw('TIMESTAMPDIFF(HOUR, approved_at, first_service_delivered_at) <= 24')
            ->count();
        $firstServiceBreaches = (clone $firstServiceQuery)
            ->whereNotNull('first_service_delivered_at')
            ->whereRaw('TIMESTAMPDIFF(HOUR, approved_at, first_service_delivered_at) > 24')
            ->count();

        return [
            'hpg_response_sla' => [
                'target' => '15 minutes',
                'total_referrals' => $hpgMetrics['total'],
                'responded_in_time' => $hpgMetrics['compliant'],
                'breaches' => $hpgMetrics['breached'],
                'compliance_rate' => $hpgMetrics['compliance_rate'],
                'average_response_minutes' => $hpgMetrics['average_response_minutes'],
                'daily_breakdown' => $hpgDaily,
            ],
            'first_service_sla' => [
                'target' => '24 hours',
                'total_care_plans' => $firstServiceTotal,
                'within_target' => $firstServiceWithin24h,
                'breaches' => $firstServiceBreaches,
                'compliance_rate' => $firstServiceTotal > 0
                    ? round(($firstServiceWithin24h / $firstServiceTotal) * 100, 1)
                    : 100,
            ],
            'missed_care' => [
                'target' => '0%',
                'total_scheduled' => $missedMetrics['total'],
                'delivered' => $missedMetrics['delivered'],
                'missed' => $missedMetrics['missed'],
                'missed_rate' => $missedMetrics['missed_rate'],
                'compliant' => $missedMetrics['compliance'],
                'daily_trend' => $missedTrend,
            ],
        ];
    }

    protected function generateCensusSection(): array
    {
        // Active patients with care plans
        $activePatients = Patient::whereHas('carePlans', function ($q) {
            $q->where('status', 'active');
        })->count();

        // New admissions this week
        $newAdmissions = Patient::whereBetween('created_at', [$this->weekStart, $this->weekEnd])->count();

        // Discharges (care plans archived this week)
        $discharges = CarePlan::where('status', 'archived')
            ->whereBetween('updated_at', [$this->weekStart, $this->weekEnd])
            ->count();

        // Patients by acuity
        $byAcuity = TriageResult::query()
            ->selectRaw('acuity_level, COUNT(DISTINCT patient_id) as count')
            ->whereHas('patient.carePlans', fn($q) => $q->where('status', 'active'))
            ->groupBy('acuity_level')
            ->pluck('count', 'acuity_level')
            ->toArray();

        return [
            'total_active' => $activePatients,
            'new_admissions' => $newAdmissions,
            'discharges' => $discharges,
            'net_change' => $newAdmissions - $discharges,
            'by_acuity' => [
                'critical' => $byAcuity['critical'] ?? 0,
                'high' => $byAcuity['high'] ?? 0,
                'medium' => $byAcuity['medium'] ?? 0,
                'low' => $byAcuity['low'] ?? 0,
            ],
        ];
    }

    protected function generateServiceDeliverySection(): array
    {
        $assignmentQuery = ServiceAssignment::query()
            ->whereBetween('scheduled_start', [$this->weekStart, $this->weekEnd]);

        if ($this->organizationId) {
            $assignmentQuery->where('service_provider_organization_id', $this->organizationId);
        }

        $total = (clone $assignmentQuery)->count();
        $completed = (clone $assignmentQuery)->where('status', 'completed')->count();
        $inProgress = (clone $assignmentQuery)->where('status', 'in_progress')->count();
        $pending = (clone $assignmentQuery)->where('status', 'pending')->count();
        $cancelled = (clone $assignmentQuery)->where('status', 'cancelled')->count();
        $missed = (clone $assignmentQuery)->where('status', 'missed')->count();

        // By service type
        $byServiceType = ServiceAssignment::query()
            ->selectRaw('service_type_id, status, COUNT(*) as count')
            ->whereBetween('scheduled_start', [$this->weekStart, $this->weekEnd])
            ->when($this->organizationId, fn($q) => $q->where('service_provider_organization_id', $this->organizationId))
            ->groupBy('service_type_id', 'status')
            ->with('serviceType:id,name,code')
            ->get()
            ->groupBy('service_type_id')
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'service_type' => $first->serviceType?->name ?? 'Unknown',
                    'code' => $first->serviceType?->code ?? 'N/A',
                    'statuses' => $items->pluck('count', 'status')->toArray(),
                    'total' => $items->sum('count'),
                ];
            })
            ->values();

        return [
            'summary' => [
                'total_scheduled' => $total,
                'completed' => $completed,
                'in_progress' => $inProgress,
                'pending' => $pending,
                'cancelled' => $cancelled,
                'missed' => $missed,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ],
            'by_service_type' => $byServiceType,
        ];
    }

    protected function generateSspoSection(MissedCareService $missedCareService): array
    {
        $sspoMetrics = $missedCareService->calculateBySspo($this->weekStart, $this->weekEnd);

        // SSPO acceptance metrics
        $sspoAcceptance = ServiceAssignment::query()
            ->selectRaw('service_provider_organization_id')
            ->selectRaw('SUM(CASE WHEN sspo_acceptance_status = "accepted" THEN 1 ELSE 0 END) as accepted')
            ->selectRaw('SUM(CASE WHEN sspo_acceptance_status = "declined" THEN 1 ELSE 0 END) as declined')
            ->selectRaw('SUM(CASE WHEN sspo_acceptance_status = "pending" THEN 1 ELSE 0 END) as pending')
            ->whereBetween('sspo_notified_at', [$this->weekStart, $this->weekEnd])
            ->where('sspo_acceptance_status', '!=', 'not_applicable')
            ->groupBy('service_provider_organization_id')
            ->get()
            ->map(function ($row) {
                $org = ServiceProviderOrganization::find($row->service_provider_organization_id);
                $total = $row->accepted + $row->declined;
                return [
                    'organization_id' => $row->service_provider_organization_id,
                    'organization_name' => $org?->name ?? 'Unknown',
                    'accepted' => (int) $row->accepted,
                    'declined' => (int) $row->declined,
                    'pending' => (int) $row->pending,
                    'acceptance_rate' => $total > 0 ? round(($row->accepted / $total) * 100, 1) : null,
                ];
            });

        return [
            'missed_care_by_sspo' => $sspoMetrics,
            'acceptance_metrics' => $sspoAcceptance,
        ];
    }

    protected function generateEscalationsSection(): array
    {
        // Escalated assignments
        $escalatedAssignments = ServiceAssignment::query()
            ->where('status', 'escalated')
            ->whereBetween('updated_at', [$this->weekStart, $this->weekEnd])
            ->with(['patient.user:id,name', 'serviceType:id,name,code'])
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'patient' => $a->patient?->user?->name,
                'service' => $a->serviceType?->name,
                'notes' => $a->notes,
                'updated_at' => $a->updated_at?->toIso8601String(),
            ]);

        // Crisis patients (high acuity with crisis designation)
        $crisisPatients = TriageResult::query()
            ->where('crisis_designation', true)
            ->whereBetween('received_at', [$this->weekStart, $this->weekEnd])
            ->with('patient.user:id,name')
            ->get()
            ->map(fn($t) => [
                'patient_id' => $t->patient_id,
                'patient_name' => $t->patient?->user?->name,
                'acuity' => $t->acuity_level,
                'received_at' => $t->received_at?->toIso8601String(),
            ]);

        return [
            'escalated_assignments' => $escalatedAssignments,
            'escalation_count' => $escalatedAssignments->count(),
            'crisis_patients' => $crisisPatients,
            'crisis_count' => $crisisPatients->count(),
        ];
    }

    protected function generateActionItems(
        HpgResponseService $hpgService,
        MissedCareService $missedCareService
    ): array {
        $actionItems = [];

        // Check HPG compliance
        $hpgMetrics = $hpgService->getComplianceMetrics($this->weekStart, $this->weekEnd, $this->organizationId);
        if ($hpgMetrics['breached'] > 0) {
            $actionItems[] = [
                'priority' => 'high',
                'category' => 'sla_compliance',
                'item' => "Review {$hpgMetrics['breached']} HPG response breaches and implement corrective action",
                'owner' => 'Operations Manager',
            ];
        }

        // Check missed care
        $missedMetrics = $missedCareService->calculate($this->organizationId, $this->weekStart, $this->weekEnd);
        if (!$missedMetrics['compliance']) {
            $actionItems[] = [
                'priority' => 'critical',
                'category' => 'missed_care',
                'item' => "Investigate {$missedMetrics['missed']} missed visits ({$missedMetrics['missed_rate']}%) - OHaH target is 0%",
                'owner' => 'Clinical Lead',
            ];
        }

        // Check SSPO acceptance
        $pendingSspo = ServiceAssignment::where('sspo_acceptance_status', 'pending')
            ->where('sspo_notified_at', '<', now()->subHours(24))
            ->count();
        if ($pendingSspo > 0) {
            $actionItems[] = [
                'priority' => 'medium',
                'category' => 'sspo_workflow',
                'item' => "Follow up on {$pendingSspo} SSPO assignments pending >24 hours",
                'owner' => 'Care Coordinator',
            ];
        }

        // Check stale InterRAI assessments
        $staleAssessments = Patient::whereHas('carePlans', fn($q) => $q->where('status', 'active'))
            ->whereDoesntHave('interraiAssessments', function ($q) {
                $q->where('assessment_date', '>=', now()->subMonths(3));
            })
            ->count();
        if ($staleAssessments > 0) {
            $actionItems[] = [
                'priority' => 'medium',
                'category' => 'interrai',
                'item' => "{$staleAssessments} active patients have InterRAI assessments >3 months old",
                'owner' => 'RN Assessor',
            ];
        }

        return $actionItems;
    }

    protected function calculateOverallHealth(array $hpgMetrics, array $missedMetrics): string
    {
        $score = 100;

        // Deduct for HPG breaches
        if ($hpgMetrics['breached'] > 0) {
            $score -= min(30, $hpgMetrics['breached'] * 10);
        }

        // Deduct for missed care
        if (!$missedMetrics['compliance']) {
            $score -= min(40, $missedMetrics['missed_rate'] * 10);
        }

        return match (true) {
            $score >= 90 => 'EXCELLENT',
            $score >= 75 => 'GOOD',
            $score >= 60 => 'NEEDS_ATTENTION',
            default => 'CRITICAL',
        };
    }
}
