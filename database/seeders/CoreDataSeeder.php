<?php

namespace Database\Seeders;

use App\Models\CareBundle;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

/**
 * CoreDataSeeder - Seeds essential business data for the metadata-object-model architecture
 *
 * This seeder creates the foundational service types and care bundles that the
 * rest of the application depends on. Must run before MetadataSeeder and
 * QueueWorkflowSeeder.
 */
class CoreDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedServiceCategories();
        $this->seedServiceTypes();
        $this->seedCareBundles();
        $this->linkBundlesToServices();
    }

    protected function seedServiceCategories(): void
    {
        $categories = [
            ['code' => 'CLINICAL', 'name' => 'Clinical Services'],
            ['code' => 'PERSONAL', 'name' => 'Personal Support & Daily Living'],
            ['code' => 'SAFETY', 'name' => 'Safety, Monitoring & Technology'],
            ['code' => 'LOGISTICS', 'name' => 'Logistics & Access Services'],
        ];

        foreach ($categories as $cat) {
            ServiceCategory::firstOrCreate(['code' => $cat['code']], $cat);
        }
    }

    protected function seedServiceTypes(): void
    {
        $clinical = ServiceCategory::where('code', 'CLINICAL')->first();
        $personal = ServiceCategory::where('code', 'PERSONAL')->first();
        $safety = ServiceCategory::where('code', 'SAFETY')->first();
        $logistics = ServiceCategory::where('code', 'LOGISTICS')->first();

        $serviceTypes = [
            // Clinical Services
            [
                'code' => 'NUR',
                'name' => 'Nursing (RN/RPN)',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-NUR',
                'cost_driver' => 'Hourly Labour or Per Visit Rate',
                'cost_per_visit' => 120.00,
                'source' => 'Sched 3 (Nursing)',
                'default_duration_minutes' => 60,
                'description' => 'Wound Care (surgical, pressure ulcers, negative pressure therapy), Infusion (IV therapy, CVAD maintenance, hypodermoclysis), Palliative (pain/symptom management, end-of-life care), Meds (administration, reconciliation)',
            ],
            [
                'code' => 'PT',
                'name' => 'Physiotherapy (PT)',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-PT',
                'cost_driver' => 'Per Visit Rate',
                'cost_per_visit' => 140.00,
                'source' => 'Sched 3 (PT)',
                'default_duration_minutes' => 45,
                'description' => 'Mobility (gait training, fall prevention, transfer training), Chest PT (postural drainage, suctioning), Modalities (ultrasound, TENS, laser)',
            ],
            [
                'code' => 'OT',
                'name' => 'Occupational Therapy (OT)',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-OT',
                'cost_driver' => 'Per Visit Rate',
                'cost_per_visit' => 150.00,
                'source' => 'Sched 3 (OT)',
                'default_duration_minutes' => 45,
                'description' => 'ADL Training (feeding, dressing, bathing retraining), Safety (home environment assessment, equipment prescription/ADP)',
            ],
            [
                'code' => 'RT',
                'name' => 'Respiratory Therapy (RT)',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-RT',
                'cost_driver' => 'Per Visit Rate',
                'cost_per_visit' => 130.00,
                'source' => 'Sched 3 (RT)',
                'default_duration_minutes' => 45,
                'description' => 'Airway (tracheostomy care, deep suctioning, ventilator management), Oxygen (home oxygen titration and setup)',
            ],
            [
                'code' => 'SW',
                'name' => 'Social Work (SW)',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-SW',
                'cost_driver' => 'Per Visit Rate',
                'cost_per_visit' => 135.00,
                'source' => 'Sched 3 (SW)',
                'default_duration_minutes' => 60,
                'description' => 'Counseling (grief, adjustment to illness, crisis intervention), Navigation (financial aid, housing, LTC placement applications)',
            ],
            [
                'code' => 'RD',
                'name' => 'Dietetics (RD)',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-RD',
                'cost_driver' => 'Per Visit Rate',
                'cost_per_visit' => 125.00,
                'source' => 'Sched 3 (RD)',
                'default_duration_minutes' => 45,
                'description' => 'Nutrition (therapeutic diets for diabetes, dysphagia, tube feeding formulas), Assessment (weight monitoring, malnutrition strategies)',
            ],
            [
                'code' => 'SLP',
                'name' => 'Speech-Language (SLP)',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-SLP',
                'cost_driver' => 'Per Visit Rate',
                'cost_per_visit' => 145.00,
                'source' => 'Sched 3 (SLP)',
                'default_duration_minutes' => 45,
                'description' => 'Swallowing (dysphagia management, texture modification), Communication (aphasia therapy, voice devices)',
            ],
            [
                'code' => 'NP',
                'name' => 'Nurse Practitioner (NP)',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-NP',
                'cost_driver' => 'Salaried / Hourly',
                'cost_per_visit' => 200.00,
                'source' => 'Bundle RFS',
                'default_duration_minutes' => 60,
                'description' => 'Advanced Care: Prescribing, diagnosing, higher-acuity management to prevent ED visits',
            ],

            // Personal Support & Daily Living
            [
                'code' => 'PSW',
                'name' => 'Personal Care (PSW)',
                'category' => 'Personal Support & Daily Living',
                'category_id' => $personal?->id,
                'cost_code' => 'COST-PSW',
                'cost_driver' => 'Hourly Labour',
                'cost_per_visit' => 45.00,
                'source' => 'Sched 3 (PSW)',
                'default_duration_minutes' => 60,
                'description' => 'Hygiene (bathing, grooming, toileting/incontinence care), Mobility (transfers with lifts, turning/positioning)',
            ],
            [
                'code' => 'HMK',
                'name' => 'Homemaking',
                'category' => 'Personal Support & Daily Living',
                'category_id' => $personal?->id,
                'cost_code' => 'COST-PSW',
                'cost_driver' => 'Hourly Labour',
                'cost_per_visit' => 40.00,
                'source' => 'Sched 3 (PSW)',
                'default_duration_minutes' => 60,
                'description' => 'Cleaning (light housekeeping, laundry, changing linens), Errands (banking, grocery shopping assistance)',
            ],
            [
                'code' => 'DEL-ACTS',
                'name' => 'Delegated Acts',
                'category' => 'Personal Support & Daily Living',
                'category_id' => $personal?->id,
                'cost_code' => 'COST-PSW',
                'cost_driver' => 'Hourly Labour',
                'cost_per_visit' => 50.00,
                'source' => 'Sched 3 (PSW)',
                'default_duration_minutes' => 30,
                'description' => 'Regulated Tasks: Pre-loaded injections, glucometer testing, suctioning (must be taught/delegated by Nurse)',
            ],
            [
                'code' => 'RES',
                'name' => 'Respite Care',
                'category' => 'Personal Support & Daily Living',
                'category_id' => $personal?->id,
                'cost_code' => 'COST-RFS',
                'cost_driver' => 'Hourly Labour',
                'cost_per_visit' => 45.00,
                'source' => 'Bundle RFS',
                'default_duration_minutes' => 240,
                'description' => 'Caregiver Relief: In-home supervision to allow family caregivers a break',
            ],

            // Safety, Monitoring & Technology
            [
                'code' => 'PERS',
                'name' => 'Lifeline (PERS)',
                'category' => 'Safety, Monitoring & Technology',
                'category_id' => $safety?->id,
                'cost_code' => 'COST-PERS',
                'cost_driver' => 'Monthly Subscription',
                'cost_per_visit' => 50.00,
                'source' => 'Bundle Q&A',
                'default_duration_minutes' => 0,
                'description' => 'Personal Emergency Response System: Wearable button (pendant/wrist) connecting to 24/7 emergency response, may include fall detection',
            ],
            [
                'code' => 'RPM',
                'name' => 'Remote Patient Monitoring (RPM)',
                'category' => 'Safety, Monitoring & Technology',
                'category_id' => $safety?->id,
                'cost_code' => 'COST-RPM',
                'cost_driver' => 'Device Lease + Software Fee',
                'cost_per_visit' => 150.00,
                'source' => 'Bundle Q&A',
                'default_duration_minutes' => 60,
                'description' => 'Digital Health Tracking: Equipment (tablets, BP cuffs, scales) to track vitals remotely, includes staff time to monitor alerts',
                // RPM has exactly 2 scheduled visits per care plan:
                // Visit 1: Setup (device installation & patient education)
                // Visit 2: Discharge (device retrieval)
                // Monitoring between visits is asynchronous and NOT scheduled
                'scheduling_mode' => 'fixed_visits',
                'fixed_visits_per_plan' => 2,
                'fixed_visit_labels' => ['Setup', 'Discharge'],
            ],
            [
                'code' => 'SEC',
                'name' => 'Security Checks',
                'category' => 'Safety, Monitoring & Technology',
                'category_id' => $safety?->id,
                'cost_code' => 'COST-SEC',
                'cost_driver' => 'Staff Time (Admin/PSW)',
                'cost_per_visit' => 30.00,
                'source' => 'Bundle RFS',
                'default_duration_minutes' => 15,
                'description' => 'Safety Checks: Telephone reassurance or physical safety checks for isolated patients',
            ],

            // Logistics & Access Services
            [
                'code' => 'TRANS',
                'name' => 'Medical Transportation',
                'category' => 'Logistics & Access Services',
                'category_id' => $logistics?->id,
                'cost_code' => 'COST-TRSPT',
                'cost_driver' => 'Per Trip / Per Km',
                'cost_per_visit' => 80.00,
                'source' => 'Bundle Q&A',
                'default_duration_minutes' => 60,
                'description' => 'Patient Transport: Travel to medical appointments, includes local and out-of-town specialist appointments',
            ],
            [
                'code' => 'LAB',
                'name' => 'In-Home Laboratory',
                'category' => 'Logistics & Access Services',
                'category_id' => $logistics?->id,
                'cost_code' => 'COST-LAB',
                'cost_driver' => 'Per Visit Fee',
                'cost_per_visit' => 60.00,
                'source' => 'Bundle RFS',
                'default_duration_minutes' => 30,
                'description' => 'Mobile Lab Services: Technicians dispatched to home for blood draws/specimen collection (OHIP covers test; SPO pays visit fee)',
            ],
            [
                'code' => 'PHAR',
                'name' => 'Pharmacy Support',
                'category' => 'Logistics & Access Services',
                'category_id' => $logistics?->id,
                'cost_code' => 'COST-PHAR',
                'cost_driver' => 'Per Delivery / Service Fee',
                'cost_per_visit' => 25.00,
                'source' => 'Bundle RFS',
                'default_duration_minutes' => 15,
                'description' => 'Medication Logistics: Delivery fees, blister packing, medication reconciliation support (Drug cost is ODB/OHIP)',
            ],
            [
                'code' => 'INTERP',
                'name' => 'Language Services',
                'category' => 'Logistics & Access Services',
                'category_id' => $logistics?->id,
                'cost_code' => 'COST-RFS',
                'cost_driver' => 'Per Minute / Per Hour',
                'cost_per_visit' => 100.00,
                'source' => 'Bundle RFS',
                'default_duration_minutes' => 60,
                'description' => 'Interpretation: Professional translation/interpretation for non-English/French speaking patients',
            ],
            [
                'code' => 'MEAL',
                'name' => 'Meal Delivery',
                'category' => 'Logistics & Access Services',
                'category_id' => $logistics?->id,
                'cost_code' => 'COST-MEAL',
                'cost_driver' => 'Per Meal Cost',
                'cost_per_visit' => 15.00,
                'source' => 'Bundle RFS',
                'default_duration_minutes' => 15,
                'description' => 'Nutrition Support: Coordination and payment for prepared meal delivery (e.g., Meals on Wheels)',
            ],
            [
                'code' => 'REC',
                'name' => 'Social/Recreational',
                'category' => 'Logistics & Access Services',
                'category_id' => $logistics?->id,
                'cost_code' => 'COST-REC',
                'cost_driver' => 'Program Fee / Hourly',
                'cost_per_visit' => 50.00,
                'source' => 'Bundle RFS',
                'default_duration_minutes' => 120,
                'description' => 'Activation: Adult day programs, friendly visiting, social inclusion programming',
            ],
            [
                'code' => 'BEH',
                'name' => 'Behavioral Supports',
                'category' => 'Logistics & Access Services',
                'category_id' => $logistics?->id,
                'cost_code' => 'COST-BEH',
                'cost_driver' => 'Hourly (Specialized)',
                'cost_per_visit' => 100.00,
                'source' => 'Bundle RFS',
                'default_duration_minutes' => 60,
                'description' => 'Dementia Care: Specialized support strategies for responsive behaviors (BSO)',
            ],
        ];

        foreach ($serviceTypes as $st) {
            // Use updateOrCreate to ensure new fields like scheduling_mode get applied
            ServiceType::updateOrCreate(
                ['code' => $st['code']],
                array_merge($st, ['active' => true])
            );
        }
    }

    protected function seedCareBundles(): void
    {
        $bundles = [
            [
                'code' => 'STD-MED',
                'name' => 'Standard Medical',
                'description' => 'Standard care bundle for patients with moderate medical needs. Includes nursing visits and personal support.',
                'price' => 2500,
            ],
            [
                'code' => 'COMPLEX',
                'name' => 'Complex Care',
                'description' => 'Enhanced care bundle for patients with complex medical conditions requiring intensive nursing and rehabilitation.',
                'price' => 4500,
            ],
            [
                'code' => 'DEM-SUP',
                'name' => 'Dementia Support',
                'description' => 'Specialized bundle for patients with cognitive impairment or dementia requiring structured support.',
                'price' => 3800,
            ],
            [
                'code' => 'PALLIATIVE',
                'name' => 'Palliative Care',
                'description' => 'End-of-life care bundle focused on comfort, symptom management, and family support.',
                'price' => 5000,
            ],
            [
                'code' => 'REHAB',
                'name' => 'Rehabilitation',
                'description' => 'Post-acute rehabilitation bundle with intensive therapy services for recovery.',
                'price' => 3500,
            ],
        ];

        foreach ($bundles as $bundle) {
            CareBundle::firstOrCreate(
                ['code' => $bundle['code']],
                array_merge($bundle, ['active' => true])
            );
        }
    }

    protected function linkBundlesToServices(): void
    {
        // STD-MED bundle - Standard Medical
        $stdMed = CareBundle::where('code', 'STD-MED')->first();
        if ($stdMed) {
            $stdMed->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'NUR')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PSW')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Either'],
                ServiceType::where('code', 'PT')->first()?->id => ['default_frequency_per_week' => 1, 'assignment_type' => 'External'],
                ServiceType::where('code', 'PERS')->first()?->id => ['default_frequency_per_week' => 1, 'assignment_type' => 'External'],
            ]);
        }

        // COMPLEX bundle - Complex Care
        $complex = CareBundle::where('code', 'COMPLEX')->first();
        if ($complex) {
            $complex->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'NUR')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PSW')->first()?->id => ['default_frequency_per_week' => 14, 'assignment_type' => 'Either'],
                ServiceType::where('code', 'PT')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'External'],
                ServiceType::where('code', 'OT')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'External'],
                ServiceType::where('code', 'SW')->first()?->id => ['default_frequency_per_week' => 1, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'RPM')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'External'],
                ServiceType::where('code', 'LAB')->first()?->id => ['default_frequency_per_week' => 1, 'assignment_type' => 'External'],
            ]);
        }

        // DEM-SUP bundle - Dementia Support
        $demSup = CareBundle::where('code', 'DEM-SUP')->first();
        if ($demSup) {
            $demSup->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'NUR')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PSW')->first()?->id => ['default_frequency_per_week' => 14, 'assignment_type' => 'Either'],
                ServiceType::where('code', 'BEH')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'External'],
                ServiceType::where('code', 'RES')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'External'],
                ServiceType::where('code', 'PERS')->first()?->id => ['default_frequency_per_week' => 1, 'assignment_type' => 'External'],
                ServiceType::where('code', 'SEC')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Internal'],
            ]);
        }

        // PALLIATIVE bundle - Palliative Care
        $palliative = CareBundle::where('code', 'PALLIATIVE')->first();
        if ($palliative) {
            $palliative->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'NUR')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'NP')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PSW')->first()?->id => ['default_frequency_per_week' => 14, 'assignment_type' => 'Either'],
                ServiceType::where('code', 'SW')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PHAR')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'External'],
                ServiceType::where('code', 'RES')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'External'],
            ]);
        }

        // REHAB bundle - Rehabilitation
        $rehab = CareBundle::where('code', 'REHAB')->first();
        if ($rehab) {
            $rehab->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'NUR')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PT')->first()?->id => ['default_frequency_per_week' => 5, 'assignment_type' => 'External'],
                ServiceType::where('code', 'OT')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'External'],
                ServiceType::where('code', 'SLP')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'External'],
                ServiceType::where('code', 'PSW')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Either'],
                ServiceType::where('code', 'TRANS')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'External'],
            ]);
        }
    }
}
