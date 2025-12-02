<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * Data Transfer Object for Time-to-First-Service Metrics
 *
 * Per OHaH RFP (Appendix 1):
 * Time-to-First-Service = time from referral acceptance to first completed visit
 * Target: <24 hours
 *
 * Band thresholds:
 * - Band A: <24 hours (Meets Target)
 * - Band B: 24-48 hours (Below Standard)
 * - Band C: >48 hours (Action Required)
 */
class TfsMetricsDTO
{
    public function __construct(
        public readonly float $averageHours,
        public readonly ?float $medianHours,
        public readonly int $patientsWithFirstService,
        public readonly int $patientsTotal,
        public readonly string $band,
        public readonly Carbon $periodStart,
        public readonly Carbon $periodEnd
    ) {}

    /**
     * Create DTO from calculation results.
     */
    public static function fromCalculation(
        float $averageHours,
        ?float $medianHours,
        int $patientsWithFirstService,
        int $patientsTotal,
        Carbon $startDate,
        Carbon $endDate
    ): self {
        $band = match (true) {
            $averageHours < 24.0 => 'A',
            $averageHours <= 48.0 => 'B',
            default => 'C',
        };

        return new self(
            averageHours: round($averageHours, 1),
            medianHours: $medianHours !== null ? round($medianHours, 1) : null,
            patientsWithFirstService: $patientsWithFirstService,
            patientsTotal: $patientsTotal,
            band: $band,
            periodStart: $startDate,
            periodEnd: $endDate
        );
    }

    /**
     * Check if compliant (Band A < 24h).
     */
    public function isCompliant(): bool
    {
        return $this->band === 'A';
    }

    /**
     * Get formatted average time (e.g., "18.2h").
     */
    public function getFormattedAverage(): string
    {
        return number_format($this->averageHours, 1) . 'h';
    }

    /**
     * Get the band threshold description.
     */
    public function getBandDescription(): string
    {
        return match ($this->band) {
            'A' => '<24h (Meets Target)',
            'B' => '24-48h (Below Standard)',
            'C' => '>48h (Action Required)',
            default => $this->band,
        };
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'average_hours' => $this->averageHours,
            'median_hours' => $this->medianHours,
            'patients_with_first_service' => $this->patientsWithFirstService,
            'patients_total' => $this->patientsTotal,
            'band' => $this->band,
            'band_description' => $this->getBandDescription(),
            'is_compliant' => $this->isCompliant(),
            'formatted_average' => $this->getFormattedAverage(),
            'period' => [
                'start' => $this->periodStart->toIso8601String(),
                'end' => $this->periodEnd->toIso8601String(),
            ],
        ];
    }
}
