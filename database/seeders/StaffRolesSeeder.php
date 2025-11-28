<?php

namespace Database\Seeders;

use App\Models\StaffRole;
use Illuminate\Database\Seeder;

/**
 * StaffRolesSeeder - Seeds staff roles metadata for workforce management.
 *
 * Roles align with Ontario Health atHome HHR complement requirements:
 * - Nursing: RN, RPN, NP
 * - Allied Health: OT, PT, SLP, SW, RD, RT
 * - Personal Support: PSW
 * - Administrative: COORD
 * - Community Support: Community Worker
 *
 * Per RFP Q&A: These roles are used for FTE ratio calculation and HHR complement reporting.
 */
class StaffRolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            // Nursing roles
            [
                'code' => StaffRole::CODE_RN,
                'name' => 'Registered Nurse',
                'description' => 'Licensed RN providing direct patient care',
                'category' => StaffRole::CATEGORY_NURSING,
                'service_type_codes' => json_encode(['NUR', 'WOUND', 'IV']),
                'is_regulated' => true,
                'regulatory_body' => 'College of Nurses of Ontario',
                'counts_for_fte' => true,
                'badge_color' => 'blue',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => StaffRole::CODE_RPN,
                'name' => 'Registered Practical Nurse',
                'description' => 'Licensed RPN providing practical nursing care',
                'category' => StaffRole::CATEGORY_NURSING,
                'service_type_codes' => json_encode(['NUR']),
                'is_regulated' => true,
                'regulatory_body' => 'College of Nurses of Ontario',
                'counts_for_fte' => true,
                'badge_color' => 'blue',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => StaffRole::CODE_NP,
                'name' => 'Nurse Practitioner',
                'description' => 'Advanced practice registered nurse',
                'category' => StaffRole::CATEGORY_NURSING,
                'service_type_codes' => json_encode(['NUR', 'NP', 'PALL']),
                'is_regulated' => true,
                'regulatory_body' => 'College of Nurses of Ontario',
                'counts_for_fte' => true,
                'badge_color' => 'indigo',
                'sort_order' => 3,
                'is_active' => true,
            ],

            // Personal Support
            [
                'code' => StaffRole::CODE_PSW,
                'name' => 'Personal Support Worker',
                'description' => 'Provides personal care and support services',
                'category' => StaffRole::CATEGORY_PERSONAL_SUPPORT,
                'service_type_codes' => json_encode(['PSW', 'PSW-ADL']),
                'is_regulated' => false,
                'regulatory_body' => null,
                'counts_for_fte' => true,
                'badge_color' => 'green',
                'sort_order' => 10,
                'is_active' => true,
            ],

            // Allied Health
            [
                'code' => StaffRole::CODE_OT,
                'name' => 'Occupational Therapist',
                'description' => 'Licensed OT providing occupational therapy',
                'category' => StaffRole::CATEGORY_ALLIED_HEALTH,
                'service_type_codes' => json_encode(['OT']),
                'is_regulated' => true,
                'regulatory_body' => 'College of Occupational Therapists of Ontario',
                'counts_for_fte' => true,
                'badge_color' => 'purple',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'code' => StaffRole::CODE_PT,
                'name' => 'Physiotherapist',
                'description' => 'Licensed PT providing physiotherapy',
                'category' => StaffRole::CATEGORY_ALLIED_HEALTH,
                'service_type_codes' => json_encode(['PT']),
                'is_regulated' => true,
                'regulatory_body' => 'College of Physiotherapists of Ontario',
                'counts_for_fte' => true,
                'badge_color' => 'purple',
                'sort_order' => 21,
                'is_active' => true,
            ],
            [
                'code' => StaffRole::CODE_SLP,
                'name' => 'Speech-Language Pathologist',
                'description' => 'Licensed SLP providing speech therapy',
                'category' => StaffRole::CATEGORY_ALLIED_HEALTH,
                'service_type_codes' => json_encode(['SLP']),
                'is_regulated' => true,
                'regulatory_body' => 'College of Audiologists and Speech-Language Pathologists of Ontario',
                'counts_for_fte' => true,
                'badge_color' => 'purple',
                'sort_order' => 22,
                'is_active' => true,
            ],
            [
                'code' => StaffRole::CODE_SW,
                'name' => 'Social Worker',
                'description' => 'Licensed social worker providing support',
                'category' => StaffRole::CATEGORY_ALLIED_HEALTH,
                'service_type_codes' => json_encode(['SW', 'SW-CASE']),
                'is_regulated' => true,
                'regulatory_body' => 'Ontario College of Social Workers and Social Service Workers',
                'counts_for_fte' => true,
                'badge_color' => 'purple',
                'sort_order' => 23,
                'is_active' => true,
            ],
            [
                'code' => StaffRole::CODE_RD,
                'name' => 'Registered Dietitian',
                'description' => 'Licensed dietitian providing nutritional care',
                'category' => StaffRole::CATEGORY_ALLIED_HEALTH,
                'service_type_codes' => json_encode(['RD', 'NUTR']),
                'is_regulated' => true,
                'regulatory_body' => 'College of Dietitians of Ontario',
                'counts_for_fte' => true,
                'badge_color' => 'purple',
                'sort_order' => 24,
                'is_active' => true,
            ],
            [
                'code' => StaffRole::CODE_RT,
                'name' => 'Respiratory Therapist',
                'description' => 'Licensed RT providing respiratory care',
                'category' => StaffRole::CATEGORY_ALLIED_HEALTH,
                'service_type_codes' => json_encode(['RT', 'RESP']),
                'is_regulated' => true,
                'regulatory_body' => 'College of Respiratory Therapists of Ontario',
                'counts_for_fte' => true,
                'badge_color' => 'purple',
                'sort_order' => 25,
                'is_active' => true,
            ],

            // Administrative
            [
                'code' => StaffRole::CODE_COORD,
                'name' => 'Care Coordinator',
                'description' => 'Care coordination and case management',
                'category' => StaffRole::CATEGORY_ADMINISTRATIVE,
                'service_type_codes' => json_encode([]),
                'is_regulated' => false,
                'regulatory_body' => null,
                'counts_for_fte' => false, // Admin staff typically don't count for FTE compliance
                'badge_color' => 'gray',
                'sort_order' => 30,
                'is_active' => true,
            ],

            // Community Support
            [
                'code' => 'CW',
                'name' => 'Community Worker',
                'description' => 'Community support and outreach',
                'category' => StaffRole::CATEGORY_COMMUNITY_SUPPORT,
                'service_type_codes' => json_encode(['COMM', 'TRANSP']),
                'is_regulated' => false,
                'regulatory_body' => null,
                'counts_for_fte' => true,
                'badge_color' => 'teal',
                'sort_order' => 40,
                'is_active' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            StaffRole::updateOrCreate(
                ['code' => $roleData['code']],
                $roleData
            );
        }

        $this->command->info('Staff roles seeded: ' . count($roles) . ' roles');
    }
}
