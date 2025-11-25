<?php

namespace App\Services\CareOps;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class FteComplianceService
{
    public function calculateSnapshot($organizationId = null)
    {
        $orgId = $organizationId ?? Auth::user()->organization_id;

        // If no org, return zero state
        if (!$orgId) {
            return [
                'total_staff' => 0,
                'full_time_staff' => 0,
                'fte_ratio' => 0,
                'band' => 'GREY'
            ];
        }

        // Get all field staff for this SPO
        $staffQuery = User::where('organization_id', $orgId)
                          ->where('role', User::ROLE_FIELD_STAFF);

        $totalStaff = $staffQuery->count();
        
        $fullTimeStaff = $staffQuery->where(function($q) {
            $q->where('employment_type', 'full_time')
              ->orWhere('fte_value', '>=', 0.8);
        })->count();

        $ratio = $totalStaff > 0 ? ($fullTimeStaff / $totalStaff) * 100 : 0;

        // Determine Band
        $band = 'RED';
        if ($ratio >= 80) {
            $band = 'GREEN'; // Compliant
        } elseif ($ratio >= 75) {
            $band = 'YELLOW'; // At Risk
        }

        return [
            'total_staff' => $totalStaff,
            'full_time_staff' => $fullTimeStaff,
            'fte_ratio' => round($ratio, 1),
            'band' => $band
        ];
    }

    public function calculateProjection($newStaffType)
    {
        $current = $this->calculateSnapshot();
        
        $newTotal = $current['total_staff'] + 1;
        $newFullTime = $current['full_time_staff'] + ($newStaffType === 'full_time' ? 1 : 0);
        
        $ratio = $newTotal > 0 ? ($newFullTime / $newTotal) * 100 : 0;
        
        $band = 'RED';
        if ($ratio >= 80) $band = 'GREEN';
        elseif ($ratio >= 75) $band = 'YELLOW';

        return [
            'current_ratio' => $current['fte_ratio'],
            'projected_ratio' => round($ratio, 1),
            'projected_band' => $band,
            'impact' => round($ratio - $current['fte_ratio'], 1)
        ];
    }
}