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
        Schema::create('transition_needs_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->json('clinical_flags')->nullable();
            $table->text('narrative_summary')->nullable();
            $table->string('status')->default('draft'); // draft, pending_review, completed
            $table->unsignedBigInteger('bundle_recommendation_id')->nullable(); // Placeholder for future relationship
            $table->string('ai_summary_status')->default('pending'); // pending, processing, completed, failed
            $table->text('ai_summary_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transition_needs_profiles');
    }
};