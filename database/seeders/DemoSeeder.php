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
     * Seed care activities (ServiceAssignments) for all staff for the current week
     * This allows testing that the FTE ratio calculation is working properly
     */
    protected function seedCareActivities(ServiceProviderOrganization $spo): void
    {
        // Get all active field staff in the organization
        $activeStaff = User::where('organization_id', $spo->id)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->where('staff_status', 'active')
            ->get();

        // Get active patients with care plans
        $patients = Patient::where('status', 'Active')
            ->whereHas('carePlans', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['carePlans' => function ($q) {
                $q->where('status', 'active');
            }])
            ->get();

        if ($patients->isEmpty() || $activeStaff->isEmpty()) {
            return; // No patients or staff to create assignments for
        }

        // Get service types matched to staff roles
        $serviceTypeMap = [
            'RN' => 'NUR',   // Nursing
            'RPN' => 'NUR',  // Nursing
            'PSW' => 'PSW',  // Personal Support Worker
            'OT' => 'OT',    // Occupational Therapy
            'PT' => 'PT',    // Physiotherapy
        ];

        // Current week boundaries
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        // Define visit schedules per employment type (hours per day)
        $hoursPerDay = [
            'full_time' => 6,   // ~30 hours/week (some admin time)
            'part_time' => 4,   // ~20 hours/week
            'casual' => 2,      // ~10 hours/week
        ];

        // Statuses to distribute among assignments
        $statuses = ['completed', 'completed', 'completed', 'in_progress', 'planned'];

        foreach ($activeStaff as $staff) {
            // Get appropriate service type for this staff member's role
            $serviceTypeCode = $serviceTypeMap[$staff->organization_role] ?? 'PSW';
            $serviceType = ServiceType::where('code', $serviceTypeCode)->first();

            if (!$serviceType) {
                continue;
            }

            $dailyHours = $hoursPerDay[$staff->employment_type] ?? 4;

            // Create assignments for each day of the week (Mon-Fri)
            for ($day = 0; $day < 5; $day++) {
                $visitDate = $weekStart->copy()->addDays($day);

                // Skip future days for completed status
                $isPastOrToday = $visitDate->lte(Carbon::today());

                // Assign to a random patient
                $patient = $patients->random();
                $carePlan = $patient->carePlans->first();

                if (!$carePlan) {
                    continue;
                }

                // Create 2-3 visits per day for this staff member
                $visitsPerDay = rand(2, 3);
                $hoursPerVisit = $dailyHours / $visitsPerDay;

                for ($visit = 0; $visit < $visitsPerDay; $visit++) {
                    // Calculate visit time slots (starting at 8am, 11am, 2pm)
                    $startHour = 8 + ($visit * 3);
                    $scheduledStart = $visitDate->copy()->setTime($startHour, 0);
                    $scheduledEnd = $scheduledStart->copy()->addHours($hoursPerVisit);

                    // Determine status based on date
                    if ($isPastOrToday) {
                        $status = $statuses[array_rand($statuses)];
                        // Past days should be completed
                        if ($visitDate->lt(Carbon::today())) {
                            $status = 'completed';
                        }
                    } else {
                        $status = 'planned';
                    }

                    // Rotate through patients
                    $patient = $patients->random();
                    $carePlan = $patient->carePlans->first();

                    ServiceAssignment::updateOrCreate(
                        [
                            'care_plan_id' => $carePlan->id,
                            'patient_id' => $patient->id,
                            'assigned_user_id' => $staff->id,
                            'scheduled_start' => $scheduledStart,
                        ],
                        [
                            'service_type_id' => $serviceType->id,
                            'service_provider_organization_id' => $spo->id,
                            'status' => $status,
                            'scheduled_end' => $scheduledEnd,
                            'frequency_rule' => 'daily',
                            'source' => 'manual',
                            'notes' => "Demo visit for {$staff->name}",
                            'actual_start' => $status === 'completed' ? $scheduledStart : null,
                            'actual_end' => $status === 'completed' ? $scheduledEnd : null,
                        ]
                    );
                }
            }
        }
    }
}
