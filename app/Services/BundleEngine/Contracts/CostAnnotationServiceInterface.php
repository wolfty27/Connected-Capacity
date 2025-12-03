<?php

namespace App\Services\BundleEngine\Contracts;

use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;
use App\Services\BundleEngine\DTOs\ScenarioServiceLine;

/**
 * CostAnnotationServiceInterface
 *
 * Contract for annotating scenarios with cost and operational metrics.
 *
 * This service:
 * - Calculates weekly costs for services and scenarios
 * - Determines cost status relative to reference cap
 * - Generates patient-centered cost notes (NOT "budget vs clinical")
 * - Calculates operational metrics (hours, visits, disciplines)
 *
 * DESIGN PRINCIPLE: Cost is a REFERENCE, not a hard constraint.
 * Annotations help with planning without creating "budget vs clinical" dichotomies.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 7
 */
interface CostAnnotationServiceInterface
{
    /**
     * Default weekly cost reference cap.
     */
    public const DEFAULT_REFERENCE_CAP = 5000.0;

    /**
     * Cap utilization thresholds.
     */
    public const CAP_THRESHOLD_WITHIN = 0.85;  // < 85% = "within cap"
    public const CAP_THRESHOLD_NEAR = 1.0;     // 85-100% = "near cap"
    // > 100% = "over cap"

    /**
     * Annotate a scenario with cost and operational metrics.
     *
     * @param ScenarioBundleDTO $scenario The scenario to annotate
     * @param float $referenceCap Weekly cost reference cap
     *
     * @return ScenarioBundleDTO Scenario with cost annotations added
     */
    public function annotateScenario(
        ScenarioBundleDTO $scenario,
        float $referenceCap = self::DEFAULT_REFERENCE_CAP
    ): ScenarioBundleDTO;

    /**
     * Calculate weekly cost for a service line.
     *
     * @param ScenarioServiceLine $serviceLine The service line
     *
     * @return float Weekly cost estimate
     */
    public function calculateServiceLineCost(ScenarioServiceLine $serviceLine): float;

    /**
     * Calculate total weekly cost for a list of service lines.
     *
     * @param array<ScenarioServiceLine> $serviceLines Service lines
     *
     * @return float Total weekly cost
     */
    public function calculateTotalWeeklyCost(array $serviceLines): float;

    /**
     * Determine cost status based on cap utilization.
     *
     * @param float $weeklyCost Weekly cost
     * @param float $referenceCap Reference cap
     *
     * @return string Status: 'within_cap', 'near_cap', 'over_cap'
     */
    public function determineCostStatus(float $weeklyCost, float $referenceCap): string;

    /**
     * Generate a patient-centered cost note.
     *
     * This note explains the cost in patient-experience terms,
     * NOT as "budget vs clinical" trade-offs.
     *
     * Examples:
     * - "Front-loaded therapy to accelerate recovery"
     * - "Balanced services within typical care parameters"
     * - "Enhanced support for complex needs"
     *
     * @param ScenarioBundleDTO $scenario The scenario
     * @param float $referenceCap Reference cap
     *
     * @return string Patient-centered cost note
     */
    public function generateCostNote(ScenarioBundleDTO $scenario, float $referenceCap): string;

    /**
     * Calculate operational metrics for a scenario.
     *
     * @param array<ScenarioServiceLine> $serviceLines Service lines
     *
     * @return array{
     *   total_weekly_hours: float,
     *   total_weekly_visits: int,
     *   in_person_percentage: float,
     *   virtual_percentage: float,
     *   discipline_count: int,
     *   disciplines: array<string>
     * }
     */
    public function calculateOperationalMetrics(array $serviceLines): array;

    /**
     * Get cost breakdown by service category.
     *
     * @param array<ScenarioServiceLine> $serviceLines Service lines
     *
     * @return array<string, array{
     *   weekly_cost: float,
     *   percentage: float,
     *   service_count: int
     * }>
     */
    public function getCostBreakdownByCategory(array $serviceLines): array;

    /**
     * Get cost breakdown by discipline.
     *
     * @param array<ScenarioServiceLine> $serviceLines Service lines
     *
     * @return array<string, array{
     *   weekly_cost: float,
     *   percentage: float,
     *   hours: float
     * }>
     */
    public function getCostBreakdownByDiscipline(array $serviceLines): array;
}

