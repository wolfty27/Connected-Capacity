<?php

namespace App\Services;

use App\Models\CareAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CareOpsService
{
    public function getAssignmentsForUser(User $user, $date = null): Collection
    {
        $query = CareAssignment::query();

        if ($user->isMaster() || $user->role === User::ROLE_ADMIN) {
            // No filter for super users
        } elseif ($user->role === User::ROLE_SPO_ADMIN || $user->role === User::ROLE_SPO_COORDINATOR) {
            $query->where('service_provider_organization_id', $user->organization_id);
        } elseif ($user->role === User::ROLE_FIELD_STAFF) {
            $query->where('assigned_user_id', $user->id);
        } else {
            return new Collection(); // Empty for unauthorized roles
        }

        if ($date) {
            // Assuming start_date determines the relevant day
            $query->whereDate('start_date', $date);
        }

        return $query->with(['patient', 'assignedUser'])->get();
    }

    public function createAssignment(array $data): CareAssignment
    {
        return DB::transaction(function () use ($data) {
            return CareAssignment::create([
                'patient_id' => $data['patient_id'],
                'assigned_user_id' => $data['assigned_user_id'],
                'service_provider_organization_id' => $data['service_provider_organization_id'],
                'status' => 'pending',
                'start_date' => $data['start_date'] ?? now(),
                'end_date' => $data['end_date'] ?? null,
            ]);
        });
    }

    public function updateAssignmentStatus(CareAssignment $assignment, string $status): CareAssignment
    {
        $assignment->update(['status' => $status]);
        return $assignment;
    }
}
