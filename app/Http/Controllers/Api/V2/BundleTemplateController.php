<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CareBundle;
use Illuminate\Http\Request;

class BundleTemplateController extends Controller
{
    public function index()
    {
        $bundles = CareBundle::where('active', true)->get();
        return response()->json($bundles);
    }

    public function show($id)
    {
        $bundle = CareBundle::with(['serviceTypes.category'])->find($id);
        
        if (!$bundle) {
            return response()->json(['message' => 'Bundle not found'], 404);
        }

        // Transform to match frontend expectations
        $items = $bundle->serviceTypes->map(function ($st) {
            return [
                'service_type_id' => $st->id,
                'service_type' => [
                    'code' => $st->code,
                    'label' => $st->name,
                    'category' => $st->category->name ?? $st->category, // fallback to string
                    'default_duration' => $st->default_duration_minutes
                ],
                'default_frequency' => $st->pivot->default_frequency_per_week,
                'assignment_type' => $st->pivot->assignment_type,
                'role_required' => $st->pivot->role_required
            ];
        });

        return response()->json([
            'id' => $bundle->id,
            'code' => $bundle->code,
            'name' => $bundle->name,
            'description' => $bundle->description,
            'items' => $items
        ]);
    }
}