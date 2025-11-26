<?php

namespace App\Http\Middleware;

use App\Services\FeatureToggle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnsureFeatureEnabled
{
    public function __construct(private FeatureToggle $features)
    {
    }

    public function handle(Request $request, Closure $next, string ...$flags)
    {
        foreach ($flags as $flag) {
            if (!$this->features->enabled($flag)) {
                throw new NotFoundHttpException();
            }
        }

        return $next($request);
    }
}
