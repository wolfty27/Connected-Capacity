<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder - Main seeder for the metadata-object-model architecture
 *
 * This seeder sets up the complete application state using the Workday-style
 * metadata-driven architecture. All seeders here work together to create
 * a consistent data model with proper queue workflow support.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters - each seeder may depend on data from previous seeders.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            // 1. Core users (admin, hospital staff, SPO admin, field staff)
            DemoSeeder::class,

            // 2. Master admin user (super admin)
            MasterUserSeeder::class,

            // 3. Service types, care bundles, and categories (required first)
            CoreDataSeeder::class,

            // 4. Workday-style metadata object model definitions
            MetadataObjectModelSeeder::class,

            // 5. Patient workflow test data with queue statuses
            QueueWorkflowSeeder::class,

            // 6. RUG-III/HC bundle templates (CC2.1 Architecture)
            RUGBundleTemplatesSeeder::class,

            // 7. RUG demo patients with InterRAI assessments
            RugDemoSeeder::class,
        ]);
    }
}
