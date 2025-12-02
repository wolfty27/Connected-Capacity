<?php

use Illuminate\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;

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
    ->withSchedule(function (Schedule $schedule) {
        // IR-009: InterRAI compliance scheduled jobs
        $schedule->job(new \App\Jobs\DetectStaleAssessmentsJob())
            ->dailyAt('06:00')
            ->withoutOverlapping();

        $schedule->job(new \App\Jobs\SyncInterraiStatusJob())
            ->hourly()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            \Log::error('Exception caught', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });
    })
    ->create();