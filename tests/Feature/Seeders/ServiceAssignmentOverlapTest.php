<?php

namespace Tests\Feature\Seeders;

use App\Models\ServiceAssignment;
use App\Models\User;
use App\Services\Scheduling\SchedulingEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * ServiceAssignmentOverlapTest
 *
 * Validates that seeded data has no overlapping ServiceAssignments
 * for any staff member. This test can be run after seeding to verify
 * data integrity.
 */
class ServiceAssignmentOverlapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test SchedulingEngine hasConflicts returns true for overlapping times.
     */
    public function test_scheduling_engine_detects_overlapping_assignments()
    {
        $engine = app(SchedulingEngine::class);

        // Create staff and organization
        $org = \App\Models\ServiceProviderOrganization::factory()->create();
        $staff = User::factory()->create([
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $org->id,
            'staff_status' => User::STAFF_STATUS_ACTIVE,
        ]);

        // Create patient and care plan
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = \App\Models\Patient::factory()->create([
            'user_id' => $patientUser->id,
            'status' => 'Active',
        ]);
        $carePlan = \App\Models\CarePlan::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'active',
        ]);
        $serviceType = \App\Models\ServiceType::factory()->create();

        // Create an assignment 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $carePlan->id,
            'patient_id' => $patient->id,
            'service_type_id' => $serviceType->id,
            'assigned_user_id' => $staff->id,
            'service_provider_organization_id' => $org->id,
            'scheduled_start' => now()->startOfWeek()->addDay()->setTime(9, 0),
            'scheduled_end' => now()->startOfWeek()->addDay()->setTime(10, 0),
            'status' => ServiceAssignment::STATUS_PLANNED,
        ]);

        // Test overlapping scenarios
        $startDate = now()->startOfWeek()->addDay();

        // Case 1: 09:30-10:30 overlaps with 09:00-10:00
        $this->assertTrue(
            $engine->hasConflicts(
                $staff->id,
                $startDate->copy()->setTime(9, 30),
                $startDate->copy()->setTime(10, 30)
            ),
            'Should detect overlap when new starts inside existing'
        );

        // Case 2: 08:30-09:15 overlaps with 09:00-10:00
        $this->assertTrue(
            $engine->hasConflicts(
                $staff->id,
                $startDate->copy()->setTime(8, 30),
                $startDate->copy()->setTime(9, 15)
            ),
            'Should detect overlap when new ends inside existing'
        );

        // Case 3: 08:30-10:30 completely contains 09:00-10:00
        $this->assertTrue(
            $engine->hasConflicts(
                $staff->id,
                $startDate->copy()->setTime(8, 30),
                $startDate->copy()->setTime(10, 30)
            ),
            'Should detect overlap when new completely contains existing'
        );

        // Case 4: 09:15-09:45 is completely inside 09:00-10:00
        $this->assertTrue(
            $engine->hasConflicts(
                $staff->id,
                $startDate->copy()->setTime(9, 15),
                $startDate->copy()->setTime(9, 45)
            ),
            'Should detect overlap when new is completely inside existing'
        );

        // Case 5: 10:00-11:00 does NOT overlap (starts exactly when other ends)
        $this->assertFalse(
            $engine->hasConflicts(
                $staff->id,
                $startDate->copy()->setTime(10, 0),
                $startDate->copy()->setTime(11, 0)
            ),
            'Should NOT detect overlap when new starts exactly at existing end'
        );

        // Case 6: 08:00-09:00 does NOT overlap (ends exactly when other starts)
        $this->assertFalse(
            $engine->hasConflicts(
                $staff->id,
                $startDate->copy()->setTime(8, 0),
                $startDate->copy()->setTime(9, 0)
            ),
            'Should NOT detect overlap when new ends exactly at existing start'
        );
    }

    /**
     * Test SchedulingEngine excludes cancelled assignments from conflict check.
     */
    public function test_scheduling_engine_ignores_cancelled_assignments()
    {
        $engine = app(SchedulingEngine::class);

        // Create staff and organization
        $org = \App\Models\ServiceProviderOrganization::factory()->create();
        $staff = User::factory()->create([
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $org->id,
            'staff_status' => User::STAFF_STATUS_ACTIVE,
        ]);

        // Create patient and care plan
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = \App\Models\Patient::factory()->create([
            'user_id' => $patientUser->id,
            'status' => 'Active',
        ]);
        $carePlan = \App\Models\CarePlan::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'active',
        ]);
        $serviceType = \App\Models\ServiceType::factory()->create();

        $startDate = now()->startOfWeek()->addDay();

        // Create a CANCELLED assignment 09:00-10:00
        ServiceAssignment::create([
            'care_plan_id' => $carePlan->id,
            'patient_id' => $patient->id,
            'service_type_id' => $serviceType->id,
            'assigned_user_id' => $staff->id,
            'service_provider_organization_id' => $org->id,
            'scheduled_start' => $startDate->copy()->setTime(9, 0),
            'scheduled_end' => $startDate->copy()->setTime(10, 0),
            'status' => ServiceAssignment::STATUS_CANCELLED,
        ]);

        // Should NOT detect conflict because the existing is cancelled
        $this->assertFalse(
            $engine->hasConflicts(
                $staff->id,
                $startDate->copy()->setTime(9, 0),
                $startDate->copy()->setTime(10, 0)
            ),
            'Should NOT detect conflict with cancelled assignment'
        );
    }

    /**
     * Test SchedulingEngine can exclude specific assignment ID from conflict check.
     */
    public function test_scheduling_engine_can_exclude_assignment_from_conflict_check()
    {
        $engine = app(SchedulingEngine::class);

        // Create staff and organization
        $org = \App\Models\ServiceProviderOrganization::factory()->create();
        $staff = User::factory()->create([
            'role' => User::ROLE_FIELD_STAFF,
            'organization_id' => $org->id,
            'staff_status' => User::STAFF_STATUS_ACTIVE,
        ]);

        // Create patient and care plan
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = \App\Models\Patient::factory()->create([
            'user_id' => $patientUser->id,
            'status' => 'Active',
        ]);
        $carePlan = \App\Models\CarePlan::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'active',
        ]);
        $serviceType = \App\Models\ServiceType::factory()->create();

        $startDate = now()->startOfWeek()->addDay();

        // Create an assignment 09:00-10:00
        $assignment = ServiceAssignment::create([
            'care_plan_id' => $carePlan->id,
            'patient_id' => $patient->id,
            'service_type_id' => $serviceType->id,
            'assigned_user_id' => $staff->id,
            'service_provider_organization_id' => $org->id,
            'scheduled_start' => $startDate->copy()->setTime(9, 0),
            'scheduled_end' => $startDate->copy()->setTime(10, 0),
            'status' => ServiceAssignment::STATUS_PLANNED,
        ]);

        // Should detect conflict when not excluding itself
        $this->assertTrue(
            $engine->hasConflicts(
                $staff->id,
                $startDate->copy()->setTime(9, 0),
                $startDate->copy()->setTime(10, 0)
            ),
            'Should detect conflict when not excluding itself'
        );

        // Should NOT detect conflict when excluding itself
        $this->assertFalse(
            $engine->hasConflicts(
                $staff->id,
                $startDate->copy()->setTime(9, 0),
                $startDate->copy()->setTime(10, 0),
                $assignment->id
            ),
            'Should NOT detect conflict when excluding itself'
        );
    }

    /**
     * Utility function to check for overlaps in a collection of assignments.
     * Can be used after seeding to validate data integrity.
     */
    public static function findOverlappingAssignments(): Collection
    {
        $overlaps = collect();

        // Get all active staff with assignments
        $staffWithAssignments = ServiceAssignment::query()
            ->whereNotIn('status', [
                ServiceAssignment::STATUS_CANCELLED,
                ServiceAssignment::STATUS_MISSED,
            ])
            ->distinct('assigned_user_id')
            ->pluck('assigned_user_id');

        foreach ($staffWithAssignments as $staffId) {
            // Get all non-cancelled/missed assignments for this staff, ordered by start time
            $assignments = ServiceAssignment::where('assigned_user_id', $staffId)
                ->whereNotIn('status', [
                    ServiceAssignment::STATUS_CANCELLED,
                    ServiceAssignment::STATUS_MISSED,
                ])
                ->orderBy('scheduled_start')
                ->get();

            // Check each pair for overlaps
            for ($i = 0; $i < $assignments->count() - 1; $i++) {
                $current = $assignments[$i];
                $next = $assignments[$i + 1];

                // Skip if on different days
                if ($current->scheduled_start->toDateString() !== $next->scheduled_start->toDateString()) {
                    continue;
                }

                // Check if current end overlaps with next start
                if ($current->scheduled_end > $next->scheduled_start) {
                    $overlaps->push([
                        'staff_id' => $staffId,
                        'staff_name' => $current->assignedUser?->name ?? 'Unknown',
                        'date' => $current->scheduled_start->toDateString(),
                        'assignment_1' => [
                            'id' => $current->id,
                            'start' => $current->scheduled_start->format('H:i'),
                            'end' => $current->scheduled_end->format('H:i'),
                            'service' => $current->serviceType?->name ?? 'Unknown',
                        ],
                        'assignment_2' => [
                            'id' => $next->id,
                            'start' => $next->scheduled_start->format('H:i'),
                            'end' => $next->scheduled_end->format('H:i'),
                            'service' => $next->serviceType?->name ?? 'Unknown',
                        ],
                    ]);
                }
            }
        }

        return $overlaps;
    }
}
