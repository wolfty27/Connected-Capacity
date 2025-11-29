<?php

use App\Http\Controllers\Api\V2\SchedulingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Connected Capacity 2.1 API routes for scheduling and care management.
|
*/

Route::prefix('v2')->group(function () {

    // Scheduling endpoints
    Route::prefix('scheduling')->group(function () {
        // Unscheduled care requirements (powers Unscheduled Care panel)
        Route::get('/requirements', [SchedulingController::class, 'requirements']);

        // Patient timeline (patient-centric view)
        Route::get('/patient-timeline', [SchedulingController::class, 'patientTimeline']);

        // Assignment CRUD
        Route::post('/assignments', [SchedulingController::class, 'storeAssignment']);
        Route::patch('/assignments/{id}', [SchedulingController::class, 'updateAssignment']);
        Route::delete('/assignments/{id}', [SchedulingController::class, 'deleteAssignment']);

        // Validation & helpers
        Route::get('/validate', [SchedulingController::class, 'validateAssignment']);
        Route::get('/suggested-slots', [SchedulingController::class, 'suggestedSlots']);
    });

});
