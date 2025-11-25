<?php

use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Illuminate\Foundation\Configuration\Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->alias([
            'feature.flag' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            'organization.context' => \App\Http\Middleware\EnsureOrganizationContext::class,
            'organization.role' => \App\Http\Middleware\EnsureOrganizationRole::class,
            'admin.routes' => \App\Http\Middleware\AdminRoutes::class,
            'authenticated.routes' => \App\Http\Middleware\AuthenticatedRoutes::class,
        ]);
    })
    ->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions) {
        //
    })
    ->create();