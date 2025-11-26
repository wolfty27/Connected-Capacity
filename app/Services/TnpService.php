<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\TransitionNeedsProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TnpService
{
    public function getProfile(Patient $patient): ?TransitionNeedsProfile
    {
        return $patient->transitionNeedsProfile;
    }

    public function createProfile(Patient $patient, array $data, User $user): TransitionNeedsProfile
    {
        return DB::transaction(function () use ($patient, $data) {
            $tnp = TransitionNeedsProfile::create([
                'patient_id' => $patient->id,
                'clinical_flags' => $data['clinical_flags'] ?? [],
                'narrative_summary' => $data['narrative_summary'] ?? null,
                'status' => 'draft',
            ]);

            return $tnp;
        });
    }

    public function updateProfile(TransitionNeedsProfile $tnp, array $data): TransitionNeedsProfile
    {
        return DB::transaction(function () use ($tnp, $data) {
            $tnp->update([
                'clinical_flags' => $data['clinical_flags'] ?? $tnp->clinical_flags,
                'narrative_summary' => $data['narrative_summary'] ?? $tnp->narrative_summary,
                'status' => $data['status'] ?? $tnp->status,
            ]);

            return $tnp;
        });
    }
}
