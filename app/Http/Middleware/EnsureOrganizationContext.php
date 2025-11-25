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
        \Illuminate\Support\Facades\Log::info('EnsureOrganizationContext middleware', ['user_id' => $user ? $user->id : 'null']);

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
            \Illuminate\Support\Facades\Log::warning('Organization context failed', ['user_id' => $user->id, 'org_id' => $user->organization_id, 'has_org' => (bool)$organization, 'active' => $organization ? $organization->active : false]);
            throw new AccessDeniedHttpException('Organization context required');
        }

        $request->attributes->set('organization', $organization);
        View::share('currentOrganization', $organization);

        return $next($request);
    }
}
