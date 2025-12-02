<?php

namespace App\Services\Llm\Contracts;

use App\Services\Llm\DTOs\ExplanationResponseDTO;
use App\Services\Scheduling\DTOs\AssignmentSuggestionDTO;

/**
 * ExplanationProviderInterface
 *
 * Contract for explanation providers (Vertex AI, fallback rules-based, etc.)
 * Implementations must be able to generate explanations from AssignmentSuggestionDTOs.
 */
interface ExplanationProviderInterface
{
    /**
     * Generate an explanation for why a staff member was suggested.
     *
     * @param AssignmentSuggestionDTO $suggestion The assignment suggestion to explain
     * @return ExplanationResponseDTO The generated explanation
     */
    public function generateExplanation(AssignmentSuggestionDTO $suggestion): ExplanationResponseDTO;

    /**
     * Generate an explanation for why no staff match was found.
     *
     * @param array $exclusionReasons Array of reasons why staff were excluded
     * @return ExplanationResponseDTO The generated explanation
     */
    public function generateNoMatchExplanation(array $exclusionReasons): ExplanationResponseDTO;

    /**
     * Check if this provider is available and can generate explanations.
     *
     * @return bool True if the provider is available
     */
    public function isAvailable(): bool;

    /**
     * Get the provider name for logging/audit purposes.
     *
     * @return string Provider identifier (e.g., 'vertex_ai', 'rules_based')
     */
    public function getProviderName(): string;
}
