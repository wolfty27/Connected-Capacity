<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * STAFF-011: Create service_type_skills pivot table for skill requirements
     */
    public function up(): void
    {
        Schema::create('service_type_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true); // Required vs preferred
            $table->enum('minimum_proficiency', [
                'basic',
                'competent',
                'proficient',
                'expert'
            ])->default('competent');
            $table->timestamps();

            $table->unique(['service_type_id', 'skill_id']);
            $table->index(['service_type_id', 'is_required']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_type_skills');
    }
};
