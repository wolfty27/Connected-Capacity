<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_provider_organizations', function (Blueprint $table) {
            // SSPO-specific fields
            if (!Schema::hasColumn('service_provider_organizations', 'website_url')) {
                $table->string('website_url')->nullable()->after('slug');
            }

            if (!Schema::hasColumn('service_provider_organizations', 'logo_url')) {
                $table->string('logo_url')->nullable()->after('website_url');
            }

            if (!Schema::hasColumn('service_provider_organizations', 'cover_photo_url')) {
                $table->string('cover_photo_url')->nullable()->after('logo_url');
            }

            if (!Schema::hasColumn('service_provider_organizations', 'description')) {
                $table->text('description')->nullable()->after('cover_photo_url');
            }

            if (!Schema::hasColumn('service_provider_organizations', 'tagline')) {
                $table->string('tagline')->nullable()->after('description');
            }

            if (!Schema::hasColumn('service_provider_organizations', 'notes')) {
                $table->text('notes')->nullable()->after('tagline');
            }

            if (!Schema::hasColumn('service_provider_organizations', 'status')) {
                $table->string('status')->default('active')->after('active');
            }

            if (!Schema::hasColumn('service_provider_organizations', 'region_code')) {
                $table->string('region_code')->nullable()->after('postal_code');
            }

            if (!Schema::hasColumn('service_provider_organizations', 'capacity_metadata')) {
                $table->json('capacity_metadata')->nullable()->after('capabilities');
            }

            // Add index for status and type queries
            $table->index(['status', 'type'], 'spo_status_type_idx');
        });

        // Create pivot table for SSPO ↔ ServiceType mappings
        if (!Schema::hasTable('organization_service_types')) {
            Schema::create('organization_service_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_provider_organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_primary')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['service_provider_organization_id', 'service_type_id'], 'org_service_type_unique');
                $table->index('service_type_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_service_types');

        Schema::table('service_provider_organizations', function (Blueprint $table) {
            $table->dropIndex('spo_status_type_idx');

            $columns = [
                'website_url', 'logo_url', 'cover_photo_url', 'description',
                'tagline', 'notes', 'status', 'region_code', 'capacity_metadata'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('service_provider_organizations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
