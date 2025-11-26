<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * STAFF-001: Create skills table for staff competency tracking
     */
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('category', [
                'clinical',           // RN-level clinical skills
                'personal_support',   // PSW-level care skills
                'specialized',        // Certifications (wound care, palliative, etc.)
                'administrative',     // Non-clinical skills
                'language'            // Language proficiencies
            ]);
            $table->text('description')->nullable();
            $table->boolean('requires_certification')->default(false);
            $table->integer('renewal_period_months')->nullable(); // null = no expiry
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('is_active');
            $table->index(['category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
