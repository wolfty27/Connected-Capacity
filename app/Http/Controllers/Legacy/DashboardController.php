<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\RetirementHome;
use App\Models\Booking;
use App\Models\NewHospital;
use App\Models\InPersonAssessment;
use App\Models\User;
use App\Models\Gallery;
use App\Models\Tier;
use App\Http\Controllers\ChartJSController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;


class DashboardController extends Controller
{
    public function index (Request $request)
    {
        try {
            $user = Auth::user();
            $data = [];

            $role = $user->role;

            if ($role == 'admin') {
                return $this->adminDashboard($request);
            } elseif ($role == 'hospital') {
                return $this->hospitalDashboard($request);
            } elseif ($role == 'retirement-home') {
                return $this->retirementHomeDashboard($request);
            } else {
                Auth::logout();
                Session::flush();
                return Redirect::to('/login')->with(['errors' => 'Invalid User']);
            }
        }
        catch (\Exception $e)
        {
            Auth::logout();
            Session::flush();
            return Redirect::to('/login')->with(['errors' => $e->getMessage().' Please contact admin.']);
        }
    }

    public function adminDashboard ($request)
    {
        try {
            $hospitalsObj = NewHospital::all();
            $hospitalsCount = $hospitalsObj->count();
            $retirementHomesObj = RetirementHome::all();
            $retirementHomesCount = $retirementHomesObj->count();
            $patientsObj = Patient::all();
            $patientsCount = $patientsObj->count();

            $hospitalVis = NewHospital::select(
                DB::raw("(COUNT(*)) as count"),
                DB::raw("MONTHNAME(created_at) as month_name")
            )
            ->whereYear('created_at', date('Y'))
            ->groupBy('month_name')
            ->get();
            $hospital_data = [];
            foreach($hospitalVis as $somedata){
                array_push($hospital_data,[
                    "count" => $somedata->count,
                    "month_name" => $somedata->month_name
                ]);
            }
            $RetirementHomeVis = RetirementHome::select(
                DB::raw("(COUNT(*)) as count"),
                DB::raw("MONTHNAME(created_at) as month_name")
            )
            ->whereYear('created_at', date('Y'))
            ->groupBy('month_name')
            ->get();
            $retirement_home_data = [];
            foreach($RetirementHomeVis as $retdata){
                array_push($retirement_home_data,[
                    "count" => $retdata->count,
                    "month_name" => $retdata->month_name
                ]);
            }
            $data = [
                'hospitalsCount' => $hospitalsCount,
                'retirementHomesCount' => $retirementHomesCount,
                'patientsCount' => $patientsCount,
                'countHospital' => $hospital_data,
                'countRetirementHome' => $retirement_home_data,
            ];
            return view('dashboard.dashboard', $data);
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }

    }

    public function hospitalDashboard ($request)
    {
        try{
            $hospitalObj = NewHospital::where('user_id', Auth::user()->id)->first();
            $patients = Patient::where('hospital_id', $hospitalObj->id)->where('status', 'Available');
            $appointments = Booking::with(['retirement.user','patient.user'])->where('hospital_id', $hospitalObj->id)->where('status', 'In person Assessment')->whereNull('retirement_home_status')->get()->reverse();
            $offers = Booking::with(['retirement.user','patient.user','assessment.tier'])->where('hospital_id', $hospitalObj->id)->where('status', 'Application Progress')->whereRetirement_home_status('accepted')->get()->reverse();

            $hospitalOffers = Booking::select(
                DB::raw("(COUNT(*)) as count"),
                DB::raw("MONTHNAME(updated_at) as month_name")
            )
            ->where('hospital_id', $hospitalObj->id)
            ->where('retirement_home_status', 'accepted')
            ->whereYear('updated_at', date('Y'))
            ->groupBy('month_name')
            ->get();

            $hospitalData = [];
            foreach($hospitalOffers as $rethomedata){
                array_push($hospitalData,[
                    "count" => $rethomedata->count,
                    "month_name" => $rethomedata->month_name
                ]);
            }
            $data = [
                'patientCount' => $patients->count(),
                'AppointmentCount' => $appointments->count(),
                'offerCount' => $offers->count(),
                'hospitalData' => $hospitalData,
            ];

            // dd($some);
            return view ('hospitals.dashboard', $data);
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function retirementHomeDashboard ($request)
    {
        try{
            $user = Auth::user();
            $retirementHomeObj = RetirementHome::where('user_id', $user->id)->first();
            $allpatientsObj = Patient::where('status', '!=' ,'Inactive')->where('status', '!=' ,'Placement Made')->get();
            $mypatientsObj = Booking::where('retirement_home_id', $retirementHomeObj->id)->where('status', 'accept')->get();
            $appointmentsObj = Booking::with('patient.user', 'hospital.user')->where('retirement_home_id', $retirementHomeObj->id)->where('status','In person Assessment')->get()->reverse();
            
            $retirementoffers = Patient::select(
                DB::raw("(COUNT(*)) as count"),
                DB::raw("MONTHNAME(updated_at) as month_name")
            )
            ->where('retirement_home_id', $retirementHomeObj->id)
            ->where('status', 'Placement Made')
            ->whereYear('updated_at', date('Y'))
            ->groupBy('month_name')
            ->get();

            // dd($retirementoffers);

            $retirementHomeData = [];
            foreach($retirementoffers as $rethomedata){
                array_push($retirementHomeData,[
                    "count" => $rethomedata->count,
                    "month_name" => $rethomedata->month_name
                ]);
            }            
            $data = [
                'mypatientCount' => $mypatientsObj->count(),
                'allpatientCount' => $allpatientsObj->count(),
                'appointmentCount' => $appointmentsObj->count(),
                'retirementHomeData' => $retirementHomeData,
            ];

            return view ('retirement_homes.dashboard', $data);
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

}
