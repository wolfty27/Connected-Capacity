<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureOrganizationRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = Auth::user();

        if (!$user) {
            throw new AccessDeniedHttpException('Unauthorized');
        }

        if ($user->role === 'admin') {
            return $next($request);
        }

        $normalized = collect($roles)
            ->filter()
            ->map(fn ($role) => strtoupper(trim($role)));

        if ($normalized->isEmpty()) {
            return $next($request);
        }

        $userRole = strtoupper((string) $user->organization_role);

        if (!$normalized->contains($userRole)) {
            throw new AccessDeniedHttpException('Insufficient role');
        }

        return $next($request);
    }
}
