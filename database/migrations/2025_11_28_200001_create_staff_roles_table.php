<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WORKFORCE-001: Staff Roles Metadata Table
 *
 * Creates metadata-driven staff roles (RN, RPN, PSW, OT, PT, SLP, SW, etc.)
 * that align with AlayaCare's discipline concepts and Ontario Health atHome
 * service delivery requirements.
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Role code: RN, RPN, PSW, OT, PT, SLP, SW, etc.');
            $table->string('name', 100)->comment('Full name: Registered Nurse, Personal Support Worker, etc.');
            $table->string('category', 50)->nullable()->comment('Category: nursing, allied_health, personal_support, administrative');
            $table->text('description')->nullable();

            // Service type relationships (which services this role can deliver)
            $table->json('service_type_codes')->nullable()->comment('Array of service type codes this role can deliver');

            // Regulatory and compliance
            $table->boolean('is_regulated')->default(false)->comment('Whether this is a regulated health profession');
            $table->string('regulatory_body', 100)->nullable()->comment('E.g., CNO for RN/RPN, College of PT, etc.');
            $table->boolean('requires_license')->default(false);
            $table->integer('default_hourly_rate_cents')->nullable()->comment('Default wage rate in cents');

            // Billing and reporting
            $table->string('billing_code', 20)->nullable()->comment('Ontario Health billing code if applicable');
            $table->boolean('counts_for_fte')->default(true)->comment('Whether this role counts in FTE calculations');

            // Display and ordering
            $table->integer('sort_order')->default(100);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
            $table->index(['is_active', 'counts_for_fte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_roles');
    }
};
