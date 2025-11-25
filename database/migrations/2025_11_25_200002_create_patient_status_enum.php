<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DQ-002: Create patient status enum for consistent status tracking
 *
 * This migration standardizes patient status values across the system
 * to align with OHaH workflow requirements:
 *
 * Status Lifecycle:
 * - referral_received: HPG referral received, pending intake
 * - intake_pending: In intake queue, awaiting triage
 * - triage_in_progress: Being triaged by coordinator
 * - triage_complete: Triage done, awaiting TNP
 * - assessment_pending: Awaiting InterRAI or other assessments
 * - bundle_building: Care bundle being constructed
 * - bundle_pending_approval: Bundle awaiting clinical approval
 * - active: Active patient receiving care
 * - on_hold: Temporarily paused (hospitalization, etc.)
 * - discharged: Discharged from program
 * - deceased: Patient deceased
 * - transferred: Transferred to another SPO
 *
 * Also creates lookup table for status metadata.
 */
return new class extends Migration
{
    /**
     * The valid patient statuses for CC2.
     */
    protected array $statuses = [
        'referral_received' => [
            'display_name' => 'Referral Received',
            'description' => 'HPG referral received, pending intake processing',
            'category' => 'intake',
            'sort_order' => 10,
            'color' => '#94a3b8', // slate-400
        ],
        'intake_pending' => [
            'display_name' => 'Intake Pending',
            'description' => 'In intake queue, awaiting triage',
            'category' => 'intake',
            'sort_order' => 20,
            'color' => '#fbbf24', // amber-400
        ],
        'triage_in_progress' => [
            'display_name' => 'Triage In Progress',
            'description' => 'Being triaged by coordinator',
            'category' => 'intake',
            'sort_order' => 30,
            'color' => '#60a5fa', // blue-400
        ],
        'triage_complete' => [
            'display_name' => 'Triage Complete',
            'description' => 'Triage completed, awaiting TNP assessment',
            'category' => 'intake',
            'sort_order' => 40,
            'color' => '#34d399', // emerald-400
        ],
        'assessment_pending' => [
            'display_name' => 'Assessment Pending',
            'description' => 'Awaiting InterRAI or other clinical assessments',
            'category' => 'assessment',
            'sort_order' => 50,
            'color' => '#a78bfa', // violet-400
        ],
        'bundle_building' => [
            'display_name' => 'Bundle Building',
            'description' => 'Care bundle being constructed',
            'category' => 'planning',
            'sort_order' => 60,
            'color' => '#f472b6', // pink-400
        ],
        'bundle_pending_approval' => [
            'display_name' => 'Pending Approval',
            'description' => 'Care bundle awaiting clinical approval',
            'category' => 'planning',
            'sort_order' => 70,
            'color' => '#fb923c', // orange-400
        ],
        'active' => [
            'display_name' => 'Active',
            'description' => 'Active patient receiving care services',
            'category' => 'active',
            'sort_order' => 80,
            'color' => '#22c55e', // green-500
        ],
        'on_hold' => [
            'display_name' => 'On Hold',
            'description' => 'Care temporarily paused (hospitalization, respite)',
            'category' => 'active',
            'sort_order' => 85,
            'color' => '#eab308', // yellow-500
        ],
        'discharged' => [
            'display_name' => 'Discharged',
            'description' => 'Discharged from home care program',
            'category' => 'closed',
            'sort_order' => 90,
            'color' => '#6b7280', // gray-500
        ],
        'deceased' => [
            'display_name' => 'Deceased',
            'description' => 'Patient deceased',
            'category' => 'closed',
            'sort_order' => 95,
            'color' => '#374151', // gray-700
        ],
        'transferred' => [
            'display_name' => 'Transferred',
            'description' => 'Transferred to another service provider',
            'category' => 'closed',
            'sort_order' => 100,
            'color' => '#9ca3af', // gray-400
        ],
    ];

    public function up(): void
    {
        // 1. Create patient_statuses lookup table
        Schema::create('patient_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            $table->string('category', 50); // intake, assessment, planning, active, closed
            $table->integer('sort_order')->default(0);
            $table->string('color', 20)->nullable(); // Hex color for UI
            $table->boolean('is_active')->default(true);
            $table->boolean('allows_care_delivery')->default(false);
            $table->boolean('counts_in_census')->default(false);
            $table->json('valid_transitions')->nullable(); // Array of status codes this can transition to
            $table->timestamps();
        });

        // 2. Seed the statuses
        foreach ($this->statuses as $code => $data) {
            $allowsCare = in_array($code, ['active', 'on_hold']);
            $countsInCensus = in_array($code, ['active', 'on_hold', 'bundle_building', 'bundle_pending_approval']);

            DB::table('patient_statuses')->insert([
                'code' => $code,
                'display_name' => $data['display_name'],
                'description' => $data['description'],
                'category' => $data['category'],
                'sort_order' => $data['sort_order'],
                'color' => $data['color'],
                'is_active' => true,
                'allows_care_delivery' => $allowsCare,
                'counts_in_census' => $countsInCensus,
                'valid_transitions' => json_encode($this->getValidTransitions($code)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Create patient_status_transitions table for audit trail
        Schema::create('patient_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->json('context')->nullable(); // Additional context data
            $table->timestamps();

            $table->index(['patient_id', 'created_at'], 'patient_status_history_idx');
            $table->index(['to_status', 'created_at'], 'patient_status_to_idx');
        });

        // 4. Migrate existing status values to new format
        $this->migrateExistingStatuses();
    }

    public function down(): void
    {
        // Restore old statuses before dropping tables
        DB::statement("UPDATE patients SET status = 'Active' WHERE status IN ('active', 'on_hold')");
        DB::statement("UPDATE patients SET status = 'Inactive' WHERE status NOT IN ('Active', 'active', 'on_hold')");

        Schema::dropIfExists('patient_status_transitions');
        Schema::dropIfExists('patient_statuses');
    }

    /**
     * Get valid transitions for a given status.
     */
    protected function getValidTransitions(string $status): array
    {
        return match ($status) {
            'referral_received' => ['intake_pending', 'triage_in_progress'],
            'intake_pending' => ['triage_in_progress', 'discharged'],
            'triage_in_progress' => ['triage_complete', 'intake_pending'],
            'triage_complete' => ['assessment_pending', 'bundle_building'],
            'assessment_pending' => ['bundle_building', 'triage_complete'],
            'bundle_building' => ['bundle_pending_approval', 'assessment_pending'],
            'bundle_pending_approval' => ['active', 'bundle_building'],
            'active' => ['on_hold', 'discharged', 'deceased', 'transferred'],
            'on_hold' => ['active', 'discharged', 'deceased', 'transferred'],
            'discharged' => ['referral_received'], // Re-admission
            'deceased' => [],
            'transferred' => ['referral_received'], // Re-referral from new SPO
            default => [],
        };
    }

    /**
     * Migrate existing patient statuses to new enum values.
     */
    protected function migrateExistingStatuses(): void
    {
        // Map old statuses to new ones
        $mapping = [
            'Active' => 'active',
            'Inactive' => 'discharged',
            'New' => 'referral_received',
            'Pending' => 'intake_pending',
            'In Progress' => 'triage_in_progress',
            'Completed' => 'active',
            // Handle any numeric statuses from very old data
            '1' => 'active',
            '0' => 'discharged',
        ];

        foreach ($mapping as $old => $new) {
            DB::statement("UPDATE patients SET status = ? WHERE status = ?", [$new, $old]);
        }

        // Set any unknown statuses to intake_pending for review
        DB::statement(
            "UPDATE patients SET status = 'intake_pending' WHERE status NOT IN (?)",
            [implode(',', array_keys($this->statuses))]
        );

        // Create initial transition records for audit trail
        DB::statement("
            INSERT INTO patient_status_transitions (patient_id, from_status, to_status, reason, created_at, updated_at)
            SELECT id, NULL, status, 'Initial migration from legacy status', NOW(), NOW()
            FROM patients
        ");
    }
};
