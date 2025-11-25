<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CarePlan;
use App\Models\CareBundle;
use App\Models\ServiceAssignment;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CarePlanController extends Controller
{
    public function index(Request $request)
    {
        $query = CarePlan::with(['patient.user', 'careBundle', 'serviceAssignments.serviceType']);

        // Filter by patient_id if provided
        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        } else {
            // Default: only show active plans when listing all
            $query->where('status', 'active');
        }

        $plans = $query->orderBy('created_at', 'desc')->get();

        // Transform for UI
        $data = $plans->map(function ($plan) {
            return [
                'id' => $plan->id,
                'patient_id' => $plan->patient_id,
                'patient' => $plan->patient->user->name ?? 'Unknown',
                'bundle' => $plan->careBundle->name ?? 'Custom',
                'bundle_code' => $plan->careBundle->code ?? null,
                'status' => $plan->status, // Keep lowercase for frontend filtering
                'start_date' => $plan->created_at->format('Y-m-d'),
                'approved_at' => $plan->approved_at?->format('Y-m-d H:i'),
                'services' => $plan->serviceAssignments->map(fn($sa) => [
                    'id' => $sa->id,
                    'service' => $sa->serviceType->name ?? 'Unknown',
                    'status' => $sa->status,
                ])->toArray(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'bundle_id' => 'required|string',
            'assignments' => 'required|array'
        ]);

        // Map wizard bundle codes to DB codes/IDs
        $bundleCodeMap = [
            'standard' => 'STD-MED',
            'complex' => 'COMPLEX',
            'palliative' => 'PALLIATIVE', // Ensure this exists in DB or handle fallback
            'dementia' => 'DEM-SUP' // In case logic changes
        ];

        $dbCode = $bundleCodeMap[$validated['bundle_id']] ?? 'STD-MED';
        $careBundle = CareBundle::where('code', $dbCode)->first();

        return DB::transaction(function () use ($validated, $careBundle) {
            // 1. Create Care Plan
            $latestVersion = CarePlan::where('patient_id', $validated['patient_id'])->max('version') ?? 0;

            $plan = CarePlan::create([
                'patient_id' => $validated['patient_id'],
                'care_bundle_id' => $careBundle ? $careBundle->id : null,
                'status' => 'active',
                'version' => $latestVersion + 1,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // 2. Create Service Assignments
            foreach ($validated['assignments'] as $serviceKey => $data) {
                // Map service key to ServiceType ID
                // We assume serviceKey matches 'code' or we have a map.
                // Wizard keys: 'nursing', 'psw', 'rehab', 'dietitian', 'dementia', 'mh', 'sw', 'rpm', 'digital'
                // DB Codes (Seeder): NURSING, PSW, REHAB, DEMENTIA, MH, YOUTH, DIGITAL, RPM

                $serviceCode = match ($serviceKey) {
                    'nursing' => 'NURSING',
                    'psw' => 'PSW',
                    'rehab' => 'REHAB',
                    'dementia' => 'DEMENTIA',
                    'mh' => 'MH',
                    'rpm' => 'RPM',
                    'digital' => 'DIGITAL',
                    default => strtoupper($serviceKey)
                };

                $serviceType = ServiceType::where('code', $serviceCode)->first();

                if (!$serviceType)
                    continue; // Skip unknown services

                $orgId = null;
                $userId = null;

                if (($data['type'] ?? '') === 'internal') {
                    $orgId = Auth::user()->organization_id; // Assign to current SPO
                    $userId = $data['staff']['id'] ?? null;
                } else {
                    $orgId = $data['partner']['id'] ?? null;
                }

                if (!$orgId)
                    continue;

                ServiceAssignment::create([
                    'care_plan_id' => $plan->id,
                    'patient_id' => $validated['patient_id'],
                    'service_type_id' => $serviceType->id,
                    'service_provider_organization_id' => $orgId,
                    'assigned_user_id' => $userId,
                    'status' => 'planned',
                    'frequency_rule' => $data['freq'] ?? 'Weekly', // Passed from wizard if updated
                    // 'estimated_hours' ... could add if passed
                ]);
            }

            // 3. Update Patient Status
            $patient = \App\Models\Patient::find($validated['patient_id']);
            if ($patient) {
                $patient->update(['status' => 'active']);
            }

            return $plan;
        });
    }
}