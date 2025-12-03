<?php

namespace App\Services\BundleEngine;

use App\Services\BundleEngine\Contracts\CostAnnotationServiceInterface;
use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;
use App\Services\BundleEngine\DTOs\ScenarioServiceLine;
use App\Services\BundleEngine\Enums\ScenarioAxis;

/**
 * CostAnnotationService
 *
 * Annotates scenarios with cost and operational metrics.
 *
 * DESIGN PRINCIPLE: Cost is a REFERENCE, not a hard constraint.
 * We annotate scenarios to help with planning but never frame as
 * "budget vs clinical" dichotomies.
 *
 * Patient-centered cost notes explain trade-offs in terms of
 * patient experience and outcomes, not just dollars.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 7
 */
class CostAnnotationService implements CostAnnotationServiceInterface
{
    /**
     * Annotate a scenario with cost and operational metrics.
     *
     * @inheritDoc
     */
    public function annotateScenario(
        ScenarioBundleDTO $scenario,
        float $referenceCap = self::DEFAULT_REFERENCE_CAP
    ): ScenarioBundleDTO {
        // Calculate costs
        $weeklyCost = $this->calculateTotalWeeklyCost($scenario->serviceLines);
        $costStatus = $this->determineCostStatus($weeklyCost, $referenceCap);
        $capUtilization = ($weeklyCost / $referenceCap) * 100;

        // Calculate operational metrics
        $operationalMetrics = $this->calculateOperationalMetrics($scenario->serviceLines);

        // Generate patient-centered cost note
        $costNote = $this->generateCostNote($scenario, $referenceCap);

        // Return annotated scenario (immutable DTO, so create new instance)
        return new ScenarioBundleDTO(
            scenarioId: $scenario->scenarioId,
            patientId: $scenario->patientId,
            primaryAxis: $scenario->primaryAxis,
            title: $scenario->title,
            description: $scenario->description,
            serviceLines: $scenario->serviceLines,
            secondaryAxes: $scenario->secondaryAxes,
            subtitle: $scenario->subtitle,
            icon: $scenario->icon,

            // Updated cost fields
            weeklyEstimatedCost: round($weeklyCost, 2),
            referenceCap: $referenceCap,
            costStatus: $costStatus,
            capUtilization: round($capUtilization, 1),
            costNote: $costNote,

            // Updated operational metrics
            totalWeeklyHours: round($operationalMetrics['total_weekly_hours'], 1),
            totalWeeklyVisits: $operationalMetrics['total_weekly_visits'],
            inPersonPercentage: round($operationalMetrics['in_person_percentage'], 1),
            virtualPercentage: round($operationalMetrics['virtual_percentage'], 1),
            disciplineCount: $operationalMetrics['discipline_count'],

            // Preserve other fields
            tradeOffs: $scenario->tradeOffs,
            keyBenefits: $scenario->keyBenefits,
            patientGoalsSupported: $scenario->patientGoalsSupported,
            risksAddressed: $scenario->risksAddressed,
            meetsSafetyRequirements: $scenario->meetsSafetyRequirements,
            safetyWarnings: $scenario->safetyWarnings,
            isValidated: $scenario->isValidated,
            source: $scenario->source,
            confidenceLevel: $scenario->confidenceLevel,
            confidenceNotes: $scenario->confidenceNotes,
            aiExplanation: $scenario->aiExplanation,
            hasAiExplanation: $scenario->hasAiExplanation,
            generatedAt: $scenario->generatedAt,
            displayOrder: $scenario->displayOrder,
            isRecommended: $scenario->isRecommended,
        );
    }

    /**
     * Calculate weekly cost for a service line.
     *
     * @inheritDoc
     */
    public function calculateServiceLineCost(ScenarioServiceLine $serviceLine): float
    {
        // If weekly cost is already calculated, use it
        if ($serviceLine->weeklyEstimatedCost > 0) {
            return $serviceLine->weeklyEstimatedCost;
        }

        // Calculate from per-visit cost and frequency
        $weeklyVisits = $serviceLine->getWeeklyVisits();

        return $weeklyVisits * $serviceLine->costPerVisit;
    }

    /**
     * Calculate total weekly cost for a list of service lines.
     *
     * @inheritDoc
     */
    public function calculateTotalWeeklyCost(array $serviceLines): float
    {
        $total = 0.0;

        foreach ($serviceLines as $line) {
            $total += $this->calculateServiceLineCost($line);
        }

        return $total;
    }

    /**
     * Determine cost status based on cap utilization.
     *
     * @inheritDoc
     */
    public function determineCostStatus(float $weeklyCost, float $referenceCap): string
    {
        $utilization = $weeklyCost / $referenceCap;

        if ($utilization <= self::CAP_THRESHOLD_WITHIN) {
            return 'within_cap';
        }

        if ($utilization <= self::CAP_THRESHOLD_NEAR) {
            return 'near_cap';
        }

        return 'over_cap';
    }

    /**
     * Generate a patient-centered cost note.
     *
     * This note explains the cost in patient-experience terms,
     * NOT as "budget vs clinical" trade-offs.
     *
     * @inheritDoc
     */
    public function generateCostNote(ScenarioBundleDTO $scenario, float $referenceCap): string
    {
        $weeklyCost = $this->calculateTotalWeeklyCost($scenario->serviceLines);
        $status = $this->determineCostStatus($weeklyCost, $referenceCap);
        $utilization = ($weeklyCost / $referenceCap) * 100;

        // Generate note based on axis and cost status
        $axisNotes = $this->getAxisCostNotes($scenario->primaryAxis);
        $statusNote = $this->getStatusNote($status, $utilization);

        return "{$axisNotes} {$statusNote}";
    }

    /**
     * Get axis-specific cost notes.
     */
    protected function getAxisCostNotes(ScenarioAxis $axis): string
    {
        return match ($axis) {
            ScenarioAxis::RECOVERY_REHAB =>
                'Therapy-intensive approach to support recovery goals.',
            ScenarioAxis::SAFETY_STABILITY =>
                'Consistent daily support for safety and stability.',
            ScenarioAxis::TECH_ENABLED =>
                'Remote monitoring reduces in-person visits while maintaining oversight.',
            ScenarioAxis::CAREGIVER_RELIEF =>
                'Includes family support services to sustain caregiving.',
            ScenarioAxis::MEDICAL_INTENSIVE =>
                'High clinical intensity for complex medical needs.',
            ScenarioAxis::COGNITIVE_SUPPORT =>
                'Specialized support for cognitive and behavioural needs.',
            ScenarioAxis::COMMUNITY_INTEGRATED =>
                'Community programs provide social connection and structure.',
            ScenarioAxis::BALANCED =>
                'Balanced allocation across all care domains.',
        };
    }

    /**
     * Get status-specific cost note.
     */
    protected function getStatusNote(string $status, float $utilization): string
    {
        return match ($status) {
            'within_cap' => sprintf(
                'Resource use at %.0f%% of typical care parameters.',
                $utilization
            ),
            'near_cap' => sprintf(
                'Resource use at %.0f%% - within typical range for this level of need.',
                $utilization
            ),
            'over_cap' => sprintf(
                'Resource use at %.0f%% reflects intensive service needs - may be appropriate for complexity.',
                $utilization
            ),
            default => '',
        };
    }

    /**
     * Calculate operational metrics for a scenario.
     *
     * @inheritDoc
     */
    public function calculateOperationalMetrics(array $serviceLines): array
    {
        $totalHours = 0.0;
        $totalVisits = 0;
        $inPersonVisits = 0;
        $virtualVisits = 0;
        $disciplines = [];

        foreach ($serviceLines as $line) {
            $weeklyVisits = $line->getWeeklyVisits();
            $weeklyHours = $line->getWeeklyHours();

            $totalHours += $weeklyHours;
            $totalVisits += (int) round($weeklyVisits);

            // Track delivery mode
            if ($line->deliveryMode === 'virtual' || $line->deliveryMode === 'automated') {
                $virtualVisits += (int) round($weeklyVisits);
            } else {
                $inPersonVisits += (int) round($weeklyVisits);
            }

            // Track disciplines
            $disciplines[$line->discipline] = true;
        }

        // Calculate percentages
        $inPersonPercentage = $totalVisits > 0
            ? ($inPersonVisits / $totalVisits) * 100
            : 100;
        $virtualPercentage = $totalVisits > 0
            ? ($virtualVisits / $totalVisits) * 100
            : 0;

        return [
            'total_weekly_hours' => $totalHours,
            'total_weekly_visits' => $totalVisits,
            'in_person_percentage' => $inPersonPercentage,
            'virtual_percentage' => $virtualPercentage,
            'discipline_count' => count($disciplines),
            'disciplines' => array_keys($disciplines),
        ];
    }

    /**
     * Get cost breakdown by service category.
     *
     * @inheritDoc
     */
    public function getCostBreakdownByCategory(array $serviceLines): array
    {
        $breakdown = [];
        $totalCost = $this->calculateTotalWeeklyCost($serviceLines);

        foreach ($serviceLines as $line) {
            $category = $line->serviceCategory;
            $cost = $this->calculateServiceLineCost($line);

            if (!isset($breakdown[$category])) {
                $breakdown[$category] = [
                    'weekly_cost' => 0,
                    'percentage' => 0,
                    'service_count' => 0,
                ];
            }

            $breakdown[$category]['weekly_cost'] += $cost;
            $breakdown[$category]['service_count']++;
        }

        // Calculate percentages
        if ($totalCost > 0) {
            foreach ($breakdown as $category => $data) {
                $breakdown[$category]['percentage'] = round(
                    ($data['weekly_cost'] / $totalCost) * 100,
                    1
                );
                $breakdown[$category]['weekly_cost'] = round($data['weekly_cost'], 2);
            }
        }

        return $breakdown;
    }

    /**
     * Get cost breakdown by discipline.
     *
     * @inheritDoc
     */
    public function getCostBreakdownByDiscipline(array $serviceLines): array
    {
        $breakdown = [];
        $totalCost = $this->calculateTotalWeeklyCost($serviceLines);

        foreach ($serviceLines as $line) {
            $discipline = $line->discipline;
            $cost = $this->calculateServiceLineCost($line);
            $hours = $line->getWeeklyHours();

            if (!isset($breakdown[$discipline])) {
                $breakdown[$discipline] = [
                    'weekly_cost' => 0,
                    'percentage' => 0,
                    'hours' => 0,
                ];
            }

            $breakdown[$discipline]['weekly_cost'] += $cost;
            $breakdown[$discipline]['hours'] += $hours;
        }

        // Calculate percentages
        if ($totalCost > 0) {
            foreach ($breakdown as $discipline => $data) {
                $breakdown[$discipline]['percentage'] = round(
                    ($data['weekly_cost'] / $totalCost) * 100,
                    1
                );
                $breakdown[$discipline]['weekly_cost'] = round($data['weekly_cost'], 2);
                $breakdown[$discipline]['hours'] = round($data['hours'], 1);
            }
        }

        return $breakdown;
    }

    /**
     * Generate comparison notes between two scenarios.
     */
    public function generateComparisonNote(ScenarioBundleDTO $scenario1, ScenarioBundleDTO $scenario2): string
    {
        $cost1 = $this->calculateTotalWeeklyCost($scenario1->serviceLines);
        $cost2 = $this->calculateTotalWeeklyCost($scenario2->serviceLines);
        $costDiff = $cost2 - $cost1;

        $hours1 = array_sum(array_map(fn($l) => $l->getWeeklyHours(), $scenario1->serviceLines));
        $hours2 = array_sum(array_map(fn($l) => $l->getWeeklyHours(), $scenario2->serviceLines));
        $hoursDiff = $hours2 - $hours1;

        $notes = [];

        if (abs($costDiff) > 100) {
            $direction = $costDiff > 0 ? 'higher' : 'lower';
            $notes[] = sprintf(
                '%s is $%.0f/week %s in resource use',
                $scenario2->title,
                abs($costDiff),
                $direction
            );
        }

        if (abs($hoursDiff) > 2) {
            $direction = $hoursDiff > 0 ? 'more' : 'fewer';
            $notes[] = sprintf(
                '%.1f %s hours of direct service per week',
                abs($hoursDiff),
                $direction
            );
        }

        return implode('; ', $notes);
    }
}

