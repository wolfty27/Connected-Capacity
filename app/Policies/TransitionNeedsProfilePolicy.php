<?php

namespace App\Policies;

use App\Models\TransitionNeedsProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransitionNeedsProfilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Allow all authenticated users to view lists for now, filtered by controller
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TransitionNeedsProfile $transitionNeedsProfile): bool
    {
        // Logic to check if user is associated with the patient's hospital, retirement home, or is an SPO/Admin
        // For V2.1 prototype, simplified:
        if ($user->isMaster() || $user->role === User::ROLE_ADMIN) {
            return true;
        }
        
        // Add specific organization checks here
        return true; 
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === User::ROLE_HOSPITAL || $user->isMaster();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TransitionNeedsProfile $transitionNeedsProfile): bool
    {
        return $user->isMaster() || 
               $user->role === User::ROLE_HOSPITAL || 
               $user->role === User::ROLE_SPO_COORDINATOR || 
               $user->role === User::ROLE_SPO_ADMIN;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TransitionNeedsProfile $transitionNeedsProfile): bool
    {
        return $user->isMaster();
    }
}