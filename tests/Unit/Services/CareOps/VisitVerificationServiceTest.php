<?php

namespace Tests\Unit\Services\CareOps;

use App\Models\ServiceAssignment;
use App\Models\User;
use App\Services\CareOps\VisitVerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected VisitVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VisitVerificationService();
    }

    public function test_isOverdue_returns_true_for_old_pending_visit(): void
    {
        $assignment = ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'scheduled_start' => Carbon::now()->subHours(48), // 2 days ago
        ]);

        $this->assertTrue($this->service->isOverdue($assignment));
    }

    public function test_isOverdue_returns_false_for_recent_pending_visit(): void
    {
        $assignment = ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'scheduled_start' => Carbon::now()->subHours(12), // 12 hours ago (within 24h grace)
        ]);

        $this->assertFalse($this->service->isOverdue($assignment));
    }

    public function test_isOverdue_returns_false_for_verified_visit(): void
    {
        $assignment = ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'scheduled_start' => Carbon::now()->subDays(3),
            'verified_at' => Carbon::now()->subDays(2),
        ]);

        $this->assertFalse($this->service->isOverdue($assignment));
    }

    public function test_markVerified_updates_verification_status(): void
    {
        $user = User::factory()->create();
        $assignment = ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
        ]);

        $result = $this->service->markVerified($assignment, $user);

        $this->assertEquals(ServiceAssignment::VERIFICATION_VERIFIED, $result->verification_status);
        $this->assertNotNull($result->verified_at);
        $this->assertEquals($user->id, $result->verified_by_user_id);
        $this->assertEquals(ServiceAssignment::VERIFICATION_SOURCE_STAFF_MANUAL, $result->verification_source);
    }

    public function test_markVerified_accepts_custom_source(): void
    {
        $assignment = ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
        ]);

        $result = $this->service->markVerified(
            $assignment,
            null,
            null,
            ServiceAssignment::VERIFICATION_SOURCE_DEVICE
        );

        $this->assertEquals(ServiceAssignment::VERIFICATION_SOURCE_DEVICE, $result->verification_source);
    }

    public function test_markMissed_updates_verification_status_and_status(): void
    {
        $user = User::factory()->create();
        $assignment = ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'status' => ServiceAssignment::STATUS_PLANNED,
        ]);

        $result = $this->service->markMissed($assignment, $user);

        $this->assertEquals(ServiceAssignment::VERIFICATION_MISSED, $result->verification_status);
        $this->assertEquals(ServiceAssignment::STATUS_MISSED, $result->status);
        $this->assertNotNull($result->verified_at);
    }

    public function test_getOverdueAssignments_returns_only_overdue(): void
    {
        // Create overdue assignment (48 hours ago)
        $overdue = ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'scheduled_start' => Carbon::now()->subHours(48),
        ]);

        // Create recent assignment (12 hours ago - within grace period)
        ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'scheduled_start' => Carbon::now()->subHours(12),
        ]);

        // Create verified assignment
        ServiceAssignment::factory()->create([
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'scheduled_start' => Carbon::now()->subHours(48),
            'verified_at' => Carbon::now()->subHours(24),
        ]);

        $result = $this->service->getOverdueAssignments();

        $this->assertCount(1, $result);
        $this->assertEquals($overdue->id, $result->first()->id);
    }

    public function test_getVerificationStats_calculates_correctly(): void
    {
        $orgId = 1;
        $startDate = Carbon::now()->subWeek();

        // Create 10 verified assignments
        ServiceAssignment::factory()->count(10)->create([
            'service_provider_organization_id' => $orgId,
            'verification_status' => ServiceAssignment::VERIFICATION_VERIFIED,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        // Create 2 missed assignments
        ServiceAssignment::factory()->count(2)->create([
            'service_provider_organization_id' => $orgId,
            'verification_status' => ServiceAssignment::VERIFICATION_MISSED,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        // Create 3 pending assignments
        ServiceAssignment::factory()->count(3)->create([
            'service_provider_organization_id' => $orgId,
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
            'scheduled_start' => Carbon::now()->subDays(3),
        ]);

        $stats = $this->service->getVerificationStats($orgId, $startDate, Carbon::now());

        $this->assertEquals(15, $stats['total_appointments']);
        $this->assertEquals(10, $stats['verified']);
        $this->assertEquals(2, $stats['missed']);
        $this->assertEquals(3, $stats['pending']);
        $this->assertEquals(66.7, $stats['verification_rate']); // 10/15 * 100
        $this->assertEquals(13.33, $stats['missed_rate']); // 2/15 * 100
        $this->assertFalse($stats['is_compliant']); // missed_rate > 0
    }

    public function test_bulkVerify_updates_multiple_assignments(): void
    {
        $assignments = ServiceAssignment::factory()->count(5)->create([
            'verification_status' => ServiceAssignment::VERIFICATION_PENDING,
        ]);

        $ids = $assignments->pluck('id')->toArray();
        $user = User::factory()->create();

        $count = $this->service->bulkVerify($ids, $user);

        $this->assertEquals(5, $count);

        foreach ($ids as $id) {
            $assignment = ServiceAssignment::find($id);
            $this->assertEquals(ServiceAssignment::VERIFICATION_VERIFIED, $assignment->verification_status);
        }
    }
}
