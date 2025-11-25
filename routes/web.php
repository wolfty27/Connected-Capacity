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

// Root route - Serve SPA
Route::get('/', function () {
    return view('app');
});

Route::middleware('guest')->group(function () {
    Route::view('/login', 'app')->name('login');
});
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout']);

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    // API routes are handled in api.php
});

// SPA Catch-all (Must be last)
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');