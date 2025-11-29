<?php

namespace Database\Seeders;

use App\Models\CarePlan;
use App\Models\CareBundleTemplate;
use App\Models\EmploymentType;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceRoleMapping;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Scheduling\SchedulingEngine;
use App\Services\Travel\FakeTravelTimeService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * WorkforceSeeder - Seeds realistic workforce data for FTE compliance demo.
 *
 * Creates a workforce that demonstrates:
 * - 80%+ FTE compliance (full-time ratio)
 * - HHR complement breakdown by role (RN, RPN, PSW, etc.)
 * - Staff satisfaction metrics
 * - Past 3 weeks + current week of service assignments
 *
 * Per RFP Q&A: FTE ratio = [Full-time direct staff ÷ Total direct staff] × 100%
 * Target: 80% full-time, 20% part-time/casual
 */
class WorkforceSeeder extends Seeder
{
    protected array $roleServiceMappings = [];

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

        // Seed service role mappings if not already done
        if (Schema::hasTable('service_role_mappings')) {
            $this->call(ServiceRoleMappingsSeeder::class);
            $this->loadRoleServiceMappings();
        }

        // Get employment type IDs
        $ftId = EmploymentType::where('code', 'FT')->value('id');
        $ptId = EmploymentType::where('code', 'PT')->value('id');
        $casualId = EmploymentType::where('code', 'CASUAL')->value('id');
        $sspoId = EmploymentType::where('code', 'SSPO')->value('id');

        // Get role IDs
        $roleIds = StaffRole::pluck('id', 'code');

        // Update existing staff with new metadata
        $this->updateExistingStaff($spo, $roleIds, $ftId, $ptId, $casualId);

        // Create additional staff for realistic FTE demo (target: 80% FT)
        $this->createAdditionalStaff($spo, $roleIds, $ftId, $ptId, $casualId);

        // Seed SSPO organization and staff
        $sspo = $this->seedSspoOrganization($roleIds, $sspoId);

        // Seed past 3 weeks (completed) + current week (scheduled) assignments
        $this->seedServiceAssignments($spo, $sspo);

        // Seed staff availability blocks based on employment type
        $this->seedStaffAvailability($spo);

        $this->command->info('Workforce seeder completed.');
    }

    /**
     * Load role to service mappings from metadata table.
     */
    protected function loadRoleServiceMappings(): void
    {
        $mappings = ServiceRoleMapping::active()
            ->with(['staffRole', 'serviceType'])
            ->get();

        foreach ($mappings as $mapping) {
            $roleCode = $mapping->staffRole?->code;
            $serviceCode = $mapping->serviceType?->code;
            if ($roleCode && $serviceCode) {
                $this->roleServiceMappings[$roleCode][] = $serviceCode;
            }
        }
    }

    /**
     * Get service codes that a role can deliver.
     */
    protected function getRoleServiceCodes(string $roleCode): array
    {
        // Use metadata mappings if available
        if (!empty($this->roleServiceMappings[$roleCode])) {
            return $this->roleServiceMappings[$roleCode];
        }

        // Fallback to hardcoded mappings
        return match ($roleCode) {
            'RN', 'RPN', 'NP' => ['NUR', 'RPM', 'LAB', 'BEH'],
            'PSW' => ['PSW', 'HMK', 'RES', 'SEC', 'MEAL', 'REC', 'PHAR'],
            'OT' => ['OT'],
            'PT' => ['PT'],
            'SLP' => ['SLP'],
            'SW' => ['SW', 'BEH', 'REC'],
            'RT' => ['RT'],
            'RD' => ['RD'],
            'COORD' => ['PERS', 'TRANS', 'INTERP', 'MEAL', 'PHAR'],
            default => [],
        };
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
     * Seed SSPO organization and staff members.
     */
    protected function seedSspoOrganization($roleIds, ?int $sspoId): ?ServiceProviderOrganization
    {
        // Create or get SSPO organization
        $sspo = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'care-partners'],
            [
                'name' => 'Care Partners SSPO',
                'type' => 'partner', // Using 'partner' type for SSPO (enum: se_health, partner, external)
                'address' => '456 Healthcare Ave, Toronto, ON',
                'contact_email' => 'dispatch@carepartners.ca',
                'contact_phone' => '416-555-2000',
                'active' => true,
            ]
        );

        // Create a few SSPO staff members
        $sspoStaff = [
            ['name' => 'Emily Watson', 'email' => 'emily.watson@carepartners.ca', 'role_code' => 'PSW'],
            ['name' => 'James Miller', 'email' => 'james.miller@carepartners.ca', 'role_code' => 'PSW'],
            ['name' => 'Olivia Brown', 'email' => 'olivia.brown@carepartners.ca', 'role_code' => 'RN'],
        ];

        foreach ($sspoStaff as $data) {
            $roleId = $roleIds[$data['role_code']] ?? null;

            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_FIELD_STAFF,
                    'organization_id' => $sspo->id,
                    'organization_role' => $data['role_code'],
                    'staff_role_id' => $roleId,
                    'employment_type' => 'sspo',
                    'employment_type_id' => $sspoId,
                    'staff_status' => User::STAFF_STATUS_ACTIVE,
                    'hire_date' => Carbon::now()->subMonths(rand(3, 12)),
                ]
            );
        }

        $this->command->info('Seeded SSPO organization and ' . count($sspoStaff) . ' SSPO staff.');

        return $sspo;
    }

    /**
     * Staff schedule tracker for non-overlapping, travel-aware assignments.
     *
     * Structure: [staff_id => [date_string => [
     *   'next_available' => Carbon (next available start time),
     *   'last_patient' => Patient|null (for travel calculation),
     * ]]]
     */
    protected array $staffSchedules = [];

    /**
     * Typical durations by service category (in minutes).
     * Used when service type doesn't have default_duration_minutes.
     */
    protected array $categoryDurations = [
        'nursing' => 60,
        'psw' => 60,
        'personal_support' => 60,
        'homemaking' => 90,
        'rehab' => 45,
        'therapy' => 45,
        'behaviour' => 60,
        'behavioral' => 60,
        'social' => 60,
        'recreational' => 60,
        'other' => 60,
    ];

    /**
     * Buffer time between assignments in minutes.
     */
    protected int $bufferMinutes = 5;

    /**
     * Travel time service for calculating travel between patients.
     */
    protected ?FakeTravelTimeService $travelTimeService = null;

    /**
     * Scheduling engine for validating patient constraints.
     */
    protected ?SchedulingEngine $schedulingEngine = null;

    /**
     * Patient schedule tracker for non-concurrency validation.
     * Structure: [patient_id => [date_string => [['start' => Carbon, 'end' => Carbon, 'service_type_id' => int]]]]
     */
    protected array $patientSchedules = [];

    /**
     * Seed service assignments for past 3 weeks (completed) + current week (scheduled).
     *
     * NEW: Generates non-overlapping, travel-aware assignments by:
     * 1. Tracking each staff member's schedule per day
     * 2. Filling their availability windows sequentially
     * 3. Respecting service durations, buffer times, and travel time between patients
     * 4. Using FakeTravelTimeService for deterministic travel calculations
     */
    protected function seedServiceAssignments(
        ServiceProviderOrganization $spo,
        ?ServiceProviderOrganization $sspo
    ): void {
        // Reset staff schedules tracker
        $this->staffSchedules = [];
        $this->patientSchedules = [];

        // Initialize travel time service for seeding (uses fake/deterministic values)
        $this->travelTimeService = new FakeTravelTimeService();

        // Initialize scheduling engine for patient constraint validation
        $this->schedulingEngine = new SchedulingEngine();
        $this->schedulingEngine->setTravelTimeService($this->travelTimeService);

        // Get active patients with care plans (using new bundle template system)
        $patients = Patient::where('status', 'Active')
            ->whereHas('carePlans', fn($q) => $q->where('status', 'active'))
            ->with(['carePlans' => function ($q) {
                $q->where('status', 'active')
                    ->with(['careBundleTemplate.services.serviceType', 'careBundle.serviceTypes']);
            }])
            ->get();

        if ($patients->isEmpty()) {
            $this->command->warn('No active patients found for service assignments.');
            return;
        }

        // Get staff grouped by role with their availabilities
        $spoStaff = $this->getStaffWithAvailability($spo);
        $sspoStaff = $sspo ? $this->getStaffWithAvailability($sspo) : collect();
        $spoStaffByRole = $this->groupStaffByRole($spoStaff);
        $sspoStaffByRole = $this->groupStaffByRole($sspoStaff);

        if ($spoStaff->isEmpty()) {
            $this->command->warn('No SPO staff found for assignments.');
            return;
        }

        // Build a queue of required visits across all weeks
        $visitQueue = $this->buildVisitQueue($patients, 4);

        // Shuffle to randomize assignment distribution
        shuffle($visitQueue);

        // Process each visit requirement
        $assignmentCount = 0;
        $skippedCount = 0;
        $currentWeekStart = Carbon::now()->startOfWeek();

        foreach ($visitQueue as $visit) {
            $serviceCode = $visit['service_code'];
            $durationMinutes = $visit['duration'];
            $visitDate = $visit['date'];
            $isCurrentWeek = $visitDate->isBetween(
                $currentWeekStart,
                $currentWeekStart->copy()->endOfWeek()
            );

            // Find staff that can provide this service
            $availableStaff = $this->findStaffForService(
                $serviceCode,
                $spoStaffByRole,
                $sspoStaffByRole
            );

            if (empty($availableStaff['internal']) && empty($availableStaff['sspo'])) {
                $skippedCount++;
                continue;
            }

            // Determine if this assignment goes to internal or SSPO (80/20 split)
            $useSSPO = rand(1, 100) <= 20 && !empty($availableStaff['sspo']);
            $staffPool = $useSSPO ? $availableStaff['sspo'] : $availableStaff['internal'];

            // Get the patient for travel calculation
            $patient = Patient::find($visit['patient_id']);

            // Find a staff member with an available slot (considering travel time)
            $assignment = $this->findAvailableSlot(
                $staffPool,
                $visitDate,
                $durationMinutes,
                $patient
            );

            if (!$assignment) {
                $skippedCount++;
                continue;
            }

            $staff = $assignment['staff'];
            $scheduledStart = $assignment['start'];
            $scheduledEnd = $assignment['end'];

            // Validate patient constraints before creating assignment
            if (!$this->isPatientSlotAvailable($visit['patient_id'], $scheduledStart, $scheduledEnd)) {
                $skippedCount++;
                continue; // Patient has overlapping visit
            }

            if (!$this->respectsSpacingRule($visit['patient_id'], $visit['service_type_id'], $scheduledStart)) {
                $skippedCount++;
                continue; // Spacing rule violated
            }

            // Record patient visit for tracking
            $this->recordPatientVisit($visit['patient_id'], $visit['service_type_id'], $scheduledStart, $scheduledEnd);

            $source = $useSSPO ? ServiceAssignment::SOURCE_SSPO : ServiceAssignment::SOURCE_INTERNAL;
            $orgId = $useSSPO ? $sspo->id : $spo->id;

            // Status: past weeks = completed, current week = scheduled/planned
            $status = $isCurrentWeek ? 'planned' : 'completed';

            // Prepare notes - include visit label for fixed-visit services (like RPM)
            $visitLabel = $visit['visit_label'] ?? null;
            $notes = $isCurrentWeek ? "Scheduled: {$serviceCode}" : "Completed: {$serviceCode}";
            if ($visitLabel) {
                $notes = "{$visitLabel} - {$notes}";
            }

            ServiceAssignment::create([
                'care_plan_id' => $visit['care_plan_id'],
                'patient_id' => $visit['patient_id'],
                'service_type_id' => $visit['service_type_id'],
                'assigned_user_id' => $staff->id,
                'service_provider_organization_id' => $orgId,
                'status' => $status,
                'scheduled_start' => $scheduledStart,
                'scheduled_end' => $scheduledEnd,
                'duration_minutes' => $durationMinutes,
                'actual_start' => $isCurrentWeek ? null : $scheduledStart,
                'actual_end' => $isCurrentWeek ? null : $scheduledEnd,
                'frequency_rule' => $visitLabel ? "Fixed visit: {$visitLabel}" : "{$visit['frequency']}x per week",
                'estimated_hours_per_week' => round(($visit['frequency'] * $durationMinutes) / 60, 2),
                'source' => $source,
                'notes' => $notes,
            ]);
            $assignmentCount++;
        }

        $this->command->info("Seeded {$assignmentCount} non-overlapping service assignments.");
        if ($skippedCount > 0) {
            $this->command->warn("Skipped {$skippedCount} visits (no available staff slots).");
        }
    }

    /**
     * Track fixed-visit services that have been scheduled per care plan.
     * Structure: [care_plan_id_service_type_id => visit_count]
     */
    protected array $fixedVisitTracker = [];

    /**
     * Build a queue of all required visits across all weeks.
     *
     * For fixed-visit services (like RPM), only schedules the exact number
     * of visits per care plan (e.g., 2 for RPM: Setup and Discharge).
     */
    protected function buildVisitQueue($patients, int $weeks): array
    {
        $queue = [];
        $currentWeekStart = Carbon::now()->startOfWeek();

        // Reset fixed visit tracker
        $this->fixedVisitTracker = [];

        for ($weekOffset = $weeks - 1; $weekOffset >= 0; $weekOffset--) {
            $weekStart = $currentWeekStart->copy()->subWeeks($weekOffset);

            foreach ($patients as $patient) {
                $carePlan = $patient->carePlans->first();
                if (!$carePlan) continue;

                $services = $this->getCarePlanServices($carePlan);

                foreach ($services as $service) {
                    $serviceType = ServiceType::find($service['service_type_id']);

                    // Handle fixed-visit services (like RPM) differently
                    if ($serviceType && $serviceType->isFixedVisits()) {
                        $this->buildFixedVisitQueue(
                            $queue,
                            $carePlan,
                            $patient,
                            $service,
                            $serviceType,
                            $weekOffset,
                            $weeks,
                            $weekStart
                        );
                        continue;
                    }

                    // Standard weekly scheduling
                    $frequencyPerWeek = $service['frequency'];
                    $durationMinutes = $this->getServiceDuration($service);
                    $visitDays = $this->distributeVisitsAcrossWeek($frequencyPerWeek);

                    foreach ($visitDays as $dayIndex => $visitsOnDay) {
                        for ($v = 0; $v < $visitsOnDay; $v++) {
                            $visitDate = $weekStart->copy()->addDays($dayIndex);

                            $queue[] = [
                                'care_plan_id' => $carePlan->id,
                                'patient_id' => $patient->id,
                                'service_type_id' => $service['service_type_id'],
                                'service_code' => $service['code'],
                                'frequency' => $frequencyPerWeek,
                                'duration' => $durationMinutes,
                                'date' => $visitDate,
                            ];
                        }
                    }
                }
            }
        }

        return $queue;
    }

    /**
     * Build queue entries for fixed-visit services like RPM.
     *
     * RPM has exactly 2 visits per care plan:
     * - Visit 1 (Setup): Scheduled in first week
     * - Visit 2 (Discharge): Scheduled in last week
     */
    protected function buildFixedVisitQueue(
        array &$queue,
        CarePlan $carePlan,
        Patient $patient,
        array $service,
        ServiceType $serviceType,
        int $weekOffset,
        int $totalWeeks,
        Carbon $weekStart
    ): void {
        $trackerKey = "{$carePlan->id}_{$serviceType->id}";
        $fixedVisitsPerPlan = $serviceType->fixed_visits_per_plan ?? 2;
        $visitLabels = $serviceType->fixed_visit_labels ?? ['Setup', 'Discharge'];

        // Initialize tracker if not set
        if (!isset($this->fixedVisitTracker[$trackerKey])) {
            $this->fixedVisitTracker[$trackerKey] = 0;
        }

        // Check if we've already scheduled all fixed visits for this care plan
        if ($this->fixedVisitTracker[$trackerKey] >= $fixedVisitsPerPlan) {
            return;
        }

        $currentVisitCount = $this->fixedVisitTracker[$trackerKey];
        $durationMinutes = $this->getServiceDuration($service);

        // Determine if this week should have a fixed visit
        // Visit 1 (Setup): First week (weekOffset = totalWeeks - 1)
        // Visit 2 (Discharge): Last week (weekOffset = 0)
        $shouldSchedule = false;
        $visitLabel = null;

        if ($currentVisitCount === 0 && $weekOffset === ($totalWeeks - 1)) {
            // First visit (Setup) in the first week
            $shouldSchedule = true;
            $visitLabel = $visitLabels[0] ?? 'Visit 1';
        } elseif ($currentVisitCount === 1 && $weekOffset === 0) {
            // Second visit (Discharge) in the last week
            $shouldSchedule = true;
            $visitLabel = $visitLabels[1] ?? 'Visit 2';
        }

        if ($shouldSchedule) {
            // Schedule on Wednesday (middle of week)
            $visitDate = $weekStart->copy()->addDays(2);

            $queue[] = [
                'care_plan_id' => $carePlan->id,
                'patient_id' => $patient->id,
                'service_type_id' => $service['service_type_id'],
                'service_code' => $service['code'],
                'frequency' => 1, // Fixed visits are counted individually
                'duration' => $durationMinutes,
                'date' => $visitDate,
                'visit_label' => $visitLabel,
            ];

            $this->fixedVisitTracker[$trackerKey]++;
        }
    }

    /**
     * Get service duration from metadata or category defaults.
     */
    protected function getServiceDuration(array $service): int
    {
        // Use explicit duration if set
        if (!empty($service['duration']) && $service['duration'] > 0) {
            return $service['duration'];
        }

        // Get from service type model
        $serviceType = ServiceType::find($service['service_type_id']);
        if ($serviceType && $serviceType->default_duration_minutes) {
            return $serviceType->default_duration_minutes;
        }

        // Fall back to category defaults
        $category = strtolower($serviceType->category ?? 'other');
        return $this->categoryDurations[$category] ?? 60;
    }

    /**
     * Find an available slot for a staff member on a given day.
     *
     * Considers travel time from the staff's previous patient location.
     *
     * @param array $staffPool Array of staff members who can provide the service
     * @param Carbon $visitDate The date for the visit
     * @param int $durationMinutes Required duration
     * @param Patient|null $patient The patient to visit (for travel calculation)
     * @return array|null ['staff' => User, 'start' => Carbon, 'end' => Carbon] or null
     */
    protected function findAvailableSlot(
        array $staffPool,
        Carbon $visitDate,
        int $durationMinutes,
        ?Patient $patient = null
    ): ?array {
        // Shuffle staff to distribute load
        shuffle($staffPool);

        foreach ($staffPool as $staff) {
            $slot = $this->getNextSlotForStaff($staff, $visitDate, $durationMinutes, $patient);
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
     * NEW: Considers travel time from previous patient location.
     *
     * @param User $staff
     * @param Carbon $visitDate
     * @param int $durationMinutes
     * @param Patient|null $patient The patient to visit (for travel calculation)
     * @return array|null ['start' => Carbon, 'end' => Carbon] or null
     */
    protected function getNextSlotForStaff(
        User $staff,
        Carbon $visitDate,
        int $durationMinutes,
        ?Patient $patient = null
    ): ?array {
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

        // Get or initialize the staff's schedule for this day
        if (!isset($this->staffSchedules[$staffKey][$dateKey])) {
            $this->staffSchedules[$staffKey][$dateKey] = [
                'next_available' => $dayStart->copy(),
                'last_patient' => null,
            ];
        }

        $schedule = $this->staffSchedules[$staffKey][$dateKey];
        $nextAvailable = $schedule['next_available']->copy();
        $lastPatient = $schedule['last_patient'];

        // Calculate travel time from previous patient (if any)
        $travelMinutes = 0;
        if ($lastPatient && $patient && $lastPatient->hasCoordinates() && $patient->hasCoordinates()) {
            $travelMinutes = $this->travelTimeService->getTravelMinutes(
                $lastPatient->lat,
                $lastPatient->lng,
                $patient->lat,
                $patient->lng,
                $nextAvailable
            );
        }

        // Add travel time to next available
        $slotStart = $nextAvailable->copy()->addMinutes($travelMinutes);
        $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

        // Check if slot fits within day's availability
        if ($slotEnd->gt($dayEnd)) {
            return null; // No more room today
        }

        // Reserve the slot (update next available time with buffer)
        $this->staffSchedules[$staffKey][$dateKey] = [
            'next_available' => $slotEnd->copy()->addMinutes($this->bufferMinutes),
            'last_patient' => $patient,
        ];

        return [
            'start' => $slotStart,
            'end' => $slotEnd,
        ];
    }

    /**
     * Get staff with their availabilities loaded.
     */
    protected function getStaffWithAvailability(ServiceProviderOrganization $spo): \Illuminate\Support\Collection
    {
        return User::where('organization_id', $spo->id)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where('staff_status', User::STAFF_STATUS_ACTIVE)
            ->with(['staffRole', 'availabilities'])
            ->get();
    }

    /**
     * Group staff by role code.
     */
    protected function groupStaffByRole($staff): array
    {
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
     * Get services from care plan (supports both new template system and legacy bundle).
     */
    protected function getCarePlanServices(CarePlan $carePlan): array
    {
        $services = [];

        // Try new CareBundleTemplate system first
        if ($carePlan->careBundleTemplate && $carePlan->careBundleTemplate->services->isNotEmpty()) {
            foreach ($carePlan->careBundleTemplate->services as $templateService) {
                $services[] = [
                    'service_type_id' => $templateService->service_type_id,
                    'code' => $templateService->serviceType?->code ?? 'UNKNOWN',
                    'frequency' => $templateService->default_frequency_per_week ?? 1,
                    'duration' => $templateService->default_duration_minutes ?? 60,
                ];
            }
        }
        // Fall back to legacy CareBundle
        elseif ($carePlan->careBundle && $carePlan->careBundle->serviceTypes->isNotEmpty()) {
            foreach ($carePlan->careBundle->serviceTypes as $serviceType) {
                $services[] = [
                    'service_type_id' => $serviceType->id,
                    'code' => $serviceType->code,
                    'frequency' => $serviceType->pivot->default_frequency_per_week ?? 1,
                    'duration' => $serviceType->default_duration_minutes ?? 60,
                ];
            }
        }

        return $services;
    }

    /**
     * Find staff that can deliver a specific service.
     */
    protected function findStaffForService(
        string $serviceCode,
        array $spoStaffByRole,
        array $sspoStaffByRole
    ): array {
        $result = ['internal' => [], 'sspo' => []];

        foreach ($spoStaffByRole as $roleCode => $staffList) {
            $serviceCodes = $this->getRoleServiceCodes($roleCode);
            if (in_array($serviceCode, $serviceCodes, true)) {
                $result['internal'] = array_merge($result['internal'], $staffList);
            }
        }

        foreach ($sspoStaffByRole as $roleCode => $staffList) {
            $serviceCodes = $this->getRoleServiceCodes($roleCode);
            if (in_array($serviceCode, $serviceCodes, true)) {
                $result['sspo'] = array_merge($result['sspo'], $staffList);
            }
        }

        return $result;
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

    /**
     * Check if a time slot is available for a patient (non-concurrency check).
     *
     * Ensures the patient doesn't have overlapping visits.
     *
     * @param int $patientId Patient ID
     * @param Carbon $start Proposed start time
     * @param Carbon $end Proposed end time
     * @return bool True if slot is available
     */
    protected function isPatientSlotAvailable(int $patientId, Carbon $start, Carbon $end): bool
    {
        $dateKey = $start->toDateString();

        if (!isset($this->patientSchedules[$patientId][$dateKey])) {
            return true; // No visits scheduled for this patient on this day
        }

        // Check for overlaps with existing visits
        foreach ($this->patientSchedules[$patientId][$dateKey] as $visit) {
            // Check if [start, end) overlaps with existing visit
            if ($start->lt($visit['end']) && $end->gt($visit['start'])) {
                return false; // Overlap detected
            }
        }

        return true;
    }

    /**
     * Check if a time slot respects spacing rules for the service type.
     *
     * Ensures minimum gap between visits of the same service type.
     *
     * @param int $patientId Patient ID
     * @param int $serviceTypeId Service type ID
     * @param Carbon $start Proposed start time
     * @return bool True if spacing rule is satisfied
     */
    protected function respectsSpacingRule(int $patientId, int $serviceTypeId, Carbon $start): bool
    {
        $serviceType = ServiceType::find($serviceTypeId);
        if (!$serviceType || !$serviceType->min_gap_between_visits_minutes) {
            return true; // No spacing rule
        }

        $minGapMinutes = $serviceType->min_gap_between_visits_minutes;
        $dateKey = $start->toDateString();

        if (!isset($this->patientSchedules[$patientId][$dateKey])) {
            return true; // No visits scheduled yet
        }

        // Find the most recent visit of the same service type
        $lastVisitEnd = null;
        foreach ($this->patientSchedules[$patientId][$dateKey] as $visit) {
            if ($visit['service_type_id'] === $serviceTypeId && $visit['end']->lte($start)) {
                if (!$lastVisitEnd || $visit['end']->gt($lastVisitEnd)) {
                    $lastVisitEnd = $visit['end'];
                }
            }
        }

        if ($lastVisitEnd) {
            $actualGap = $lastVisitEnd->diffInMinutes($start);
            return $actualGap >= $minGapMinutes;
        }

        return true; // No previous visit of this service type
    }

    /**
     * Record a patient visit in the schedule tracker.
     *
     * @param int $patientId Patient ID
     * @param int $serviceTypeId Service type ID
     * @param Carbon $start Start time
     * @param Carbon $end End time
     */
    protected function recordPatientVisit(int $patientId, int $serviceTypeId, Carbon $start, Carbon $end): void
    {
        $dateKey = $start->toDateString();

        if (!isset($this->patientSchedules[$patientId])) {
            $this->patientSchedules[$patientId] = [];
        }

        if (!isset($this->patientSchedules[$patientId][$dateKey])) {
            $this->patientSchedules[$patientId][$dateKey] = [];
        }

        $this->patientSchedules[$patientId][$dateKey][] = [
            'start' => $start,
            'end' => $end,
            'service_type_id' => $serviceTypeId,
        ];
    }
}
