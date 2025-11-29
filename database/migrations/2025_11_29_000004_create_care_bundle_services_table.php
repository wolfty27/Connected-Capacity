<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_bundle_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_bundle_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_type_id')->constrained()->onDelete('cascade');

            // For 'weekly' mode: hours required per week
            $table->decimal('hours_per_week', 5, 2)->nullable();

            // For 'fixed_visits' mode: visits required per care plan (usually matches service_type setting)
            $table->integer('visits_per_plan')->nullable();

            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['care_bundle_template_id', 'service_type_id'], 'bundle_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_bundle_services');
    }
};
