<?php

namespace Database\Seeders;

use App\Models\ServiceRoleMapping;
use App\Models\ServiceType;
use App\Models\StaffRole;
use Illuminate\Database\Seeder;

/**
 * ServiceRoleMappingsSeeder
 *
 * Seeds the metadata-driven mapping of which staff roles can deliver which services.
 * This is the single source of truth for role→service capabilities in CC2.1.
 *
 * Mappings align with Ontario Health atHome RFP requirements and CIHI scope definitions.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class ServiceRoleMappingsSeeder extends Seeder
{
    /**
     * Service code to role codes mapping.
     * Format: service_code => [['role' => role_code, 'primary' => bool, 'delegation' => bool], ...]
     */
    protected array $mappings = [
        // Clinical Services
        'NUR' => [
            ['role' => 'RN', 'primary' => true, 'delegation' => false],
            ['role' => 'RPN', 'primary' => false, 'delegation' => false],
            ['role' => 'NP', 'primary' => false, 'delegation' => false],
        ],
        'PT' => [
            ['role' => 'PT', 'primary' => true, 'delegation' => false],
        ],
        'OT' => [
            ['role' => 'OT', 'primary' => true, 'delegation' => false],
        ],
        'RT' => [
            ['role' => 'RT', 'primary' => true, 'delegation' => false],
        ],
        'SW' => [
            ['role' => 'SW', 'primary' => true, 'delegation' => false],
        ],
        'RD' => [
            ['role' => 'RD', 'primary' => true, 'delegation' => false],
        ],
        'SLP' => [
            ['role' => 'SLP', 'primary' => true, 'delegation' => false],
        ],
        'NP' => [
            ['role' => 'NP', 'primary' => true, 'delegation' => false],
        ],

        // Personal Support & Daily Living
        'PSW' => [
            ['role' => 'PSW', 'primary' => true, 'delegation' => false],
        ],
        'HMK' => [
            ['role' => 'PSW', 'primary' => true, 'delegation' => false],
        ],
        'DEL-ACTS' => [
            ['role' => 'PSW', 'primary' => true, 'delegation' => true], // Requires RN delegation
        ],
        'RES' => [
            ['role' => 'PSW', 'primary' => true, 'delegation' => false],
        ],

        // Safety, Monitoring & Technology
        'PERS' => [
            ['role' => 'COORD', 'primary' => true, 'delegation' => false],
        ],
        'RPM' => [
            ['role' => 'RN', 'primary' => true, 'delegation' => false],
            ['role' => 'RPN', 'primary' => false, 'delegation' => false],
        ],
        'SEC' => [
            ['role' => 'PSW', 'primary' => true, 'delegation' => false],
            ['role' => 'COORD', 'primary' => false, 'delegation' => false],
        ],

        // Logistics & Access Services
        'TRANS' => [
            ['role' => 'COORD', 'primary' => true, 'delegation' => false],
        ],
        'LAB' => [
            ['role' => 'RN', 'primary' => true, 'delegation' => false],
            ['role' => 'RPN', 'primary' => false, 'delegation' => false],
        ],
        'PHAR' => [
            ['role' => 'COORD', 'primary' => true, 'delegation' => false],
            ['role' => 'PSW', 'primary' => false, 'delegation' => false],
        ],
        'INTERP' => [
            ['role' => 'COORD', 'primary' => true, 'delegation' => false],
        ],
        'MEAL' => [
            ['role' => 'COORD', 'primary' => true, 'delegation' => false],
            ['role' => 'PSW', 'primary' => false, 'delegation' => false],
        ],
        'REC' => [
            ['role' => 'PSW', 'primary' => true, 'delegation' => false],
            ['role' => 'SW', 'primary' => false, 'delegation' => false],
        ],
        'BEH' => [
            ['role' => 'RN', 'primary' => true, 'delegation' => false],
            ['role' => 'RPN', 'primary' => false, 'delegation' => false],
            ['role' => 'PSW', 'primary' => false, 'delegation' => false], // Behavioural PSW
            ['role' => 'SW', 'primary' => false, 'delegation' => false],
        ],
    ];

    public function run(): void
    {
        // Cache role and service IDs
        $roleIds = StaffRole::pluck('id', 'code')->toArray();
        $serviceIds = ServiceType::pluck('id', 'code')->toArray();

        $created = 0;
        $sortOrder = 0;

        foreach ($this->mappings as $serviceCode => $roleMappings) {
            $serviceId = $serviceIds[$serviceCode] ?? null;
            if (!$serviceId) {
                $this->command->warn("Service type not found: {$serviceCode}");
                continue;
            }

            foreach ($roleMappings as $mapping) {
                $roleId = $roleIds[$mapping['role']] ?? null;
                if (!$roleId) {
                    $this->command->warn("Staff role not found: {$mapping['role']}");
                    continue;
                }

                ServiceRoleMapping::updateOrCreate(
                    [
                        'staff_role_id' => $roleId,
                        'service_type_id' => $serviceId,
                    ],
                    [
                        'is_primary' => $mapping['primary'] ?? false,
                        'requires_delegation' => $mapping['delegation'] ?? false,
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                    ]
                );

                $created++;
            }
        }

        $this->command->info("Seeded {$created} service-role mappings.");
    }
}
