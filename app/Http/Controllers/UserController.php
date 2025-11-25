<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;


class UserController extends Controller
{
    public function loginView (Request $request)
    {
        return Auth::check() ? Redirect::to('/dashboard') : view('login');
    }

    public function login (Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Login attempt', ['email' => $request->email, 'wantsJson' => $request->wantsJson()]);
        try{
            $validator = Validator::make($request->all(), [
                'email' => 'required|exists:users,email',
                'password' => 'required'
            ]);
            if ($validator->fails())
            {
                \Illuminate\Support\Facades\Log::warning('Login validation failed', ['errors' => $validator->errors()]);
                if ($request->wantsJson()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }
                return Redirect::back()->with(['errors' => $validator->errors()->first()])->withInput();
            }
            else
            {
                if (Auth::attempt(['email' => $request->email, 'password' => $request->password]))
                {
                    \Illuminate\Support\Facades\Log::info('Login successful', ['user_id' => Auth::id()]);
                    $request->session()->regenerate();
                    if ($request->wantsJson()) {
                        return response()->json(['message' => 'Login successful', 'user' => Auth::user()], 200);
                    }
                    return Redirect::to('/dashboard');
                }
                else
                {
                    \Illuminate\Support\Facades\Log::warning('Login failed: Invalid credentials');
                    if ($request->wantsJson()) {
                        return response()->json(['errors' => ['email' => ['Invalid email or password']]], 422);
                    }
                    return Redirect::back()->with(['errors' => 'Invalid email or password'])->withInput();
                }
            }
        }
        catch (\Exception $e)
        {
            \Illuminate\Support\Facades\Log::error('Login exception', ['message' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['message' => $e->getMessage()]], 500);
            }
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function logout (Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Logged out successfully'], 200);
        }

        return redirect('/');
    }

    public function createAdmin (Request $request)
    {
        $userObj = User::create([
            'name' => 'admin',
            'email' => 'admin@mailinator.com',
            'password' => Hash::make('11111'),
            'role' => 'admin',
            'phone_number' => '03322121173',
            'country' => 'PK',
            'address' => 'test',
            'city' => 'KHI',
            'state' => 'Sindh',
            'timezone' => 'GMT+5'
        ]);
        Admin::create([
            'user_id' => $userObj->id
        ]);
    }
}
