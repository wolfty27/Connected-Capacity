<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        // dd($request->code);
        return Redirect::to('/login');
    }

}
