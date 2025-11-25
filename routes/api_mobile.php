<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Mobile\V1\VisitController;
use App\Http\Controllers\Api\Mobile\V1\TaskController;
use App\Http\Controllers\Api\Mobile\V1\NoteController;

/*
|--------------------------------------------------------------------------
| Mobile API Routes
|--------------------------------------------------------------------------
|
| Routes for the mobile field application.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/visits/today', [VisitController::class, 'index']);
    Route::patch('/visits/{visit}/clock-in', [VisitController::class, 'clockIn']);
    Route::patch('/visits/{visit}/clock-out', [VisitController::class, 'clockOut']);
    
    Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete']);
    
    Route::post('/notes', [NoteController::class, 'store']);
});
