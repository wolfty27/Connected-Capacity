<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\ServiceAssignment;
use App\Models\ServiceProviderOrganization;
use App\Models\ServiceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * SSPO Marketplace Controller
 *
 * Provides API endpoints for browsing and viewing SSPO organizations.
 *
 * Endpoints:
 * - GET /api/v2/sspo-marketplace - List SSPOs with filtering
 * - GET /api/v2/sspo-marketplace/{id} - Get SSPO profile details
 * - GET /api/v2/sspo-marketplace/filters - Get available filter options
 */
class SspoMarketplaceController extends Controller
{
    /**
     * GET /api/v2/sspo-marketplace
     *
     * List all SSPO organizations with optional filtering.
     *
     * Query params:
     * - search: Text search on name/description
     * - service_type_id: Filter by service type offering
     * - region_code: Filter by region
     * - status: Filter by status (active/draft/inactive)
     * - per_page: Results per page (default: 20)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServiceProviderOrganization::sspo()
            ->with(['serviceTypes' => function ($q) {
                $q->select('service_types.id', 'code', 'name', 'category', 'delivery_mode', 'preferred_provider');
            }]);

        // Text search
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        // Filter by service type
        if ($request->filled('service_type_id')) {
            $query->offeringService((int) $request->input('service_type_id'));
        }

        // Filter by region
        if ($request->filled('region_code')) {
            $query->inRegion($request->input('region_code'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        } else {
            // Default to active SSPOs only
            $query->activeOnly();
        }

        // Order by name
        $query->orderBy('name');

        // Paginate
        $perPage = min($request->input('per_page', 20), 100);
        $sspos = $query->paginate($perPage);

        // Transform the data
        $sspos->getCollection()->transform(function ($sspo) {
            return $this->transformSspoCard($sspo);
        });

        return response()->json($sspos);
    }

    /**
     * GET /api/v2/sspo-marketplace/{id}
     *
     * Get detailed SSPO profile.
     */
    public function show(int $id): JsonResponse
    {
        $sspo = ServiceProviderOrganization::sspo()
            ->with(['serviceTypes' => function ($q) {
                $q->select(
                    'service_types.id', 'code', 'name', 'category', 'description',
                    'delivery_mode', 'preferred_provider', 'default_duration_minutes',
                    'cost_per_visit'
                );
            }])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->transformSspoProfile($sspo),
        ]);
    }

    /**
     * GET /api/v2/sspo-marketplace/filters
     *
     * Get available filter options for the marketplace.
     */
    public function filters(): JsonResponse
    {
        // Get all SSPO service types
        $serviceTypes = ServiceType::whereHas('organizations', function ($q) {
            $q->sspo();
        })
            ->select('id', 'code', 'name', 'category')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(function ($st) {
                return [
                    'id' => $st->id,
                    'code' => $st->code,
                    'name' => $st->name,
                    'category' => $st->category,
                ];
            });

        // Get regions that have SSPOs
        $regions = Region::whereHas('organizations', function ($q) {
            $q->sspo();
        })
            ->orWhereIn('code', ServiceProviderOrganization::sspo()->pluck('region_code'))
            ->select('code', 'name')
            ->orderBy('name')
            ->get();

        // If no regions through relationship, get from region_code
        if ($regions->isEmpty()) {
            $regionCodes = ServiceProviderOrganization::sspo()
                ->whereNotNull('region_code')
                ->distinct()
                ->pluck('region_code');

            $regions = Region::whereIn('code', $regionCodes)
                ->select('code', 'name')
                ->orderBy('name')
                ->get();
        }

        // Status options
        $statuses = [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'all', 'label' => 'All'],
        ];

        return response()->json([
            'service_types' => $serviceTypes,
            'regions' => $regions,
            'statuses' => $statuses,
        ]);
    }

    /**
     * GET /api/v2/sspo-marketplace/stats
     *
     * Get marketplace statistics.
     */
    public function stats(): JsonResponse
    {
        $totalSspos = ServiceProviderOrganization::sspo()->count();
        $activeSspos = ServiceProviderOrganization::sspo()->activeOnly()->count();

        $totalServiceTypes = ServiceType::whereHas('organizations', function ($q) {
            $q->sspo();
        })->count();

        // Calculate average capacity (simplified)
        $avgCapacity = ServiceProviderOrganization::sspo()
            ->activeOnly()
            ->get()
            ->avg(function ($sspo) {
                // Extract capacity from metadata if available
                $metadata = $sspo->capacity_metadata ?? [];
                return $metadata['max_monitored_patients']
                    ?? $metadata['home_care_clients']
                    ?? $metadata['max_residents']
                    ?? 0;
            });

        return response()->json([
            'total_partners' => $totalSspos,
            'active_partners' => $activeSspos,
            'service_types_offered' => $totalServiceTypes,
            'average_capacity' => round($avgCapacity),
        ]);
    }

    /**
     * Transform SSPO for card display (list view).
     */
    protected function transformSspoCard(ServiceProviderOrganization $sspo): array
    {
        // Get primary services (max 5 for display)
        $services = $sspo->serviceTypes
            ->sortByDesc('pivot.is_primary')
            ->take(5)
            ->map(function ($st) {
                return [
                    'id' => $st->id,
                    'code' => $st->code,
                    'name' => $st->name,
                    'is_primary' => $st->pivot->is_primary ?? false,
                ];
            })
            ->values();

        // Get capacity status
        $capacityStatus = $this->getCapacityStatus($sspo);

        return [
            'id' => $sspo->id,
            'slug' => $sspo->slug,
            'name' => $sspo->name,
            'initials' => $sspo->initials,
            'logo_url' => $sspo->logo_url,
            'tagline' => $sspo->tagline,
            'short_description' => $sspo->short_description,
            'status' => $sspo->status,
            'region_code' => $sspo->region_code,
            'regions' => $sspo->regions ?? [],
            'city' => $sspo->city,
            'services' => $services,
            'service_count' => $sspo->serviceTypes->count(),
            'capacity_status' => $capacityStatus,
            'website_url' => $sspo->website_url,
        ];
    }

    /**
     * Transform SSPO for profile display (detail view).
     */
    protected function transformSspoProfile(ServiceProviderOrganization $sspo): array
    {
        // Get all services grouped by category
        $servicesByCategory = $sspo->serviceTypes
            ->groupBy('category')
            ->map(function ($services) {
                return $services->map(function ($st) {
                    return [
                        'id' => $st->id,
                        'code' => $st->code,
                        'name' => $st->name,
                        'description' => $st->description,
                        'delivery_mode' => $st->delivery_mode,
                        'preferred_provider' => $st->preferred_provider,
                        'duration_minutes' => $st->default_duration_minutes,
                        'is_primary' => $st->pivot->is_primary ?? false,
                    ];
                })->values();
            });

        return [
            'id' => $sspo->id,
            'slug' => $sspo->slug,
            'name' => $sspo->name,
            'initials' => $sspo->initials,
            'type' => $sspo->type,
            'status' => $sspo->status,

            // Branding
            'logo_url' => $sspo->logo_url,
            'cover_photo_url' => $sspo->cover_photo_url,
            'tagline' => $sspo->tagline,
            'description' => $sspo->description,
            'notes' => $sspo->notes,

            // Contact
            'website_url' => $sspo->website_url,
            'contact_name' => $sspo->contact_name,
            'contact_email' => $sspo->contact_email,
            'contact_phone' => $sspo->contact_phone,

            // Location
            'address' => $sspo->address,
            'city' => $sspo->city,
            'province' => $sspo->province,
            'postal_code' => $sspo->postal_code,
            'region_code' => $sspo->region_code,
            'regions' => $sspo->regions ?? [],

            // Services
            'services' => $sspo->serviceTypes->map(function ($st) {
                return [
                    'id' => $st->id,
                    'code' => $st->code,
                    'name' => $st->name,
                    'category' => $st->category,
                    'description' => $st->description,
                    'delivery_mode' => $st->delivery_mode,
                    'is_primary' => $st->pivot->is_primary ?? false,
                ];
            })->values(),
            'services_by_category' => $servicesByCategory,
            'service_count' => $sspo->serviceTypes->count(),

            // Capabilities & Capacity
            'capabilities' => $sspo->capabilities ?? [],
            'capacity_metadata' => $sspo->capacity_metadata,
            'capacity_status' => $this->getCapacityStatus($sspo),

            // Upcoming appointments (next 7 days)
            'upcoming_assignments' => $this->getUpcomingAssignments($sspo),

            // Recent service history (past 7 days)
            'recent_assignments' => $this->getRecentAssignments($sspo),

            // Capacity summary with utilization metrics
            'capacity_summary' => $this->getCapacitySummary($sspo),

            // Metadata
            'created_at' => $sspo->created_at?->toIso8601String(),
            'updated_at' => $sspo->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get capacity status for an SSPO.
     *
     * Returns 'High', 'Moderate', or 'Low' based on capacity metadata.
     */
    protected function getCapacityStatus(ServiceProviderOrganization $sspo): string
    {
        $metadata = $sspo->capacity_metadata ?? [];

        // Check for explicit capacity status
        if (isset($metadata['capacity_status'])) {
            return $metadata['capacity_status'];
        }

        // Infer from available capacity data
        $maxCapacity = $metadata['max_monitored_patients']
            ?? $metadata['home_care_clients']
            ?? $metadata['max_residents']
            ?? $metadata['platform_capacity']
            ?? 0;

        $currentUtilization = $metadata['current_utilization'] ?? 0.5; // Default 50%

        if ($maxCapacity === 0) {
            return 'Unknown';
        }

        $availableRatio = 1 - $currentUtilization;

        if ($availableRatio >= 0.3) {
            return 'High';
        } elseif ($availableRatio >= 0.1) {
            return 'Moderate';
        }

        return 'Low';
    }

    /**
     * Get upcoming assignments for an SSPO (next 7 days).
     *
     * Returns assignments grouped by patient with appointment details.
     */
    protected function getUpcomingAssignments(ServiceProviderOrganization $sspo): array
    {
        $now = Carbon::now();
        $weekFromNow = $now->copy()->addDays(7);

        $assignments = ServiceAssignment::forOrganization($sspo->id)
            ->with(['patient', 'serviceType', 'assignedUser'])
            ->whereBetween('scheduled_start', [$now, $weekFromNow])
            ->whereIn('status', [
                ServiceAssignment::STATUS_PLANNED,
                ServiceAssignment::STATUS_PENDING,
                ServiceAssignment::STATUS_ACTIVE,
            ])
            ->orderBy('scheduled_start', 'asc')
            ->limit(20)
            ->get();

        // Group by patient
        $byPatient = $assignments->groupBy('patient_id');

        return $byPatient->map(function ($patientAssignments, $patientId) {
            $patient = $patientAssignments->first()->patient;

            return [
                'patient' => [
                    'id' => $patient?->id,
                    'name' => $patient?->full_name ?? 'Unknown Patient',
                    'initials' => $patient?->initials ?? '??',
                ],
                'appointments' => $patientAssignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'service_type' => [
                            'id' => $assignment->serviceType?->id,
                            'code' => $assignment->serviceType?->code,
                            'name' => $assignment->serviceType?->name,
                            'category' => $assignment->serviceType?->category,
                        ],
                        'scheduled_start' => $assignment->scheduled_start?->toIso8601String(),
                        'scheduled_end' => $assignment->scheduled_end?->toIso8601String(),
                        'duration_minutes' => $assignment->duration_minutes,
                        'staff' => [
                            'id' => $assignment->assignedUser?->id,
                            'name' => $assignment->assignedUser?->name ?? 'Unassigned',
                        ],
                        'status' => $assignment->status,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Get recent service history for an SSPO (past 7 days).
     *
     * Returns completed assignments with verification status.
     */
    protected function getRecentAssignments(ServiceProviderOrganization $sspo): array
    {
        $now = Carbon::now();
        $weekAgo = $now->copy()->subDays(7);

        $assignments = ServiceAssignment::forOrganization($sspo->id)
            ->with(['patient', 'serviceType', 'assignedUser'])
            ->where('scheduled_start', '<', $now)
            ->where('scheduled_start', '>=', $weekAgo)
            ->whereIn('status', [
                ServiceAssignment::STATUS_COMPLETED,
                ServiceAssignment::STATUS_MISSED,
            ])
            ->orderBy('scheduled_start', 'desc')
            ->limit(15)
            ->get();

        return $assignments->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'patient' => [
                    'id' => $assignment->patient?->id,
                    'name' => $assignment->patient?->full_name ?? 'Unknown Patient',
                    'initials' => $assignment->patient?->initials ?? '??',
                ],
                'service_type' => [
                    'id' => $assignment->serviceType?->id,
                    'code' => $assignment->serviceType?->code,
                    'name' => $assignment->serviceType?->name,
                    'category' => $assignment->serviceType?->category,
                ],
                'scheduled_start' => $assignment->scheduled_start?->toIso8601String(),
                'scheduled_end' => $assignment->scheduled_end?->toIso8601String(),
                'actual_start' => $assignment->actual_start?->toIso8601String(),
                'actual_end' => $assignment->actual_end?->toIso8601String(),
                'staff' => [
                    'id' => $assignment->assignedUser?->id,
                    'name' => $assignment->assignedUser?->name ?? 'Unassigned',
                ],
                'status' => $assignment->status,
                'verification_status' => $assignment->verification_status,
                'verified_at' => $assignment->verified_at?->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Get capacity summary for an SSPO.
     *
     * Returns utilization metrics including scheduled hours, patient count, visit count.
     */
    protected function getCapacitySummary(ServiceProviderOrganization $sspo): array
    {
        $now = Carbon::now();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();

        // Get this week's assignments
        $weekAssignments = ServiceAssignment::forOrganization($sspo->id)
            ->whereBetween('scheduled_start', [$weekStart, $weekEnd])
            ->whereIn('status', [
                ServiceAssignment::STATUS_PLANNED,
                ServiceAssignment::STATUS_PENDING,
                ServiceAssignment::STATUS_ACTIVE,
                ServiceAssignment::STATUS_IN_PROGRESS,
                ServiceAssignment::STATUS_COMPLETED,
            ])
            ->get();

        // Calculate scheduled hours (sum of duration_minutes / 60)
        $scheduledMinutes = $weekAssignments->sum('duration_minutes');
        $scheduledHours = round($scheduledMinutes / 60, 1);

        // Unique patients served this week
        $patientCount = $weekAssignments->pluck('patient_id')->unique()->count();

        // Total visit count
        $visitCount = $weekAssignments->count();

        // Completed visits this week
        $completedCount = $weekAssignments->where('status', ServiceAssignment::STATUS_COMPLETED)->count();

        // Get available hours from capacity metadata
        $metadata = $sspo->capacity_metadata ?? [];
        $maxWeeklyHours = $metadata['max_weekly_hours'] ?? 40;

        // Calculate utilization
        $utilizationPct = $maxWeeklyHours > 0
            ? round(($scheduledHours / $maxWeeklyHours) * 100, 1)
            : 0;

        // Determine availability status
        $availabilityStatus = match (true) {
            $utilizationPct >= 90 => 'Low',
            $utilizationPct >= 70 => 'Moderate',
            default => 'High',
        };

        return [
            'scheduled_hours' => $scheduledHours,
            'available_hours' => max(0, $maxWeeklyHours - $scheduledHours),
            'max_weekly_hours' => $maxWeeklyHours,
            'utilization_pct' => $utilizationPct,
            'patient_count' => $patientCount,
            'visit_count' => $visitCount,
            'completed_count' => $completedCount,
            'availability_status' => $availabilityStatus,
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
        ];
    }
}
