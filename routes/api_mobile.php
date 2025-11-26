<?php

use App\Http\Controllers\Api\Mobile\MobileWorklistController;
use App\Http\Controllers\Api\Mobile\MobileClockController;
use App\Http\Controllers\Api\Mobile\MobileNoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes
|--------------------------------------------------------------------------
|
| Per MOB-001: Mobile API routes for field staff applications
|
| These routes are designed for mobile clients and include:
| - Optimized payloads for limited bandwidth
| - Offline sync support
| - Geolocation tracking for clock in/out
|
| Base URL: /mobile/v1
|
*/

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Worklist Endpoints (MOB-002)
    |--------------------------------------------------------------------------
    */
    Route::prefix('worklist')->group(function () {
        Route::get('/', [MobileWorklistController::class, 'index']);
        Route::get('/today', [MobileWorklistController::class, 'today']);
        Route::get('/upcoming', [MobileWorklistController::class, 'upcoming']);
        Route::get('/{assignment}', [MobileWorklistController::class, 'show']);
        Route::get('/{assignment}/patient', [MobileWorklistController::class, 'patientDetails']);
        Route::get('/{assignment}/care-plan', [MobileWorklistController::class, 'carePlanSummary']);
    });

    /*
    |--------------------------------------------------------------------------
    | Clock In/Out Endpoints (MOB-003)
    |--------------------------------------------------------------------------
    */
    Route::prefix('assignments')->group(function () {
        Route::post('/{assignment}/clock-in', [MobileClockController::class, 'clockIn']);
        Route::post('/{assignment}/clock-out', [MobileClockController::class, 'clockOut']);
        Route::get('/{assignment}/clock-status', [MobileClockController::class, 'status']);
        Route::post('/{assignment}/location-ping', [MobileClockController::class, 'locationPing']);
    });

    /*
    |--------------------------------------------------------------------------
    | Note Submission Endpoints (MOB-004)
    |--------------------------------------------------------------------------
    */
    Route::prefix('notes')->group(function () {
        Route::get('/templates', [MobileNoteController::class, 'templates']);
        Route::post('/', [MobileNoteController::class, 'store']);
        Route::post('/batch', [MobileNoteController::class, 'batchStore']);
        Route::get('/pending-sync', [MobileNoteController::class, 'pendingSync']);
        Route::post('/{note}/acknowledge-sync', [MobileNoteController::class, 'acknowledgeSync']);
    });

    /*
    |--------------------------------------------------------------------------
    | User & Profile
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', function () {
        $user = auth()->user();
        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'organization_id' => $user->organization_id,
                'organization_name' => $user->organization?->name,
                'organization_role' => $user->organization_role,
                'is_field_staff' => in_array($user->organization_role, ['PSW', 'RN', 'RPN', 'OT', 'PT', 'SW']),
            ],
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Sync Status
    |--------------------------------------------------------------------------
    */
    Route::get('/sync-status', function () {
        return response()->json([
            'server_time' => now()->toIso8601String(),
            'api_version' => '1.0.0',
            'min_app_version' => '1.0.0',
        ]);
    });
});
