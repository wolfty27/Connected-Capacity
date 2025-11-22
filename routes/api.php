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

    Route::get('/patients/{patient}/tnp', [\App\Http\Controllers\Api\V2\TnpController::class, 'show']);
    Route::post('/patients/{patient}/tnp', [\App\Http\Controllers\Api\V2\TnpController::class, 'store']);
    Route::put('/tnp/{tnp}', [\App\Http\Controllers\Api\V2\TnpController::class, 'update']);
    Route::post('/tnp/{tnp}/analyze', [\App\Http\Controllers\Api\V2\TnpController::class, 'analyze']);

    Route::get('/care-assignments', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'index']);
    Route::post('/care-assignments', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'store']);
    Route::put('/care-assignments/{assignment}', [\App\Http\Controllers\Api\V2\CareOpsController::class, 'update']);

    Route::apiResource('patients', \App\Http\Controllers\Api\PatientController::class);
});