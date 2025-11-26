<?php

namespace App\Policies;

use App\Models\CareAssignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CareAssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isMaster() || 
               $user->role === User::ROLE_ADMIN || 
               $user->role === User::ROLE_SPO_ADMIN || 
               $user->role === User::ROLE_SPO_COORDINATOR ||
               $user->role === User::ROLE_FIELD_STAFF;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CareAssignment $careAssignment): bool
    {
        if ($user->isMaster() || $user->role === User::ROLE_ADMIN) {
            return true;
        }

        if ($user->role === User::ROLE_SPO_ADMIN || $user->role === User::ROLE_SPO_COORDINATOR) {
            return $user->organization_id === $careAssignment->service_provider_organization_id;
        }

        if ($user->role === User::ROLE_FIELD_STAFF) {
            return $user->id === $careAssignment->assigned_user_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isMaster() || 
               $user->role === User::ROLE_ADMIN || 
               $user->role === User::ROLE_SPO_ADMIN;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CareAssignment $careAssignment): bool
    {
        if ($user->isMaster() || $user->role === User::ROLE_ADMIN) {
            return true;
        }

        if ($user->role === User::ROLE_SPO_ADMIN) {
            return $user->organization_id === $careAssignment->service_provider_organization_id;
        }

        // Field staff can likely update status, but this might be restricted to specific fields in controller
        if ($user->role === User::ROLE_FIELD_STAFF) {
            return $user->id === $careAssignment->assigned_user_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CareAssignment $careAssignment): bool
    {
        return $user->isMaster() || $user->role === User::ROLE_ADMIN;
    }
}