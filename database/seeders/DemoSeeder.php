<?php

namespace Database\Seeders;

use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\ServiceProviderOrganization;
use App\Models\Hospital;
use App\Models\RetirementHome;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run()
    {
        // 1. Admin
        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'System Admin',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        // 2. SPO Admin & Organization (SE Health)
        $spo = ServiceProviderOrganization::firstOrCreate(
            ['slug' => 'se-health'],
            ['name' => 'SE Health', 'active' => true]
        );
        User::updateOrCreate(['email' => 'admin@sehc.com'], [
            'name' => 'SE Health Admin',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SPO_ADMIN,
            'organization_id' => $spo->id,
            'organization_role' => User::ROLE_SPO_ADMIN,
        ]);

        // 3. Hospital User
        $hospitalUser = User::updateOrCreate(['email' => 'hospital@example.com'], [
            'name' => 'Hospital Staff',
            'password' => Hash::make('password'),
            'role' => User::ROLE_HOSPITAL,
        ]);
        Hospital::firstOrCreate(['user_id' => $hospitalUser->id]);

        // 4. SPO Coordinator
        User::updateOrCreate(['email' => 'coordinator@sehc.com'], [
            'name' => 'Sarah Mitchell',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SPO_COORDINATOR,
            'organization_id' => $spo->id,
            'organization_role' => 'Care Coordinator',
            'employment_type' => 'full_time',
            'max_weekly_hours' => 40,
            'staff_status' => 'active',
        ]);

        // 5. Field Staff - Full Time
        User::updateOrCreate(['email' => 'maria.santos@sehc.com'], [
            'name' => 'Maria Santos',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'RN',
            'employment_type' => 'full_time',
            'max_weekly_hours' => 40,
            'staff_status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'james.chen@sehc.com'], [
            'name' => 'James Chen',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'PSW',
            'employment_type' => 'full_time',
            'max_weekly_hours' => 40,
            'staff_status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'aisha.patel@sehc.com'], [
            'name' => 'Aisha Patel',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'OT',
            'employment_type' => 'full_time',
            'max_weekly_hours' => 40,
            'staff_status' => 'active',
        ]);

        // 6. Field Staff - Part Time
        User::updateOrCreate(['email' => 'david.wilson@sehc.com'], [
            'name' => 'David Wilson',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'RPN',
            'employment_type' => 'part_time',
            'max_weekly_hours' => 24,
            'staff_status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'lisa.nguyen@sehc.com'], [
            'name' => 'Lisa Nguyen',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'PSW',
            'employment_type' => 'part_time',
            'max_weekly_hours' => 20,
            'staff_status' => 'active',
        ]);

        // 7. Field Staff - Casual
        User::updateOrCreate(['email' => 'michael.brown@sehc.com'], [
            'name' => 'Michael Brown',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'PSW',
            'employment_type' => 'casual',
            'max_weekly_hours' => 16,
            'staff_status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'emma.taylor@sehc.com'], [
            'name' => 'Emma Taylor',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'RN',
            'employment_type' => 'casual',
            'max_weekly_hours' => 12,
            'staff_status' => 'active',
        ]);

        // 8. Staff on leave
        User::updateOrCreate(['email' => 'robert.lee@sehc.com'], [
            'name' => 'Robert Lee',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $spo->id,
            'organization_role' => 'PT',
            'employment_type' => 'full_time',
            'max_weekly_hours' => 40,
            'staff_status' => 'on_leave',
        ]);

        // 9. Seed care activities (service assignments) for the current week
        $this->seedCareActivities($spo);
    }

    /**
     * Seed care activities (ServiceAssignments) for all staff for the current week.
     *
     * This method is metadata-driven:
     * - Uses bundle's default_frequency_per_week to determine visits per week
     * - Uses service type's default_duration_minutes for visit duration
     * - Assigns staff based on role matching service type codes
     * - Respects bundle's assignment_type (Internal vs External)
     */
    protected function seedCareActivities(ServiceProviderOrganization $spo): void
    {
        // Map staff roles to service type codes
        $roleToServiceType = [
            'RN' => 'NUR',
            'RPN' => 'NUR',
            'PSW' => 'PSW',
            'OT' => 'OT',
            'PT' => 'PT',
        ];

        // Get all active field staff in the organization, grouped by their service type
        $staffByServiceType = [];
        $activeStaff = User::where('organization_id', $spo->id)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where('staff_status', 'active')
            ->get();

        foreach ($activeStaff as $staff) {
            $serviceTypeCode = $roleToServiceType[$staff->organization_role] ?? null;
            if ($serviceTypeCode) {
                $staffByServiceType[$serviceTypeCode][] = $staff;
            }
        }

        if (empty($staffByServiceType)) {
            return;
        }

        // Get active patients with care plans and their bundles' service types (with pivot data)
        $patients = Patient::where('status', 'Active')
            ->whereHas('carePlans', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['carePlans' => function ($q) {
                $q->where('status', 'active')->with('careBundle.serviceTypes');
            }])
            ->get();

        if ($patients->isEmpty()) {
            return;
        }

        // Current week boundaries
        $weekStart = Carbon::now()->startOfWeek();

        // Process each patient's care plan
        foreach ($patients as $patient) {
            $carePlan = $patient->carePlans->first();
            if (!$carePlan || !$carePlan->careBundle) {
                continue;
            }

            $bundle = $carePlan->careBundle;

            // Process each service type in the bundle
            foreach ($bundle->serviceTypes as $serviceType) {
                // Get bundle-defined frequency and assignment type from pivot
                $frequencyPerWeek = $serviceType->pivot->default_frequency_per_week ?? 1;
                $assignmentType = $serviceType->pivot->assignment_type ?? 'Either';

                // Skip external-only services (those would be assigned to SSPOs)
                if ($assignmentType === 'External') {
                    continue;
                }

                // Get available staff for this service type
                $availableStaff = $staffByServiceType[$serviceType->code] ?? [];
                if (empty($availableStaff)) {
                    continue;
                }

                // Get duration from service type metadata (default to 60 min if not set)
                $durationMinutes = $serviceType->default_duration_minutes ?? 60;

                // Distribute visits across the week based on frequency
                $visitDays = $this->distributeVisitsAcrossWeek($frequencyPerWeek);

                foreach ($visitDays as $dayIndex => $visitsOnDay) {
                    for ($visitNum = 0; $visitNum < $visitsOnDay; $visitNum++) {
                        $visitDate = $weekStart->copy()->addDays($dayIndex);
                        $isPastOrToday = $visitDate->lte(Carbon::today());

                        // Round-robin staff assignment
                        $staff = $availableStaff[array_rand($availableStaff)];

                        // Calculate visit time (spread visits throughout the day)
                        $startHour = 8 + ($visitNum * 3); // 8am, 11am, 2pm, etc.
                        $scheduledStart = $visitDate->copy()->setTime($startHour, 0);
                        $scheduledEnd = $scheduledStart->copy()->addMinutes($durationMinutes);

                        // Determine status based on date
                        $status = $this->determineVisitStatus($visitDate, $isPastOrToday);

                        // Create the service assignment using bundle metadata
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
                                'status' => $status,
                                'scheduled_end' => $scheduledEnd,
                                'frequency_rule' => "{$frequencyPerWeek}x per week",
                                'estimated_hours_per_week' => round(($frequencyPerWeek * $durationMinutes) / 60, 2),
                                'source' => 'manual',
                                'notes' => "Bundle: {$bundle->code} | Service: {$serviceType->name}",
                                'actual_start' => $status === 'completed' ? $scheduledStart : null,
                                'actual_end' => $status === 'completed' ? $scheduledEnd : null,
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * Distribute visits across the week based on frequency.
     * Returns array where index = day (0=Mon, 4=Fri), value = visits on that day.
     */
    protected function distributeVisitsAcrossWeek(int $frequencyPerWeek): array
    {
        $days = [0, 0, 0, 0, 0]; // Mon-Fri

        if ($frequencyPerWeek >= 14) {
            // 14+ visits = 2-3 visits per day
            $days = [3, 3, 3, 3, 2];
        } elseif ($frequencyPerWeek >= 7) {
            // 7-13 visits = 1-2 visits per day
            $perDay = intdiv($frequencyPerWeek, 5);
            $remainder = $frequencyPerWeek % 5;
            for ($i = 0; $i < 5; $i++) {
                $days[$i] = $perDay + ($i < $remainder ? 1 : 0);
            }
        } elseif ($frequencyPerWeek >= 5) {
            // 5-6 visits = 1 per day, some days have 2
            $days = [1, 1, 1, 1, 1];
            $extra = $frequencyPerWeek - 5;
            for ($i = 0; $i < $extra; $i++) {
                $days[$i]++;
            }
        } elseif ($frequencyPerWeek >= 3) {
            // 3-4 visits = Mon, Wed, Fri (+ Tue if 4)
            $days[0] = 1; // Mon
            $days[2] = 1; // Wed
            $days[4] = 1; // Fri
            if ($frequencyPerWeek == 4) {
                $days[1] = 1; // Tue
            }
        } elseif ($frequencyPerWeek == 2) {
            // 2 visits = Mon, Thu
            $days[0] = 1;
            $days[3] = 1;
        } elseif ($frequencyPerWeek == 1) {
            // 1 visit = Wednesday (mid-week)
            $days[2] = 1;
        }

        return $days;
    }

    /**
     * Determine visit status based on the date.
     */
    protected function determineVisitStatus(Carbon $visitDate, bool $isPastOrToday): string
    {
        if (!$isPastOrToday) {
            return 'planned';
        }

        if ($visitDate->lt(Carbon::today())) {
            return 'completed';
        }

        // Today - mix of statuses
        $statuses = ['completed', 'completed', 'in_progress', 'planned'];
        return $statuses[array_rand($statuses)];
    }
}
