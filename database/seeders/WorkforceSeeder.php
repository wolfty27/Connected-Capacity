<?php

namespace Database\Seeders;

use App\Models\CarePlan;
use App\Models\EmploymentType;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * WorkforceSeeder - Seeds realistic workforce data for FTE compliance demo.
 *
 * Creates a workforce that demonstrates:
 * - 80%+ FTE compliance (full-time ratio)
 * - HHR complement breakdown by role (RN, RPN, PSW, etc.)
 * - Staff satisfaction metrics
 * - 8 weeks of historical service assignments
 *
 * Per RFP Q&A: FTE ratio = [Full-time direct staff ÷ Total direct staff] × 100%
 * Target: 80% full-time, 20% part-time/casual
 */
class WorkforceSeeder extends Seeder
{
    public function run(): void
    {
        $spo = ServiceProviderOrganization::where('slug', 'se-health')->first();

        if (!$spo) {
            $this->command->warn('SE Health organization not found. Run DemoSeeder first.');
            return;
        }

        // Ensure roles and employment types are seeded first
        $this->call([
            StaffRolesSeeder::class,
            EmploymentTypesSeeder::class,
        ]);

        // Get employment type IDs
        $ftId = EmploymentType::where('code', 'FT')->value('id');
        $ptId = EmploymentType::where('code', 'PT')->value('id');
        $casualId = EmploymentType::where('code', 'CASUAL')->value('id');

        // Get role IDs
        $roleIds = StaffRole::pluck('id', 'code');

        // Update existing staff with new metadata
        $this->updateExistingStaff($spo, $roleIds, $ftId, $ptId, $casualId);

        // Create additional staff for realistic FTE demo (target: 80% FT)
        $this->createAdditionalStaff($spo, $roleIds, $ftId, $ptId, $casualId);

        // Seed historical service assignments (8 weeks)
        $this->seedHistoricalAssignments($spo);

        // Seed staff availability blocks based on employment type
        $this->seedStaffAvailability($spo);

        $this->command->info('Workforce seeder completed.');
    }

    /**
     * Update existing staff with new metadata (staff_role_id, employment_type_id, satisfaction).
     */
    protected function updateExistingStaff(
        ServiceProviderOrganization $spo,
        $roleIds,
        int $ftId,
        int $ptId,
        int $casualId
    ): void {
        // Map organization_role to staff_role_id
        $roleMapping = [
            'RN' => $roleIds['RN'] ?? null,
            'RPN' => $roleIds['RPN'] ?? null,
            'PSW' => $roleIds['PSW'] ?? null,
            'OT' => $roleIds['OT'] ?? null,
            'PT' => $roleIds['PT'] ?? null,
            'NP' => $roleIds['NP'] ?? null,
            'SLP' => $roleIds['SLP'] ?? null,
            'SW' => $roleIds['SW'] ?? null,
            'Care Coordinator' => $roleIds['COORD'] ?? null,
        ];

        // Map employment_type string to employment_type_id
        $empTypeMapping = [
            'full_time' => $ftId,
            'part_time' => $ptId,
            'casual' => $casualId,
        ];

        $staff = User::where('organization_id', $spo->id)
            ->whereIn('role', [User::ROLE_FIELD_STAFF, User::ROLE_SPO_COORDINATOR])
            ->get();

        foreach ($staff as $member) {
            $updates = [];

            // Set staff_role_id based on organization_role
            if ($member->organization_role && isset($roleMapping[$member->organization_role])) {
                $updates['staff_role_id'] = $roleMapping[$member->organization_role];
            }

            // Set employment_type_id based on employment_type
            if ($member->employment_type && isset($empTypeMapping[$member->employment_type])) {
                $updates['employment_type_id'] = $empTypeMapping[$member->employment_type];
            }

            // Add satisfaction (enum: excellent, good, neutral, poor)
            $satisfactionOptions = ['excellent', 'good', 'good', 'excellent']; // Bias toward positive
            $updates['job_satisfaction'] = $satisfactionOptions[array_rand($satisfactionOptions)];
            $updates['job_satisfaction_recorded_at'] = Carbon::now()->subDays(rand(1, 30));

            if (!empty($updates)) {
                $member->update($updates);
            }
        }

        $this->command->info('Updated existing staff with role and employment type metadata.');
    }

    /**
     * Create additional staff to achieve ~80% FTE ratio.
     *
     * Current demo has: 4 FT, 2 PT, 2 Casual (50% FTE ratio)
     * To get 80%: Need more full-time staff
     * Target: 16 FT, 2 PT, 2 Casual = 80% FTE
     */
    protected function createAdditionalStaff(
        ServiceProviderOrganization $spo,
        $roleIds,
        int $ftId,
        int $ptId,
        int $casualId
    ): void {
        // Additional full-time staff to reach 80% FTE
        $additionalStaff = [
            // More Full-Time RNs
            ['name' => 'Jennifer Kim', 'email' => 'jennifer.kim@sehc.com', 'role_code' => 'RN', 'emp_type_id' => $ftId, 'hours' => 40],
            ['name' => 'Thomas Wright', 'email' => 'thomas.wright@sehc.com', 'role_code' => 'RN', 'emp_type_id' => $ftId, 'hours' => 40],
            ['name' => 'Rachel Green', 'email' => 'rachel.green@sehc.com', 'role_code' => 'RN', 'emp_type_id' => $ftId, 'hours' => 40],

            // More Full-Time RPNs
            ['name' => 'Kevin Martinez', 'email' => 'kevin.martinez@sehc.com', 'role_code' => 'RPN', 'emp_type_id' => $ftId, 'hours' => 40],
            ['name' => 'Amanda Clark', 'email' => 'amanda.clark@sehc.com', 'role_code' => 'RPN', 'emp_type_id' => $ftId, 'hours' => 40],

            // More Full-Time PSWs
            ['name' => 'Daniel Park', 'email' => 'daniel.park@sehc.com', 'role_code' => 'PSW', 'emp_type_id' => $ftId, 'hours' => 40],
            ['name' => 'Michelle Thompson', 'email' => 'michelle.thompson@sehc.com', 'role_code' => 'PSW', 'emp_type_id' => $ftId, 'hours' => 40],
            ['name' => 'Andrew Garcia', 'email' => 'andrew.garcia@sehc.com', 'role_code' => 'PSW', 'emp_type_id' => $ftId, 'hours' => 40],
            ['name' => 'Sarah Johnson', 'email' => 'sarah.johnson@sehc.com', 'role_code' => 'PSW', 'emp_type_id' => $ftId, 'hours' => 40],
            ['name' => 'Christopher Lee', 'email' => 'christopher.lee@sehc.com', 'role_code' => 'PSW', 'emp_type_id' => $ftId, 'hours' => 40],

            // Full-Time Allied Health
            ['name' => 'Dr. Elizabeth Chen', 'email' => 'elizabeth.chen@sehc.com', 'role_code' => 'PT', 'emp_type_id' => $ftId, 'hours' => 40],
            ['name' => 'Dr. Nathan Brooks', 'email' => 'nathan.brooks@sehc.com', 'role_code' => 'SLP', 'emp_type_id' => $ftId, 'hours' => 40],
            ['name' => 'Dr. Laura Davis', 'email' => 'laura.davis@sehc.com', 'role_code' => 'SW', 'emp_type_id' => $ftId, 'hours' => 40],
        ];

        foreach ($additionalStaff as $data) {
            $roleId = $roleIds[$data['role_code']] ?? null;

            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_FIELD_STAFF,
                    'organization_id' => $spo->id,
                    'organization_role' => $data['role_code'],
                    'staff_role_id' => $roleId,
                    'employment_type' => $data['emp_type_id'] === $ftId ? 'full_time' : ($data['emp_type_id'] === $ptId ? 'part_time' : 'casual'),
                    'employment_type_id' => $data['emp_type_id'],
                    'max_weekly_hours' => $data['hours'],
                    'staff_status' => User::STAFF_STATUS_ACTIVE,
                    'hire_date' => Carbon::now()->subMonths(rand(1, 24)),
                    'job_satisfaction' => ['excellent', 'good', 'excellent'][rand(0, 2)], // Higher satisfaction for new hires
                    'job_satisfaction_recorded_at' => Carbon::now()->subDays(rand(1, 14)),
                ]
            );
        }

        $this->command->info('Created ' . count($additionalStaff) . ' additional staff for FTE compliance demo.');
    }

    /**
     * Seed 8 weeks of historical service assignments for trend analysis.
     */
    protected function seedHistoricalAssignments(ServiceProviderOrganization $spo): void
    {
        // Get active patients with care plans
        $patients = Patient::where('status', 'Active')
            ->whereHas('carePlans', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['carePlans' => function ($q) {
                $q->where('status', 'active')->with('careBundle.serviceTypes');
            }])
            ->get();

        if ($patients->isEmpty()) {
            $this->command->warn('No active patients found for historical assignments.');
            return;
        }

        // Get active staff grouped by role
        $staffByRole = $this->getStaffByRole($spo);

        if (empty($staffByRole)) {
            $this->command->warn('No staff found for assignments.');
            return;
        }

        // Role to service type mapping
        $roleToServiceType = [
            'RN' => 'NUR',
            'RPN' => 'NUR',
            'PSW' => 'PSW',
            'OT' => 'OT',
            'PT' => 'PT',
            'SLP' => 'SLP',
            'SW' => 'SW',
        ];

        // Seed 8 weeks of historical data
        $weeksToSeed = 8;
        $currentWeekStart = Carbon::now()->startOfWeek();

        for ($weekOffset = 1; $weekOffset <= $weeksToSeed; $weekOffset++) {
            $weekStart = $currentWeekStart->copy()->subWeeks($weekOffset);

            foreach ($patients as $patient) {
                $carePlan = $patient->carePlans->first();
                if (!$carePlan || !$carePlan->careBundle) {
                    continue;
                }

                $bundle = $carePlan->careBundle;

                foreach ($bundle->serviceTypes as $serviceType) {
                    $frequencyPerWeek = $serviceType->pivot->default_frequency_per_week ?? 1;
                    $durationMinutes = $serviceType->default_duration_minutes ?? 60;

                    // Find staff that can provide this service
                    $availableStaff = [];
                    foreach ($roleToServiceType as $roleCode => $stCode) {
                        if ($stCode === $serviceType->code && isset($staffByRole[$roleCode])) {
                            $availableStaff = array_merge($availableStaff, $staffByRole[$roleCode]);
                        }
                    }

                    if (empty($availableStaff)) {
                        continue;
                    }

                    // Create assignments for this week
                    $visitDays = $this->distributeVisitsAcrossWeek($frequencyPerWeek);

                    foreach ($visitDays as $dayIndex => $visitsOnDay) {
                        for ($visitNum = 0; $visitNum < $visitsOnDay; $visitNum++) {
                            $visitDate = $weekStart->copy()->addDays($dayIndex);
                            $staff = $availableStaff[array_rand($availableStaff)];

                            $startHour = 8 + ($visitNum * 3);
                            $scheduledStart = $visitDate->copy()->setTime($startHour, 0);
                            $scheduledEnd = $scheduledStart->copy()->addMinutes($durationMinutes);

                            // Historical assignments are all completed
                            ServiceAssignment::updateOrCreate(
                                [
                                    'care_plan_id' => $carePlan->id,
                                    'patient_id' => $patient->id,
                                    'service_type_id' => $serviceType->id,
                                    'scheduled_start' => $scheduledStart,
                                ],
                                [
                                    'assigned_user_id' => $staff->id,
                                    'service_provider_organization_id' => $spo->id,
                                    'status' => 'completed',
                                    'scheduled_end' => $scheduledEnd,
                                    'actual_start' => $scheduledStart,
                                    'actual_end' => $scheduledEnd,
                                    'frequency_rule' => "{$frequencyPerWeek}x per week",
                                    'estimated_hours_per_week' => round(($frequencyPerWeek * $durationMinutes) / 60, 2),
                                    'source' => ServiceAssignment::SOURCE_INTERNAL,
                                    'notes' => "Historical: {$bundle->code} | {$serviceType->name}",
                                ]
                            );
                        }
                    }
                }
            }
        }

        $this->command->info("Seeded {$weeksToSeed} weeks of historical service assignments.");
    }

    /**
     * Get staff grouped by role code.
     */
    protected function getStaffByRole(ServiceProviderOrganization $spo): array
    {
        $staff = User::where('organization_id', $spo->id)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where('staff_status', User::STAFF_STATUS_ACTIVE)
            ->with('staffRole')
            ->get();

        $grouped = [];
        foreach ($staff as $member) {
            $roleCode = $member->staffRole?->code ?? $member->organization_role;
            if ($roleCode) {
                $grouped[$roleCode][] = $member;
            }
        }

        return $grouped;
    }

    /**
     * Distribute visits across the week based on frequency.
     */
    protected function distributeVisitsAcrossWeek(int $frequencyPerWeek): array
    {
        $days = [0, 0, 0, 0, 0]; // Mon-Fri

        if ($frequencyPerWeek >= 7) {
            $perDay = intdiv($frequencyPerWeek, 5);
            $remainder = $frequencyPerWeek % 5;
            for ($i = 0; $i < 5; $i++) {
                $days[$i] = $perDay + ($i < $remainder ? 1 : 0);
            }
        } elseif ($frequencyPerWeek >= 5) {
            $days = [1, 1, 1, 1, 1];
            $extra = $frequencyPerWeek - 5;
            for ($i = 0; $i < $extra; $i++) {
                $days[$i]++;
            }
        } elseif ($frequencyPerWeek >= 3) {
            $days[0] = 1; $days[2] = 1; $days[4] = 1;
            if ($frequencyPerWeek == 4) $days[1] = 1;
        } elseif ($frequencyPerWeek == 2) {
            $days[0] = 1; $days[3] = 1;
        } elseif ($frequencyPerWeek == 1) {
            $days[2] = 1;
        }

        return $days;
    }

    /**
     * Seed staff availability blocks based on employment type metadata.
     *
     * Creates weekly recurring availability patterns:
     * - Full-Time (40h): Mon-Fri 08:00-16:00 (8h x 5 days)
     * - Part-Time (24h): Mon, Wed, Fri 08:00-16:00 (8h x 3 days)
     * - Casual (16h): Tue, Thu 08:00-16:00 (8h x 2 days)
     *
     * Availability is derived from EmploymentType.standard_hours_per_week metadata.
     */
    protected function seedStaffAvailability(ServiceProviderOrganization $spo): void
    {
        // Define availability patterns based on employment type code
        // Maps employment type code to [days of week => [start_time, end_time]]
        $availabilityPatterns = [
            EmploymentType::CODE_FULL_TIME => [
                // Mon-Fri 08:00-16:00 (40h/week)
                StaffAvailability::MONDAY => ['08:00', '16:00'],
                StaffAvailability::TUESDAY => ['08:00', '16:00'],
                StaffAvailability::WEDNESDAY => ['08:00', '16:00'],
                StaffAvailability::THURSDAY => ['08:00', '16:00'],
                StaffAvailability::FRIDAY => ['08:00', '16:00'],
            ],
            EmploymentType::CODE_PART_TIME => [
                // Mon, Wed, Fri 08:00-16:00 (24h/week)
                StaffAvailability::MONDAY => ['08:00', '16:00'],
                StaffAvailability::WEDNESDAY => ['08:00', '16:00'],
                StaffAvailability::FRIDAY => ['08:00', '16:00'],
            ],
            EmploymentType::CODE_CASUAL => [
                // Tue, Thu 08:00-16:00 (16h/week)
                StaffAvailability::TUESDAY => ['08:00', '16:00'],
                StaffAvailability::THURSDAY => ['08:00', '16:00'],
            ],
            // SSPO staff don't have defined availability (variable/as-needed)
        ];

        // Get all active staff in the organization with employment type
        $staff = User::where('organization_id', $spo->id)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where('staff_status', User::STAFF_STATUS_ACTIVE)
            ->with('employmentTypeModel')
            ->get();

        $effectiveFrom = Carbon::now()->subMonths(3)->startOfWeek();
        $createdCount = 0;

        foreach ($staff as $member) {
            $empTypeCode = $member->employmentTypeModel?->code;

            // Skip if no pattern defined for this employment type (e.g., SSPO)
            if (!isset($availabilityPatterns[$empTypeCode])) {
                continue;
            }

            $pattern = $availabilityPatterns[$empTypeCode];

            foreach ($pattern as $dayOfWeek => $times) {
                StaffAvailability::updateOrCreate(
                    [
                        'user_id' => $member->id,
                        'day_of_week' => $dayOfWeek,
                    ],
                    [
                        'start_time' => $times[0],
                        'end_time' => $times[1],
                        'effective_from' => $effectiveFrom,
                        'effective_until' => null, // Ongoing
                        'is_recurring' => true,
                        'notes' => "Default {$empTypeCode} schedule",
                    ]
                );
                $createdCount++;
            }
        }

        $this->command->info("Seeded {$createdCount} staff availability blocks.");
    }
}
