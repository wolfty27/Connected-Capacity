<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the qin_records table for storing officially issued Quality Improvement Notices.
 *
 * QINs are issued by Ontario Health at Home (OHaH) when an SPO breaches performance
 * band thresholds on key indicators (Schedule 4 metrics).
 *
 * This supports both:
 * - Webhook ingestion from OHaH (future integration)
 * - Manual/seeded entries for demo purposes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qin_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                  ->constrained('service_provider_organizations')
                  ->onDelete('cascade');

            // QIN identification
            $table->string('qin_number')->unique()->comment('OHaH-assigned QIN ID, e.g. QIN-2025-001');

            // Breach details
            $table->string('indicator')->comment('Performance indicator name, e.g. Referral Acceptance Rate');
            $table->string('band_breach')->comment('Band and threshold breached, e.g. Band C (<95%)');
            
            // Metric evidence
            $table->decimal('metric_value', 8, 2)->nullable()->comment('Actual metric value at time of breach');
            $table->timestamp('evidence_period_start')->nullable()->comment('Start of reporting period for breach');
            $table->timestamp('evidence_period_end')->nullable()->comment('End of reporting period for breach');
            $table->foreignId('evidence_service_assignment_id')
                  ->nullable()
                  ->constrained('service_assignments')
                  ->nullOnDelete()
                  ->comment('Optional: specific assignment that caused breach');

            // Dates
            $table->date('issued_date')->comment('Date QIN was issued by OHaH');
            $table->date('qip_due_date')->nullable()->comment('Deadline for QIP submission');
            $table->timestamp('closed_at')->nullable()->comment('When QIN was resolved/closed');

            // Status workflow: open -> submitted -> under_review -> closed
            $table->enum('status', ['open', 'submitted', 'under_review', 'closed'])
                  ->default('open')
                  ->comment('Current QIN status');

            // OHaH contact and notes
            $table->string('ohah_contact')->nullable()->comment('OHaH representative name');
            $table->text('notes')->nullable()->comment('Internal notes or context');

            // Source tracking
            $table->enum('source', ['ohah_webhook', 'manual', 'seeded'])
                  ->default('manual')
                  ->comment('How this QIN was created');

            $table->timestamps();

            // Indexes for common queries
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'issued_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qin_records');
    }
};
