<?php

namespace App\Services\CC2;

use App\Models\Patient;
use App\Models\TriageResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TriageService
{
    public function recordResult(Patient $patient, array $attributes, User $user): TriageResult
    {
        return DB::transaction(function () use ($patient, $attributes, $user) {
            $baseAttributes = [
                'patient_id' => $patient->id,
                'triaged_at' => now(),
                'triaged_by' => $user->id,
            ];

            if (!$patient->triageResult) {
                $baseAttributes['received_at'] = now();
            }

            $triageResult = TriageResult::updateOrCreate(
                ['patient_id' => $patient->id],
                array_merge($baseAttributes, $attributes)
            );

            $this->updatePatientTriageInformation($patient, $attributes, $triageResult);
            $this->findOrCreateCarePlan($patient);

            return $triageResult;
        });
    }

    private function updatePatientTriageInformation(Patient $patient, array $attributes, TriageResult $triageResult): void
    {
        $patient->update([
            'triage_summary' => [
                'acuity_level' => $attributes['acuity_level'],
                'notes' => $attributes['notes'] ?? null,
                'triaged_at' => $triageResult->triaged_at,
            ],
            'risk_flags' => [
                'dementia' => (bool) ($attributes['dementia_flag'] ?? false),
                'mental_health' => (bool) ($attributes['mh_flag'] ?? false),
                'rpm' => (bool) ($attributes['rpm_required'] ?? false),
                'fall' => (bool) ($attributes['fall_risk'] ?? false),
                'behavioural' => (bool) ($attributes['behavioural_risk'] ?? false),
            ],
        ]);
    }

    private function findOrCreateCarePlan(Patient $patient): void
    {
        $patient->carePlans()->firstOrCreate(
            [
                'patient_id' => $patient->id,
                'version' => 1,
            ],
            [
                'status' => 'draft',
            ]
        );
    }
}
