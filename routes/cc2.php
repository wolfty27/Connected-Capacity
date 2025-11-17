<?php

use App\Http\Controllers\CC2\LandingController;
use App\Http\Controllers\CC2\Organizations\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('dashboard');

Route::prefix('organizations')->name('organizations.')->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
});
