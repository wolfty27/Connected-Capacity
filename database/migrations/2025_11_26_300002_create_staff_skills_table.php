<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * STAFF-002: Create staff_skills pivot table with proficiency and certification tracking
     */
    public function up(): void
    {
        Schema::create('staff_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->enum('proficiency_level', [
                'basic',        // Can perform under supervision
                'competent',    // Can perform independently
                'proficient',   // Can mentor others
                'expert'        // Subject matter expert
            ])->default('competent');
            $table->date('certified_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('verified_at')->nullable();
            $table->string('certification_number')->nullable();
            $table->string('certification_document_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'skill_id']);
            $table->index('expires_at');
            $table->index(['user_id', 'proficiency_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_skills');
    }
};
