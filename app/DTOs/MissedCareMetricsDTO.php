<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * Data Transfer Object for Missed Care Metrics
 *
 * Per OHaH RFP (Appendix 1):
 * Missed Care Rate = (# missed care events) / (# delivered visits + # missed care events)
 * Target: 0%
 */
class MissedCareMetricsDTO
{
    public function __construct(
        public readonly int $missedEvents,
        public readonly int $deliveredEvents,
        public readonly int $totalEvents,
        public readonly float $ratePercent,
        public readonly bool $isCompliant,
        public readonly Carbon $periodStart,
        public readonly Carbon $periodEnd,
        public readonly string $riskLevel = 'low',
        public readonly string $message = ''
    ) {}

    /**
     * Create DTO from calculation arrays.
     */
    public static function fromCalculation(
        int $missed,
        int $delivered,
        Carbon $startDate,
        Carbon $endDate
    ): self {
        $total = $missed + $delivered;
        $rate = $total > 0 ? round(($missed / $total) * 100, 2) : 0.0;
        $isCompliant = $rate === 0.0;

        $riskLevel = match (true) {
            $rate === 0.0 => 'low',
            $rate <= 0.5 => 'medium',
            $rate <= 2.0 => 'high',
            default => 'critical',
        };

        $message = match ($riskLevel) {
            'low' => 'Fully compliant - 0% missed care',
            'medium' => 'Minor variance detected',
            'high' => 'Warning: Missed care rate approaching risk threshold',
            'critical' => 'Critical: Significant missed care - immediate action required',
        };

        return new self(
            missedEvents: $missed,
            deliveredEvents: $delivered,
            totalEvents: $total,
            ratePercent: $rate,
            isCompliant: $isCompliant,
            periodStart: $startDate,
            periodEnd: $endDate,
            riskLevel: $riskLevel,
            message: $message
        );
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'missed_events' => $this->missedEvents,
            'delivered_events' => $this->deliveredEvents,
            'total_events' => $this->totalEvents,
            'rate_percent' => $this->ratePercent,
            'is_compliant' => $this->isCompliant,
            'risk_level' => $this->riskLevel,
            'message' => $this->message,
            'period' => [
                'start' => $this->periodStart->toIso8601String(),
                'end' => $this->periodEnd->toIso8601String(),
            ],
        ];
    }

    /**
     * Get compliance band based on rate.
     * Band A: 0%, Band B: 0.01-0.5%, Band C: >0.5%
     */
    public function getComplianceBand(): string
    {
        return match (true) {
            $this->ratePercent === 0.0 => 'A',
            $this->ratePercent <= 0.5 => 'B',
            default => 'C',
        };
    }
}
