<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Legacy\PatientsController;
use App\Http\Controllers\Legacy\BookingsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Restore default redirect for root
Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/dashboard');
    }
    return redirect('/login');
});

Route::get('/login', [UserController::class, 'showLoginForm'])->name('login');
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout']);

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
    
    // Restore legacy patient routes for tests
    Route::get('/patients', [PatientsController::class, 'index']);
    
    // Restore legacy bookings routes for tests
    Route::get('/bookings', [BookingsController::class, 'index']);

    // Temporary route for BookingCreationTest
    Route::middleware('web')->get('/test/bookings/{patient}', [BookingsController::class, 'bookAppointment']);
});

// SPA Catch-all (Must be last)
Route::get('/{any?}', function () {
    // If user is logged in, render SPA
    if (Auth::check()) {
        return view('app');
    }
    // Otherwise redirect to login
    return redirect('/login');
})->where('any', '.*');