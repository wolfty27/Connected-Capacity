<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\SatisfactionReport;
use App\Models\ServiceAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * SatisfactionReportSeeder
 * 
 * Seeds patient satisfaction feedback for completed service assignments.
 * Staff satisfaction scores are derived from these reports.
 * 
 * Creates realistic feedback distribution:
 * - ~70% of completed visits have feedback
 * - Ratings biased toward positive (70% 4-5 stars)
 * - Some staff have higher/lower average ratings for demo variation
 */
class SatisfactionReportSeeder extends Seeder
{
    // Probability that a completed visit has feedback (0.0 - 1.0)
    protected float $feedbackRate = 0.70;
    
    // Rating distribution (biased toward positive)
    // Keys are ratings (1-5), values are weights
    protected array $ratingDistribution = [
        1 => 2,   // Poor - 2% 
        2 => 5,   // Fair - 5%
        3 => 15,  // Good - 15%
        4 => 35,  // Very Good - 35%
        5 => 43,  // Excellent - 43%
    ];
    
    // Staff variation modifiers (some staff have higher/lower ratings)
    protected array $staffVariation = [];
    
    public function run(): void
    {
        $this->command->info('Seeding satisfaction reports...');
        
        // Get completed assignments from last 90 days
        $assignments = ServiceAssignment::where('status', ServiceAssignment::STATUS_COMPLETED)
            ->where('scheduled_start', '>=', Carbon::now()->subDays(90))
            ->whereNotNull('assigned_user_id')
            ->with(['patient', 'assignedUser'])
            ->get();
        
        if ($assignments->isEmpty()) {
            $this->command->warn('No completed assignments found. Run WorkforceSeeder first.');
            return;
        }
        
        $this->command->info("  Found {$assignments->count()} completed assignments");
        
        // Set staff variation modifiers for demo
        $this->initStaffVariation($assignments->pluck('assigned_user_id')->unique()->toArray());
        
        $createdCount = 0;
        
        foreach ($assignments as $assignment) {
            // Skip if report already exists
            if (SatisfactionReport::where('service_assignment_id', $assignment->id)->exists()) {
                continue;
            }
            
            // Random chance to have feedback
            if (mt_rand(1, 100) > ($this->feedbackRate * 100)) {
                continue;
            }
            
            // Generate rating (with staff variation)
            $rating = $this->generateRating($assignment->assigned_user_id);
            
            // Create satisfaction report
            SatisfactionReport::create([
                'service_assignment_id' => $assignment->id,
                'patient_id' => $assignment->patient_id,
                'staff_user_id' => $assignment->assigned_user_id,
                'rating' => $rating,
                'feedback_text' => $this->generateFeedbackText($rating),
                'reported_at' => $this->generateReportedAt($assignment),
                'reporter_type' => $this->randomReporterType(),
            ]);
            
            $createdCount++;
        }
        
        $this->command->info("  Created {$createdCount} satisfaction reports");
        
        // Log average ratings per staff
        $this->logStaffSummary();
    }
    
    /**
     * Initialize staff variation modifiers.
     * Some staff get consistently higher/lower ratings.
     */
    protected function initStaffVariation(array $staffIds): void
    {
        foreach ($staffIds as $staffId) {
            // Random variation from -1 to +1
            // Most staff get 0 (no variation), some get -1 or +1
            $roll = mt_rand(1, 100);
            if ($roll <= 10) {
                $this->staffVariation[$staffId] = -1; // Lower ratings
            } elseif ($roll <= 25) {
                $this->staffVariation[$staffId] = 1;  // Higher ratings
            } else {
                $this->staffVariation[$staffId] = 0;  // Normal
            }
        }
    }
    
    /**
     * Generate a rating based on distribution and staff variation.
     */
    protected function generateRating(int $staffId): int
    {
        // Build weighted array
        $pool = [];
        foreach ($this->ratingDistribution as $rating => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $pool[] = $rating;
            }
        }
        
        // Pick random rating
        $rating = $pool[array_rand($pool)];
        
        // Apply staff variation
        $variation = $this->staffVariation[$staffId] ?? 0;
        $rating = max(1, min(5, $rating + $variation));
        
        return $rating;
    }
    
    /**
     * Generate feedback text based on rating.
     */
    protected function generateFeedbackText(int $rating): ?string
    {
        // Only 60% of reports have text feedback
        if (mt_rand(1, 100) > 60) {
            return null;
        }
        
        $texts = [
            1 => [
                'Service did not meet expectations.',
                'Disappointed with the visit.',
                'Need improvement in care quality.',
            ],
            2 => [
                'Service was below average.',
                'Some issues with the visit.',
                'Could be better.',
            ],
            3 => [
                'Service was adequate.',
                'Average experience.',
                'Met basic expectations.',
            ],
            4 => [
                'Very good service.',
                'Pleased with the care provided.',
                'Staff was professional and helpful.',
                'Would recommend.',
            ],
            5 => [
                'Excellent service!',
                'Outstanding care and attention.',
                'Staff went above and beyond.',
                'Very satisfied with the visit.',
                'Exceptional experience.',
            ],
        ];
        
        $options = $texts[$rating] ?? $texts[3];
        return $options[array_rand($options)];
    }
    
    /**
     * Generate reported_at timestamp (1-3 days after visit).
     */
    protected function generateReportedAt(ServiceAssignment $assignment): Carbon
    {
        $visitEnd = $assignment->actual_end ?? $assignment->scheduled_start;
        $daysLater = mt_rand(1, 3);
        $hour = mt_rand(9, 20);
        
        return Carbon::parse($visitEnd)->addDays($daysLater)->setHour($hour);
    }
    
    /**
     * Get random reporter type.
     */
    protected function randomReporterType(): string
    {
        $types = [
            'patient' => 70,
            'family_member' => 25,
            'caregiver' => 5,
        ];
        
        $pool = [];
        foreach ($types as $type => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $pool[] = $type;
            }
        }
        
        return $pool[array_rand($pool)];
    }
    
    /**
     * Log summary of staff ratings.
     */
    protected function logStaffSummary(): void
    {
        $reports = SatisfactionReport::selectRaw('staff_user_id, AVG(rating) as avg_rating, COUNT(*) as count')
            ->groupBy('staff_user_id')
            ->orderByDesc('avg_rating')
            ->limit(10)
            ->get();
        
        $this->command->info('  Top staff by satisfaction:');
        foreach ($reports as $report) {
            $staff = User::find($report->staff_user_id);
            $name = $staff ? $staff->name : "Staff #{$report->staff_user_id}";
            $pct = round((($report->avg_rating - 1) / 4) * 100, 1);
            $this->command->info("    - {$name}: {$pct}% ({$report->count} reports)");
        }
    }
}
