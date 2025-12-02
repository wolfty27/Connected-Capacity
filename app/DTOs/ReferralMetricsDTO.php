<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * Data Transfer Object for Referral Acceptance Metrics
 *
 * Per OHaH RFP (Appendix 1):
 * Referral Acceptance Rate = (accepted referrals / total referrals) × 100%
 * Target: 100% (SPO must accept all referrals in their service area)
 *
 * Band thresholds:
 * - Band A: 100%
 * - Band B: 95-99.9%
 * - Band C: <95%
 */
class ReferralMetricsDTO
{
    public function __construct(
        public readonly float $ratePercent,
        public readonly int $accepted,
        public readonly int $total,
        public readonly int $pending,
        public readonly int $rejected,
        public readonly string $band,
        public readonly Carbon $periodStart,
        public readonly Carbon $periodEnd
    ) {}

    /**
     * Create DTO from calculation results.
     */
    public static function fromCalculation(
        int $accepted,
        int $pending,
        int $rejected,
        Carbon $startDate,
        Carbon $endDate
    ): self {
        $total = $accepted + $pending + $rejected;
        $rate = $total > 0 ? round(($accepted / $total) * 100, 2) : 100.0;
        
        $band = match (true) {
            $rate >= 100.0 => 'A',
            $rate >= 95.0 => 'B',
            default => 'C',
        };

        return new self(
            ratePercent: $rate,
            accepted: $accepted,
            total: $total,
            pending: $pending,
            rejected: $rejected,
            band: $band,
            periodStart: $startDate,
            periodEnd: $endDate
        );
    }

    /**
     * Check if compliant (Band A = 100%).
     */
    public function isCompliant(): bool
    {
        return $this->band === 'A';
    }

    /**
     * Get the band threshold description.
     */
    public function getBandDescription(): string
    {
        return match ($this->band) {
            'A' => '100% (Meets Target)',
            'B' => '95-99.9% (Below Standard)',
            'C' => '<95% (Action Required)',
            default => $this->band,
        };
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'rate_percent' => $this->ratePercent,
            'accepted' => $this->accepted,
            'total' => $this->total,
            'pending' => $this->pending,
            'rejected' => $this->rejected,
            'band' => $this->band,
            'band_description' => $this->getBandDescription(),
            'is_compliant' => $this->isCompliant(),
            'period' => [
                'start' => $this->periodStart->toIso8601String(),
                'end' => $this->periodEnd->toIso8601String(),
            ],
        ];
    }
}
