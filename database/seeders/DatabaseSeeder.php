<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder - Main seeder for Connected Capacity 2.1
 *
 * Creates a complete demo environment with:
 * - 15 patients total (5 in intake queue, 10 active with care plans)
 * - InterRAI HC assessments and RUG-III/HC classifications
 * - RUG-based bundle templates (23 templates covering all categories)
 * - Care plans for active patients using the new Bundle Engine
 *
 * Run with: php artisan migrate:fresh --seed
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters - each seeder depends on data from previous seeders.
     */
    public function run(): void
    {
        $this->call([
            // ============================================
            // FOUNDATION: Users, Organizations, Config
            // ============================================

            // 1. Core users (admin, hospital, SPO admin, field staff)
            DemoSeeder::class,

            // 2. Master admin user (super admin)
            MasterUserSeeder::class,

            // 3. Service types, categories, legacy care bundles
            CoreDataSeeder::class,

            // 4. Ontario-aligned service billing rates
            ServiceRatesSeeder::class,

            // 5. Metadata object model definitions (Workday-style)
            MetadataObjectModelSeeder::class,

            // ============================================
            // CC2.1 ARCHITECTURE: RUG Templates
            // ============================================

            // 6. RUG-III/HC bundle templates (23 templates)
            RUGBundleTemplatesSeeder::class,

            // ============================================
            // GEOGRAPHIC: Regions and FSA Mappings
            // ============================================

            // 7. Toronto/GTA regions with FSA prefix mappings for travel time calculations
            RegionSeeder::class,

            // ============================================
            // SSPO ORGANIZATIONS: Secondary Service Providers
            // ============================================

            // 8. SSPO organizations with service type mappings
            // Creates: Alexis Lodge, Reconnect, Toronto Grace RCM, WellHaus
            SSPOSeeder::class,

            // ============================================
            // DEMO DATA: Patients, Assessments, Plans
            // ============================================

            // 8. Demo patients (5 queue + 10 active = 15 total) with Toronto addresses
            DemoPatientsSeeder::class,

            // 9. InterRAI assessments + RUG classifications
            DemoAssessmentsSeeder::class,

            // 10. Care plans for 10 active patients
            DemoBundlesSeeder::class,

            // 11. Patient notes and narrative summaries
            PatientNotesSeeder::class,

            // ============================================
            // WORKFORCE: Staff Roles, Employment Types, FTE Demo
            // ============================================

            // 12. Service role mappings (which roles can deliver which services)
            ServiceRoleMappingsSeeder::class,

            // 13. RUG-based service recommendations (clinically indicated services)
            RugServiceRecommendationsSeeder::class,

            // 14. Workforce metadata and demo staff for FTE compliance
            // Seeds: staff_roles, employment_types, additional staff, SSPO org,
            // past 3 weeks + current week of assignments
            SkillCatalogSeeder::class,

            // 14.5 Workforce metadata and demo staff for FTE compliance
            // Seeds: staff_roles, employment_types, additional staff, SSPO org,
            // past 3 weeks + current week of assignments
            WorkforceSeeder::class,

            // 14.6 Staff skills assignment based on role
            StaffSkillsSeeder::class,

            // ============================================
            // VISIT VERIFICATION: Jeopardy & Missed Care
            // ============================================

            // 15. Visit verification data for Jeopardy Board and Missed Care Rate
            // Creates realistic verification statuses for past 4 weeks of visits
            VisitVerificationSeeder::class,

            // 15.5 Patient satisfaction reports for staff
            // Creates feedback records for completed visits
            SatisfactionReportSeeder::class,

            // ============================================
            // QIN: Quality Improvement Notices
            // ============================================

            // 16. Demo QIN record (1 active QIN for dashboard demonstration)
            // Creates a single officially issued QIN matching the demo metrics
            QinSeeder::class,
        ]);
    }
}
