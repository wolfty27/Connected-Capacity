<?php

namespace App\Services\CC2;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReferralService
{
    public function listForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = Referral::query()
            ->with(['patient', 'serviceType', 'organization', 'submittedBy'])
            ->latest();

        if ($user->organization_id) {
            $query->where(function ($builder) use ($user) {
                $builder
                    ->whereNull('service_provider_organization_id')
                    ->orWhere('service_provider_organization_id', $user->organization_id);
            });
        }

        return $query->paginate($perPage);
    }

    public function createReferral(array $payload, User $user): Referral
    {
        $attributes = array_merge(
            [
                'submitted_by' => $user->id,
                'status' => Referral::STATUS_SUBMITTED,
                'source' => 'manual',
            ],
            $payload
        );

        if (empty($attributes['service_provider_organization_id']) && $user->organization_id) {
            $attributes['service_provider_organization_id'] = $user->organization_id;
        }

        $attributes['metadata'] = $attributes['metadata'] ?? [];

        return Referral::create($attributes);
    }
}
