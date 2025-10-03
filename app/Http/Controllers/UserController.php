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

        try{
            $validator = Validator::make($request->all(), [
                'email' => 'required|exists:users,email',
                'password' => 'required'
            ]);
            if ($validator->fails())
            {
                return Redirect::back()->with(['errors' => $validator->errors()->first()])->withInput();
            }
            else
            {
                if (Auth::attempt(['email' => $request->email, 'password' => $request->password]))
                {
                    return Redirect::to('/dashboard');
                }
                else
                {
                    return Redirect::back()->with(['errors' => 'Invalid email or password'])->withInput();
                }
            }
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function logout (Request $request)
    {
        Auth::logout();
        Session::flush();
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
            'user_id', $userObj->id
        ]);
    }
}
