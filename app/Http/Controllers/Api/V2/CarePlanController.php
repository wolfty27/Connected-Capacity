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
            // Group service assignments by service_type_id to get unique services
            // This prevents duplicate chips when multiple visits are scheduled for the same service
            $groupedServices = $plan->serviceAssignments
                ->groupBy('service_type_id')
                ->map(function ($assignments) {
                    $first = $assignments->first();
                    $serviceType = $first->serviceType;

                    // Parse frequency from first assignment's frequency_rule
                    $frequencyPerWeek = 1;
                    if ($first->frequency_rule) {
                        preg_match('/(\d+)/', $first->frequency_rule, $matches);
                        $frequencyPerWeek = isset($matches[1]) ? (int) $matches[1] : 1;
                    }

                    // Sum up estimated hours across all assignments of this type
                    $totalEstimatedHours = $assignments->sum('estimated_hours_per_week');

                    // Get duration from first assignment or default to 12 weeks
                    $durationWeeks = 12;
                    if ($first->scheduled_start && $first->scheduled_end) {
                        $days = $first->scheduled_start->diffInDays($first->scheduled_end);
                        $durationWeeks = max(1, ceil($days / 7));
                    }

                    return [
                        'id' => $first->id,
                        'service_type_id' => $first->service_type_id,
                        'name' => $serviceType->name ?? 'Unknown',
                        'code' => $serviceType->code ?? null,
                        'category' => $serviceType->category ?? 'Clinical Services',
                        'status' => $first->status,
                        'cost_per_visit' => (float) ($serviceType->cost_per_visit ?? 0),
                        'frequency' => $frequencyPerWeek,
                        'frequency_rule' => $first->frequency_rule,
                        'duration' => $durationWeeks,
                        'estimated_hours_per_week' => $totalEstimatedHours,
                        'assignment_count' => $assignments->count(),
                    ];
                })
                ->values()
                ->toArray();

            return [
                'id' => $plan->id,
                'patient_id' => $plan->patient_id,
                'patient' => $plan->patient->user->name ?? 'Unknown',
                'bundle' => $plan->careBundle->name ?? 'Custom',
                'bundle_code' => $plan->careBundle->code ?? null,
                'status' => $plan->status, // Keep lowercase for frontend filtering
                'start_date' => $plan->created_at->format('Y-m-d'),
                'approved_at' => $plan->approved_at?->format('Y-m-d H:i'),
                'services' => $groupedServices,
                'total_cost' => collect($groupedServices)->sum(function ($service) {
                    return ($service['cost_per_visit'] ?? 0) * ($service['frequency'] ?? 1);
                }),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function show($id)
    {
        $plan = CarePlan::with(['patient.user', 'careBundle', 'serviceAssignments.serviceType'])
            ->findOrFail($id);

        // Parse frequency from frequency_rule helper
        $parseFrequency = function ($frequencyRule) {
            if ($frequencyRule) {
                preg_match('/(\d+)/', $frequencyRule, $matches);
                return isset($matches[1]) ? (int) $matches[1] : 1;
            }
            return 1;
        };

        // Group service assignments by service_type_id to get unique services
        $groupedServices = $plan->serviceAssignments
            ->groupBy('service_type_id')
            ->map(function ($assignments) use ($parseFrequency) {
                $first = $assignments->first();
                $serviceType = $first->serviceType;

                $frequencyPerWeek = $parseFrequency($first->frequency_rule);
                $totalEstimatedHours = $assignments->sum('estimated_hours_per_week');

                $durationWeeks = 12;
                if ($first->scheduled_start && $first->scheduled_end) {
                    $days = $first->scheduled_start->diffInDays($first->scheduled_end);
                    $durationWeeks = max(1, ceil($days / 7));
                }

                return [
                    'id' => $first->id,
                    'service_type_id' => $first->service_type_id,
                    'name' => $serviceType->name ?? 'Unknown',
                    'code' => $serviceType->code ?? null,
                    'category' => $serviceType->category ?? 'Clinical Services',
                    'status' => $first->status,
                    'cost_per_visit' => (float) ($serviceType->cost_per_visit ?? 0),
                    'frequency' => $frequencyPerWeek,
                    'frequency_rule' => $first->frequency_rule,
                    'duration' => $durationWeeks,
                    'estimated_hours_per_week' => $totalEstimatedHours,
                    'assignment_count' => $assignments->count(),
                ];
            })
            ->values()
            ->toArray();

        return response()->json([
            'data' => [
                'id' => $plan->id,
                'patient_id' => $plan->patient_id,
                'patient' => $plan->patient->user->name ?? 'Unknown',
                'bundle' => $plan->careBundle->name ?? 'Custom',
                'bundle_code' => $plan->careBundle->code ?? null,
                'status' => $plan->status,
                'start_date' => $plan->created_at->format('Y-m-d'),
                'approved_at' => $plan->approved_at?->format('Y-m-d H:i'),
                'services' => $groupedServices,
                'total_cost' => collect($groupedServices)->sum(function ($service) {
                    return ($service['cost_per_visit'] ?? 0) * ($service['frequency'] ?? 1);
                }),
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $plan = CarePlan::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:active,draft,completed,cancelled',
            'bundle_id' => 'sometimes|string',
            'assignments' => 'sometimes|array',
        ]);

        return DB::transaction(function () use ($plan, $validated, $request) {
            // Update status if provided
            if (isset($validated['status'])) {
                $plan->status = $validated['status'];
            }

            // Update bundle if provided
            if (isset($validated['bundle_id'])) {
                $bundleCodeMap = [
                    'standard' => 'STD-MED',
                    'complex' => 'COMPLEX',
                    'palliative' => 'PALLIATIVE',
                    'dementia' => 'DEM-SUP'
                ];
                $dbCode = $bundleCodeMap[$validated['bundle_id']] ?? $validated['bundle_id'];
                $careBundle = CareBundle::where('code', $dbCode)->first();
                if ($careBundle) {
                    $plan->care_bundle_id = $careBundle->id;
                }
            }

            $plan->save();

            // Update service assignments if provided
            if (isset($validated['assignments'])) {
                // Collect all service codes first
                $serviceCodes = [];
                foreach ($validated['assignments'] as $serviceKey => $data) {
                    $serviceCodes[$serviceKey] = match ($serviceKey) {
                        'nursing' => 'NURSING',
                        'psw' => 'PSW',
                        'rehab' => 'REHAB',
                        'dementia' => 'DEMENTIA',
                        'mh' => 'MH',
                        'rpm' => 'RPM',
                        'digital' => 'DIGITAL',
                        default => strtoupper($serviceKey)
                    };
                }

                // Bulk fetch service types
                $serviceTypes = ServiceType::whereIn('code', array_values($serviceCodes))->get()->keyBy('code');

                // Delete existing assignments and create new ones
                ServiceAssignment::where('care_plan_id', $plan->id)->delete();

                foreach ($validated['assignments'] as $serviceKey => $data) {
                    $serviceCode = $serviceCodes[$serviceKey];
                    $serviceType = $serviceTypes->get($serviceCode);

                    if (!$serviceType) continue;

                    $orgId = null;
                    $userId = null;

                    if (($data['type'] ?? '') === 'internal') {
                        $orgId = Auth::user()->organization_id;
                        $userId = $data['staff']['id'] ?? null;
                    } else {
                        $orgId = $data['partner']['id'] ?? null;
                    }

                    if (!$orgId) continue;

                    ServiceAssignment::create([
                        'care_plan_id' => $plan->id,
                        'patient_id' => $plan->patient_id,
                        'service_type_id' => $serviceType->id,
                        'service_provider_organization_id' => $orgId,
                        'assigned_user_id' => $userId,
                        'status' => 'planned',
                        'frequency_rule' => $data['freq'] ?? 'Weekly',
                    ]);
                }
            }

            return response()->json([
                'message' => 'Care plan updated successfully',
                'data' => $plan->fresh(['patient.user', 'careBundle', 'serviceAssignments.serviceType'])
            ]);
        });
    }

    public function destroy($id)
    {
        $plan = CarePlan::findOrFail($id);

        return DB::transaction(function () use ($plan) {
            // Delete associated service assignments first
            ServiceAssignment::where('care_plan_id', $plan->id)->delete();

            // Delete the care plan
            $plan->delete();

            return response()->json([
                'message' => 'Care plan deleted successfully'
            ]);
        });
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

            // Collect all service codes first
            $serviceCodes = [];
            foreach ($validated['assignments'] as $serviceKey => $data) {
                $serviceCodes[$serviceKey] = match ($serviceKey) {
                    'nursing' => 'NURSING',
                    'psw' => 'PSW',
                    'rehab' => 'REHAB',
                    'dementia' => 'DEMENTIA',
                    'mh' => 'MH',
                    'rpm' => 'RPM',
                    'digital' => 'DIGITAL',
                    default => strtoupper($serviceKey)
                };
            }

            // Bulk fetch service types
            $serviceTypes = ServiceType::whereIn('code', array_values($serviceCodes))->get()->keyBy('code');

            foreach ($validated['assignments'] as $serviceKey => $data) {
                $serviceCode = $serviceCodes[$serviceKey];
                $serviceType = $serviceTypes->get($serviceCode);

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