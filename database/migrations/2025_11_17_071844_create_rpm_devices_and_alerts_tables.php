<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRpmDevicesAndAlertsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('rpm_devices')) {
            Schema::create('rpm_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('service_provider_organization_id')->constrained('service_provider_organizations')->cascadeOnDelete();
                $table->string('device_type');
                $table->string('manufacturer')->nullable();
                $table->string('model')->nullable();
                $table->string('serial_number')->unique();
                $table->timestamp('assigned_at');
                $table->timestamp('returned_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['patient_id', 'device_type'], 'rpm_devices_patient_device_idx');
            });
        }

        if (!Schema::hasTable('rpm_alerts')) {
            Schema::create('rpm_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('rpm_device_id')->nullable()->constrained('rpm_devices')->nullOnDelete();
                $table->foreignId('service_assignment_id')->nullable()->constrained('service_assignments')->nullOnDelete();
                $table->string('event_type');
                $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
                $table->json('payload');
                $table->timestamp('triggered_at');
                $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('handled_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->enum('status', ['open', 'in_progress', 'resolved', 'escalated'])->default('open');
                $table->string('source_reference')->nullable();
                $table->timestamps();

                $table->index(['status', 'severity'], 'rpm_alerts_status_severity_idx');
                $table->index(['patient_id', 'triggered_at'], 'rpm_alerts_patient_triggered_idx');
                $table->index('service_assignment_id');
            });
        }

        if (Schema::hasTable('service_assignments') && Schema::hasColumn('service_assignments', 'rpm_alert_id')) {
            Schema::table('service_assignments', function (Blueprint $table) {
                $table->foreign('rpm_alert_id')->references('id')->on('rpm_alerts')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('service_assignments') && Schema::hasColumn('service_assignments', 'rpm_alert_id')) {
            Schema::table('service_assignments', function (Blueprint $table) {
                $table->dropForeign(['rpm_alert_id']);
            });
        }

        if (Schema::hasTable('rpm_alerts')) {
            Schema::dropIfExists('rpm_alerts');
        }

        if (Schema::hasTable('rpm_devices')) {
            Schema::dropIfExists('rpm_devices');
        }
    }
}
