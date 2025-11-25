<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            // Core demo users (admin, hospital staff, etc.)
            DemoSeeder::class,
            // Metadata object model definitions
            MetadataObjectModelSeeder::class,
            // Patient workflow test data with proper names and queue statuses
            QueueWorkflowSeeder::class,
        ]);
    }
}
