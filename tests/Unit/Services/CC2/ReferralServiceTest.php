<?php

namespace Tests\Unit\Services\CC2;

use App\Models\Patient;
use App\Models\Referral;
use App\Models\ServiceProviderOrganization;
use App\Models\User;
use App\Services\CC2\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReferralService();
    }

    public function test_create_referral_defaults_org_and_metadata(): void
    {
        $org = $this->makeOrganization('se-health');
        $user = User::factory()->create([
            'organization_id' => $org->id,
        ]);
        $patient = Patient::factory()->create();

        $referral = $this->service->createReferral([
            'patient_id' => $patient->id,
            'intake_notes' => 'Needs urgent support',
        ], $user);

        $this->assertEquals($org->id, $referral->service_provider_organization_id);
        $this->assertEquals($user->id, $referral->submitted_by);
        $this->assertEquals(Referral::STATUS_SUBMITTED, $referral->status);
        $this->assertEquals('manual', $referral->source);
        $this->assertSame([], $referral->metadata);
    }

    public function test_create_referral_respects_explicit_org(): void
    {
        $userOrg = $this->makeOrganization('default-org');
        $payloadOrg = $this->makeOrganization('payload-org');
        $user = User::factory()->create([
            'organization_id' => $userOrg->id,
        ]);
        $patient = Patient::factory()->create();

        $referral = $this->service->createReferral([
            'patient_id' => $patient->id,
            'service_provider_organization_id' => $payloadOrg->id,
        ], $user);

        $this->assertEquals($payloadOrg->id, $referral->service_provider_organization_id);
    }

    public function test_list_for_user_without_org_returns_all_referrals(): void
    {
        $patient = Patient::factory()->create();
        $orgA = $this->makeOrganization('org-a');
        $orgB = $this->makeOrganization('org-b');

        Referral::factory()->create([
            'patient_id' => $patient->id,
            'service_provider_organization_id' => null,
        ]);
        Referral::factory()->create([
            'patient_id' => $patient->id,
            'service_provider_organization_id' => $orgA->id,
        ]);
        Referral::factory()->create([
            'patient_id' => $patient->id,
            'service_provider_organization_id' => $orgB->id,
        ]);

        $user = User::factory()->create(['organization_id' => null]);

        $paginator = $this->service->listForUser($user, 10);
        $this->assertCount(3, $paginator->items());
    }

    public function test_list_for_user_with_org_filters_by_org_or_null(): void
    {
        $patient = Patient::factory()->create();
        $orgA = $this->makeOrganization('org-a');
        $orgB = $this->makeOrganization('org-b');

        $matches = [
            Referral::factory()->create([
                'patient_id' => $patient->id,
                'service_provider_organization_id' => null,
            ]),
            Referral::factory()->create([
                'patient_id' => $patient->id,
                'service_provider_organization_id' => $orgA->id,
            ]),
        ];

        Referral::factory()->create([
            'patient_id' => $patient->id,
            'service_provider_organization_id' => $orgB->id,
        ]);

        $user = User::factory()->create(['organization_id' => $orgA->id]);

        $paginator = $this->service->listForUser($user, 10);
        $resultIds = collect($paginator->items())->pluck('id')->sort()->values();

        $this->assertEquals(
            collect($matches)->pluck('id')->sort()->values(),
            $resultIds
        );
    }

    private function makeOrganization(string $slug): ServiceProviderOrganization
    {
        return ServiceProviderOrganization::create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'type' => 'se_health',
            'contact_email' => "{$slug}@example.test",
            'active' => true,
        ]);
    }
}
