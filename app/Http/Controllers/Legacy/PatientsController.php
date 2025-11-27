<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;

use App\Models\AssessmentForm;
use App\Models\Booking;
use App\Models\Hospital;

use App\Models\InPersonAssessment;
use App\Models\Patient;
use App\Models\RetirementHome;
use App\Models\User;
use App\Models\Tier;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class PatientsController extends Controller
{
    public function index(Request $request)
    {
        // Method body removed as view 'patients.read' is deleted
        return Redirect::route('dashboard');
    }

    public function create(Request $request)
    {
        // Method body removed as view 'patients.create' is deleted
        return Redirect::route('dashboard');
    }

    public function store(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'name' => 'required',
                'gender' => 'required|in:Male,Female',
                'image' => 'nullable|image|max:2048'
            ]);

            if ($validation->fails()) {
                return Redirect::back()->with(['errors' => $validation->errors()->first()])->withInput();
            } else {
                $filename = 'default.png';
                if ($request->hasFile('image')) {
                    $logo = $request->file('image');
                    $filename = time() . '.' . $logo->getClientOriginalExtension();
                    $logo->move(public_path('/assets/images/patients'), $filename);
                }

                $userObj = User::create([
                    'name' => $request->name,
                    'role' => 'patient',
                    'image' => '/assets/images/patients/' . $filename,
                ]);

                $hospitalObj = Hospital::where('user_id', Auth::user()->id)->first();
                Patient::create([
                    'user_id' => $userObj->id,
                    'status' => 'Inactive',
                    'hospital_id' => $hospitalObj->id,
                    'gender' => $request->gender
                ]);

                return Redirect::to('/patients')->with(['success' => 'Patient created successfully!']);
            }
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }

    public function appointedView(Request $request, $patientId, $bookingId)
    {
        // Method body removed as view 'patients.confirm-patient' is deleted
        return Redirect::route('dashboard');
    }

    public function delete(Request $request, $id)
    {
        try {
            $patientObj = Patient::where('id', $id)->first();
            $bookings = Booking::where('patient_id', $patientObj->id)->get();
            $inPersonAssessments = InPersonAssessment::all();
            foreach ($bookings as $booking) {
                if ($booking) {
                    $inPersonAssessment = $inPersonAssessments->where('booking_id', $booking->id)->first();
                    $inPersonAssessment != null ?? $inPersonAssessment->delete();
                    $booking->first()->delete();
                }
            }
            User::where('id', $patientObj->user_id)->first()->delete();
            $patientObj->delete();

            return Redirect::to('/patients');
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }

    public function edit(Request $request, $id)
    {
        // Method body removed as view 'patients.edit' is deleted
        return Redirect::route('dashboard');
    }

    public function update(Request $request, $id)
    {
        try {
            $patientObj = Patient::where('id', $id)->first();
            $userObj = User::where('id', $patientObj->user_id)->first();

            if ($request->has('name') && $userObj->name != $request->name) {
                $userObj->update(['name' => $request->name]);
            }

            if ($request->has('gender') && $patientObj->gender != $request->gender) {
                $patientObj->update(['gender' => $request->gender]);
            }

            if ($request->hasFile('image')) {
                $logo = $request->file('image');
                $filename = time() . '.' . $logo->getClientOriginalExtension();
                $logo->move(public_path('/assets/images/patients'), $filename);
                $userObj->update(['image' => '/assets/images/patients/' . $filename]);
            }

            $userObj->save();
            $patientObj->save();

            return Redirect::back()->with(['success' => 'Patient updated successfully!']);
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }

    public function placedPatients(Request $request)
    {
        // Method body removed as view 'patients.placed-patients' is deleted
        return Redirect::route('dashboard');
    }
}
