<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_bundle_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "RB0 - Rehab Bundle High"
            $table->string('rug_group', 10)->nullable(); // RB0, RA2, SE3, etc.
            $table->string('category')->nullable(); // Rehab, Special Care, Clinically Complex, etc.
            $table->text('description')->nullable();
            $table->integer('weekly_cost_cents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_bundle_templates');
    }
};
