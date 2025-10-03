<?php

namespace App\Http\Controllers;

use App\Models\AssessmentForm;
use App\Models\Booking;
use App\Models\Hospital;
use App\Models\NewHospital;
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
        try {
            $data = [];
            if (Auth::user()->role == 'hospital') {
                $hospitalObj = Hospital::where('user_id', Auth::user()->id)->first();
                $patientsObj = Patient::where('hospital_id', $hospitalObj->id)->get();
            }
            elseif (Auth::user()->role == 'retirement-home')
            {
                $patientsObj = Patient::where('status', '!=' ,'Inactive')->where('status', '!=' ,'Placement Made')->get();
            }
            else
            {
                $patientsObj = Patient::where('status' ,'Available')->get();

            }

            foreach ($patientsObj as $patient) {
                $userObj = User::where('id', $patient->user_id)->first();
                $patientHospitalObj = Hospital::where('id', $patient->hospital_id)->first();
                $hospitalUserObj = User::where('id', $patientHospitalObj->user_id)->first();

                $name = $userObj->name ?? '';
                $gender = $patient->gender ?? '';
                $status = $patient->status ?? '';
                $hospital = $hospitalUserObj->name ?? '<p style="color: red;">Deleted</p>';
                $photo = $userObj->image ?? '/assets/images/patients/default.png';
                // $patient_form = AssessmentForm::wherePatient_id($patient->id)->first();

                $patientData = [
                    'photo' => $photo,
                    'name' => $name,
                    'gender' => $gender,
                    'status' => $status,
                    'hospital' => $hospital,
                    'id' => $patient->id,
                    'calendly' => $patientHospitalObj->calendly ?? null,
                    // 'patient_form' => $patient_form
                ];


                $data[] = $patientData;
            }

            return view('patients.read', compact('data'));
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }

    public function create(Request $request)
    {
        try {
            if (Auth::user()->role == 'hospital') {
                $data = [];

                return view('patients.create', $data);
            }
            return Redirect::back()->with(['errors' => 'Only hospitals can create patient accounts.'])->withInput();
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
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

    public function view(Request $request, $patientId)
    {
        try {
            $patientObj = Patient::where('id', $patientId)->first();
            $patientHospitalObj = Hospital::where('id', $patientObj->hospital_id)->first();
            $userObj = User::where('id', $patientObj->user_id)->first();
            $patient_form = AssessmentForm::wherePatient_id($patientId)->first();
            if (!$patient_form) {
                $patient_form['secondary_contact_name'] = "";
                $patient_form['secondary_contact_relationship'] = "";
                $patient_form['secondary_contact_phone'] = "";
                $patient_form['secondary_contact_email'] = "";
                $patient_form['designated_alc'] = "";
                $patient_form['least_3_days'] = "";
                $patient_form['pcr_covid_test'] = "";
                $patient_form['post_acute'] = "";
                $patient_form['if_yes'] = "";
                $patient_form['length'] = "";
                $patient_form['npc'] = "";
                $patient_form['apc'] = "";
                $patient_form['bk'] = "";
                $patient_form = (object)$patient_form;
            }

            $data['name'] = $userObj->name;
            $data['gender'] = $patientObj->gender;
            $data['phone'] = $userObj->phone_number;
            $data['email'] = $userObj->email;
            $data['image'] = $userObj->image;
            $data['status'] = $patientObj->status;
            $data['patient_id'] = $patientObj->id;
            $data['patient_form'] = $patient_form;
            $data['calendly'] = $patientHospitalObj->calendly;
            // dd($data);

        return view('patients.patient-assesment-detail-view', compact('data'));
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }
    public function appointedView(Request $request, $patientId,$bookingId)
    {
        try {
            $userId = Auth::user()->id;
            $retirementHomeObj = RetirementHome::where('user_id', $userId)->first();
            $patientObj = Patient::where('id', $patientId)->first();
            $patientBooking = Booking::where('id', $bookingId)->first();
            $userObj = User::where('id', $patientObj->user_id)->first();
            $patient_form = AssessmentForm::wherePatient_id($patientId)->first();
            if (!$patient_form) {
                $patient_form['secondary_contact_name'] = "";
                $patient_form['secondary_contact_relationship'] = "";
                $patient_form['secondary_contact_phone'] = "";
                $patient_form['secondary_contact_email'] = "";
                $patient_form['designated_alc'] = "";
                $patient_form['least_3_days'] = "";
                $patient_form['pcr_covid_test'] = "";
                $patient_form['post_acute'] = "";
                $patient_form['if_yes'] = "";
                $patient_form['length'] = "";
                $patient_form['npc'] = "";
                $patient_form['apc'] = "";
                $patient_form['bk'] = "";
                $patient_form = (object)$patient_form;
            }

            $data['name'] = $userObj->name;
            $data['gender'] = $patientObj->gender;
            $data['phone'] = $userObj->phone_number;
            $data['email'] = $userObj->email;
            $data['image'] = $userObj->image;
            $data['status'] = $patientObj->status;
            $data['patient_id'] = $patientObj->id;
            $data['patient_form'] = $patient_form;
            $data['booking_id'] = $patientBooking->id;
            $data['booking_status'] = $patientBooking->status;


            $some['mytiers'] = [];
            $retirementHomeTiers = Tier::where('retirement_home_id', $retirementHomeObj->id)->get();
            foreach ($retirementHomeTiers as $Tiers)
            {
                $arr['tier_id'] = $Tiers->id;
                $arr['tier'] = $Tiers->tier;
                $arr['retirement_home_price'] = $Tiers->retirement_home_price;
                $arr['hospotal_price'] = $Tiers->hospital_price;
    
                $some['mytiers'][] = $arr;
            } 
            // dd($some, $data);

        return view('patients.confirm-patient')->with(['data'=>$data, 'some'=>$some]);
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
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
        try {
            $patientObj = Patient::where('id', $id)->first();
            $userObj = User::where('id', $patientObj->user_id)->first();

            $data['name'] = $userObj->name ?? '';
            $data['id'] = $patientObj->id ?? '';
            $data['gender'] = $patientObj->gender ?? '';

            return view('patients.edit', compact('data'));
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
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

    public function assessmentForm(Request $request, $patientId)
    {
        try{
            $patientObj = Patient::where('id', $patientId)->first();
            $userObj = User::where('id', $patientObj->user_id)->first();
            $patient_form = AssessmentForm::wherePatient_id($patientId)->first();
            if (!$patient_form) {
                $patient_form['secondary_contact_name'] = "";
                $patient_form['secondary_contact_relationship'] = "";
                $patient_form['secondary_contact_phone'] = "";
                $patient_form['secondary_contact_email'] = "";
                $patient_form['designated_alc'] = "";
                $patient_form['least_3_days'] = "";
                $patient_form['pcr_covid_test'] = "";
                $patient_form['post_acute'] = "";
                $patient_form['if_yes'] = "";
                $patient_form['length'] = "";
                $patient_form['npc'] = "";
                $patient_form['apc'] = "";
                $patient_form['bk'] = "";
                $patient_form = (object)$patient_form;
            }

            $data['name'] = $userObj->name;
            $data['gender'] = $patientObj->gender;
            $data['phone'] = $userObj->phone_number;
            $data['email'] = $userObj->email;
            $data['status'] = $patientObj->status;
            $data['patient_id'] = $patientObj->id;
            $data['patient_form'] = $patient_form;

            return view('patients.assessment-form', compact('data'));
        }
        catch (\Throwable $th) {
            return Redirect::back()->with(['errors' => $th->getMessage() . ' Please contact admin.'])->withInput();
        }
    }

    public function storeAssessmentForm(Request $request, $id)
    {
        // dd($request->apc);

        $request->validate([
            'secondary_contact_name' => "required",
            'secondary_contact_relationship' => "required",
            'secondary_contact_phone' => "required",
            'secondary_contact_email' => "required",
            'designated_alc' => "required",
            'least_3_days' => "required",
            'pcr_covid_test' => "required",
            'post_acute' => "required",
            'if_yes' => "required",
            'length' => "required",
            'npc' => "required",
            'apc' => "required",
            'bk' => "required",
        ]);

        try {
            $patient_form = AssessmentForm::wherePatient_id($id)->delete();
            $patient = Patient::find($id);
            $patient->status = 'Available';
            $patient->save();
            $assessment_form = AssessmentForm::Create([
                'patient_id' => $id,
                'secondary_contact_name' => $request->secondary_contact_name,
                'secondary_contact_relationship' => $request->secondary_contact_relationship,
                'secondary_contact_phone' => $request->secondary_contact_phone,
                'secondary_contact_email' => $request->secondary_contact_email,
                'designated_alc' => $request->designated_alc,
                'least_3_days' => $request->least_3_days,
                'pcr_covid_test' => $request->pcr_covid_test,
                'post_acute' => $request->post_acute,
                'if_yes' => $request->if_yes,
                'length' => $request->length,
                'npc' => implode(',', (array) $request->npc),
                'apc' => implode(',', (array) $request->apc),
                'bk' => implode(',', (array) $request->bk),
            ]);
            return Redirect::to('patients')->with(['success' => 'Assessment form has been submitted']);
        } catch (\Throwable $th) {
            return Redirect::back()->with(['errors' => $th->getMessage() . ' Please contact admin.'])->withInput();
        }
    }
    public function placedPatients(Request $request){
        try {
            $data = [];
            $patientsObj = Patient::where('status' ,'Placement Made')->get();
            // dd($patientsObj);


            foreach ($patientsObj as $patient) {
                $userObj = User::where('id', $patient->user_id)->first();
                $patientHospitalObj = Hospital::where('id', $patient->hospital_id)->first();
                // $hospitalUserObj = User::where('id', $patientHospitalObj->user_id)->first();
                // dd($hospitalUserObj);

                // $retirementHomeObj = RetirementHome::where('id', $patient->retirement_home_id)->first();
                // $retirementHomeUserObj = User::where('id', $retirementHomeObj->user_id)->first();


                $name = $userObj->name ?? '';
                $gender = $patient->gender ?? '';
                $status = $patient->status ?? '';
                // $hospital = $hospitalUserObj->name ?? '';
                // $retirmentHome = $retirementHomeUserObj->name ?? '';
                $photo = $userObj->image ?? '/assets/images/patients/default.png';

                // $patient_form = AssessmentForm::wherePatient_id($patient->id)->first();

                $patientData = [
                    'photo' => $photo,
                    'name' => $name,
                    'gender' => $gender,
                    'status' => $status,
                    // 'hospital' => $hospital,
                    // 'retirementHome' => $retirmentHome,
                    'id' => $patient->id,
                    'calendly' => $patientHospitalObj->calendly ?? null,
                    // 'patient_form' => $patient_form
                ];

                $data[] = $patientData;
            }

            return view('patients.placed-patients', compact('data'));
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }

    }
}
