<?php

namespace App\Services\Llm\DTOs;

use Carbon\Carbon;

/**
 * ExplanationResponseDTO
 *
 * Data transfer object for LLM-generated or rules-based explanations.
 * Contains the explanation content and metadata about how it was generated.
 */
class ExplanationResponseDTO
{
    public function __construct(
        /**
         * Short 1-2 sentence explanation summary.
         */
        public readonly string $shortExplanation,

        /**
         * Array of detailed bullet points explaining the match factors.
         * @var string[]
         */
        public readonly array $detailedPoints,

        /**
         * Confidence label: "High Match", "Good Match", "Acceptable", "Limited Options", "No Match"
         */
        public readonly string $confidenceLabel,

        /**
         * Source of the explanation: "vertex_ai" or "rules_based"
         */
        public readonly string $source,

        /**
         * When the explanation was generated.
         */
        public readonly Carbon $generatedAt,

        /**
         * Response time in milliseconds (null if not measured).
         */
        public readonly ?int $responseTimeMs = null,
    ) {}

    /**
     * Create a fallback explanation when something goes wrong.
     */
    public static function fallback(string $reason = 'Unable to generate explanation'): self
    {
        return new self(
            shortExplanation: $reason,
            detailedPoints: [],
            confidenceLabel: 'Unknown',
            source: 'fallback',
            generatedAt: Carbon::now(),
        );
    }

    /**
     * Create a "queued" response when explanation is being generated asynchronously.
     */
    public static function queued(): self
    {
        return new self(
            shortExplanation: 'Explanation is being generated...',
            detailedPoints: [],
            confidenceLabel: 'Pending',
            source: 'queued',
            generatedAt: Carbon::now(),
        );
    }

    /**
     * Convert to array for API responses.
     */
    public function toArray(): array
    {
        return [
            'short_explanation' => $this->shortExplanation,
            'detailed_points' => $this->detailedPoints,
            'confidence_label' => $this->confidenceLabel,
            'source' => $this->source,
            'generated_at' => $this->generatedAt->toIso8601String(),
            'response_time_ms' => $this->responseTimeMs,
        ];
    }
}
