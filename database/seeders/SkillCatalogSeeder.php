<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

/**
 * STAFF-010: Seed skill catalog with OHaH-relevant skills
 *
 * Categories:
 * - clinical: RN-level clinical competencies
 * - personal_support: PSW-level care skills
 * - specialized: Certifications required for specific bundles
 * - administrative: Non-clinical support skills
 * - language: Language proficiencies for patient communication
 */
class SkillCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            // ==========================================
            // Clinical Skills (RN Level)
            // ==========================================
            [
                'name' => 'Wound Care',
                'code' => 'WOUND_CARE',
                'category' => 'clinical',
                'description' => 'Assessment and management of acute and chronic wounds, including pressure injuries, surgical wounds, and diabetic ulcers.',
                'requires_certification' => true,
                'renewal_period_months' => 24,
            ],
            [
                'name' => 'IV Therapy',
                'code' => 'IV_THERAPY',
                'category' => 'clinical',
                'description' => 'Intravenous therapy administration including medication infusion, IV maintenance, and central line care.',
                'requires_certification' => true,
                'renewal_period_months' => 24,
            ],
            [
                'name' => 'Medication Administration',
                'code' => 'MED_ADMIN',
                'category' => 'clinical',
                'description' => 'Safe administration of medications via various routes including oral, topical, subcutaneous, and intramuscular.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Vital Signs Monitoring',
                'code' => 'VITAL_SIGNS',
                'category' => 'clinical',
                'description' => 'Assessment and documentation of vital signs including blood pressure, heart rate, respiratory rate, temperature, and oxygen saturation.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Catheter Care',
                'code' => 'CATHETER_CARE',
                'category' => 'clinical',
                'description' => 'Management of urinary catheters including insertion, maintenance, and removal.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Ostomy Care',
                'code' => 'OSTOMY_CARE',
                'category' => 'clinical',
                'description' => 'Care and management of ostomies including colostomy, ileostomy, and urostomy.',
                'requires_certification' => true,
                'renewal_period_months' => 24,
            ],
            [
                'name' => 'Tracheostomy Care',
                'code' => 'TRACH_CARE',
                'category' => 'clinical',
                'description' => 'Management of tracheostomy including suctioning, cleaning, and emergency protocols.',
                'requires_certification' => true,
                'renewal_period_months' => 12,
            ],
            [
                'name' => 'Pain Management',
                'code' => 'PAIN_MGMT',
                'category' => 'clinical',
                'description' => 'Assessment and non-pharmacological management of acute and chronic pain.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Diabetes Management',
                'code' => 'DIABETES_MGMT',
                'category' => 'clinical',
                'description' => 'Blood glucose monitoring, insulin administration, and diabetes education.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Respiratory Care',
                'code' => 'RESP_CARE',
                'category' => 'clinical',
                'description' => 'Oxygen therapy, nebulizer treatments, and respiratory assessment.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],

            // ==========================================
            // Personal Support Skills (PSW Level)
            // ==========================================
            [
                'name' => 'Activities of Daily Living (ADL)',
                'code' => 'ADL_SUPPORT',
                'category' => 'personal_support',
                'description' => 'Assistance with bathing, dressing, grooming, toileting, and mobility.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Meal Preparation',
                'code' => 'MEAL_PREP',
                'category' => 'personal_support',
                'description' => 'Planning and preparing nutritious meals according to dietary requirements.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Light Housekeeping',
                'code' => 'HOUSEKEEPING',
                'category' => 'personal_support',
                'description' => 'Basic cleaning, laundry, and home maintenance tasks.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Mobility Assistance',
                'code' => 'MOBILITY_ASSIST',
                'category' => 'personal_support',
                'description' => 'Safe transfers, ambulation support, and use of mobility aids.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Companionship',
                'code' => 'COMPANIONSHIP',
                'category' => 'personal_support',
                'description' => 'Social interaction, engagement activities, and emotional support.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Medication Reminders',
                'code' => 'MED_REMIND',
                'category' => 'personal_support',
                'description' => 'Prompting and assisting clients with self-administered medications.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],

            // ==========================================
            // Specialized Certifications (OHaH Bundle Requirements)
            // ==========================================
            [
                'name' => 'Dementia Care Certification',
                'code' => 'DEMENTIA_CARE',
                'category' => 'specialized',
                'description' => 'Specialized training in caring for patients with Alzheimer\'s and other dementias. Required for DEM-SUP bundles.',
                'requires_certification' => true,
                'renewal_period_months' => 24,
            ],
            [
                'name' => 'Palliative Care Certification',
                'code' => 'PALLIATIVE_CARE',
                'category' => 'specialized',
                'description' => 'End-of-life care, symptom management, and family support. Required for PALL-SUP bundles.',
                'requires_certification' => true,
                'renewal_period_months' => 24,
            ],
            [
                'name' => 'Mental Health First Aid',
                'code' => 'MH_FIRST_AID',
                'category' => 'specialized',
                'description' => 'Recognition and initial response to mental health crises. Required for MH bundles.',
                'requires_certification' => true,
                'renewal_period_months' => 36,
            ],
            [
                'name' => 'Gentle Persuasive Approaches (GPA)',
                'code' => 'GPA_CERT',
                'category' => 'specialized',
                'description' => 'Person-centered approach to responsive behaviors in dementia care.',
                'requires_certification' => true,
                'renewal_period_months' => 24,
            ],
            [
                'name' => 'LEAP (Living Every day with Alzheimer\'s & Palliative care)',
                'code' => 'LEAP_CERT',
                'category' => 'specialized',
                'description' => 'Integrated approach to Alzheimer\'s and palliative care.',
                'requires_certification' => true,
                'renewal_period_months' => 36,
            ],
            [
                'name' => 'Infection Prevention & Control',
                'code' => 'IPAC',
                'category' => 'specialized',
                'description' => 'Infection prevention protocols and outbreak management.',
                'requires_certification' => true,
                'renewal_period_months' => 12,
            ],
            [
                'name' => 'CPR & First Aid',
                'code' => 'CPR_FIRST_AID',
                'category' => 'specialized',
                'description' => 'Cardiopulmonary resuscitation and basic first aid.',
                'requires_certification' => true,
                'renewal_period_months' => 12,
            ],
            [
                'name' => 'Safe Patient Handling (WHMIS)',
                'code' => 'SAFE_HANDLING',
                'category' => 'specialized',
                'description' => 'Workplace Hazardous Materials Information System and safe patient handling techniques.',
                'requires_certification' => true,
                'renewal_period_months' => 12,
            ],
            [
                'name' => 'Pediatric Home Care',
                'code' => 'PEDS_HOME_CARE',
                'category' => 'specialized',
                'description' => 'Specialized care for pediatric patients in home setting.',
                'requires_certification' => true,
                'renewal_period_months' => 24,
            ],
            [
                'name' => 'Ventilator Care',
                'code' => 'VENT_CARE',
                'category' => 'specialized',
                'description' => 'Care and monitoring of patients on home ventilators.',
                'requires_certification' => true,
                'renewal_period_months' => 12,
            ],

            // ==========================================
            // Administrative Skills
            // ==========================================
            [
                'name' => 'Care Coordination',
                'code' => 'CARE_COORD',
                'category' => 'administrative',
                'description' => 'Coordination of care between multiple providers and services.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Documentation',
                'code' => 'DOCUMENTATION',
                'category' => 'administrative',
                'description' => 'Accurate and timely documentation of care provided.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'InterRAI Assessment',
                'code' => 'INTERRAI_ASSESS',
                'category' => 'administrative',
                'description' => 'Certified to conduct InterRAI HC assessments.',
                'requires_certification' => true,
                'renewal_period_months' => 24,
            ],

            // ==========================================
            // Language Skills
            // ==========================================
            [
                'name' => 'French Language',
                'code' => 'LANG_FR',
                'category' => 'language',
                'description' => 'Fluent in French for patient communication.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Mandarin Language',
                'code' => 'LANG_ZH',
                'category' => 'language',
                'description' => 'Fluent in Mandarin for patient communication.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Cantonese Language',
                'code' => 'LANG_YUE',
                'category' => 'language',
                'description' => 'Fluent in Cantonese for patient communication.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Punjabi Language',
                'code' => 'LANG_PA',
                'category' => 'language',
                'description' => 'Fluent in Punjabi for patient communication.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Hindi Language',
                'code' => 'LANG_HI',
                'category' => 'language',
                'description' => 'Fluent in Hindi for patient communication.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Spanish Language',
                'code' => 'LANG_ES',
                'category' => 'language',
                'description' => 'Fluent in Spanish for patient communication.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Portuguese Language',
                'code' => 'LANG_PT',
                'category' => 'language',
                'description' => 'Fluent in Portuguese for patient communication.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Italian Language',
                'code' => 'LANG_IT',
                'category' => 'language',
                'description' => 'Fluent in Italian for patient communication.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Arabic Language',
                'code' => 'LANG_AR',
                'category' => 'language',
                'description' => 'Fluent in Arabic for patient communication.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
            [
                'name' => 'Filipino/Tagalog Language',
                'code' => 'LANG_TL',
                'category' => 'language',
                'description' => 'Fluent in Filipino/Tagalog for patient communication.',
                'requires_certification' => false,
                'renewal_period_months' => null,
            ],
        ];

        foreach ($skills as $skillData) {
            Skill::updateOrCreate(
                ['code' => $skillData['code']],
                $skillData
            );
        }

        $this->command->info('Skill catalog seeded with ' . count($skills) . ' skills.');
    }
}
