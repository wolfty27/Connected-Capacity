<?php

namespace App\Http\Controllers\CC2\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\CC2\UpdateOrganizationProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $organization = $request->attributes->get('organization', $request->user()?->organization);

        return view('cc2.organizations.profile', [
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

    public function update(UpdateOrganizationProfileRequest $request): RedirectResponse
    {
        $organization = $request->attributes->get('organization', $request->user()?->organization);

        $organization->update($request->validated());

        return redirect()
            ->route('cc2.organizations.profile.show')
            ->with('status', 'Organization profile updated.');
    }
}
