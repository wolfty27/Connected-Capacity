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

        $this->command->info('Seeded 23 RUG-III/HC bundle templates.');
    }

    protected function loadServiceCache(): void
    {
        $this->serviceCache = ServiceType::pluck('id', 'code')->toArray();
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

        $this->addServices($template, [
            ['code' => 'PT', 'freq' => 3, 'duration' => 60, 'required' => true],
            ['code' => 'OT', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'SLP', 'freq' => 1, 'duration' => 45, 'conditional' => true, 'condition_flags' => ['swallowing_issue']],
            ['code' => 'NUR', 'freq' => 4, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60, 'required' => true], // Tier 2: 10 h/wk
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

        $this->addServices($template, [
            ['code' => 'PT', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'OT', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'NUR', 'freq' => 2, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60, 'required' => true], // Tier 2: 10 h/wk
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

        $this->addServices($template, [
            ['code' => 'PT', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'OT', 'freq' => 1, 'duration' => 60],
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 7, 'duration' => 60], // Tier 1: 7 h/wk
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

        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 14, 'duration' => 480, 'required' => true, 'role' => 'RN'], // 8hr shifts
            ['code' => 'PSW', 'freq' => 28, 'duration' => 60, 'required' => true], // Tier 4: 28 h/wk (4x/day)
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

        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 14, 'duration' => 60, 'required' => true],
            ['code' => 'PSW', 'freq' => 21, 'duration' => 60, 'required' => true], // Tier 4: 21 h/wk (3x/day)
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

        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 10, 'duration' => 60, 'required' => true],
            ['code' => 'PSW', 'freq' => 14, 'duration' => 60, 'required' => true], // Tier 3: 14 h/wk (2x/day)
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

        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 10, 'duration' => 60, 'required' => true],
            ['code' => 'PSW', 'freq' => 28, 'duration' => 60, 'required' => true], // Tier 4: 28 h/wk (4x/day)
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

        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 6, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 14, 'duration' => 60, 'required' => true],
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

        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 7, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 21, 'duration' => 60, 'required' => true],
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

        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 5, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 14, 'duration' => 60, 'required' => true],
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

        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 3, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60], // Tier 2: 10 h/wk
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

        $this->addServices($template, [
            ['code' => 'NUR', 'freq' => 3, 'duration' => 45, 'required' => true],
            ['code' => 'PSW', 'freq' => 7, 'duration' => 60], // Tier 1: 7 h/wk
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 14, 'duration' => 60, 'required' => true], // Tier 3: 14 h/wk (2x/day)
            ['code' => 'NUR', 'freq' => 2, 'duration' => 45, 'required' => true],
            ['code' => 'BEH', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'REC', 'freq' => 3, 'duration' => 60],
            ['code' => 'RES', 'freq' => 1, 'duration' => 240],
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60, 'required' => true], // Tier 2: 10 h/wk
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45],
            ['code' => 'REC', 'freq' => 2, 'duration' => 60],
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 7, 'duration' => 60, 'required' => true], // Tier 1: 7 h/wk
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45],
            ['code' => 'REC', 'freq' => 2, 'duration' => 60],
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 14, 'duration' => 60, 'required' => true], // Tier 3: 14 h/wk (2x/day)
            ['code' => 'NUR', 'freq' => 3, 'duration' => 60, 'required' => true],
            ['code' => 'BEH', 'freq' => 3, 'duration' => 60, 'required' => true],
            ['code' => 'REC', 'freq' => 3, 'duration' => 60],
            ['code' => 'RES', 'freq' => 2, 'duration' => 240],
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60, 'required' => true], // Tier 2: 10 h/wk
            ['code' => 'NUR', 'freq' => 2, 'duration' => 60],
            ['code' => 'BEH', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'REC', 'freq' => 3, 'duration' => 60],
            ['code' => 'RES', 'freq' => 1, 'duration' => 240],
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 7, 'duration' => 60, 'required' => true], // Tier 1: 7 h/wk
            ['code' => 'NUR', 'freq' => 2, 'duration' => 60],
            ['code' => 'BEH', 'freq' => 2, 'duration' => 60, 'required' => true],
            ['code' => 'REC', 'freq' => 2, 'duration' => 60],
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 14, 'duration' => 60, 'required' => true], // Tier 3: 14 h/wk (2x/day)
            ['code' => 'NUR', 'freq' => 2, 'duration' => 45],
            ['code' => 'OT', 'freq' => 1, 'duration' => 60],
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60, 'required' => true], // Tier 2: 10 h/wk
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 10, 'duration' => 60, 'required' => true],
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 7, 'duration' => 60, 'required' => true],
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

        $this->addServices($template, [
            ['code' => 'PSW', 'freq' => 5, 'duration' => 60, 'required' => true],
            ['code' => 'NUR', 'freq' => 1, 'duration' => 45],
        ]);
    }
}
