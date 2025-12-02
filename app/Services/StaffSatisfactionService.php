<?php

namespace App\Services;

use App\Models\SatisfactionReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * StaffSatisfactionService
 * 
 * Computes staff satisfaction scores from patient-reported feedback.
 * Satisfaction is derived from SatisfactionReport records, NOT self-reported job satisfaction.
 * 
 * Rating Scale: 1-5 (Poor to Excellent)
 * Percentage: Converted to 0-100% scale for display
 */
class StaffSatisfactionService
{
    // Default period for satisfaction calculations (days)
    public const DEFAULT_PERIOD_DAYS = 90;
    
    // Minimum reports required for meaningful score
    public const MINIMUM_REPORTS = 3;

    /**
     * Get satisfaction score for a staff member.
     * 
     * @param int $staffUserId Staff user ID
     * @param int $days Period in days (default 90)
     * @return array{score: float|null, rating: float|null, count: int, period_days: int, label: string}
     */
    public function getStaffSatisfaction(int $staffUserId, int $days = self::DEFAULT_PERIOD_DAYS): array
    {
        $reports = SatisfactionReport::forStaff($staffUserId)
            ->lastDays($days)
            ->get();
        
        $count = $reports->count();
        
        if ($count < self::MINIMUM_REPORTS) {
            return [
                'score' => null,
                'rating' => null,
                'count' => $count,
                'period_days' => $days,
                'label' => 'Insufficient Data',
                'has_sufficient_data' => false,
            ];
        }
        
        $avgRating = $reports->avg('rating');
        $score = $this->ratingToPercent($avgRating);
        
        return [
            'score' => round($score, 1),
            'rating' => round($avgRating, 2),
            'count' => $count,
            'period_days' => $days,
            'label' => $this->getScoreLabel($score),
            'has_sufficient_data' => true,
        ];
    }

    /**
     * Get satisfaction breakdown by rating level for a staff member.
     * 
     * @return array{excellent: int, very_good: int, good: int, fair: int, poor: int}
     */
    public function getSatisfactionBreakdown(int $staffUserId, int $days = self::DEFAULT_PERIOD_DAYS): array
    {
        $reports = SatisfactionReport::forStaff($staffUserId)
            ->lastDays($days)
            ->get();
        
        return [
            'excellent' => $reports->where('rating', SatisfactionReport::RATING_EXCELLENT)->count(),
            'very_good' => $reports->where('rating', SatisfactionReport::RATING_VERY_GOOD)->count(),
            'good' => $reports->where('rating', SatisfactionReport::RATING_GOOD)->count(),
            'fair' => $reports->where('rating', SatisfactionReport::RATING_FAIR)->count(),
            'poor' => $reports->where('rating', SatisfactionReport::RATING_POOR)->count(),
            'total' => $reports->count(),
        ];
    }

    /**
     * Get recent satisfaction reports for a staff member.
     */
    public function getRecentReports(int $staffUserId, int $limit = 10): Collection
    {
        return SatisfactionReport::forStaff($staffUserId)
            ->with(['patient', 'serviceAssignment.serviceType'])
            ->orderBy('reported_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get satisfaction trend (weekly averages) for a staff member.
     * 
     * @return array Array of weekly averages [{week: string, score: float, count: int}]
     */
    public function getSatisfactionTrend(int $staffUserId, int $weeks = 12): array
    {
        $startDate = Carbon::now()->subWeeks($weeks)->startOfWeek();
        
        $reports = SatisfactionReport::forStaff($staffUserId)
            ->where('reported_at', '>=', $startDate)
            ->orderBy('reported_at')
            ->get();
        
        $trend = [];
        
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $startDate->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();
            
            $weekReports = $reports->filter(function ($report) use ($weekStart, $weekEnd) {
                return $report->reported_at->between($weekStart, $weekEnd);
            });
            
            $trend[] = [
                'week' => $weekStart->format('M d'),
                'score' => $weekReports->count() > 0 
                    ? round($this->ratingToPercent($weekReports->avg('rating')), 1)
                    : null,
                'count' => $weekReports->count(),
            ];
        }
        
        return $trend;
    }

    /**
     * Get organization-wide satisfaction average (for comparison).
     */
    public function getOrganizationAverage(?int $organizationId = null, int $days = self::DEFAULT_PERIOD_DAYS): ?float
    {
        $query = SatisfactionReport::lastDays($days);
        
        if ($organizationId) {
            $query->whereHas('staffUser', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            });
        }
        
        $avgRating = $query->avg('rating');
        
        return $avgRating ? round($this->ratingToPercent($avgRating), 1) : null;
    }

    /**
     * Convert 1-5 rating to 0-100% score.
     */
    protected function ratingToPercent(float $rating): float
    {
        // 1 = 0%, 5 = 100%
        return (($rating - 1) / 4) * 100;
    }

    /**
     * Get human-readable label for satisfaction score.
     */
    protected function getScoreLabel(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 75 => 'Very Good',
            $score >= 60 => 'Good',
            $score >= 40 => 'Fair',
            default => 'Needs Improvement',
        };
    }

    /**
     * Get badge color for satisfaction score.
     */
    public function getScoreColor(float $score): string
    {
        return match (true) {
            $score >= 90 => 'green',
            $score >= 75 => 'teal',
            $score >= 60 => 'blue',
            $score >= 40 => 'amber',
            default => 'red',
        };
    }
}
