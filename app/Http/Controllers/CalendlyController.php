<?php

namespace App\Http\Controllers;

use App\Models\AssessmentForm;
use App\Models\Booking;
use App\Models\Hospital;
use App\Models\InPersonAssessment;
use App\Models\Patient;
use App\Models\RetirementHome;
use App\Models\User;
use App\Models\Tier;
use App\Models\Calendly;
use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class CalendlyController extends Controller
{
    public function index(Request $request)
    {
        try{
            $user = Auth::user();
            $userObj = User::where('id', $user->id)->first();
            $hospitalObj = Hospital::where('user_id', $user->id)->first();
            $calendlyObj = Calendly::where('hospital_id', $hospitalObj->id)->first();
            if($calendlyObj)
            {
                return Redirect::back()->with(['error' => 'Calendly Account Already Exist !']);
            }
            $code = $request->code;
            $grant_type = "authorization_code";
            $redirect_uri = config('calendly.redirect_uri');
            $client_id = config('calendly.client_id');
            $client_secret = config('calendly.client_secret_id');
    
            $url = config('calendly.calendly_auth_base_url').'oauth/token';
            $header = array(
                // "Authorization" => "Basic ".base64_encode($client_id.':'.$client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded'
            );
    
            $data_array = array(
                'grant_type' => $grant_type,
                'code' => $code,
                'redirect_uri' => $redirect_uri
            );
            $data = http_build_query($data_array);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $client_id.':'.$client_secret);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    
            $response = json_decode(curl_exec($ch));
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // SECOND API HIT
            $url2 = $response->owner;
            $headers = [];
            $headers[] = "Content-Type:application/x-www-form-urlencoded";
            $headers[] = "Authorization: Bearer ".$response->access_token;
    
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $url2);
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch2, CURLOPT_ENCODING, "");
            curl_setopt($ch2, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    
            $response2 = json_decode(curl_exec($ch2));
            $httpcode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $error2 = curl_error($ch2);
            curl_close($ch2);
            // END

            Calendly::create([
                'hospital_id' => $hospitalObj->id,
                'code' => $code,
                'access_token' => $response->access_token,
                'refresh_token' => $response->refresh_token,
                'token_type' => $response->token_type,
                'token_created_at' => $response->created_at,
                'expires_in' => $response->expires_in,
                'organization' => $response->organization,
                'owner' => $response->owner
            ]);

            $userObj->update([
                'calendly_status' => "1",
                'calendly_username' => $response2->resource->name
            ]);

            $hospitalObj->update([
                'calendly' => $response2->resource->slug
            ]);

            return Redirect::back()->with(['success' => 'Calendly Connected Successfully!'])->withInput();
            
        }
        catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }

    }

    public function getEvents(Request $request){
        try{
            $user = Auth::user();
            $hospitalObj = Hospital::where('user_id', $user->id)->first();
            $calendlyObj = Calendly::where('hospital_id', $hospitalObj->id)->first();

            $grant_type = "authorization_code";
            $redirect_uri = config('calendly.redirect_uri');
            $client_id = config('calendly.client_id');
            $client_secret = config('calendly.client_secret_id');
    
            $url = config('calendly.calendly_api_base_url').'event_types';
            $headers = [];
            $headers[] = "Content-Type:application/x-www-form-urlencoded";
            $headers[] = "Authorization: Bearer ".$calendlyObj->access_token;
    
            $data_array = array(
                "organization" => $calendlyObj->organization,
                "user" => $calendlyObj->owner,
                "active" => "true",
                "count" => "20",
                "sort" => "name"
            );
            $data = http_build_query($data_array);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, "");
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    
            $response = json_decode(curl_exec($ch));
            $error = curl_error($ch);
            curl_close($ch);
            // dd($response);
        }
        catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }

        return view('hospitals.calendly_ui', compact('response'));
    }

    public function getScheduledEvents(Request $request){
        try{
            $user = Auth::user();
            $hospitalObj = Hospital::where('user_id', $user->id)->first();
            $calendlyObj = Calendly::where('hospital_id', $hospitalObj->id)->first();
            if(!$calendlyObj){
              return Redirect::back()->with(['errors' =>' Kindly Connect Calendly.'])->withInput();
            }
            $grant_type = "authorization_code";
            $redirect_uri = config('calendly.redirect_uri');
            $client_id = config('calendly.client_id');
            $client_secret = config('calendly.client_secret_id');
    
            $url = config('calendly.calendly_api_base_url').'scheduled_events';
            $headers = [];
            $headers[] = "Content-Type:application/x-www-form-urlencoded";
            $headers[] = "Authorization: Bearer ".$calendlyObj->access_token;
    
            $data_array = array(
                "count" => "20",
                "min_start_time" => "20",
                "organization" => $calendlyObj->organization,
                "status" => "active",
                "user" => $calendlyObj->owner
                // "invitee_email" => "",
                // "max_start_time" => "",
                // "page_token" => "",
                // "sort" => "",
            );
            $data = http_build_query($data_array);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, "");
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $response = json_decode(curl_exec($ch));
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if($httpcode != "200"){
                return Redirect::back()->with(['errors' => $response->message. ', kindly connect Calendly or contact your admin.'])->withInput();
            }
        }
        catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
        return view('hospitals.calendly_ui', compact('response'));
    }

    public function getInviteeData(Request $request ){
        try{
            $user = Auth::user();
            $hospitalObj = Hospital::where('user_id', $user->id)->first();
            $calendlyObj = Calendly::where('hospital_id', $hospitalObj->id)->first();
    
            $url = $request->uri.'/invitees';
            $headers = [];
            $headers[] = "Content-Type:application/x-www-form-urlencoded";
            $headers[] = "Authorization: Bearer ".$calendlyObj->access_token;
            
            // FIRST API HIT
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, "");
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    
            $response1 = json_decode(curl_exec($ch));
            $httpcode1= curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            // END

            // SECOND API HIT
            $url2 = $request->uri;
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $url2);
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, "");
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch2, CURLOPT_ENCODING, "");
            curl_setopt($ch2, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    
            $response2 = json_decode(curl_exec($ch2));
            $httpcode2= curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $error2 = curl_error($ch2);
            curl_close($ch2);
            // END
        }
        catch (\Exception $e) {
            return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }
        return response()->json([
            'inviteeData'=>$response1,
            'scheduledEventData'=>$response2,
            'httpcode'=>$httpcode2
        ]);    
    }

    public function bookCalendlyAppointment(Request $request){
        try {
            $userId = Auth::user()->id;
            $retirementHomeObj = RetirementHome::where('user_id', $userId)->first();
            $patientObj = Patient::where('id', $request->patientID)->first();
            $calendlyObj = Calendly::where('hospital_id', $patientObj->hospital_id)->first();


            $headers = [];
            $headers[] = "Content-Type:application/x-www-form-urlencoded";
            $headers[] = "Authorization: Bearer ".$calendlyObj->access_token;
            
            // FIRST API HIT
            $url1 = $request->eventUri;
            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, $url1);
            curl_setopt($ch1, CURLOPT_POST, true);
            curl_setopt($ch1, CURLOPT_POSTFIELDS, "");
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch1, CURLOPT_ENCODING, "");
            curl_setopt($ch1, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch1, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch1, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    
            $response1 = json_decode(curl_exec($ch1));
            $httpcode1= curl_getinfo($ch1, CURLINFO_HTTP_CODE);
            $error = curl_error($ch1);
            curl_close($ch1);
            // END

            // SECOND API HIT
            $url2 = $request->inviteeUri;
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $url2);
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, "");
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch2, CURLOPT_ENCODING, "");
            curl_setopt($ch2, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    
            $response2 = json_decode(curl_exec($ch2));
            $httpcode2= curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $error2 = curl_error($ch2);
            curl_close($ch2);
            // END
            
            if($httpcode2 != "200"){
               $msg =  $this->refreshToken($request, $calendlyObj->refresh_token);
               if($msg === "succces"){

                $ssTime = new Carbon($response1->resource->start_time);
                $eeTime = new Carbon($response1->resource->end_time);

                Booking::create([
                    'hospital_id' => $patientObj->hospital_id,
                    'retirement_home_id' => $retirementHomeObj->id,
                    'patient_id' => $request->patientID,
                    'start_time' => $ssTime,
                    'end_time' => $eeTime,
                    'event_uri' => $request->eventUri,
                    'invitee_uri' => $request->inviteeUri,
                    'status' => "In person Assessment",

                ]);                
               }
            }
            else{
                $sTime = new Carbon($response1->resource->start_time);
                $eTime = new Carbon($response1->resource->end_time);

                Booking::create([
                    'hospital_id' => $patientObj->hospital_id,
                    'retirement_home_id' => $retirementHomeObj->id,
                    'patient_id' => $request->patientID,
                    'start_time' => $sTime,
                    'end_time' => $eTime,
                    'event_uri' => $request->eventUri,
                    'invitee_uri' => $request->inviteeUri,                    
                    'status' => "In person Assessment"
                ]); 
            }

            return response()->json([
                'msg' => "Appointment booked successfully"
            ]);       

            // return Redirect::to('/bookings')->with(['success' => 'Appointment booked successfully for October 24, 2022 Tuesday 2:00PM']);
        } catch (\Exception $e) {

            return response()->json([
                'msg' => $e->getMessage()
            ]); 
            // return Redirect::back()->with(['errors' => $e->getMessage() . ' Please contact admin.'])->withInput();
        }        

    }


    public function refreshToken(Request $request, $refresh_token){
        try{
            $calendlyObj = Calendly::where('refresh_token', $refresh_token)->first();
    
            $grant_type = "refresh_token";
            $redirect_uri = config('calendly.redirect_uri');
            $client_id = config('calendly.client_id');
            $client_secret = config('calendly.client_secret_id');
    
            $url = config('calendly.calendly_auth_base_url').'oauth/token';
            $header = array(
                // "Authorization" => "Basic ".base64_encode($client_id.':'.$client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded'
            );
    
            $data_array = array(
                'grant_type' => $grant_type,
                'refresh_token' => $refresh_token
            );
            $data = http_build_query($data_array);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $client_id.':'.$client_secret);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    
            $response = json_decode(curl_exec($ch));
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $calendlyObj->update([
                'access_token' => $response->access_token,
                'refresh_token' => $response->refresh_token,
                'token_type' => $response->token_type,
                'token_created_at' => $response->created_at,
                'expires_in' => $response->expires_in,
                'organization' => $response->organization,
                'owner' => $response->owner 
            ]);

            return "success";        
            
        }
        catch (\Exception $e) {
            return "fail";
        }
    }

    public function logoutCalendly(Request $request){

        $user = Auth::user();
        $userObj = User::where('id', $user->id)->first();
        $hospitalObj = Hospital::where('user_id', $user->id)->first();
        $calendlyObj = Calendly::where('hospital_id', $hospitalObj->id)->first();

        $userObj->update([
            'calendly_status' => null,
            'calendly_username' => null
        ]);
        $hospitalObj->update([
            'calendly' => null
        ]);
        $calendlyObj->delete();

        return Redirect::back()->with(['success' =>'Calendly Account Removed Successfully!']);
        
    }
}
