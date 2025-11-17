<?php

use App\Http\Controllers\CC2\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('dashboard');
