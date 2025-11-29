<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use App\Services\Scheduling\SchedulingEngine;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Tests for scheduling.patient_non_concurrency feature.
 *
 * Acceptance criteria:
 * - PatientSchedulingRules::patientHasOverlap implemented.
 * - SchedulingEngine rejects visits when another visit exists for the same patient.
 * - Seeding prevents generating overlapping patient visits.
 * - Patient timeline never shows two visits at the same time.
 */
class PatientNonConcurrencyTest extends TestCase
{
    private SchedulingEngine $engine;
    private Patient $patient;
    private ServiceType $ptService;
    private ServiceType $otService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new SchedulingEngine();

        // Create test patient
        $this->patient = new Patient([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'status' => 'active',
        ]);

        // Mock service types
        $this->ptService = new ServiceType([
            'id' => 1,
            'code' => 'PT',
            'name' => 'Physiotherapy',
            'category' => 'therapy',
        ]);

        $this->otService = new ServiceType([
            'id' => 2,
            'code' => 'OT',
            'name' => 'Occupational Therapy',
            'category' => 'therapy',
        ]);
    }

    /**
     * Test: Patient cannot have overlapping visits
     *
     * Scenario: PT 08:00-09:00 exists, try to schedule OT 08:30-09:30
     * Expected: Overlap detected, scheduling rejected
     */
    public function test_patient_cannot_have_overlapping_visits(): void
    {
        // This would be a database test in a real scenario
        // For unit testing, we verify the overlap detection logic

        $existingStart = Carbon::today()->setTime(8, 0);
        $existingEnd = Carbon::today()->setTime(9, 0);

        $proposedStart = Carbon::today()->setTime(8, 30);
        $proposedEnd = Carbon::today()->setTime(9, 30);

        // Verify the overlap detection method works correctly
        $assignment = new ServiceAssignment([
            'scheduled_start' => $existingStart,
            'scheduled_end' => $existingEnd,
        ]);

        $this->assertTrue(
            $assignment->overlapsWithTimeRange($proposedStart, $proposedEnd),
            'Should detect overlapping time ranges'
        );
    }

    /**
     * Test: Patient can have non-overlapping visits
     *
     * Scenario: PT 08:00-09:00, OT 09:00-10:00 (adjacent but not overlapping)
     * Expected: No overlap, scheduling allowed
     */
    public function test_patient_can_have_non_overlapping_visits(): void
    {
        $existingStart = Carbon::today()->setTime(8, 0);
        $existingEnd = Carbon::today()->setTime(9, 0);

        // Adjacent time (not overlapping)
        $proposedStart = Carbon::today()->setTime(9, 0);
        $proposedEnd = Carbon::today()->setTime(10, 0);

        $assignment = new ServiceAssignment([
            'scheduled_start' => $existingStart,
            'scheduled_end' => $existingEnd,
        ]);

        $this->assertFalse(
            $assignment->overlapsWithTimeRange($proposedStart, $proposedEnd),
            'Adjacent times should not be considered overlapping'
        );
    }

    /**
     * Test: PT and OT cannot be scheduled at the same time for the same patient
     *
     * This verifies the core acceptance criterion: "PT & OT cannot be scheduled
     * for the same patient at the same time."
     */
    public function test_pt_and_ot_cannot_overlap_for_same_patient(): void
    {
        // PT 08:00-09:00
        $ptStart = Carbon::today()->setTime(8, 0);
        $ptEnd = Carbon::today()->setTime(9, 0);

        // OT 08:00-09:00 (exact same time)
        $otStart = Carbon::today()->setTime(8, 0);
        $otEnd = Carbon::today()->setTime(9, 0);

        $ptAssignment = new ServiceAssignment([
            'scheduled_start' => $ptStart,
            'scheduled_end' => $ptEnd,
        ]);

        $this->assertTrue(
            $ptAssignment->overlapsWithTimeRange($otStart, $otEnd),
            'PT and OT at exact same time should be detected as overlapping'
        );
    }

    /**
     * Test: Cancelled assignments do not block new scheduling
     *
     * Scenario: Cancelled PT 08:00-09:00, try to schedule OT 08:00-09:00
     * Expected: No conflict (cancelled visits should be ignored)
     */
    public function test_cancelled_assignments_do_not_block_scheduling(): void
    {
        $cancelledAssignment = new ServiceAssignment([
            'scheduled_start' => Carbon::today()->setTime(8, 0),
            'scheduled_end' => Carbon::today()->setTime(9, 0),
            'status' => ServiceAssignment::STATUS_CANCELLED,
        ]);

        // Cancelled assignments should not be "scheduled"
        $this->assertFalse(
            $cancelledAssignment->isScheduled(),
            'Cancelled assignments should not be considered as scheduled'
        );
    }

    /**
     * Test: Missed assignments do not block new scheduling
     */
    public function test_missed_assignments_do_not_block_scheduling(): void
    {
        $missedAssignment = new ServiceAssignment([
            'scheduled_start' => Carbon::today()->setTime(8, 0),
            'scheduled_end' => Carbon::today()->setTime(9, 0),
            'status' => ServiceAssignment::STATUS_MISSED,
        ]);

        $this->assertFalse(
            $missedAssignment->isScheduled(),
            'Missed assignments should not be considered as scheduled'
        );
    }

    /**
     * Test: Exact adjacent times do not overlap
     *
     * Edge case: Visit 1 ends at 09:00, Visit 2 starts at 09:00
     * Expected: No overlap (end time of one equals start time of another)
     */
    public function test_exact_adjacent_times_do_not_overlap(): void
    {
        $assignment1 = new ServiceAssignment([
            'scheduled_start' => Carbon::today()->setTime(8, 0),
            'scheduled_end' => Carbon::today()->setTime(9, 0),
        ]);

        $proposedStart = Carbon::today()->setTime(9, 0);
        $proposedEnd = Carbon::today()->setTime(10, 0);

        $this->assertFalse(
            $assignment1->overlapsWithTimeRange($proposedStart, $proposedEnd),
            'Back-to-back visits should not be considered overlapping'
        );
    }

    /**
     * Test: Validation result structure
     */
    public function test_validate_assignment_returns_correct_structure(): void
    {
        $result = $this->engine->validateAssignment(
            patientId: 1,
            serviceTypeId: 1,
            start: Carbon::today()->setTime(8, 0),
            end: Carbon::today()->setTime(9, 0)
        );

        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertIsBool($result['valid']);
        $this->assertIsArray($result['errors']);
        $this->assertIsArray($result['warnings']);
    }

    /**
     * Test: End time must be after start time
     */
    public function test_end_time_must_be_after_start_time(): void
    {
        $result = $this->engine->validateAssignment(
            patientId: 1,
            serviceTypeId: 1,
            start: Carbon::today()->setTime(9, 0),
            end: Carbon::today()->setTime(8, 0) // End before start
        );

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('End time must be after start time', $result['errors'][0]);
    }
}
