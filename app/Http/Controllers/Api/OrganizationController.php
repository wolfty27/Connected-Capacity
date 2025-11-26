<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\CC2\UpdateOrganizationProfileRequest;

class OrganizationController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        if (!$organization) {
            return response()->json(['message' => 'No organization associated with this user.'], 404);
        }

        return response()->json([
            'organization' => $organization,
            'capabilityOptions' => [
                'dementia' => 'Dementia & Cognitive Supports',
                'mental_health' => 'Mental Health & Addictions',
                'clinical' => 'High-Intensity Clinical & PSW',
                'community' => 'Community Support & SDOH',
                'technology' => 'Technology / RPM',
            ],
        ]);
    }

    public function update(UpdateOrganizationProfileRequest $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        if (!$organization) {
            return response()->json(['message' => 'No organization associated with this user.'], 404);
        }

        $organization->update($request->validated());

        return response()->json([
            'message' => 'Organization profile updated successfully.',
            'organization' => $organization
        ]);
    }
}
