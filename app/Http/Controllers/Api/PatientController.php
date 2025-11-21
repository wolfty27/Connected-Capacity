<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $patients = collect([]);

            // Basic role-based filtering (adapting from Legacy/PatientsController)
            if ($user->role == 'hospital') {
                $hospitalObj = Hospital::where('user_id', $user->id)->first();
                if ($hospitalObj) {
                    $patients = Patient::where('hospital_id', $hospitalObj->id)->get();
                }
            } elseif ($user->role == 'retirement-home') {
                $patients = Patient::where('status', '!=', 'Inactive')
                    ->where('status', '!=', 'Placement Made')
                    ->get();
            } else {
                // Admin or other roles see available patients (or all for now for debugging)
                $patients = Patient::all();
            }

            // Transform data for API
            $data = $patients->map(function ($patient) {
                $userObj = User::find($patient->user_id);
                $hospitalObj = Hospital::find($patient->hospital_id);
                $hospitalUser = $hospitalObj ? User::find($hospitalObj->user_id) : null;

                return [
                    'id' => $patient->id,
                    'name' => $userObj ? $userObj->name : 'Unknown',
                    'photo' => $userObj ? $userObj->image : '/assets/images/patients/default.png',
                    'gender' => $patient->gender,
                    'status' => $patient->status,
                    'hospital' => $hospitalUser ? $hospitalUser->name : 'Unknown',
                    'created_at' => $patient->created_at,
                ];
            });

            return response()->json(['data' => $data]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $patient = Patient::find($id);
            if (!$patient) {
                return response()->json(['error' => 'Patient not found'], 404);
            }

            $userObj = User::find($patient->user_id);

            return response()->json([
                'data' => [
                    'id' => $patient->id,
                    'name' => $userObj ? $userObj->name : 'Unknown',
                    'email' => $userObj ? $userObj->email : '',
                    'phone' => $userObj ? $userObj->phone_number : '',
                    'gender' => $patient->gender,
                    'status' => $patient->status,
                    'photo' => $userObj ? $userObj->image : '/assets/images/patients/default.png',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
