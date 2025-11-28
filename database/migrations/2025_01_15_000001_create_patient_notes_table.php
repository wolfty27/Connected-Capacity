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
        Schema::create('patient_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('author_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('source'); // e.g., "Ontario Health atHome", "SE Health"
            $table->string('note_type')->default('general'); // summary, update, contact, clinical, general
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_id', 'note_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_notes');
    }
};
