<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hospital;
use App\Models\NewHospital;
use App\Models\Patient;
use App\Models\RetirementHome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role;

        if ($role == 'admin') {
            return $this->adminDashboard();
        } elseif ($role == 'hospital') {
            return $this->hospitalDashboard($user);
        } elseif ($role == 'retirement-home') {
            return $this->retirementHomeDashboard($user);
        }

        // For CC2 roles or unknown roles, return a basic structure or error
        // The SPA should handle navigation to specific CC2 dashboards based on role
        return response()->json(['message' => 'Dashboard data not available for this role.'], 403);
    }

    protected function adminDashboard()
    {
        $hospitalsCount = NewHospital::count();
        $retirementHomesCount = RetirementHome::count();
        $patientsCount = Patient::count();

        $hospitalVis = NewHospital::select(
            DB::raw("(COUNT(*)) as count"),
            DB::raw("MONTHNAME(created_at) as month_name")
        )
            ->whereYear('created_at', date('Y'))
            ->groupBy('month_name')
            ->get();

        $hospital_data = $hospitalVis->map(function ($data) {
            return [
                "count" => $data->count,
                "month_name" => $data->month_name
            ];
        });

        $RetirementHomeVis = RetirementHome::select(
            DB::raw("(COUNT(*)) as count"),
            DB::raw("MONTHNAME(created_at) as month_name")
        )
            ->whereYear('created_at', date('Y'))
            ->groupBy('month_name')
            ->get();

        $retirement_home_data = $RetirementHomeVis->map(function ($data) {
            return [
                "count" => $data->count,
                "month_name" => $data->month_name
            ];
        });

        return response()->json([
            'hospitalsCount' => $hospitalsCount,
            'retirementHomesCount' => $retirementHomesCount,
            'patientsCount' => $patientsCount,
            'countHospital' => $hospital_data,
            'countRetirementHome' => $retirement_home_data,
        ]);
    }

    protected function hospitalDashboard($user)
    {
        $hospitalObj = NewHospital::where('user_id', $user->id)->first();
        
        if (!$hospitalObj) {
             return response()->json(['error' => 'Hospital profile not found'], 404);
        }

        $patientsCount = Patient::where('hospital_id', $hospitalObj->id)->where('status', 'Available')->count();
        $appointmentCount = Booking::where('hospital_id', $hospitalObj->id)->where('status', 'In person Assessment')->whereNull('retirement_home_status')->count();
        $offerCount = Booking::where('hospital_id', $hospitalObj->id)->where('status', 'Application Progress')->whereRetirement_home_status('accepted')->count();

        $hospitalOffers = Booking::select(
            DB::raw("(COUNT(*)) as count"),
            DB::raw("MONTHNAME(updated_at) as month_name")
        )
            ->where('hospital_id', $hospitalObj->id)
            ->where('retirement_home_status', 'accepted')
            ->whereYear('updated_at', date('Y'))
            ->groupBy('month_name')
            ->get();

        $hospitalData = $hospitalOffers->map(function ($data) {
            return [
                "count" => $data->count,
                "month_name" => $data->month_name
            ];
        });

        return response()->json([
            'patientCount' => $patientsCount,
            'AppointmentCount' => $appointmentCount,
            'offerCount' => $offerCount,
            'hospitalData' => $hospitalData,
        ]);
    }

    protected function retirementHomeDashboard($user)
    {
        $retirementHomeObj = RetirementHome::where('user_id', $user->id)->first();

        if (!$retirementHomeObj) {
             return response()->json(['error' => 'Retirement Home profile not found'], 404);
        }

        $allpatientsCount = Patient::where('status', '!=', 'Inactive')->where('status', '!=', 'Placement Made')->count();
        $mypatientsCount = Booking::where('retirement_home_id', $retirementHomeObj->id)->where('status', 'accept')->count();
        $appointmentCount = Booking::where('retirement_home_id', $retirementHomeObj->id)->where('status', 'In person Assessment')->count();

        $retirementoffers = Patient::select(
            DB::raw("(COUNT(*)) as count"),
            DB::raw("MONTHNAME(updated_at) as month_name")
        )
            ->where('retirement_home_id', $retirementHomeObj->id)
            ->where('status', 'Placement Made')
            ->whereYear('updated_at', date('Y'))
            ->groupBy('month_name')
            ->get();

        $retirementHomeData = $retirementoffers->map(function ($data) {
            return [
                "count" => $data->count,
                "month_name" => $data->month_name
            ];
        });

        return response()->json([
            'mypatientCount' => $mypatientsCount,
            'allpatientCount' => $allpatientsCount,
            'appointmentCount' => $appointmentCount,
            'retirementHomeData' => $retirementHomeData,
        ]);
    }
}
