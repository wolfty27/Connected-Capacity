<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use App\Models\CareBundle;

class MetadataSeeder extends Seeder
{
    public function run()
    {
        // 1. Categories
        $clinical = ServiceCategory::firstOrCreate(['code' => 'CLINICAL'], ['name' => 'Clinical Core']);
        $support = ServiceCategory::firstOrCreate(['code' => 'SUPPORT'], ['name' => 'Personal Support & SDOH']);
        $specialized = ServiceCategory::firstOrCreate(['code' => 'SPECIALIZED'], ['name' => 'Specialized & Social']);
        $digital = ServiceCategory::firstOrCreate(['code' => 'DIGITAL'], ['name' => 'Digital & Innovation']);

        // 2. Link Service Types
        $services = [
            'NURSING' => $clinical,
            'REHAB' => $clinical,
            'PSW' => $support,
            'DEMENTIA' => $specialized,
            'MH' => $specialized,
            'YOUTH' => $specialized,
            'DIGITAL' => $digital,
            'RPM' => $digital,
        ];

        foreach ($services as $code => $cat) {
            ServiceType::where('code', $code)->update(['category_id' => $cat->id]);
        }

        // 3. Bundle Template Items (Populate Pivot with Defaults)
        // Standard (STD-MED)
        $std = CareBundle::where('code', 'STD-MED')->first();
        if ($std) {
            $std->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'NURSING')->first()->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'PSW')->first()->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Either'],
            ]);
        }

        // Complex (COMPLEX)
        $complex = CareBundle::where('code', 'COMPLEX')->first();
        if ($complex) {
            $complex->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'NURSING')->first()->id => ['default_frequency_per_week' => 7, 'assignment_type' => 'Internal'],
                ServiceType::where('code', 'REHAB')->first()->id => ['default_frequency_per_week' => 2, 'assignment_type' => 'External'],
                ServiceType::where('code', 'RPM')->first()->id => ['default_frequency_per_week' => 0, 'assignment_type' => 'External'],
            ]);
        }

        // Dementia (DEM-SUP)
        $dem = CareBundle::where('code', 'DEM-SUP')->first();
        if ($dem) {
            $dem->serviceTypes()->syncWithoutDetaching([
                ServiceType::where('code', 'DEMENTIA')->first()->id => ['default_frequency_per_week' => 3, 'assignment_type' => 'External'],
                ServiceType::where('code', 'NURSING')->first()->id => ['default_frequency_per_week' => 1, 'assignment_type' => 'Internal'],
            ]);
        }
    }
}