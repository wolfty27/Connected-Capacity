<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Define API Rate Limiter
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/v2/dashboard', [\App\Http\Controllers\Api\V2\DashboardController::class, 'index']);
    Route::get('/v2/dashboards/spo', [\App\Http\Controllers\Api\V2\SpoDashboardController::class, 'index']);
    Route::get('/v2/staffing/fte', [\App\Http\Controllers\Api\V2\CareOps\FteComplianceController::class, 'current']);
    Route::post('/v2/staffing/fte-project', [\App\Http\Controllers\Api\V2\CareOps\FteComplianceController::class, 'project']);
    Route::post('/v2/assignments/sspo-estimate', [\App\Http\Controllers\Api\V2\CareOps\AssignmentEstimationController::class, 'estimate']);
    Route::post('/v2/finance/shadow-billing', [\App\Http\Controllers\Api\V2\Finance\ShadowBillingController::class, 'generate']);
    Route::post('/v2/ai/forecast', [\App\Http\Controllers\Api\V2\AiForecastController::class, 'forecast']);

    Route::get('/patients/{patient}/tnp', [\App\Http\Controllers\Api\V2\TnpController::class, 'show']);
    Route::post('/patients/{patient}/tnp', [\App\Http\Controllers\Api\V2\TnpController::class, 'store']);
    Route::put('/tnp/{tnp}', [\App\Http\Controllers\Api\V2\TnpController::class, 'update']);
    Route::post('/tnp/{tnp}/analyze', [\App\Http\Controllers\Api\V2\TnpController::class, 'analyze']);

    Route::get('/care-assignments', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'index']);
    Route::get('/care-assignments/{assignment}', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'show']);
    Route::post('/care-assignments', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'store']);
    Route::put('/care-assignments/{assignment}', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'update']);

    Route::apiResource('v2/care-plans', \App\Http\Controllers\Api\V2\CarePlanController::class);
    Route::get('v2/bundle-templates', [\App\Http\Controllers\Api\V2\BundleTemplateController::class, 'index']);
    Route::get('v2/bundle-templates/{id}', [\App\Http\Controllers\Api\V2\BundleTemplateController::class, 'show']);

    Route::apiResource('patients', \App\Http\Controllers\Api\PatientController::class);

    Route::get('/organization', [\App\Http\Controllers\Api\OrganizationController::class, 'show']);
    Route::put('/organization', [\App\Http\Controllers\Api\OrganizationController::class, 'update']);
});