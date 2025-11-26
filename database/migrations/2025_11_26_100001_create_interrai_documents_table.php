<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IR-004-01: Create interrai_documents table
 *
 * Stores documents attached to InterRAI assessments including:
 * - Uploaded PDF files
 * - External IAR document ID links
 * - Other assessment attachments
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interrai_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interrai_assessment_id')
                ->constrained('interrai_assessments')
                ->onDelete('cascade');

            // Document type: 'pdf', 'external_iar_id', 'attachment'
            $table->string('document_type', 50);

            // For uploaded files
            $table->string('file_path', 500)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // For linked external IAR records
            $table->string('external_iar_id', 100)->nullable();

            // Upload tracking
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->timestamp('uploaded_at')->nullable();

            // Additional metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('document_type');
            $table->index('external_iar_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interrai_documents');
    }
};
