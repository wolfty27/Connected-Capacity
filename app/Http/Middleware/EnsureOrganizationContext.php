<?php

namespace App\Http\Middleware;

use App\Models\ServiceProviderOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureOrganizationContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            throw new AccessDeniedHttpException('Unauthorized');
        }

        if ($user->isMaster()) {
            return $next($request);
        }

        // Relies on User::organization() relationship to resolve SPO/SSPO context.
        /** @var ServiceProviderOrganization|null $organization */
        $organization = $user->organization;

        if (!$organization || !$organization->active) {
            throw new AccessDeniedHttpException('Organization context required');
        }

        $request->attributes->set('organization', $organization);
        View::share('currentOrganization', $organization);

        return $next($request);
    }
}
