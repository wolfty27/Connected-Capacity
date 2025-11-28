<?php

namespace Database\Seeders;

use App\Models\EmploymentType;
use Illuminate\Database\Seeder;

/**
 * EmploymentTypesSeeder - Seeds employment type metadata for FTE compliance.
 *
 * Per RFP Q&A:
 * - FTE ratio = [Full-time direct staff ÷ Total direct staff] × 100%
 * - Full-time aligns with Ontario's Employment Standards Act (typically 40h/week)
 * - SSPO staff do NOT count in FTE ratio (either numerator or denominator)
 *
 * Employment Types:
 * - Full-Time (FT): is_direct_staff=true, is_full_time=true - counts in numerator AND denominator
 * - Part-Time (PT): is_direct_staff=true, is_full_time=false - counts in denominator only
 * - Casual: is_direct_staff=true, is_full_time=false - counts in denominator only
 * - SSPO: is_direct_staff=false, is_full_time=false - excluded from FTE ratio entirely
 */
class EmploymentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => EmploymentType::CODE_FULL_TIME,
                'name' => 'Full-Time',
                'description' => 'Full-time employment per Ontario ESA (40h/week)',
                'standard_hours_per_week' => 40.00,
                'min_hours_per_week' => 35.00,
                'max_hours_per_week' => 44.00,
                'is_direct_staff' => true,  // Counts in FTE denominator
                'is_full_time' => true,     // Counts in FTE numerator
                'counts_for_capacity' => true,
                'benefits_eligible' => true,
                'fte_equivalent' => 1.00,
                'badge_color' => EmploymentType::BADGE_GREEN,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => EmploymentType::CODE_PART_TIME,
                'name' => 'Part-Time',
                'description' => 'Part-time employment (less than 40h/week)',
                'standard_hours_per_week' => 24.00,
                'min_hours_per_week' => 12.00,
                'max_hours_per_week' => 34.00,
                'is_direct_staff' => true,  // Counts in FTE denominator
                'is_full_time' => false,    // Does NOT count in FTE numerator
                'counts_for_capacity' => true,
                'benefits_eligible' => true,
                'fte_equivalent' => 0.60,
                'badge_color' => EmploymentType::BADGE_BLUE,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => EmploymentType::CODE_CASUAL,
                'name' => 'Casual',
                'description' => 'Casual/on-call employment (variable hours)',
                'standard_hours_per_week' => 16.00,
                'min_hours_per_week' => 0.00,
                'max_hours_per_week' => 24.00,
                'is_direct_staff' => true,  // Counts in FTE denominator
                'is_full_time' => false,    // Does NOT count in FTE numerator
                'counts_for_capacity' => true,
                'benefits_eligible' => false,
                'fte_equivalent' => 0.40,
                'badge_color' => EmploymentType::BADGE_ORANGE,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'code' => EmploymentType::CODE_SSPO,
                'name' => 'SSPO Contract',
                'description' => 'Subcontracted SSPO staff - excluded from FTE ratio',
                'standard_hours_per_week' => null,
                'min_hours_per_week' => null,
                'max_hours_per_week' => null,
                'is_direct_staff' => false, // Does NOT count in FTE ratio at all
                'is_full_time' => false,    // Does NOT count in FTE numerator
                'counts_for_capacity' => true,  // Still counts for capacity/utilization
                'benefits_eligible' => false,
                'fte_equivalent' => null,
                'badge_color' => EmploymentType::BADGE_PURPLE,
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($types as $typeData) {
            EmploymentType::updateOrCreate(
                ['code' => $typeData['code']],
                $typeData
            );
        }

        $this->command->info('Employment types seeded: ' . count($types) . ' types');
    }
}
