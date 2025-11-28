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
            // DEMO DATA: Patients, Assessments, Plans
            // ============================================

            // 7. Demo patients (5 queue + 10 active = 15 total)
            DemoPatientsSeeder::class,

            // 8. InterRAI assessments + RUG classifications
            DemoAssessmentsSeeder::class,

            // 9. Care plans for 10 active patients
            DemoBundlesSeeder::class,

            // 10. Patient notes and narrative summaries
            PatientNotesSeeder::class,

            // ============================================
            // WORKFORCE: Staff Roles, Employment Types, FTE Demo
            // ============================================

            // 11. Service role mappings (which roles can deliver which services)
            ServiceRoleMappingsSeeder::class,

            // 12. RUG-based service recommendations (clinically indicated services)
            RugServiceRecommendationsSeeder::class,

            // 13. Workforce metadata and demo staff for FTE compliance
            // Seeds: staff_roles, employment_types, additional staff, SSPO org,
            // past 3 weeks + current week of assignments
            WorkforceSeeder::class,

            // ============================================
            // VISIT VERIFICATION: Jeopardy & Missed Care
            // ============================================

            // 14. Visit verification data for Jeopardy Board and Missed Care Rate
            // Creates realistic verification statuses for past 4 weeks of visits
            VisitVerificationSeeder::class,
        ]);
    }
}
