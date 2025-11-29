<?php

namespace Database\Seeders;

use App\Models\CareBundleService;
use App\Models\CareBundleTemplate;
use App\Models\CarePlan;
use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * CoreDataSeeder - Seeds essential data for Connected Capacity 2.1
 *
 * This seeder creates:
 * - Service types with proper spacing rules (PSW = 120 min gap)
 * - Service types with fixed-visits mode (RPM = 2 visits per plan)
 * - Sample care bundle templates
 * - Demo patients with care plans
 * - Sample service assignments that respect spacing rules
 */
class CoreDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedServiceTypes();
        $this->seedCareBundleTemplates();
        $this->seedDemoPatients();
        $this->seedSampleAssignments();
    }

    /**
     * Seed service types with proper scheduling configurations.
     */
    private function seedServiceTypes(): void
    {
        $serviceTypes = [
            [
                'code' => 'PSW',
                'name' => 'Personal Support Worker',
                'category' => 'personal_care',
                'color' => '#10b981', // Green
                'default_duration_minutes' => 60,
                'scheduling_mode' => 'weekly',
                'min_gap_between_visits_minutes' => 120, // 2 hours spacing
            ],
            [
                'code' => 'PT',
                'name' => 'Physiotherapy',
                'category' => 'therapy',
                'color' => '#6366f1', // Indigo
                'default_duration_minutes' => 60,
                'scheduling_mode' => 'weekly',
                'min_gap_between_visits_minutes' => null, // No spacing requirement
            ],
            [
                'code' => 'OT',
                'name' => 'Occupational Therapy',
                'category' => 'therapy',
                'color' => '#8b5cf6', // Purple
                'default_duration_minutes' => 60,
                'scheduling_mode' => 'weekly',
                'min_gap_between_visits_minutes' => null,
            ],
            [
                'code' => 'NUR',
                'name' => 'Nursing',
                'category' => 'nursing',
                'color' => '#ef4444', // Red
                'default_duration_minutes' => 45,
                'scheduling_mode' => 'weekly',
                'min_gap_between_visits_minutes' => 60, // 1 hour spacing
            ],
            [
                'code' => 'RPM',
                'name' => 'Remote Patient Monitoring',
                'category' => 'monitoring',
                'color' => '#0ea5e9', // Sky blue
                'default_duration_minutes' => 30,
                'scheduling_mode' => 'fixed_visits',
                'fixed_visits_per_plan' => 2,
                'fixed_visit_labels' => ['Setup', 'Discharge'],
                'min_gap_between_visits_minutes' => null,
            ],
            [
                'code' => 'SW',
                'name' => 'Social Work',
                'category' => 'support',
                'color' => '#f59e0b', // Amber
                'default_duration_minutes' => 60,
                'scheduling_mode' => 'weekly',
                'min_gap_between_visits_minutes' => null,
            ],
            [
                'code' => 'MEAL',
                'name' => 'Meal Service',
                'category' => 'support',
                'color' => '#84cc16', // Lime
                'default_duration_minutes' => 30,
                'scheduling_mode' => 'weekly',
                'min_gap_between_visits_minutes' => 180, // 3 hours between meals
            ],
            [
                'code' => 'RESP',
                'name' => 'Respite Care',
                'category' => 'personal_care',
                'color' => '#14b8a6', // Teal
                'default_duration_minutes' => 180,
                'scheduling_mode' => 'weekly',
                'min_gap_between_visits_minutes' => null,
            ],
        ];

        foreach ($serviceTypes as $data) {
            ServiceType::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }

    /**
     * Seed care bundle templates with service requirements.
     */
    private function seedCareBundleTemplates(): void
    {
        // Get service types
        $psw = ServiceType::where('code', 'PSW')->first();
        $pt = ServiceType::where('code', 'PT')->first();
        $ot = ServiceType::where('code', 'OT')->first();
        $nur = ServiceType::where('code', 'NUR')->first();
        $rpm = ServiceType::where('code', 'RPM')->first();
        $sw = ServiceType::where('code', 'SW')->first();

        // Rehab Bundle - High complexity
        $rehabBundle = CareBundleTemplate::updateOrCreate(
            ['rug_group' => 'RB0'],
            [
                'name' => 'RB0 - Rehab Bundle High',
                'category' => 'Rehab',
                'description' => 'High-intensity rehabilitation bundle with extensive therapy and personal care.',
                'weekly_cost_cents' => 450000, // $4,500/week
            ]
        );

        // Add services to rehab bundle
        $this->addBundleService($rehabBundle, $psw, 21); // 21h/week PSW
        $this->addBundleService($rehabBundle, $pt, 3);   // 3h/week PT
        $this->addBundleService($rehabBundle, $ot, 2);   // 2h/week OT
        $this->addBundleService($rehabBundle, $nur, 2);  // 2h/week NUR
        $this->addBundleServiceVisits($rehabBundle, $rpm, 2); // 2 visits RPM

        // Clinically Complex Bundle
        $ccBundle = CareBundleTemplate::updateOrCreate(
            ['rug_group' => 'CC0'],
            [
                'name' => 'CC0 - Clinically Complex',
                'category' => 'Clinically Complex',
                'description' => 'Bundle for clinically complex patients requiring skilled nursing.',
                'weekly_cost_cents' => 350000, // $3,500/week
            ]
        );

        $this->addBundleService($ccBundle, $psw, 14); // 14h/week PSW
        $this->addBundleService($ccBundle, $nur, 4);  // 4h/week NUR
        $this->addBundleService($ccBundle, $sw, 1);   // 1h/week SW
        $this->addBundleServiceVisits($ccBundle, $rpm, 2); // 2 visits RPM

        // Basic Physical Function Bundle
        $physBundle = CareBundleTemplate::updateOrCreate(
            ['rug_group' => 'PA1'],
            [
                'name' => 'PA1 - Physical Function Basic',
                'category' => 'Physical',
                'description' => 'Basic physical function support bundle.',
                'weekly_cost_cents' => 200000, // $2,000/week
            ]
        );

        $this->addBundleService($physBundle, $psw, 10); // 10h/week PSW
        $this->addBundleService($physBundle, $pt, 1);   // 1h/week PT
    }

    private function addBundleService(CareBundleTemplate $bundle, ServiceType $serviceType, float $hoursPerWeek): void
    {
        CareBundleService::updateOrCreate(
            [
                'care_bundle_template_id' => $bundle->id,
                'service_type_id' => $serviceType->id,
            ],
            [
                'hours_per_week' => $hoursPerWeek,
                'visits_per_plan' => null,
                'is_required' => true,
            ]
        );
    }

    private function addBundleServiceVisits(CareBundleTemplate $bundle, ServiceType $serviceType, int $visitsPerPlan): void
    {
        CareBundleService::updateOrCreate(
            [
                'care_bundle_template_id' => $bundle->id,
                'service_type_id' => $serviceType->id,
            ],
            [
                'hours_per_week' => null,
                'visits_per_plan' => $visitsPerPlan,
                'is_required' => true,
            ]
        );
    }

    /**
     * Seed demo patients with care plans.
     */
    private function seedDemoPatients(): void
    {
        $rehabBundle = CareBundleTemplate::where('rug_group', 'RB0')->first();
        $ccBundle = CareBundleTemplate::where('rug_group', 'CC0')->first();
        $physBundle = CareBundleTemplate::where('rug_group', 'PA1')->first();

        // Patient 1 - Rehab patient with high needs
        $patient1 = Patient::updateOrCreate(
            ['email' => 'margaret.chen@example.com'],
            [
                'first_name' => 'Margaret',
                'last_name' => 'Chen',
                'date_of_birth' => '1945-03-15',
                'phone' => '416-555-0101',
                'address' => '123 Maple Street, Toronto, ON',
                'postal_code' => 'M4X 1K9',
                'status' => 'active',
                'rug_category' => 'RB0',
                'risk_flags' => ['high_fall_risk', 'cognitive_impairment'],
            ]
        );

        CarePlan::updateOrCreate(
            ['patient_id' => $patient1->id, 'status' => 'active'],
            [
                'care_bundle_template_id' => $rehabBundle->id,
                'version' => 1,
                'start_date' => Carbon::now()->subMonth(),
            ]
        );

        // Patient 2 - Clinically complex
        $patient2 = Patient::updateOrCreate(
            ['email' => 'robert.smith@example.com'],
            [
                'first_name' => 'Robert',
                'last_name' => 'Smith',
                'date_of_birth' => '1938-07-22',
                'phone' => '416-555-0102',
                'address' => '456 Oak Avenue, Toronto, ON',
                'postal_code' => 'M5G 2C4',
                'status' => 'active',
                'rug_category' => 'CC0',
                'risk_flags' => ['clinical_instability'],
            ]
        );

        CarePlan::updateOrCreate(
            ['patient_id' => $patient2->id, 'status' => 'active'],
            [
                'care_bundle_template_id' => $ccBundle->id,
                'version' => 1,
                'start_date' => Carbon::now()->subWeeks(2),
            ]
        );

        // Patient 3 - Basic physical function
        $patient3 = Patient::updateOrCreate(
            ['email' => 'helen.davis@example.com'],
            [
                'first_name' => 'Helen',
                'last_name' => 'Davis',
                'date_of_birth' => '1950-11-08',
                'phone' => '416-555-0103',
                'address' => '789 Pine Road, Scarborough, ON',
                'postal_code' => 'M1B 3K5',
                'status' => 'active',
                'rug_category' => 'PA1',
                'risk_flags' => [],
            ]
        );

        CarePlan::updateOrCreate(
            ['patient_id' => $patient3->id, 'status' => 'active'],
            [
                'care_bundle_template_id' => $physBundle->id,
                'version' => 1,
                'start_date' => Carbon::now()->subDays(10),
            ]
        );
    }

    /**
     * Seed sample service assignments that respect spacing rules.
     *
     * This demonstrates:
     * - PSW visits spaced 2+ hours apart (morning/midday/afternoon)
     * - No overlapping visits for the same patient
     * - RPM visits with Setup/Discharge labels
     */
    private function seedSampleAssignments(): void
    {
        $psw = ServiceType::where('code', 'PSW')->first();
        $pt = ServiceType::where('code', 'PT')->first();
        $rpm = ServiceType::where('code', 'RPM')->first();

        $patient1 = Patient::where('email', 'margaret.chen@example.com')->first();
        $carePlan1 = $patient1->activeCarePlan;

        if (!$patient1 || !$carePlan1) {
            return;
        }

        $monday = Carbon::now()->startOfWeek();

        // Monday: PSW morning (08:00-09:00), PT (10:00-11:00), PSW afternoon (14:00-15:00)
        // This respects: patient non-concurrency AND PSW 120-min spacing
        $this->createAssignment($patient1, $carePlan1, $psw, $monday->copy()->setTime(8, 0), 60);
        $this->createAssignment($patient1, $carePlan1, $pt, $monday->copy()->setTime(10, 0), 60);
        $this->createAssignment($patient1, $carePlan1, $psw, $monday->copy()->setTime(14, 0), 60);

        // Tuesday: PSW morning (08:00-09:00), PSW midday (11:00-12:00), PSW afternoon (15:00-16:00)
        $tuesday = $monday->copy()->addDay();
        $this->createAssignment($patient1, $carePlan1, $psw, $tuesday->copy()->setTime(8, 0), 60);
        $this->createAssignment($patient1, $carePlan1, $psw, $tuesday->copy()->setTime(11, 0), 60);
        $this->createAssignment($patient1, $carePlan1, $psw, $tuesday->copy()->setTime(15, 0), 60);

        // Wednesday: RPM Setup visit
        $wednesday = $monday->copy()->addDays(2);
        $this->createAssignment($patient1, $carePlan1, $rpm, $wednesday->copy()->setTime(9, 0), 30, 'Setup');

        // Next week: RPM Discharge visit (scheduled in advance)
        $nextWeekWed = $wednesday->copy()->addWeek();
        $this->createAssignment($patient1, $carePlan1, $rpm, $nextWeekWed->copy()->setTime(9, 0), 30, 'Discharge');
    }

    private function createAssignment(
        Patient $patient,
        CarePlan $carePlan,
        ServiceType $serviceType,
        Carbon $start,
        int $durationMinutes,
        ?string $visitLabel = null
    ): void {
        ServiceAssignment::updateOrCreate(
            [
                'patient_id' => $patient->id,
                'service_type_id' => $serviceType->id,
                'scheduled_start' => $start,
            ],
            [
                'care_plan_id' => $carePlan->id,
                'scheduled_end' => $start->copy()->addMinutes($durationMinutes),
                'duration_minutes' => $durationMinutes,
                'status' => 'planned',
                'visit_label' => $visitLabel,
            ]
        );
    }
}
