<?php

namespace App\Services\BundleEngine\Contracts;

use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\DTOs\ScenarioBundleDTO;
use App\Services\BundleEngine\Enums\ScenarioAxis;

/**
 * ScenarioGeneratorInterface
 *
 * Contract for generating scenario bundles based on patient needs profile.
 *
 * The generator produces 3-5 scenario bundles that:
 * - Meet minimum clinical safety requirements
 * - Vary in emphasis (recovery, stability, tech, caregiver support, etc.)
 * - Are annotated with cost and operational metrics
 * - Include patient-experience oriented descriptions
 *
 * Implementations may use:
 * - Rule-based generation (Phase 1)
 * - Template-based generation (Phase 1)
 * - AI-assisted generation (Future)
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 5
 */
interface ScenarioGeneratorInterface
{
    /**
     * Generate scenario bundles for a patient.
     *
     * @param PatientNeedsProfile $profile The patient's needs profile
     * @param array<string, mixed> $options Optional configuration:
     *   - 'min_scenarios': int - Minimum scenarios to generate (default: 3)
     *   - 'max_scenarios': int - Maximum scenarios to generate (default: 5)
     *   - 'required_axes': array<ScenarioAxis> - Axes that must be included
     *   - 'excluded_axes': array<ScenarioAxis> - Axes to exclude
     *   - 'include_balanced': bool - Always include balanced option (default: true)
     *   - 'reference_cap': float - Weekly cost reference cap (default: 5000)
     *
     * @return array<ScenarioBundleDTO> Generated scenarios in recommended order
     */
    public function generateScenarios(PatientNeedsProfile $profile, array $options = []): array;

    /**
     * Generate a single scenario for a specific axis.
     *
     * @param PatientNeedsProfile $profile The patient's needs profile
     * @param ScenarioAxis $axis The primary axis to optimize for
     * @param array<ScenarioAxis> $secondaryAxes Optional secondary axes
     * @param array<string, mixed> $options Additional configuration
     *
     * @return ScenarioBundleDTO The generated scenario
     */
    public function generateSingleScenario(
        PatientNeedsProfile $profile,
        ScenarioAxis $axis,
        array $secondaryAxes = [],
        array $options = []
    ): ScenarioBundleDTO;

    /**
     * Validate a scenario bundle against safety requirements.
     *
     * Checks that the scenario:
     * - Addresses identified clinical risks
     * - Includes required core services
     * - Meets minimum care thresholds
     *
     * @param ScenarioBundleDTO $scenario The scenario to validate
     * @param PatientNeedsProfile $profile The patient's needs profile
     *
     * @return array{valid: bool, warnings: array<string>, errors: array<string>}
     */
    public function validateScenario(ScenarioBundleDTO $scenario, PatientNeedsProfile $profile): array;

    /**
     * Compare two scenarios and return difference analysis.
     *
     * @param ScenarioBundleDTO $scenario1 First scenario
     * @param ScenarioBundleDTO $scenario2 Second scenario
     *
     * @return array{
     *   services_added: array<string>,
     *   services_removed: array<string>,
     *   frequency_changes: array<string>,
     *   cost_difference: float,
     *   hours_difference: float,
     *   emphasis_shift: string
     * }
     */
    public function compareScenarios(ScenarioBundleDTO $scenario1, ScenarioBundleDTO $scenario2): array;

    /**
     * Get the applicable scenario axes for a profile.
     *
     * This should delegate to ScenarioAxisSelector.
     *
     * @param PatientNeedsProfile $profile The patient's needs profile
     * @param int $maxAxes Maximum axes to return
     *
     * @return array<ScenarioAxis> Applicable axes in priority order
     */
    public function getApplicableAxes(PatientNeedsProfile $profile, int $maxAxes = 4): array;
}

