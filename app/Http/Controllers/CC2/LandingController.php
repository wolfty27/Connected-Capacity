<?php

namespace App\Http\Controllers\CC2;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function __invoke(): View
    {
        return view('cc2.landing');
    }
}
