<?php

namespace App\Policies;

use App\Models\TriageResult;
use App\Models\User;

class TriageResultPolicy
{
    protected const ALLOWED_ORG_ROLES = [
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

    public function update(User $user, TriageResult $result): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $role = strtoupper((string) $user->organization_role);

        return in_array($role, self::ALLOWED_ORG_ROLES, true);
    }
}
