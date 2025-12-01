<?php

namespace Database\Seeders;

use App\Models\CareBundleTemplate;
use App\Models\CareBundleTemplateService;
use App\Models\RUGClassification;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

/**
 * RUGBundleTemplatesSeeder
 *
 * Seeds the 23 RUG-III/HC-specific care bundle templates as defined in
 * docs/CC21_RUG_Bundle_Templates.md
 *
 * Templates are organized by RUG category:
 * - Special Rehabilitation (3): RB0, RA2, RA1
 * - Extensive Services (3): SE3, SE2, SE1
 * - Special Care (2): SSB, SSA
 * - Clinically Complex (4): CC0, CB0, CA2, CA1
 * - Impaired Cognition (3): IB0, IA2, IA1
 * - Behaviour Problems (3): BB0, BA2, BA1
 * - Reduced Physical Function (5): PD0, PC0, PB0, PA2, PA1
 */
class RUGBundleTemplatesSeeder extends Seeder
{
    protected array $serviceCache = [];

    public function run(): void
    {
        $this->loadServiceCache();

        // Special Rehabilitation
        $this->seedRB0();
        $this->seedRA2();
        $this->seedRA1();

        // Extensive Services
        $this->seedSE3();
        $this->seedSE2();
        $this->seedSE1();

        // Special Care
        $this->seedSSB();
        $this->seedSSA();

        // Clinically Complex
        $this->seedCC0();
        $this->seedCB0();
        $this->seedCA2();
        $this->seedCA1();

        // Impaired Cognition
        $this->seedIB0();
        $this->seedIA2();
        $this->seedIA1();

        // Behaviour Problems
        $this->seedBB0();
        $this->seedBA2();
        $this->seedBA1();

        // Reduced Physical Function
        $this->seedPD0();
        $this->seedPC0();
        $this->seedPB0();
        $this->seedPA2();
        $this->seedPA1();

        // Diagnostic: Count template services created
        $templateCount = CareBundleTemplate::count();
        $serviceCount = CareBundleTemplateService::count();
        $this->command->info("Seeded {$templateCount} RUG-III/HC bundle templates with {$serviceCount} template services.");

        if ($serviceCount === 0) {
            $this->command->error('⚠️  No template services created! Service types may be missing.');
        }
    }

    protected function loadServiceCache(): void
    {
        $this->serviceCache = ServiceType::pluck('id', 'code')->toArray();

        // Diagnostic: verify service types exist
        $count = count($this->serviceCache);
        if ($count === 0) {
            $this->command->error('⚠️  No ServiceTypes found! CoreDataSeeder may not have run.');
        } else {
            $this->command->info("Loaded {$count} ServiceTypes for template service creation");
        }
    }

    protected function getServiceId(string $code): ?int
    {
        return $this->serviceCache[$code] ?? null;
    }

    protected function createTemplate(array $data): CareBundleTemplate
    {
        return CareBundleTemplate::updateOrCreate(
            ['code' => $data['code']],
            $data
        );
    }

    protected function addServices(CareBundleTemplate $template, array $services): void
    {
        foreach ($services as $service) {
            $serviceId = $this->getServiceId($service['code']);
            if (!$serviceId) {
                $this->command->warn("Service type not found: {$service['code']}");
                continue;
            }

            CareBundleTemplateService::updateOrCreate(
                [
                    'care_bundle_template_id' => $template->id,
                    'service_type_id' => $serviceId,
                ],
                [
                    'default_frequency_per_week' => $service['freq'] ?? 1,
                    'default_duration_minutes' => $service['duration'] ?? 60,
                    'default_duration_weeks' => $service['weeks'] ?? 12,
                    'is_required' => $service['required'] ?? false,
                    'is_conditional' => $service['conditional'] ?? false,
                    'condition_flags' => $service['condition_flags'] ?? null,
                    'assignment_type' => $service['assignment'] ?? 'Either',
                    'role_required' => $service['role'] ?? null,
                ]
            );
        }
    }

    // =========================================================================
    // SPECIAL REHABILITATION (RB0, RA2, RA1)
    // =========================================================================

    protected function seedRB0(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_RB0_STANDARD',
            'name' => 'Special Rehabilitation - High ADL',
            'description' => 'Intensive rehabilitation bundle for patients with high physical dependency (ADL 11-18) and therapy minutes ≥120/week.',
            'rug_group' => 'RB0',
            'rug_category' => RUGClassification::CATEGORY_SPECIAL_REHABILITATION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 11,
            'max_adl_sum' => 18,
            'required_flags' => ['rehab'],
            'weekly_cap_cents' => 500000,
            'priority_weight' => 90,
            'clinical_notes' => 'Goal-focused rehab to avoid LTC admission. Consider 3x/day PSW if ADL ≥15.',
        ]);

        // Tier 2: 14-18 h/wk PSW for RB0 (high ADL rehab)
        $this->addServices($template, [
            ['code' => 'PT', 'freq' => 3, 'duration' => 60, 'required' => true],
            ['code' => 'OT', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'SLP', 'freq' => 1, 'duration' => 45, 'conditional' => true, 'condition_flags' => ['swallowing_issue']],
            ['code' => 'NUR', 'freq' => 4, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 16, 'duration' => 60, 'required' => true], // Tier 2: 16 h/wk (~2.3h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking for IADL support
            ['code' => 'RPM', 'freq' => 7, 'duration' => 10, 'required' => true],
            ['code' => 'MEAL', 'freq' => 5, 'duration' => 15],
            ['code' => 'TRANS', 'freq' => 1, 'duration' => 60],
        ]);
    }

    protected function seedRA2(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_RA2_STANDARD',
            'name' => 'Special Rehabilitation - Lower ADL, Higher IADL',
            'description' => 'Rehabilitation bundle for mobile patients with significant IADL limitations.',
            'rug_group' => 'RA2',
            'rug_category' => RUGClassification::CATEGORY_SPECIAL_REHABILITATION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 10,
            'min_iadl_sum' => 2,
            'required_flags' => ['rehab'],
            'weekly_cap_cents' => 400000,
            'priority_weight' => 85,
        ]);

        // Tier 2: 14-18 h/wk PSW for RA2
        $this->addServices($template, [
            ['code' => 'PT', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'OT', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'NUR', 'freq' => 2, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 15, 'duration' => 60, 'required' => true], // Tier 2: 15 h/wk (~2h/day)
            ['code' => 'HMK', 'freq' => 3, 'duration' => 60], // Homemaking for high IADL needs
            ['code' => 'MEAL', 'freq' => 5, 'duration' => 15],
            ['code' => 'PHAR', 'freq' => 3, 'duration' => 15],
            ['code' => 'TRANS', 'freq' => 1, 'duration' => 60],
        ]);
    }

    protected function seedRA1(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_RA1_STANDARD',
            'name' => 'Special Rehabilitation - Lower ADL, Lower IADL',
            'description' => 'Rehab-focused bundle for relatively independent patients who mainly need therapy.',
            'rug_group' => 'RA1',
            'rug_category' => RUGClassification::CATEGORY_SPECIAL_REHABILITATION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 10,
            'max_iadl_sum' => 1,
            'required_flags' => ['rehab'],
            'weekly_cap_cents' => 300000,
            'priority_weight' => 80,
        ]);

        // Tier 1: 7-10 h/wk PSW for RA1
        $this->addServices($template, [
            ['code' => 'PT', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'OT', 'freq' => 1, 'duration' => 60],
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60], // Tier 1: 10 h/wk (~1.5h/day)
            ['code' => 'MEAL', 'freq' => 3, 'duration' => 15],
        ]);
    }

    // =========================================================================
    // EXTENSIVE SERVICES (SE3, SE2, SE1)
    // =========================================================================

    protected function seedSE3(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_SE3_MAX_INTENSITY',
            'name' => 'Extensive Services - Maximum Intensity',
            'description' => 'Near-ICU level support for patients with IV/ventilator/trach needs and highest complexity.',
            'rug_group' => 'SE3',
            'rug_category' => RUGClassification::CATEGORY_EXTENSIVE_SERVICES,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 7,
            'max_adl_sum' => 18,
            'required_flags' => ['extensive_services'],
            'weekly_cap_cents' => 500000,
            'priority_weight' => 100,
            'clinical_notes' => 'Requires shift-based nursing coverage. Tight budget management.',
        ]);

        // Tier 4: 32-35 h/wk PSW for SE3 (Ultra High)
        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 14, 'duration' => 480, 'required' => true, 'role' => 'RN'], // 8hr shifts
            ['code' => 'PSW', 'freq' => 35, 'duration' => 60, 'required' => true], // Tier 4: 35 h/wk (~5h/day)
            ['code' => 'HMK', 'freq' => 3, 'duration' => 60], // Homemaking support
            ['code' => 'RT', 'freq' => 3, 'duration' => 60, 'required' => true],
            ['code' => 'RPM', 'freq' => 7, 'duration' => 10, 'required' => true],
            ['code' => 'TRANS', 'freq' => 1, 'duration' => 60],
        ]);
    }

    protected function seedSE2(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_SE2_STANDARD',
            'name' => 'Extensive Services - Moderate Complexity',
            'description' => 'High-frequency nursing with some continuous coverage.',
            'rug_group' => 'SE2',
            'rug_category' => RUGClassification::CATEGORY_EXTENSIVE_SERVICES,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 7,
            'max_adl_sum' => 18,
            'required_flags' => ['extensive_services'],
            'weekly_cap_cents' => 450000,
            'priority_weight' => 95,
        ]);

        // Tier 4: 24-28 h/wk PSW for SE2
        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 14, 'duration' => 60, 'required' => true],
            ['code' => 'PSW', 'freq' => 28, 'duration' => 60, 'required' => true], // Tier 4: 28 h/wk (~4h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking support
            ['code' => 'RT', 'freq' => 2, 'duration' => 60],
            ['code' => 'RPM', 'freq' => 7, 'duration' => 10, 'required' => true],
        ]);
    }

    protected function seedSE1(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_SE1_STANDARD',
            'name' => 'Extensive Services - Lower Complexity',
            'description' => 'Support for 1-2 extensive therapies with strong daily nursing.',
            'rug_group' => 'SE1',
            'rug_category' => RUGClassification::CATEGORY_EXTENSIVE_SERVICES,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 7,
            'max_adl_sum' => 18,
            'required_flags' => ['extensive_services'],
            'weekly_cap_cents' => 400000,
            'priority_weight' => 90,
        ]);

        // Tier 3: 21 h/wk PSW for SE1
        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 10, 'duration' => 60, 'required' => true],
            ['code' => 'PSW', 'freq' => 21, 'duration' => 60, 'required' => true], // Tier 3: 21 h/wk (~3h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking support
            ['code' => 'RPM', 'freq' => 7, 'duration' => 10],
        ]);
    }

    // =========================================================================
    // SPECIAL CARE (SSB, SSA)
    // =========================================================================

    protected function seedSSB(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_SSB_STANDARD',
            'name' => 'Special Care - High ADL',
            'description' => 'Medically fragile patient with severe physical dependency and high-risk conditions.',
            'rug_group' => 'SSB',
            'rug_category' => RUGClassification::CATEGORY_SPECIAL_CARE,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 14,
            'max_adl_sum' => 18,
            'required_flags' => ['special_care'],
            'weekly_cap_cents' => 500000,
            'priority_weight' => 88,
        ]);

        // Tier 4: 32-35 h/wk PSW for SSB (Ultra High ADL)
        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 10, 'duration' => 60, 'required' => true],
            ['code' => 'PSW', 'freq' => 32, 'duration' => 60, 'required' => true], // Tier 4: 32 h/wk (~4.5h/day)
            ['code' => 'HMK', 'freq' => 3, 'duration' => 60], // Homemaking support
            ['code' => 'OT', 'freq' => 1, 'duration' => 60],
            ['code' => 'MEAL', 'freq' => 7, 'duration' => 15],
            ['code' => 'TRANS', 'freq' => 1, 'duration' => 60],
        ]);
    }

    protected function seedSSA(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_SSA_STANDARD',
            'name' => 'Special Care - Lower ADL',
            'description' => 'Clinical complexity with moderate physical dependency.',
            'rug_group' => 'SSA',
            'rug_category' => RUGClassification::CATEGORY_SPECIAL_CARE,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 13,
            'required_flags' => ['special_care'],
            'weekly_cap_cents' => 400000,
            'priority_weight' => 83,
        ]);

        // Tier 3: 21 h/wk PSW for SSA
        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 6, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 21, 'duration' => 60, 'required' => true], // Tier 3: 21 h/wk (~3h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking support
        ]);
    }

    // =========================================================================
    // CLINICALLY COMPLEX (CC0, CB0, CA2, CA1)
    // =========================================================================

    protected function seedCC0(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_CC0_STANDARD',
            'name' => 'Clinically Complex - High ADL',
            'description' => 'High-touch nursing and PSW support to prevent ED/hospital use.',
            'rug_group' => 'CC0',
            'rug_category' => RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 11,
            'max_adl_sum' => 18,
            'required_flags' => ['clinically_complex'],
            'weekly_cap_cents' => 500000,
            'priority_weight' => 78,
        ]);

        // Tier 4: 24-28 h/wk PSW for CC0 (high ADL)
        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 7, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 26, 'duration' => 60, 'required' => true], // Tier 4: 26 h/wk (~3.7h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking support
            ['code' => 'SW', 'freq' => 1, 'duration' => 60], // Caregiver coaching
            ['code' => 'PT', 'freq' => 1, 'duration' => 60],
            ['code' => 'OT', 'freq' => 1, 'duration' => 60],
            ['code' => 'RPM', 'freq' => 7, 'duration' => 10, 'required' => true],
            ['code' => 'MEAL', 'freq' => 7, 'duration' => 15],
        ]);
    }

    protected function seedCB0(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_CB0_STANDARD',
            'name' => 'Clinically Complex - Moderate ADL',
            'description' => 'High clinical complexity with moderate physical assistance needs.',
            'rug_group' => 'CB0',
            'rug_category' => RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 6,
            'max_adl_sum' => 10,
            'required_flags' => ['clinically_complex'],
            'weekly_cap_cents' => 450000,
            'priority_weight' => 73,
        ]);

        // Tier 3: 21 h/wk PSW for CB0
        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 5, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 21, 'duration' => 60, 'required' => true], // Tier 3: 21 h/wk (~3h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking support
            ['code' => 'PT', 'freq' => 1, 'duration' => 60],
            ['code' => 'OT', 'freq' => 1, 'duration' => 60],
            ['code' => 'MEAL', 'freq' => 5, 'duration' => 15],
        ]);
    }

    protected function seedCA2(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_CA2_STANDARD',
            'name' => 'Clinically Complex - Low ADL, Higher IADL',
            'description' => 'Medical complexity management where ADLs intact but IADLs impaired.',
            'rug_group' => 'CA2',
            'rug_category' => RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 5,
            'min_iadl_sum' => 1,
            'required_flags' => ['clinically_complex'],
            'weekly_cap_cents' => 350000,
            'priority_weight' => 68,
        ]);

        // Tier 2: 14-18 h/wk PSW for CA2
        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 3, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 15, 'duration' => 60], // Tier 2: 15 h/wk (~2h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking for IADL needs
            ['code' => 'MEAL', 'freq' => 5, 'duration' => 15],
            ['code' => 'PHAR', 'freq' => 3, 'duration' => 15],
        ]);
    }

    protected function seedCA1(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_CA1_STANDARD',
            'name' => 'Clinically Complex - Low ADL, Low IADL',
            'description' => 'Lower intensity clinical complexity management.',
            'rug_group' => 'CA1',
            'rug_category' => RUGClassification::CATEGORY_CLINICALLY_COMPLEX,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 5,
            'max_iadl_sum' => 0,
            'required_flags' => ['clinically_complex'],
            'weekly_cap_cents' => 300000,
            'priority_weight' => 63,
        ]);

        // Tier 1: 7-10 h/wk PSW for CA1
        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 3, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60], // Tier 1: 10 h/wk (~1.5h/day)
            ['code' => 'MEAL', 'freq' => 3, 'duration' => 15],
        ]);
    }

    // =========================================================================
    // IMPAIRED COGNITION (IB0, IA2, IA1)
    // =========================================================================

    protected function seedIB0(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_IB0_STANDARD',
            'name' => 'Impaired Cognition - Moderate ADL',
            'description' => 'Dementia/cognitive bundle with consistent PSW support and caregiver coaching.',
            'rug_group' => 'IB0',
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 6,
            'max_adl_sum' => 10,
            'required_flags' => ['impaired_cognition'],
            'weekly_cap_cents' => 450000,
            'priority_weight' => 58,
        ]);

        // Tier 3: 21-24 h/wk PSW for IB0 (behaviour + care burden)
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 24, 'duration' => 60, 'required' => true], // Tier 3: 24 h/wk (~3.5h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking support
            ['code' => 'NUR', 'freq' => 2, 'duration' => 45, 'required' => true],
            ['code' => 'BEH', 'freq' => 2, 'duration' => 60, 'required' => true], // Behavioural supports
            ['code' => 'REC', 'freq' => 3, 'duration' => 60], // Social/Recreational activation
            ['code' => 'SW', 'freq' => 1, 'duration' => 60], // Caregiver coaching
            ['code' => 'RES', 'freq' => 2, 'duration' => 240], // Respite
        ]);
    }

    protected function seedIA2(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_IA2_STANDARD',
            'name' => 'Impaired Cognition - Lower ADL, Higher IADL',
            'description' => 'Cognitive support for more mobile patients with IADL limitations.',
            'rug_group' => 'IA2',
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 5,
            'min_iadl_sum' => 1,
            'required_flags' => ['impaired_cognition'],
            'weekly_cap_cents' => 350000,
            'priority_weight' => 53,
        ]);

        // Tier 2: 14-18 h/wk PSW for IA2
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 16, 'duration' => 60, 'required' => true], // Tier 2: 16 h/wk (~2.3h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking for IADL needs
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45],
            ['code' => 'REC', 'freq' => 2, 'duration' => 60], // Social/Recreational
            ['code' => 'SW', 'freq' => 1, 'duration' => 60], // Caregiver coaching
            ['code' => 'MEAL', 'freq' => 5, 'duration' => 15],
        ]);
    }

    protected function seedIA1(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_IA1_STANDARD',
            'name' => 'Impaired Cognition - Lower ADL, Lower IADL',
            'description' => 'Lighter cognitive support bundle.',
            'rug_group' => 'IA1',
            'rug_category' => RUGClassification::CATEGORY_IMPAIRED_COGNITION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 5,
            'max_iadl_sum' => 0,
            'required_flags' => ['impaired_cognition'],
            'weekly_cap_cents' => 300000,
            'priority_weight' => 48,
        ]);

        // Tier 1: 7-10 h/wk PSW for IA1
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60, 'required' => true], // Tier 1: 10 h/wk (~1.5h/day)
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45],
            ['code' => 'REC', 'freq' => 2, 'duration' => 60], // Social/Recreational
        ]);
    }

    // =========================================================================
    // BEHAVIOUR PROBLEMS (BB0, BA2, BA1)
    // =========================================================================

    protected function seedBB0(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_BB0_STANDARD',
            'name' => 'Behaviour Problems - Moderate ADL',
            'description' => 'Intense behavioural support and structure to prevent crisis.',
            'rug_group' => 'BB0',
            'rug_category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 6,
            'max_adl_sum' => 10,
            'required_flags' => ['behaviour_problems'],
            'weekly_cap_cents' => 450000,
            'priority_weight' => 43,
        ]);

        // Tier 3: 21-24 h/wk PSW for BB0 (behaviour + care burden)
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 24, 'duration' => 60, 'required' => true], // Tier 3: 24 h/wk (~3.5h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking support
            ['code' => 'NUR', 'freq' => 3, 'duration' => 60, 'required' => true],
            ['code' => 'BEH', 'freq' => 3, 'duration' => 60, 'required' => true], // Behavioural supports
            ['code' => 'REC', 'freq' => 3, 'duration' => 60], // Social/Recreational activation
            ['code' => 'SW', 'freq' => 1, 'duration' => 60], // Caregiver coaching
            ['code' => 'RES', 'freq' => 2, 'duration' => 240], // Respite (8 h/wk)
        ]);
    }

    protected function seedBA2(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_BA2_STANDARD',
            'name' => 'Behaviour Problems - Lower ADL, Higher IADL',
            'description' => 'Behavioural support for more mobile patients.',
            'rug_group' => 'BA2',
            'rug_category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 5,
            'min_iadl_sum' => 1,
            'required_flags' => ['behaviour_problems'],
            'weekly_cap_cents' => 350000,
            'priority_weight' => 38,
        ]);

        // Tier 2: 14-18 h/wk PSW for BA2
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 16, 'duration' => 60, 'required' => true], // Tier 2: 16 h/wk (~2.3h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking for IADL needs
            ['code' => 'NUR', 'freq' => 2, 'duration' => 60],
            ['code' => 'BEH', 'freq' => 2, 'duration' => 60, 'required' => true], // Behavioural supports
            ['code' => 'REC', 'freq' => 3, 'duration' => 60], // Social/Recreational
            ['code' => 'RES', 'freq' => 1, 'duration' => 240], // Respite (4 h/wk)
        ]);
    }

    protected function seedBA1(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_BA1_STANDARD',
            'name' => 'Behaviour Problems - Lower ADL, Lower IADL',
            'description' => 'Lighter behavioural support bundle.',
            'rug_group' => 'BA1',
            'rug_category' => RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 5,
            'max_iadl_sum' => 0,
            'required_flags' => ['behaviour_problems'],
            'weekly_cap_cents' => 300000,
            'priority_weight' => 33,
        ]);

        // Tier 1: 7-10 h/wk PSW for BA1
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60, 'required' => true], // Tier 1: 10 h/wk (~1.5h/day)
            ['code' => 'NUR', 'freq' => 2, 'duration' => 60],
            ['code' => 'BEH', 'freq' => 2, 'duration' => 60, 'required' => true], // Behavioural supports
            ['code' => 'REC', 'freq' => 2, 'duration' => 60], // Social/Recreational
        ]);
    }

    // =========================================================================
    // REDUCED PHYSICAL FUNCTION (PD0, PC0, PB0, PA2, PA1)
    // =========================================================================

    protected function seedPD0(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_PD0_STANDARD',
            'name' => 'Reduced Physical Function - High ADL',
            'description' => 'Intensive PSW support for mobility and self-care.',
            'rug_group' => 'PD0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 11,
            'max_adl_sum' => 18,
            'weekly_cap_cents' => 400000,
            'priority_weight' => 28,
        ]);

        // Tier 3: 21 h/wk PSW for PD0 (high ADL)
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 21, 'duration' => 60, 'required' => true], // Tier 3: 21 h/wk (~3h/day)
            ['code' => 'HMK', 'freq' => 3, 'duration' => 60], // Homemaking for high ADL
            ['code' => 'NUR', 'freq' => 2, 'duration' => 45],
            ['code' => 'OT', 'freq' => 1, 'duration' => 60], // Home safety assessment
            ['code' => 'MEAL', 'freq' => 7, 'duration' => 15],
        ]);
    }

    protected function seedPC0(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_PC0_STANDARD',
            'name' => 'Reduced Physical Function - ADL 9-10',
            'description' => 'Moderate-high physical support.',
            'rug_group' => 'PC0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 9,
            'max_adl_sum' => 10,
            'weekly_cap_cents' => 350000,
            'priority_weight' => 23,
        ]);

        // Tier 2: 14-18 h/wk PSW for PC0
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 16, 'duration' => 60, 'required' => true], // Tier 2: 16 h/wk (~2.3h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking support
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45],
            ['code' => 'PT', 'freq' => 1, 'duration' => 60],
            ['code' => 'MEAL', 'freq' => 5, 'duration' => 15],
        ]);
    }

    protected function seedPB0(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_PB0_STANDARD',
            'name' => 'Reduced Physical Function - ADL 6-8',
            'description' => 'Moderate physical support.',
            'rug_group' => 'PB0',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 6,
            'max_adl_sum' => 8,
            'weekly_cap_cents' => 300000,
            'priority_weight' => 18,
        ]);

        // Tier 2: 14-18 h/wk PSW for PB0
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 14, 'duration' => 60, 'required' => true], // Tier 2: 14 h/wk (~2h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking support
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45],
            ['code' => 'OT', 'freq' => 1, 'duration' => 60],
        ]);
    }

    protected function seedPA2(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_PA2_STANDARD',
            'name' => 'Reduced Physical Function - Low ADL, Higher IADL',
            'description' => 'Light physical support with IADL assistance.',
            'rug_group' => 'PA2',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 5,
            'min_iadl_sum' => 1,
            'weekly_cap_cents' => 250000,
            'priority_weight' => 13,
        ]);

        // Tier 1: 7-10 h/wk PSW for PA2
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60, 'required' => true], // Tier 1: 10 h/wk (~1.5h/day)
            ['code' => 'HMK', 'freq' => 2, 'duration' => 60], // Homemaking for IADL needs
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45],
            ['code' => 'MEAL', 'freq' => 5, 'duration' => 15],
            ['code' => 'PHAR', 'freq' => 2, 'duration' => 15],
        ]);
    }

    protected function seedPA1(): void
    {
        $template = $this->createTemplate([
            'code' => 'LTC_PA1_STANDARD',
            'name' => 'Reduced Physical Function - Low ADL, Low IADL',
            'description' => 'Lightest support bundle for relatively independent patients.',
            'rug_group' => 'PA1',
            'rug_category' => RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION,
            'funding_stream' => 'LTC',
            'min_adl_sum' => 4,
            'max_adl_sum' => 5,
            'max_iadl_sum' => 0,
            'weekly_cap_cents' => 200000,
            'priority_weight' => 8,
        ]);

        // Tier 1: 7-10 h/wk PSW for PA1
        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 7, 'duration' => 60, 'required' => true], // Tier 1: 7 h/wk (~1h/day)
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45],
        ]);
    }
}
