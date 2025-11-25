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
            ['code' => 'CLINICAL', 'name' => 'Clinical Core'],
            ['code' => 'SUPPORT', 'name' => 'Personal Support & SDOH'],
            ['code' => 'SPECIALIZED', 'name' => 'Specialized & Social'],
            ['code' => 'DIGITAL', 'name' => 'Digital & Innovation'],
        ];

        foreach ($categories as $cat) {
            ServiceCategory::firstOrCreate(['code' => $cat['code']], $cat);
        }
    }

    protected function seedServiceTypes(): void
    {
        $clinical = ServiceCategory::where('code', 'CLINICAL')->first();
        $support = ServiceCategory::where('code', 'SUPPORT')->first();
        $specialized = ServiceCategory::where('code', 'SPECIALIZED')->first();
        $digital = ServiceCategory::where('code', 'DIGITAL')->first();

        $serviceTypes = [
            // Clinical Core
            ['code' => 'RN/RPN', 'name' => 'Registered Nurse / RPN', 'category' => 'Clinical', 'category_id' => $clinical?->id, 'default_duration_minutes' => 60, 'description' => 'Skilled nursing visits for assessments, wound care, medication management'],
            ['code' => 'PT', 'name' => 'Physiotherapy', 'category' => 'Clinical', 'category_id' => $clinical?->id, 'default_duration_minutes' => 45, 'description' => 'Physical therapy for mobility, strength, balance'],
            ['code' => 'OT', 'name' => 'Occupational Therapy', 'category' => 'Clinical', 'category_id' => $clinical?->id, 'default_duration_minutes' => 45, 'description' => 'Activities of daily living assessment and equipment recommendations'],
            ['code' => 'RT', 'name' => 'Respiratory Therapy', 'category' => 'Clinical', 'category_id' => $clinical?->id, 'default_duration_minutes' => 45, 'description' => 'Respiratory assessments, CPAP/BiPAP, oxygen therapy'],
            ['code' => 'SW', 'name' => 'Social Work', 'category' => 'Clinical', 'category_id' => $clinical?->id, 'default_duration_minutes' => 60, 'description' => 'Care coordination, family support, resource navigation'],
            ['code' => 'RD', 'name' => 'Registered Dietitian', 'category' => 'Clinical', 'category_id' => $clinical?->id, 'default_duration_minutes' => 45, 'description' => 'Nutrition assessment and meal planning'],
            ['code' => 'SLP', 'name' => 'Speech Language Pathology', 'category' => 'Clinical', 'category_id' => $clinical?->id, 'default_duration_minutes' => 45, 'description' => 'Swallowing assessments, communication therapy'],
            ['code' => 'NP', 'name' => 'Nurse Practitioner', 'category' => 'Clinical', 'category_id' => $clinical?->id, 'default_duration_minutes' => 60, 'description' => 'Advanced practice nursing, prescribing, complex care management'],

            // Personal Support & SDOH
            ['code' => 'PSW', 'name' => 'Personal Support Worker', 'category' => 'Support', 'category_id' => $support?->id, 'default_duration_minutes' => 60, 'description' => 'Personal care, bathing, dressing, meal prep'],
            ['code' => 'HMK', 'name' => 'Homemaking', 'category' => 'Support', 'category_id' => $support?->id, 'default_duration_minutes' => 60, 'description' => 'Light housekeeping, laundry, meal preparation'],
            ['code' => 'DEL', 'name' => 'Meal Delivery', 'category' => 'Support', 'category_id' => $support?->id, 'default_duration_minutes' => 15, 'description' => 'Hot or frozen meal delivery service'],
            ['code' => 'RES', 'name' => 'Respite Care', 'category' => 'Support', 'category_id' => $support?->id, 'default_duration_minutes' => 240, 'description' => 'Caregiver relief, companionship, supervision'],
            ['code' => 'TRANS', 'name' => 'Transportation', 'category' => 'Support', 'category_id' => $support?->id, 'default_duration_minutes' => 60, 'description' => 'Medical appointment transportation'],

            // Specialized & Social
            ['code' => 'PERS', 'name' => 'Personal Emergency Response', 'category' => 'Specialized', 'category_id' => $specialized?->id, 'default_duration_minutes' => 0, 'description' => '24/7 emergency response system'],
            ['code' => 'SEC', 'name' => 'Security Check-In', 'category' => 'Specialized', 'category_id' => $specialized?->id, 'default_duration_minutes' => 15, 'description' => 'Daily wellness check-in calls'],
            ['code' => 'DEM', 'name' => 'Dementia Support', 'category' => 'Specialized', 'category_id' => $specialized?->id, 'default_duration_minutes' => 60, 'description' => 'Specialized dementia care and cognitive support'],
            ['code' => 'PALL', 'name' => 'Palliative Support', 'category' => 'Specialized', 'category_id' => $specialized?->id, 'default_duration_minutes' => 60, 'description' => 'End-of-life care coordination and comfort measures'],
            ['code' => 'MH', 'name' => 'Mental Health', 'category' => 'Specialized', 'category_id' => $specialized?->id, 'default_duration_minutes' => 60, 'description' => 'Mental health counseling and support'],

            // Digital & Innovation
            ['code' => 'RPM', 'name' => 'Remote Patient Monitoring', 'category' => 'Digital', 'category_id' => $digital?->id, 'default_duration_minutes' => 30, 'description' => 'Vital signs monitoring, telehealth integration'],
            ['code' => 'TELE', 'name' => 'Telehealth Visit', 'category' => 'Digital', 'category_id' => $digital?->id, 'default_duration_minutes' => 30, 'description' => 'Virtual video or phone consultations'],
            ['code' => 'APP', 'name' => 'Mobile App Support', 'category' => 'Digital', 'category_id' => $digital?->id, 'default_duration_minutes' => 15, 'description' => 'Patient engagement app onboarding and support'],
        ];

        foreach ($serviceTypes as $st) {
            ServiceType::firstOrCreate(
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
        // STD-MED bundle
        $stdMed = CareBundle::where('code', 'STD-MED')->first();
        if ($stdMed) {
            $stdMed->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'RN/RPN')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PSW')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Either'],
                ServiceType::where('code', 'PT')->first()?->id => ['default_frequency_per_week' => 1, 'assignment_type' => 'External'],
            ]);
        }

        // COMPLEX bundle
        $complex = CareBundle::where('code', 'COMPLEX')->first();
        if ($complex) {
            $complex->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'RN/RPN')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PSW')->first()?->id => ['default_frequency_per_week' => 14, 'assignment_type' => 'Either'],
                ServiceType::where('code', 'PT')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'External'],
                ServiceType::where('code', 'OT')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'External'],
                ServiceType::where('code', 'SW')->first()?->id => ['default_frequency_per_week' => 1, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'RPM')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'External'],
            ]);
        }

        // DEM-SUP bundle
        $demSup = CareBundle::where('code', 'DEM-SUP')->first();
        if ($demSup) {
            $demSup->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'RN/RPN')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PSW')->first()?->id => ['default_frequency_per_week' => 14, 'assignment_type' => 'Either'],
                ServiceType::where('code', 'DEM')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'External'],
                ServiceType::where('code', 'RES')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'External'],
                ServiceType::where('code', 'PERS')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'External'],
            ]);
        }

        // PALLIATIVE bundle
        $palliative = CareBundle::where('code', 'PALLIATIVE')->first();
        if ($palliative) {
            $palliative->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'RN/RPN')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'NP')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PSW')->first()?->id => ['default_frequency_per_week' => 14, 'assignment_type' => 'Either'],
                ServiceType::where('code', 'SW')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PALL')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'External'],
            ]);
        }

        // REHAB bundle
        $rehab = CareBundle::where('code', 'REHAB')->first();
        if ($rehab) {
            $rehab->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'RN/RPN')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PT')->first()?->id => ['default_frequency_per_week' => 5, 'assignment_type' => 'External'],
                ServiceType::where('code', 'OT')->first()?->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'External'],
                ServiceType::where('code', 'SLP')->first()?->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'External'],
                ServiceType::where('code', 'PSW')->first()?->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Either'],
            ]);
        }
    }
}
