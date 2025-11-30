<?php

namespace Database\Seeders;

use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * VisitVerificationSeeder - Creates realistic visit verification data
 *
 * This seeder creates 4 weeks of service assignments with:
 * - ~95-99% VERIFIED visits (delivered care)
 * - ~1-3% MISSED visits (missed care)
 * - 30-50 PENDING visits that are overdue (for Jeopardy Board demo)
 *
 * Per OHaH RFP: Target is 0% missed care, so this data shows
 * a realistic scenario with a small non-compliant rate.
 */
class VisitVerificationSeeder extends Seeder
{
    /**
     * Target verification rate (95-99%).
     */
    protected float $verificationRate = 0.97;

    /**
     * Target missed rate (1-3%).
     */
    protected float $missedRate = 0.01;

    /**
     * Number of overdue alerts for jeopardy board demo (per CC2.1: 3-10 for demo).
     */
    protected int $overdueAlertsCount = 8;

    /**
     * Number of weeks to generate data for.
     */
    protected int $weeksBack = 4;

    /**
     * Buffer time between assignments in minutes.
     */
    protected int $bufferMinutes = 10;

    /**
     * Staff schedule tracker for non-overlapping assignments.
     * Structure: [staff_id => [date_string => Carbon (next available start time)]]
     */
    protected array $staffSchedules = [];

    public function run(): void
    {
        $this->command->info('Creating visit verification data for the last 4 weeks...');

        $spo = ServiceProviderOrganization::first();
        if (!$spo) {
            $this->command->error('No SPO found. Run DemoSeeder first.');
            return;
        }

        // Get active patients with care plans
        $patients = Patient::where('status', 'Active')
            ->whereHas('carePlans', fn($q) => $q->where('status', 'active'))
            ->with(['carePlans' => fn($q) => $q->where('status', 'active')])
            ->get();

        if ($patients->isEmpty()) {
            $this->command->error('No active patients found. Run DemoSeeder and DemoBundlesSeeder first.');
            return;
        }

        // Get active staff
        $staff = User::where('organization_id', $spo->id)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where('staff_status', 'active')
            ->get();

        if ($staff->isEmpty()) {
            $this->command->error('No active staff found. Run DemoSeeder first.');
            return;
        }

        // Get service types
        $serviceTypes = ServiceType::where('active', true)->get();
        if ($serviceTypes->isEmpty()) {
            // Fallback to all service types if none are marked active
            $serviceTypes = ServiceType::all();
        }
        if ($serviceTypes->isEmpty()) {
            $this->command->error('No service types found. Run MetadataObjectModelSeeder first.');
            return;
        }

        // Check for existing assignments from WorkforceSeeder
        $existingCount = ServiceAssignment::count();
        if ($existingCount > 0) {
            $this->command->info("Found {$existingCount} existing assignments. Adding verification statuses...");
            $this->updateExistingAssignments();
            return;
        }

        $this->command->info('No existing assignments found. Creating new visit verification data...');

        // Reset staff schedules tracker
        $this->staffSchedules = [];

        // Load staff with availabilities for non-overlapping scheduling
        $staffWithAvailability = User::where('organization_id', $spo->id)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where('staff_status', 'active')
            ->with('availabilities')
            ->get();

        // Generate visits for the last 4 weeks
        $startDate = Carbon::now()->subWeeks($this->weeksBack)->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();

        $totalVisits = 0;
        $verifiedCount = 0;
        $missedCount = 0;
        $pendingCount = 0;
        $skippedCount = 0;

        // Calculate expected visits per week per patient (based on typical care bundle)
        $visitsPerWeekPerPatient = rand(3, 7);

        $this->command->info("Generating visits from {$startDate->toDateString()} to {$endDate->toDateString()}...");

        // Iterate through each week
        $currentWeekStart = $startDate->copy();
        while ($currentWeekStart->lt($endDate)) {
            $currentWeekEnd = $currentWeekStart->copy()->endOfWeek();
            $isCurrentWeek = $currentWeekStart->isSameWeek(Carbon::now());
            $isPastWeek = $currentWeekEnd->lt(Carbon::today());

            foreach ($patients as $patient) {
                $carePlan = $patient->carePlans->first();
                if (!$carePlan) {
                    continue;
                }

                // Generate visits for this patient this week
                for ($i = 0; $i < $visitsPerWeekPerPatient; $i++) {
                    $serviceType = $serviceTypes->random();
                    $durationMinutes = $serviceType->default_duration_minutes ?? 60;

                    // Random day within the week (Mon-Fri)
                    $dayOffset = rand(0, 4);
                    $visitDate = $currentWeekStart->copy()->addDays($dayOffset);

                    // Find an available staff member with an open slot
                    $slot = $this->findAvailableSlotForStaff(
                        $staffWithAvailability,
                        $visitDate,
                        $durationMinutes
                    );

                    if (!$slot) {
                        $skippedCount++;
                        continue;
                    }

                    $staffMember = $slot['staff'];
                    $scheduledStart = $slot['start'];
                    $scheduledEnd = $slot['end'];

                    // Determine status and verification based on timing
                    // Overdue threshold is 12 hours per CC2.1/RFP/Q&A requirements
                    $isPast = $scheduledStart->lt(Carbon::now());
                    $isOverdueThreshold = $scheduledStart->lt(Carbon::now()->subHours(12));

                    $assignmentData = $this->generateAssignmentData(
                        $isPast,
                        $isPastWeek,
                        $isCurrentWeek,
                        $isOverdueThreshold,
                        $scheduledStart
                    );

                    ServiceAssignment::create([
                        'care_plan_id' => $carePlan->id,
                        'patient_id' => $patient->id,
                        'service_type_id' => $serviceType->id,
                        'service_provider_organization_id' => $spo->id,
                        'assigned_user_id' => $staffMember->id,
                        'scheduled_start' => $scheduledStart,
                        'scheduled_end' => $scheduledEnd,
                        'duration_minutes' => $durationMinutes,
                        'status' => $assignmentData['status'],
                        'verification_status' => $assignmentData['verification_status'],
                        'verified_at' => $assignmentData['verified_at'],
                        'verification_source' => $assignmentData['verification_source'],
                        'actual_start' => $assignmentData['actual_start'],
                        'actual_end' => $assignmentData['actual_end'],
                        'frequency_rule' => "{$visitsPerWeekPerPatient}x per week",
                        'source' => ServiceAssignment::SOURCE_INTERNAL,
                        'notes' => "Week of {$currentWeekStart->toDateString()}",
                    ]);

                    $totalVisits++;

                    // Track counts
                    match ($assignmentData['verification_status']) {
                        ServiceAssignment::VERIFICATION_VERIFIED => $verifiedCount++,
                        ServiceAssignment::VERIFICATION_MISSED => $missedCount++,
                        default => $pendingCount++,
                    };
                }
            }

            $currentWeekStart->addWeek();
        }

        if ($skippedCount > 0) {
            $this->command->warn("Skipped {$skippedCount} visits (no available staff slots).");
        }

        // Create specific overdue alerts for jeopardy board
        $this->createJeopardyAlerts($patients, $staff, $serviceTypes, $spo);

        // Report stats
        $rate = $totalVisits > 0 ? round(($missedCount / ($verifiedCount + $missedCount)) * 100, 2) : 0;

        $this->command->info("Visit Verification Data Created:");
        $this->command->info("  Total Visits: {$totalVisits}");
        $this->command->info("  Verified: {$verifiedCount}");
        $this->command->info("  Missed: {$missedCount}");
        $this->command->info("  Pending: {$pendingCount}");
        $this->command->info("  Missed Care Rate: {$rate}%");

        // Count jeopardy alerts
        $overdueCount = ServiceAssignment::overdueUnverified()->count();
        $this->command->info("  Overdue (Jeopardy Board): {$overdueCount}");
    }

    /**
     * Generate assignment data based on timing.
     */
    protected function generateAssignmentData(
        bool $isPast,
        bool $isPastWeek,
        bool $isCurrentWeek,
        bool $isOverdueThreshold,
        Carbon $scheduledStart
    ): array {
        // Future visits
        if (!$isPast) {
            return [
                'status' => ServiceAssignment::STATUS_PLANNED,
                'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
                'verified_at' => null,
                'verification_source' => null,
                'actual_start' => null,
                'actual_end' => null,
            ];
        }

        // Past visits - most should be verified
        $random = mt_rand(1, 100) / 100;

        // 97% verified, 1% missed, 2% pending (overdue)
        if ($random <= $this->verificationRate) {
            // VERIFIED - delivered care
            return [
                'status' => ServiceAssignment::STATUS_COMPLETED,
                'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
                'verified_at' => $scheduledStart->copy()->addHours(rand(1, 8)),
                'verification_source' => $this->randomVerificationSource(),
                'actual_start' => $scheduledStart,
                'actual_end' => $scheduledStart->copy()->addMinutes(rand(45, 75)),
            ];
        } elseif ($random <= $this->verificationRate + $this->missedRate) {
            // MISSED - missed care
            return [
                'status' => ServiceAssignment::STATUS_MISSED,
                'verification_status' => ServiceAssignment::VERIFICATION_MISSED,
                'verified_at' => $scheduledStart->copy()->addHours(rand(24, 48)),
                'verification_source' => ServiceAssignment::VERIFICATION_SOURCE_COORDINATOR,
                'actual_start' => null,
                'actual_end' => null,
            ];
        } else {
            // PENDING - overdue (will appear on jeopardy board)
            return [
                'status' => ServiceAssignment::STATUS_PLANNED,
                'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
                'verified_at' => null,
                'verification_source' => null,
                'actual_start' => null,
                'actual_end' => null,
            ];
        }
    }

    /**
     * Create specific overdue alerts for the jeopardy board demo.
     * Uses non-overlapping scheduling to ensure realistic data.
     */
    protected function createJeopardyAlerts($patients, $staff, $serviceTypes, $spo): void
    {
        $this->command->info("Creating {$this->overdueAlertsCount} overdue alerts for Jeopardy Board...");

        // Load staff with availabilities
        $staffWithAvailability = User::where('organization_id', $spo->id)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where('staff_status', 'active')
            ->with('availabilities')
            ->get();

        $createdCount = 0;
        $attempts = 0;
        $maxAttempts = $this->overdueAlertsCount * 3;

        while ($createdCount < $this->overdueAlertsCount && $attempts < $maxAttempts) {
            $attempts++;

            $patient = $patients->random();
            $carePlan = $patient->carePlans->first();
            if (!$carePlan) {
                continue;
            }

            $serviceType = $serviceTypes->random();
            $durationMinutes = $serviceType->default_duration_minutes ?? 60;

            // Schedule 1-7 days ago (past the 24h grace period)
            $daysAgo = rand(1, 7);
            $visitDate = Carbon::now()->subDays($daysAgo);

            // Find an available slot for this staff member
            $slot = $this->findAvailableSlotForStaff(
                $staffWithAvailability,
                $visitDate,
                $durationMinutes
            );

            if (!$slot) {
                continue;
            }

            ServiceAssignment::create([
                'care_plan_id' => $carePlan->id,
                'patient_id' => $patient->id,
                'service_type_id' => $serviceType->id,
                'service_provider_organization_id' => $spo->id,
                'assigned_user_id' => $slot['staff']->id,
                'scheduled_start' => $slot['start'],
                'scheduled_end' => $slot['end'],
                'duration_minutes' => $durationMinutes,
                'status' => ServiceAssignment::STATUS_PLANNED,
                'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
                'verified_at' => null,
                'verification_source' => null,
                'frequency_rule' => 'as needed',
                'source' => ServiceAssignment::SOURCE_INTERNAL,
                'notes' => 'Overdue - requires verification',
            ]);

            $createdCount++;
        }

        if ($createdCount < $this->overdueAlertsCount) {
            $this->command->warn("Only created {$createdCount} overdue alerts (limited staff availability).");
        }
    }

    /**
     * Find an available slot for any staff member on a given day.
     *
     * @param \Illuminate\Support\Collection $staffCollection Collection of staff with availabilities
     * @param Carbon $visitDate The date for the visit
     * @param int $durationMinutes Required duration
     * @return array|null ['staff' => User, 'start' => Carbon, 'end' => Carbon] or null
     */
    protected function findAvailableSlotForStaff($staffCollection, Carbon $visitDate, int $durationMinutes): ?array
    {
        // Shuffle to distribute load
        $shuffledStaff = $staffCollection->shuffle();

        foreach ($shuffledStaff as $staff) {
            $slot = $this->getNextSlotForStaff($staff, $visitDate, $durationMinutes);
            if ($slot) {
                return [
                    'staff' => $staff,
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                ];
            }
        }

        return null;
    }

    /**
     * Get the next available slot for a staff member on a specific day.
     *
     * @param User $staff
     * @param Carbon $visitDate
     * @param int $durationMinutes
     * @return array|null ['start' => Carbon, 'end' => Carbon] or null
     */
    protected function getNextSlotForStaff(User $staff, Carbon $visitDate, int $durationMinutes): ?array
    {
        $dateKey = $visitDate->toDateString();
        $staffKey = $staff->id;
        $dayOfWeek = $visitDate->dayOfWeek;

        // Get staff's availability for this day of week
        $availability = $staff->availabilities
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$availability) {
            // No availability defined for this day - skip weekends, use default for weekdays
            if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                return null;
            }
            // Default: 08:00 - 16:00 for weekdays if no availability set
            $dayStart = $visitDate->copy()->setTime(8, 0, 0);
            $dayEnd = $visitDate->copy()->setTime(16, 0, 0);
        } else {
            // Parse availability times
            $startTime = Carbon::parse($availability->start_time);
            $endTime = Carbon::parse($availability->end_time);

            $dayStart = $visitDate->copy()->setTime(
                $startTime->hour,
                $startTime->minute,
                0
            );
            $dayEnd = $visitDate->copy()->setTime(
                $endTime->hour,
                $endTime->minute,
                0
            );
        }

        // Get or initialize the staff's next available time for this day
        if (!isset($this->staffSchedules[$staffKey][$dateKey])) {
            $this->staffSchedules[$staffKey][$dateKey] = $dayStart->copy();
        }

        $nextAvailable = $this->staffSchedules[$staffKey][$dateKey];

        // Calculate potential slot
        $slotStart = $nextAvailable->copy();
        $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

        // Check if slot fits within day's availability
        if ($slotEnd->gt($dayEnd)) {
            return null; // No more room today
        }

        // Reserve the slot (update next available time with buffer)
        $this->staffSchedules[$staffKey][$dateKey] = $slotEnd->copy()->addMinutes($this->bufferMinutes);

        return [
            'start' => $slotStart,
            'end' => $slotEnd,
        ];
    }

    /**
     * Get random verification source weighted toward common sources.
     */
    protected function randomVerificationSource(): string
    {
        $sources = [
            ServiceAssignment::VERIFICATION_SOURCE_STAFF_MANUAL,
            ServiceAssignment::VERIFICATION_SOURCE_STAFF_MANUAL,
            ServiceAssignment::VERIFICATION_SOURCE_STAFF_MANUAL,
            ServiceAssignment::VERIFICATION_SOURCE_DEVICE,
            ServiceAssignment::VERIFICATION_SOURCE_DEVICE,
            ServiceAssignment::VERIFICATION_SOURCE_SSPO_SYSTEM,
        ];

        return $sources[array_rand($sources)];
    }

    /**
     * Update existing assignments with verification statuses.
     * Called when WorkforceSeeder has already created assignments.
     */
    protected function updateExistingAssignments(): void
    {
        $verifiedCount = 0;
        $missedCount = 0;
        $pendingCount = 0;

        // Get all assignments that have a scheduled_start
        $assignments = ServiceAssignment::whereNotNull('scheduled_start')->get();

        foreach ($assignments as $assignment) {
            // Skip if scheduled_start is somehow still null (defensive check)
            if (!$assignment->scheduled_start) {
                $pendingCount++;
                continue;
            }

            $isPast = $assignment->scheduled_start->lt(Carbon::now());
            $isPastWeek = $assignment->scheduled_start->lt(Carbon::now()->startOfWeek());
            $isCurrentWeek = $assignment->scheduled_start->isBetween(
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            );
            // Overdue threshold is 12 hours per CC2.1/RFP/Q&A requirements
            $isOverdueThreshold = $assignment->scheduled_start->lt(Carbon::now()->subHours(12));

            $data = $this->generateAssignmentData(
                $isPast,
                $isPastWeek,
                $isCurrentWeek,
                $isOverdueThreshold,
                $assignment->scheduled_start
            );

            $assignment->update([
                'status' => $data['status'],
                'verification_status' => $data['verification_status'],
                'verified_at' => $data['verified_at'],
                'verification_source' => $data['verification_source'],
                'actual_start' => $data['actual_start'],
                'actual_end' => $data['actual_end'],
            ]);

            // Track counts
            match ($data['verification_status']) {
                ServiceAssignment::VERIFICATION_VERIFIED => $verifiedCount++,
                ServiceAssignment::VERIFICATION_MISSED => $missedCount++,
                default => $pendingCount++,
            };
        }

        // Create additional overdue alerts if needed
        $overdueCount = ServiceAssignment::overdueUnverified()->count();
        if ($overdueCount < $this->overdueAlertsCount) {
            $this->command->info("Creating additional overdue alerts (currently {$overdueCount})...");
            // Update some of the pending assignments to be overdue
            // Overdue threshold is 12 hours per CC2.1/RFP/Q&A requirements
            $pendingAssignments = ServiceAssignment::where('verification_status', ServiceAssignment::VERIFICATION_PENDING)
                ->where('scheduled_start', '<', Carbon::now()->subHours(12))
                ->limit($this->overdueAlertsCount - $overdueCount)
                ->get();

            foreach ($pendingAssignments as $assignment) {
                // Leave as PENDING but ensure they're in the past (already are)
                // These will show on Jeopardy Board
            }
        }

        // Report stats
        $totalVisits = $assignments->count();
        $rate = ($verifiedCount + $missedCount) > 0
            ? round(($missedCount / ($verifiedCount + $missedCount)) * 100, 2)
            : 0;

        $this->command->info("Visit Verification Data Updated:");
        $this->command->info("  Total Visits: {$totalVisits}");
        $this->command->info("  Verified: {$verifiedCount}");
        $this->command->info("  Missed: {$missedCount}");
        $this->command->info("  Pending: {$pendingCount}");
        $this->command->info("  Missed Care Rate: {$rate}%");

        $overdueCount = ServiceAssignment::overdueUnverified()->count();
        $this->command->info("  Overdue (Jeopardy Board): {$overdueCount}");
    }
}
