<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceCategory;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * SSPOSeeder - Seeds SSPO (Secondary Service Provider Organization) data.
 *
 * Creates 4 SSPO organizations with complete profiles:
 * 1. Alexis Lodge Retirement Residence - Dementia/Memory Care specialist
 * 2. Reconnect Health Services - Community/Mental Health services
 * 3. Toronto Grace Health Centre RCM - Remote Care Monitoring
 * 4. WellHaus - Virtual care technology platform
 *
 * Also creates SSPO-specific service types and mappings.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class SSPOSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding SSPO organizations and service types...');

        // Create SSPO-specific service types first
        $this->seedSspoServiceTypes();

        // Create the 4 SSPO organizations
        $sspos = $this->seedSspoOrganizations();

        // Map service types to each SSPO
        $this->mapServiceTypesToSspos($sspos);

        // Create sample service assignments for SSPO profile pages
        $this->seedSspoAssignments($sspos);

        $this->command->info('SSPO seeding complete: ' . count($sspos) . ' organizations created.');
    }

    /**
     * Seed SSPO-specific service types that may not exist in CoreDataSeeder.
     */
    protected function seedSspoServiceTypes(): void
    {
        $this->command->info('  Creating SSPO-specific service types...');

        $safety = ServiceCategory::where('code', 'SAFETY')->first();
        $personal = ServiceCategory::where('code', 'PERSONAL')->first();
        $clinical = ServiceCategory::where('code', 'CLINICAL')->first();
        $logistics = ServiceCategory::where('code', 'LOGISTICS')->first();

        $sspoServiceTypes = [
            // Dementia/Memory Care Services
            [
                'code' => 'DEM',
                'name' => 'Dementia Care',
                'category' => 'Personal Support & Daily Living',
                'category_id' => $personal?->id,
                'cost_code' => 'COST-DEM',
                'cost_driver' => 'Hourly Labour',
                'cost_per_visit' => 55.00,
                'source' => 'SSPO Specialty',
                'default_duration_minutes' => 120,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_IN_PERSON,
                'description' => 'Specialized dementia care including cognitive stimulation, behavioural support, and ADL assistance for patients with Alzheimers and related disorders.',
            ],
            [
                'code' => 'BEH',
                'name' => 'Behavioural Supports',
                'category' => 'Personal Support & Daily Living',
                'category_id' => $personal?->id,
                'cost_code' => 'COST-BEH',
                'cost_driver' => 'Hourly Labour',
                'cost_per_visit' => 65.00,
                'source' => 'SSPO Specialty',
                'default_duration_minutes' => 60,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SPO, ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_IN_PERSON,
                'description' => 'Specialized behavioural support for patients with responsive behaviours, including de-escalation, caregiver coaching, and intervention strategies.',
            ],
            [
                'code' => 'CGC',
                'name' => 'Caregiver Coaching',
                'category' => 'Personal Support & Daily Living',
                'category_id' => $personal?->id,
                'cost_code' => 'COST-CGC',
                'cost_driver' => 'Per Session',
                'cost_per_visit' => 75.00,
                'source' => 'SSPO Specialty',
                'default_duration_minutes' => 60,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_EITHER,
                'description' => 'Education and coaching for family caregivers on care techniques, behavioural management, and self-care strategies.',
            ],

            // Meals & Nutrition Services
            [
                'code' => 'MOW',
                'name' => 'Meals on Wheels',
                'category' => 'Logistics & Access Services',
                'category_id' => $logistics?->id,
                'cost_code' => 'COST-MOW',
                'cost_driver' => 'Per Meal Delivery',
                'cost_per_visit' => 12.00,
                'source' => 'SSPO Community',
                'default_duration_minutes' => 15,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_IN_PERSON,
                'description' => 'Nutritious hot meal delivery service for homebound seniors, includes frozen meal options and dietary modifications.',
            ],
            [
                'code' => 'ADP',
                'name' => 'Adult Day Program',
                'category' => 'Personal Support & Daily Living',
                'category_id' => $personal?->id,
                'cost_code' => 'COST-ADP',
                'cost_driver' => 'Per Day',
                'cost_per_visit' => 85.00,
                'source' => 'SSPO Community',
                'default_duration_minutes' => 360,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_IN_PERSON,
                'description' => 'Structured day program with social activities, meals, and supervision. Provides caregiver respite.',
            ],

            // Remote Monitoring Services
            [
                'code' => 'PERS-ADV',
                'name' => 'PERS/Pendant Monitoring',
                'category' => 'Safety, Monitoring & Technology',
                'category_id' => $safety?->id,
                'cost_code' => 'COST-PERS',
                'cost_driver' => 'Monthly Subscription',
                'cost_per_visit' => 50.00,
                'source' => 'SSPO Technology',
                'default_duration_minutes' => 0,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_REMOTE,
                'description' => 'Advanced PERS with wearable SOS/fall pendant, 24/7 call centre monitoring, and GPS tracking for wandering patients.',
            ],
            [
                'code' => 'MED-DISP',
                'name' => 'Medication Dispensing Support',
                'category' => 'Safety, Monitoring & Technology',
                'category_id' => $safety?->id,
                'cost_code' => 'COST-MED',
                'cost_driver' => 'Monthly Device + Monitoring',
                'cost_per_visit' => 75.00,
                'source' => 'SSPO Technology',
                'default_duration_minutes' => 0,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_REMOTE,
                'description' => 'Automated medication dispensing devices with remote monitoring and adherence alerts.',
            ],
            [
                'code' => 'FALL-MON',
                'name' => 'SOS/Falls Monitoring',
                'category' => 'Safety, Monitoring & Technology',
                'category_id' => $safety?->id,
                'cost_code' => 'COST-FALL',
                'cost_driver' => 'Monthly Subscription',
                'cost_per_visit' => 45.00,
                'source' => 'SSPO Technology',
                'default_duration_minutes' => 0,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_REMOTE,
                'description' => '24/7 fall detection monitoring with automatic emergency response activation.',
            ],
            [
                'code' => 'CDM',
                'name' => 'Chronic Disease Monitoring',
                'category' => 'Safety, Monitoring & Technology',
                'category_id' => $safety?->id,
                'cost_code' => 'COST-CDM',
                'cost_driver' => 'Monthly Subscription',
                'cost_per_visit' => 100.00,
                'source' => 'SSPO Technology',
                'default_duration_minutes' => 0,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_REMOTE,
                'description' => 'Remote monitoring of chronic conditions (CHF, COPD, Diabetes) with vital signs tracking and clinical alerts.',
            ],
            [
                'code' => 'TELE',
                'name' => 'Tele-Health / Virtual Support',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-TELE',
                'cost_driver' => 'Per Virtual Visit',
                'cost_per_visit' => 60.00,
                'source' => 'SSPO Technology',
                'default_duration_minutes' => 30,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SPO, ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_REMOTE,
                'description' => 'Virtual health consultations via video/phone for assessments, follow-ups, and care coordination.',
            ],

            // Case Management & Mental Health
            [
                'code' => 'CM',
                'name' => 'Case Management',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-CM',
                'cost_driver' => 'Per Hour',
                'cost_per_visit' => 80.00,
                'source' => 'SSPO Community',
                'default_duration_minutes' => 60,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SPO, ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_EITHER,
                'description' => 'Comprehensive care coordination including service navigation, advocacy, and follow-up for complex cases.',
            ],
            [
                'code' => 'CRISIS',
                'name' => 'Crisis Outreach',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-CRISIS',
                'cost_driver' => 'Per Intervention',
                'cost_per_visit' => 120.00,
                'source' => 'SSPO Community',
                'default_duration_minutes' => 90,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_IN_PERSON,
                'description' => 'Mobile crisis intervention for mental health and addictions emergencies.',
            ],

            // Transitional Care
            [
                'code' => 'TRANS-BED',
                'name' => 'Transitional Care Bed',
                'category' => 'Personal Support & Daily Living',
                'category_id' => $personal?->id,
                'cost_code' => 'COST-TRANS',
                'cost_driver' => 'Per Day',
                'cost_per_visit' => 350.00,
                'source' => 'SSPO Specialty',
                'default_duration_minutes' => 1440,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_IN_PERSON,
                'description' => 'Short-term residential placement for transitions from hospital or during caregiver absence.',
            ],

            // Virtual Primary Care
            [
                'code' => 'VPC',
                'name' => 'Virtual Primary Care',
                'category' => 'Clinical Services',
                'category_id' => $clinical?->id,
                'cost_code' => 'COST-VPC',
                'cost_driver' => 'Per Visit',
                'cost_per_visit' => 100.00,
                'source' => 'SSPO Technology',
                'default_duration_minutes' => 20,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_REMOTE,
                'description' => 'On-demand virtual primary care consultations with physicians or nurse practitioners.',
            ],
            [
                'code' => 'LAB-MOBILE',
                'name' => 'Mobile Lab Services',
                'category' => 'Logistics & Access Services',
                'category_id' => $logistics?->id,
                'cost_code' => 'COST-LAB',
                'cost_driver' => 'Per Visit',
                'cost_per_visit' => 65.00,
                'source' => 'SSPO Technology',
                'default_duration_minutes' => 30,
                'preferred_provider' => ServiceType::PROVIDER_SSPO,
                'allowed_provider_types' => [ServiceType::PROVIDER_SSPO],
                'delivery_mode' => ServiceType::DELIVERY_IN_PERSON,
                'description' => 'In-home laboratory specimen collection with digital result delivery.',
            ],
        ];

        foreach ($sspoServiceTypes as $data) {
            ServiceType::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        $this->command->info('  Created ' . count($sspoServiceTypes) . ' SSPO service types.');
    }

    /**
     * Seed the 4 SSPO organizations.
     */
    protected function seedSspoOrganizations(): array
    {
        $sspos = [];

        // 1. Alexis Lodge Retirement Residence
        $sspos['alexis_lodge'] = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'alexis-lodge-retirement-residence'],
            [
                'name' => 'Alexis Lodge Retirement Residence',
                'type' => ServiceProviderOrganization::TYPE_SSPO,
                'status' => ServiceProviderOrganization::STATUS_ACTIVE,
                'website_url' => 'https://www.alexislodge.com',
                'contact_phone' => '416-752-1923',
                'contact_email' => null,
                'address' => '707 Ellesmere Rd',
                'city' => 'Toronto',
                'province' => 'ON',
                'postal_code' => 'M1P 2W6',
                'region_code' => 'TORONTO_CENTRAL',
                'regions' => ['TORONTO_CENTRAL', 'EAST'],
                'tagline' => 'Memory Care Excellence Since 1998',
                'description' => "Licensed RHRA retirement home operator in Toronto Central with approximately 26 years of dementia-care experience. Alexis Lodge specializes in high-acuity cognitive impairment and responsive behaviours, offering dementia-trained PSWs, behavioural supports, caregiver coaching, activation programming, in-home adaptation/redesign, and transitional-care bed offerings for last-minute placement barriers.\n\nTheir specialized memory care unit provides 24/7 supervision with trained staff experienced in Alzheimer's disease and related dementias. The facility offers respite care, palliative support, and specialized continence and wound care programs.",
                'notes' => 'Memory-care residence; confirm extent of in-home service capacity beyond residence-based care. Strong fit for complex dementia cases requiring specialized environment.',
                'capabilities' => [
                    'dementia_care',
                    'behavioural_support',
                    'respite_care',
                    'palliative_care',
                    'caregiver_training',
                    'transitional_beds',
                ],
                'capacity_metadata' => [
                    'max_residents' => 120,
                    'respite_beds' => 8,
                    'transitional_beds' => 4,
                    'waitlist_weeks' => 2,
                ],
                'active' => true,
            ]
        );
        $this->command->info('  Created SSPO: Alexis Lodge Retirement Residence');

        // 2. Reconnect Health Services
        $sspos['reconnect'] = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'reconnect-health-services'],
            [
                'name' => 'Reconnect Health Services',
                'type' => ServiceProviderOrganization::TYPE_SSPO,
                'status' => ServiceProviderOrganization::STATUS_ACTIVE,
                'website_url' => 'https://www.reconnect.on.ca',
                'contact_phone' => null,
                'contact_email' => null,
                'address' => '1281 St Clair Ave W',
                'city' => 'Toronto',
                'province' => 'ON',
                'postal_code' => 'M6E 1B8',
                'region_code' => 'TORONTO_CENTRAL',
                'regions' => ['TORONTO_CENTRAL', 'CENTRAL_WEST', 'NORTH'],
                'tagline' => 'Community Care & Mental Wellness',
                'description' => "Reconnect Health Services is a not-for-profit community and home-care provider serving seniors, caregivers, and individuals with mental health or addictions concerns across the Greater Toronto Area.\n\nTheir comprehensive service offerings include home help/homemaking, respite care, Meals on Wheels, assisted living supports, adult day programming, medical transportation, mental health & addictions case management, crisis outreach, and supportive housing.\n\nReconnect has established partnerships with hospitals and community health centres to provide integrated care pathways for complex clients requiring mental health and addictions support alongside home care services.",
                'notes' => 'Versatile SSPO with strong mental health/addictions expertise. Verify details on in-home PSW capacity and exact home-care coverage area. Good fit for clients with dual diagnosis or complex social needs.',
                'capabilities' => [
                    'homemaking',
                    'respite_care',
                    'meals_delivery',
                    'adult_day_program',
                    'transportation',
                    'mental_health',
                    'case_management',
                    'crisis_outreach',
                ],
                'capacity_metadata' => [
                    'home_care_clients' => 250,
                    'day_program_capacity' => 40,
                    'crisis_team_available' => true,
                    'service_hours' => '7am-9pm daily',
                ],
                'active' => true,
            ]
        );
        $this->command->info('  Created SSPO: Reconnect Health Services');

        // 3. Toronto Grace Health Centre - Remote Care Monitoring (RCM)
        $sspos['toronto_grace'] = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'toronto-grace-health-centre-rcm'],
            [
                'name' => 'Toronto Grace Health Centre - Remote Care Monitoring (RCM)',
                'type' => ServiceProviderOrganization::TYPE_SSPO,
                'status' => ServiceProviderOrganization::STATUS_ACTIVE,
                'website_url' => 'https://www.torontograce.org',
                'contact_phone' => null,
                'contact_email' => null,
                'address' => '650 Church St',
                'city' => 'Toronto',
                'province' => 'ON',
                'postal_code' => 'M4Y 2G5',
                'region_code' => 'TORONTO_CENTRAL',
                'regions' => ['TORONTO_CENTRAL', 'CENTRAL_EAST', 'CENTRAL_WEST', 'NORTH', 'EAST'],
                'tagline' => 'Remote Monitoring & Virtual Care Excellence',
                'description' => "Toronto Grace Health Centre operates a specialized Remote Care Monitoring (RCM) program designed to support patients with chronic conditions and reduce emergency department visits.\n\nTheir technology-enabled services include wearable SOS/fall pendants, automated medication-dispensing devices, chronic disease remote monitoring (heart failure, COPD, diabetes), 24/7 call-centre triage staffed by registered nurses, and comprehensive remote safety monitoring.\n\nThe program integrates with existing care plans to provide continuous oversight between in-person visits, with real-time alerting and escalation protocols. All devices are Health Canada approved and data is securely transmitted via encrypted connections.",
                'notes' => 'RCM program strong fit for PERS/RPM-type services. Covers all Toronto regions remotely. Capacity model must be confirmed - currently based on device inventory and monitoring staff ratios.',
                'capabilities' => [
                    'pers_monitoring',
                    'rpm_devices',
                    'medication_dispensing',
                    'fall_detection',
                    'chronic_disease_monitoring',
                    'virtual_triage',
                    '24_7_call_centre',
                ],
                'capacity_metadata' => [
                    'max_monitored_patients' => 500,
                    'devices_available' => 200,
                    'monitoring_nurses' => 8,
                    'response_time_minutes' => 3,
                ],
                'active' => true,
            ]
        );
        $this->command->info('  Created SSPO: Toronto Grace Health Centre - RCM');

        // 4. WellHaus
        $sspos['wellhaus'] = ServiceProviderOrganization::updateOrCreate(
            ['slug' => 'wellhaus'],
            [
                'name' => 'WellHaus',
                'type' => ServiceProviderOrganization::TYPE_SSPO,
                'status' => ServiceProviderOrganization::STATUS_ACTIVE,
                'website_url' => 'https://www.wellnesshaus.com',
                'contact_phone' => '437-826-1959',
                'contact_email' => null,
                'address' => '413 Spadina Rd',
                'city' => 'Toronto',
                'province' => 'ON',
                'postal_code' => 'M5P 2W5',
                'region_code' => 'TORONTO_CENTRAL',
                'regions' => ['TORONTO_CENTRAL', 'CENTRAL_EAST', 'CENTRAL_WEST', 'NORTH', 'EAST'],
                'tagline' => 'Technology-Enabled Home Care Platform',
                'description' => "WellHaus offers a multi-tiered, non-proprietary platform built on open standards (HL7 FHIR, SMART on FHIR, HL7 CQL) that delivers personalized, proactive, and measurable home-based care for high-intensity patients.\n\nTheir integrated platform connects virtual primary care physicians, mobile laboratory services, and telemedicine specialists with patients in their homes. The system includes predictive analytics for early intervention, care coordination dashboards, and seamless integration with hospital EMR systems.\n\nWellHaus focuses on technology interoperability and data-driven care management, making them an ideal partner for digital health initiatives and patients who benefit from virtual care modalities.",
                'notes' => 'Technology platform partner for advanced home care monitoring. Strong fit for virtual care services and digital health integration. All services delivered remotely or via mobile teams.',
                'capabilities' => [
                    'virtual_primary_care',
                    'mobile_lab',
                    'telemedicine',
                    'care_coordination',
                    'predictive_analytics',
                    'emr_integration',
                ],
                'capacity_metadata' => [
                    'virtual_physicians' => 12,
                    'lab_technicians' => 6,
                    'platform_capacity' => 1000,
                    'average_response_hours' => 4,
                ],
                'active' => true,
            ]
        );
        $this->command->info('  Created SSPO: WellHaus');

        return $sspos;
    }

    /**
     * Map service types to each SSPO organization.
     */
    protected function mapServiceTypesToSspos(array $sspos): void
    {
        $this->command->info('  Mapping service types to SSPOs...');

        // Alexis Lodge service mappings
        $alexisLodgeServices = [
            'DEM' => true,      // Dementia Care (primary)
            'BEH' => true,      // Behavioural Supports (primary)
            'PSW' => false,     // Personal Support
            'RES' => true,      // Respite Care (primary)
            'CGC' => true,      // Caregiver Coaching (primary)
            'TRANS-BED' => true, // Transitional Care Bed (primary)
            'NUR' => false,     // Nursing (wound/palliative)
            'HMK' => false,     // Homemaking
        ];
        $this->attachServiceTypes($sspos['alexis_lodge'], $alexisLodgeServices);

        // Reconnect Health Services mappings
        $reconnectServices = [
            'HMK' => true,      // Homemaking (primary)
            'RES' => true,      // Respite Care (primary)
            'MOW' => true,      // Meals on Wheels (primary)
            'ADP' => true,      // Adult Day Program (primary)
            'TRANS' => true,    // Transportation (primary)
            'BEH' => true,      // Behavioural Supports (primary)
            'CM' => true,       // Case Management (primary)
            'CRISIS' => true,   // Crisis Outreach (primary)
            'SW' => false,      // Social Work
        ];
        $this->attachServiceTypes($sspos['reconnect'], $reconnectServices);

        // Toronto Grace RCM mappings
        $torontoGraceServices = [
            'PERS-ADV' => true,    // PERS/Pendant Monitoring (primary)
            'RPM' => true,         // Remote Patient Monitoring (primary)
            'MED-DISP' => true,    // Medication Dispensing (primary)
            'FALL-MON' => true,    // Falls Monitoring (primary)
            'CDM' => true,         // Chronic Disease Monitoring (primary)
            'TELE' => true,        // Tele-Health (primary)
            'PERS' => false,       // Basic PERS (if exists)
        ];
        $this->attachServiceTypes($sspos['toronto_grace'], $torontoGraceServices);

        // WellHaus mappings
        $wellhausServices = [
            'VPC' => true,         // Virtual Primary Care (primary)
            'LAB-MOBILE' => true,  // Mobile Lab Services (primary)
            'TELE' => true,        // Tele-Health (primary)
            'CDM' => false,        // Chronic Disease Monitoring
            'RPM' => false,        // Remote Patient Monitoring (partner)
        ];
        $this->attachServiceTypes($sspos['wellhaus'], $wellhausServices);
    }

    /**
     * Attach service types to an SSPO organization.
     *
     * @param ServiceProviderOrganization $sspo The organization
     * @param array $serviceMap Array of service_code => is_primary
     */
    protected function attachServiceTypes(ServiceProviderOrganization $sspo, array $serviceMap): void
    {
        $attachData = [];

        foreach ($serviceMap as $code => $isPrimary) {
            $serviceType = ServiceType::where('code', $code)->first();

            if ($serviceType) {
                $attachData[$serviceType->id] = [
                    'is_primary' => $isPrimary,
                    'metadata' => null,
                ];
            }
        }

        if (!empty($attachData)) {
            $sspo->serviceTypes()->sync($attachData);
            $this->command->info("    Mapped " . count($attachData) . " services to {$sspo->name}");
        }
    }

    /**
     * Seed sample service assignments for SSPO profile pages.
     *
     * Creates realistic upcoming and past appointments for each SSPO
     * to demonstrate the profile page features.
     */
    protected function seedSspoAssignments(array $sspos): void
    {
        $this->command->info('  Creating sample SSPO service assignments...');

        // Get some patients to assign to SSPOs
        $patients = Patient::limit(12)->get();
        if ($patients->isEmpty()) {
            $this->command->warn('    No patients found - skipping SSPO assignments');
            return;
        }

        // Get a staff user to assign (or use null)
        $staffUser = User::where('role', 'staff')->first();

        $now = Carbon::now();
        $assignmentCount = 0;

        foreach ($sspos as $key => $sspo) {
            // Get this SSPO's primary service types
            $serviceTypes = $sspo->serviceTypes()->wherePivot('is_primary', true)->limit(3)->get();

            if ($serviceTypes->isEmpty()) {
                $serviceTypes = $sspo->serviceTypes()->limit(2)->get();
            }

            if ($serviceTypes->isEmpty()) {
                continue;
            }

            // Assign 2-3 patients to each SSPO
            $sspoPatients = $patients->random(min(3, $patients->count()));

            foreach ($sspoPatients as $patientIndex => $patient) {
                foreach ($serviceTypes as $serviceIndex => $serviceType) {
                    // Create upcoming appointments (next 7 days)
                    $upcomingDays = rand(1, 6);
                    $upcomingHour = rand(9, 16);

                    ServiceAssignment::create([
                        'patient_id' => $patient->id,
                        'service_provider_organization_id' => $sspo->id,
                        'service_type_id' => $serviceType->id,
                        'assigned_user_id' => $staffUser?->id,
                        'status' => ServiceAssignment::STATUS_PLANNED,
                        'scheduled_start' => $now->copy()->addDays($upcomingDays)->setHour($upcomingHour)->setMinute(0),
                        'scheduled_end' => $now->copy()->addDays($upcomingDays)->setHour($upcomingHour)->addMinutes($serviceType->default_duration_minutes ?? 60),
                        'duration_minutes' => $serviceType->default_duration_minutes ?? 60,
                        'source' => ServiceAssignment::SOURCE_SSPO,
                        'sspo_acceptance_status' => ServiceAssignment::SSPO_ACCEPTED,
                        'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
                    ]);
                    $assignmentCount++;

                    // Create some past/completed appointments (past 7 days)
                    if ($serviceIndex === 0) {
                        $pastDays = rand(1, 6);
                        $pastHour = rand(9, 16);

                        ServiceAssignment::create([
                            'patient_id' => $patient->id,
                            'service_provider_organization_id' => $sspo->id,
                            'service_type_id' => $serviceType->id,
                            'assigned_user_id' => $staffUser?->id,
                            'status' => ServiceAssignment::STATUS_COMPLETED,
                            'scheduled_start' => $now->copy()->subDays($pastDays)->setHour($pastHour)->setMinute(0),
                            'scheduled_end' => $now->copy()->subDays($pastDays)->setHour($pastHour)->addMinutes($serviceType->default_duration_minutes ?? 60),
                            'actual_start' => $now->copy()->subDays($pastDays)->setHour($pastHour)->setMinute(0),
                            'actual_end' => $now->copy()->subDays($pastDays)->setHour($pastHour)->addMinutes($serviceType->default_duration_minutes ?? 60),
                            'duration_minutes' => $serviceType->default_duration_minutes ?? 60,
                            'source' => ServiceAssignment::SOURCE_SSPO,
                            'sspo_acceptance_status' => ServiceAssignment::SSPO_ACCEPTED,
                            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
                            'verified_at' => $now->copy()->subDays($pastDays)->addHours(2),
                        ]);
                        $assignmentCount++;
                    }
                }
            }
        }

        $this->command->info("    Created {$assignmentCount} sample SSPO assignments");
    }
}
