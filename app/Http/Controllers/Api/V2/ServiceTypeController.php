<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ServiceType;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * ServiceTypeController - CRUD API for service types
 *
 * Provides endpoints for managing the service catalog that powers
 * care bundle configuration.
 */
class ServiceTypeController extends Controller
{
    /**
     * Get all service types.
     *
     * GET /api/v2/service-types
     *
     * Query params:
     * - active: bool (filter by active status)
     * - category: string (filter by category code)
     * - with_category: bool (include category relationship)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServiceType::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by category
        if ($request->has('category')) {
            $query->whereHas('serviceCategory', function ($q) use ($request) {
                $q->where('code', $request->category);
            });
        }

        // Include category relationship
        if ($request->boolean('with_category', true)) {
            $query->with('serviceCategory');
        }

        $serviceTypes = $query->orderBy('category')->orderBy('name')->get();

        // Transform for frontend compatibility
        $transformed = $serviceTypes->map(fn($st) => $this->transformServiceType($st));

        return response()->json([
            'data' => $transformed,
            'meta' => [
                'total' => $transformed->count(),
            ],
        ]);
    }

    /**
     * Get a single service type.
     *
     * GET /api/v2/service-types/{id}
     */
    public function show(int $id): JsonResponse
    {
        $serviceType = ServiceType::with('serviceCategory', 'careBundles')->find($id);

        if (!$serviceType) {
            return response()->json(['message' => 'Service type not found'], 404);
        }

        return response()->json([
            'data' => $this->transformServiceType($serviceType, true),
        ]);
    }

    /**
     * Create a new service type.
     *
     * POST /api/v2/service-types
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:service_types,name',
            'code' => 'required|string|max:50|unique:service_types,code',
            'category_id' => 'nullable|exists:service_categories,id',
            'category' => 'required_without:category_id|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_duration_minutes' => 'nullable|integer|min:0|max:480',
            'cost_per_visit' => 'nullable|numeric|min:0',
            'cost_code' => 'nullable|string|max:50',
            'cost_driver' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // If category_id not provided but category string is, find or use the string
        if (!isset($data['category_id']) && isset($data['category'])) {
            $category = ServiceCategory::where('code', $data['category'])
                ->orWhere('name', $data['category'])
                ->first();
            if ($category) {
                $data['category_id'] = $category->id;
            }
        }

        $data['active'] = $data['active'] ?? true;

        $serviceType = ServiceType::create($data);
        $serviceType->load('serviceCategory');

        return response()->json([
            'message' => 'Service type created successfully',
            'data' => $this->transformServiceType($serviceType),
        ], 201);
    }

    /**
     * Update a service type.
     *
     * PUT /api/v2/service-types/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $serviceType = ServiceType::find($id);

        if (!$serviceType) {
            return response()->json(['message' => 'Service type not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:service_types,name,' . $id,
            'code' => 'sometimes|string|max:50|unique:service_types,code,' . $id,
            'category_id' => 'nullable|exists:service_categories,id',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_duration_minutes' => 'nullable|integer|min:0|max:480',
            'cost_per_visit' => 'nullable|numeric|min:0',
            'cost_code' => 'nullable|string|max:50',
            'cost_driver' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Handle category update
        if (isset($data['category']) && !isset($data['category_id'])) {
            $category = ServiceCategory::where('code', $data['category'])
                ->orWhere('name', $data['category'])
                ->first();
            if ($category) {
                $data['category_id'] = $category->id;
            }
        }

        $serviceType->update($data);
        $serviceType->load('serviceCategory');

        return response()->json([
            'message' => 'Service type updated successfully',
            'data' => $this->transformServiceType($serviceType),
        ]);
    }

    /**
     * Delete a service type.
     *
     * DELETE /api/v2/service-types/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $serviceType = ServiceType::find($id);

        if (!$serviceType) {
            return response()->json(['message' => 'Service type not found'], 404);
        }

        // Check if service type is in use
        $assignmentCount = $serviceType->serviceAssignments()->count();
        $bundleCount = $serviceType->careBundles()->count();

        if ($assignmentCount > 0 || $bundleCount > 0) {
            return response()->json([
                'message' => 'Cannot delete service type that is in use',
                'details' => [
                    'assignments_count' => $assignmentCount,
                    'bundles_count' => $bundleCount,
                ],
            ], 409);
        }

        $serviceType->delete();

        return response()->json([
            'message' => 'Service type deleted successfully',
        ]);
    }

    /**
     * Get service types grouped by category.
     *
     * GET /api/v2/service-types/by-category
     */
    public function byCategory(): JsonResponse
    {
        $serviceTypes = ServiceType::where('active', true)
            ->with('serviceCategory')
            ->orderBy('name')
            ->get();

        // Group by the category column value (a string)
        $grouped = $serviceTypes->groupBy(function ($st) {
            return $st->getAttributeValue('category') ?? 'Uncategorized';
        });

        $result = $grouped->map(function ($types, $categoryName) {
            return [
                'category' => $categoryName,
                'services' => $types->map(fn($st) => $this->transformServiceType($st)),
            ];
        })->values();

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Get all service categories.
     *
     * GET /api/v2/service-types/categories
     */
    public function categories(): JsonResponse
    {
        $categories = ServiceCategory::where('active', true)
            ->withCount([
                'serviceTypes' => function ($q) {
                    $q->where('active', true);
                }
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn($cat) => [
                'id' => $cat->id,
                'code' => $cat->code,
                'name' => $cat->name,
                'description' => $cat->description,
                'service_count' => $cat->service_types_count,
            ]),
        ]);
    }

    /**
     * Toggle active status of a service type.
     *
     * POST /api/v2/service-types/{id}/toggle-active
     */
    public function toggleActive(int $id): JsonResponse
    {
        $serviceType = ServiceType::find($id);

        if (!$serviceType) {
            return response()->json(['message' => 'Service type not found'], 404);
        }

        $serviceType->update(['active' => !$serviceType->active]);

        return response()->json([
            'message' => $serviceType->active ? 'Service type activated' : 'Service type deactivated',
            'data' => $this->transformServiceType($serviceType),
        ]);
    }

    /**
     * Transform service type for API response.
     *
     * Note: ServiceType has both a 'category' column (string) AND a category() relationship.
     * We use getAttributeValue() to access the column directly and avoid the name conflict.
     */
    protected function transformServiceType(ServiceType $serviceType, bool $includeRelations = false): array
    {
        // Get the category relationship if loaded (use getRelation to avoid column conflict)
        $categoryRelation = $serviceType->relationLoaded('serviceCategory')
            ? $serviceType->getRelation('serviceCategory')
            : null;

        // Use the category column value directly (it stores the category name as a string)
        $categoryName = $serviceType->getAttributeValue('category');
        $categoryCode = $categoryRelation?->code;

        $data = [
            'id' => $serviceType->id,
            'code' => $serviceType->code,
            'name' => $serviceType->name,
            'category' => $categoryName,
            'category_code' => $categoryCode,
            'category_id' => $serviceType->category_id,
            'description' => $serviceType->description,
            'default_duration_minutes' => $serviceType->default_duration_minutes,
            'cost_per_visit' => $serviceType->cost_per_visit,
            'cost_code' => $serviceType->cost_code,
            'cost_driver' => $serviceType->cost_driver,
            'source' => $serviceType->source,
            'active' => $serviceType->active,
        ];

        if ($includeRelations && $serviceType->relationLoaded('careBundles')) {
            $data['bundles'] = $serviceType->careBundles->map(fn($bundle) => [
                'id' => $bundle->id,
                'code' => $bundle->code,
                'name' => $bundle->name,
                'default_frequency' => $bundle->pivot->default_frequency_per_week,
            ]);
        }

        return $data;
    }
}
