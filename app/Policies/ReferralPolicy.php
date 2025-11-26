<?php

namespace App\Policies;

use App\Models\Referral;
use App\Models\User;

class ReferralPolicy
{
    protected const ALLOWED_ORG_ROLES = [
        'INTAKE_NURSE',
        'TRIAGE_NURSE',
        'COORDINATOR',
    ];

    public function create(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $role = strtoupper((string) $user->organization_role);

        return in_array($role, self::ALLOWED_ORG_ROLES, true);
    }

    public function view(User $user, Referral $referral): bool
    {
        return $this->create($user);
    }
}
