<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;

use App\Models\Booking;
use App\Models\Hospital;
use App\Models\InPersonAssessment;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\RetirementHome;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookingsController extends Controller
{

    public function index(Request $request)
    {
        try {
            $role = Auth::user()->role;

            return $role == 'hospital' ? $this->hospitalBookings() : ($role == 'retirement-home' ? $this->retirementHomeBookings() : ($role == 'admin' ? $this->adminBookings() : null));
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }

public function hospitalBookings()
    {
        try {
             $userId = Auth::user()->id;
            $hospitalObj = Hospital::where('user_id', $userId)->first();
            $bookings = Booking::with(['retirement.user','patient.user','assessment.tier'])->where('hospital_id', $hospitalObj->id)->where('status', 'Application Progress')->whereRetirement_home_status('accepted')->get()->reverse();
            return view('bookings.hospital')->with(["bookings"=>$bookings]);
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }

    public function retirementHomeBookings()
    {
        try {
            $retirementHome = RetirementHome::whereUser_id(Auth::user()->id)->first();
            $bookings = Booking::with('patient.user', 'hospital.user')->where('retirement_home_id', $retirementHome->id)->where('status','In person Assessment')->get()->reverse();
            return view('bookings.retirement_home')->with(['data' => $bookings]);
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage()])->withInput();
        }
    }

    public function adminBookings()
    {
        try {
            $bookings = Booking::all()->reverse();
            $patientsObj = Patient::all();
            $usersObj = User::all();
            $hospitalsObj = Hospital::all();
            $retirementHomesObj = RetirementHome::all();
            // dd($patientsObj);

            $data = [];
            foreach ($bookings as $booking) {
                $patientObj = $patientsObj->where('id', $booking->patient_id)->first();
                $patientUserObj = $usersObj->where('id', $patientObj->user_id)->first();
                $hospitalObj = $hospitalsObj->where('id', $booking->hospital_id)->first();
                $hospitalUserObj = $usersObj->where('id', $hospitalObj->user_id)->first();
                $retirementHomeObj = $retirementHomesObj->where('id', $booking->retirement_home_id)->first();
                $retirementHomeUserObj = $usersObj->where('id', $retirementHomeObj->user_id)->first();

                $patientName = $patientUserObj->name;
                $hospitalName = $hospitalUserObj->name;
                $retirementHomeName = $retirementHomeUserObj->name;
                $status = $patientObj->status;
                $tier = 'N/A';
                $price = 'N/A';
                $inPersonAssessmentObj = InPersonAssessment::where('booking_id', $booking->id)->first();
                if ($inPersonAssessmentObj) {
                    $tierObj = Tier::where('id', $inPersonAssessmentObj->tier_id)->first();
                    $tier = $tierObj->tier ?? 'N/A';
                    $price = $tierObj->hospital_price ?? 'N/A';
                }

                $data[] = [
                    'patient_name' => $patientName,
                    'hospital_name' => $hospitalName,
                    'retirement_home_name' => $retirementHomeName,
                    'status' => $status,
                    'tier' => $tier,
                    'price' => $price,
                    'patient_id' => $patientObj->id,
                    'booking_id' => $booking->id,
                ];
            }

            return view('bookings.admin', compact('data'));
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }

    public function bookAppointment(Request $request, $id = null)
    {
        try {
            $userId = Auth::user()->id;
            $retirementHomeObj = RetirementHome::where('user_id', $userId)->first();
            $patientId = $id ?? $request->route('patient');
            if (!$patientId) {
                throw new \InvalidArgumentException('No patient identifier provided for booking.');
            }

            $patientObj = Patient::where('id', $patientId)->first();
            if (!$patientObj) {
                throw new \InvalidArgumentException('Patient not found.');
            }
            // $patientObj->update(['status' => 'Application Progress']);
            // $patientObj->save();

            $bookingDate = Carbon::tomorrow();
            $slot = rand(1, 9);
            $bookingReference = Str::uuid()->toString();
            $startTime = $bookingDate->copy()->setTime(9, 0);
            $endTime = $startTime->copy()->addHour();

            Booking::create([
                'hospital_id' => $patientObj->hospital_id,
                'retirement_home_id' => $retirementHomeObj->id,
                'patient_id' => $patientObj->id,
                'date' => $bookingDate,
                'slot' => $slot,
                'status' => "In person Assessment",
                'start_time' => $startTime,
                'end_time' => $endTime,
                'event_uri' => 'manual-booking-' . $bookingReference,
                'invitee_uri' => 'manual-booking-invitee-' . $bookingReference,

            ]);

            return redirect('/bookings')->with([
                'success' => 'Appointment booked successfully for October 24, 2022 Tuesday 2:00PM',
            ]);
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }


    public function inPersonAssessment(Request $request, $id)
    {
        //do not touch this  code
        // dd(123);
        try {
            $data = ['booking_id' => $id];

            return view('bookings.in_person_assessment', compact('data'));
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.']);
        }
    }

    public function storeInPersonAssessment(Request $request)
    {
        // dd($request->all());
        try {

            $bookingObj = Booking::where('id', $request->booking_id)->first();
            $patientObj = Patient::where('id', $request->patient_id)->first();

            if ($request->status == 'accepted') {
                $bookingObj->update(['status' => 'Application Progress']);
                $bookingObj->update(['retirement_home_status' => 'accepted']);
            } 

            InPersonAssessment::create([
                'booking_id' => $request->booking_id,
                'assessed_care_level' => '1',
                'status' => $request->status,
                'tier_id' => $request->tier_id
            ]);

            return Redirect::to('/bookings')->with(['success' => 'Offer has been made!']);
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }
    public function rejectInPersonAssessment(Request $request)
    {
        try {

            $bookingObj = Booking::whereId($request->booking_id_rej)->delete();
            $bookingpatient = Booking::where('patient_id', $request->patient_id_rej)->count();
            if($bookingpatient < 1){
                $patientObj = Patient::where('id', $request->patient_id_rej)->first();
                $patientObj->update(['status' => 'Available']);
            }
            

            InPersonAssessment::create([
                'booking_id' => $request->booking_id_rej,
                'assessed_care_level' => '1',
                'status' => $request->status_rej,
            ]);

            return Redirect::to('/bookings')->with(['success' => 'Booking Rejected Successfully!']);
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }
    public function updateStatus(Request $request, $bookingId, $status)
    {
        try {
            $bookingObj = Booking::where('id', $bookingId)->first();
            $bookingObj->update(['status' => $status]);
            $patientObj = Patient::where('id', $bookingObj->patient_id)->first();

            if ($status == 'accept') {
                $patientObj->update(['status' => 'Placement Made']);
                $patientObj->update(['retirement_home_id' => $bookingObj->retirement_home_id]);
                
                //cancel all other bookings
                $bookings = Booking::where('patient_id', $patientObj->id)->where('status', null)->get();
                foreach ($bookings as $booking) {
                    $booking->first()->delete();
                }
            } 
            else {
                $bookingObj->delete();
                $patientObj->update(['status' => 'Available']);
            }

            return Redirect::back()->with(['success' => 'Status Updated Successfully!']);
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin'])->withInput();
        }
    }

    public function bookingView(Request $request, $id)
    {
        try {
            $bookingObj = Booking::where('id', $id)->first();
            $patientObj = Patient::where('id', $bookingObj->patient_id)->first();
            $patientUserObj = User::where('id', $patientObj->user_id)->first();
            $hospitalObj = Hospital::where('id', $bookingObj->hospital_id)->first();

            $image = $patientUserObj->image ?? '';
            $patientName = $patientUserObj->name ?? '';
            $gender = $patientObj->gender ?? '';
            $hospitalName = User::where('id', $hospitalObj->user_id)->first()->name;

            $retirementHomeName = 'N/A';
            if ($bookingObj->status == 'accept') {
                $retirementHomeObj = RetirementHome::where('id', $bookingObj->retirement_home_id)->first();
                $retirementHomeName = User::where('id', $retirementHomeObj->user_id)->first()->name;
            }

            $tier = 'N/A';
            $hospitalPrice = 'N/A';
            $retirementHomePrice = 'N/A';
            $inPersonAssessmentObj = InPersonAssessment::where('booking_id', $bookingObj->id)->first();
            if ($inPersonAssessmentObj) {
                $tierObj = Tier::where('id', $inPersonAssessmentObj->tier_id)->first();
                $tier = $tierObj->tier;
                $hospitalPrice = $tierObj->hospital_price;
                $retirementHomePrice = $tierObj->retirement_home_price;
            }

            $data['image'] = $image;
            $data['patient_name'] = $patientName;
            $data['gender'] = $gender;
            $data['retirement_home'] = $retirementHomeName;
            $data['hospital'] = $hospitalName;
            $data['status'] = $patientObj->status;
            $data['tier'] = $tier;
            $data['retirement_home_price'] = $retirementHomePrice;
            $data['hospital_price'] = $hospitalPrice;

            return view('bookings.view', compact('data'));
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.']);
        }
    }

    public function bookingHospital(Request $request)
    {
        try {
            $userId = Auth::user()->id;
            $hospitalObj = Hospital::where('user_id', $userId)->first();
            $bookings = Booking::with(['retirement.user','patient.user'])->where('hospital_id', $hospitalObj->id)->where('status', 'In person Assessment')->whereNull('retirement_home_status')->get()->reverse();
            return view('bookings.hospital_appointments')->with(["bookings"=>$bookings]);
        } catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
    }
}
