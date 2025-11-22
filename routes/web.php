<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\UserController;

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
    // CC2 Routes (Migrated from RouteServiceProvider)
    Route::prefix('cc2')
        ->middleware(['web', 'auth', 'feature.flag:cc2.enabled', 'organization.context'])
        ->as('cc2.')
        ->group(base_path('routes/cc2.php'));
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