<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Testing\Fluent\Concerns\Has;

class HospitalsController extends Controller
{

    public function index (Request $request)
    {
        try{
            $data = [];
            // $hospitalsObj = Hospital::all();
            $hospitalsObj = Hospital::where('deleted_at', null)->get();
            foreach ($hospitalsObj as $hospital)
            {
                $userObj = User::where('id', $hospital->user_id)->first();
                // dd($userObj);

                $logo = $userObj->image ?? '/assets/images/hospitals/default.jpg';
                $name = $userObj->name ?? '';
                $email = $userObj->email ?? '';
                $phone = $userObj->phone_number ?? '';
                $website = $hospital->website ?? '';

                $hospitalData = [
                    'logo' => $logo,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'website' => $website,
                    'id' => $hospital->id,
                ];

                $data[] = $hospitalData;
            }

            return view('hospitals.read', compact('data'));
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function create (Request $request)
    {
        $data = [];

        return view ('hospitals.create', $data);
    }

    public function store (Request $request)
    {
        // dd($request->all());
        try{
            $validation = Validator::make($request->all(), [
                'name' => 'required',
                'email' => 'required|unique:users,email',
                'website' => 'required|unique:hospitals,website',
                'phone' => 'required',
                'password' => 'required|confirmed|min:6',
                'logo' => 'nullable|image|max:2048',
                'address' => 'required',
                'city' => 'required',
                'state' => 'required',
                'country' => 'required',
                'zipcode' => 'required',
                'latitude' => 'required',
                'longitude' => 'required',                
            ]);

            if ($validation->fails())
            {
                return Redirect::back()->with(['errors' => $validation->errors()->first()])->withInput();
            }
            elseif($request->password !== $request->password_confirmation)
            {
                return Redirect::back()->with(['errors' => $validation->errors()->first()])->withInput();
            }
            else
            {
                $filename = 'default.jpg';
                if ($request->hasFile('logo'))
                {
                    $logo = $request->file('logo');
                    $filename = time().'.'.$logo->getClientOriginalExtension();
                    $logo->move(public_path('/assets/images/hospitals'),$filename);
                }

                $userObj = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'hospital',
                    'phone_number' => $request->phone,
                    'image' => '/assets/images/hospitals/'.$filename,
                    'address' => $request->address,
                    'city' => $request->city,
                    'state' => $request->state,
                    'country' => $request->country,
                    'zipcode' => $request->zipcode,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ]);

                Hospital::create([
                    'user_id' => $userObj->id,
                    'website' => $request->website
                ]);

                return Redirect::to('/hospitals')->with(['success' => 'Hospital registered successfully!']);
           }
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function view (Request $request, $id)
    {
        try{
            $hospitalObj = Hospital::where('id', $id)->first();
            if ($hospitalObj)
            {
                $userObj = User::where('id', $hospitalObj->user_id)->first();
                $data['name'] = $userObj->name;
                $data['email'] = $userObj->email;
                $data['website'] = $userObj->website ?? 'N/A';
                $data['phone'] = $userObj->phone_number;
                $data['logo'] = $userObj->image;
                $data['id'] = $hospitalObj->id;
                $data['calendly'] = $hospitalObj->calendly ?? 'N/A';

                $data['patients'] = [];
                $patients = Patient::where('hospital_id', $hospitalObj->id)->where('status', '!=' , 'Inactive')->get();
                $usersObj = User::all();
                $patientsInHospitals = [];
                foreach ($patients as $patient)
                {
                    $userObj = $usersObj->where('id', $patient->user_id)->first();
                    $photo = $userObj->image;
                    $name = $userObj->name;
                    $gender = $patient->gender;
                    $status = $patient->status;
                    $id = $patient->id;
                    $calendly = $hospitalObj->calendly ?? null;

                    $patientsInHospitals[] = [$photo, $name, $gender, $status, $id, $calendly];
                }

                $data['patients'] = $patientsInHospitals;

                return view('hospitals.view', compact('data'));
            }
            else
            {
                return Redirect::back()->with(['errors' => 'This hospital does not exist.'])->withInput();
            }
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function edit (Request $request, $id)
    {
        try{
            $hospitalObj = Hospital::where('id', $id)->first();
            $userObj = User::where('id', $hospitalObj->user_id)->first();

            $data['name'] = $userObj->name ?? '';
            $data['email'] = $userObj->email ?? '';
            $data['phone'] = $userObj->phone_number ?? '';
            $data['website'] = $hospitalObj->website ?? '';
            $data['id'] = $hospitalObj->id ?? '';
            $data['calendly'] = $hospitalObj->calendly ?? '';
            $data['logo'] = $userObj->image ?? '';
            $data['address'] = $userObj->address ?? '';
            $data['city'] = $userObj->city ?? '';
            $data['state'] = $userObj->state ?? '';
            $data['country'] = $userObj->country ?? '';
            $data['zipcode'] = $userObj->zipcode ?? '';
            $data['latitude'] = $userObj->latitude ?? '';
            $data['longitude'] = $userObj->longitude ?? '';

            return view('hospitals.edit', compact('data'));
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function update (Request $request, $id)
    {
        try{
            $hospitalObj = Hospital::where('id', $id)->first();
            $userObj = User::where('id', $hospitalObj->user_id)->first();

            if ($request->has('name') && $userObj->name != $request->name && $request->name != '')
            {
                $userObj->update(['name' => $request->name]);
            }

            if ($request->has('phone') && $userObj->phone_number != $request->phone && $request->phone != '' )
            {
                $userObj->update(['phone_number' => $request->phone]);
            }

            if ($request->hasFile('logo'))
            {
                $logo = $request->file('logo');
                $filename = time().'.'.$logo->getClientOriginalExtension();
                $logo->move(public_path('/assets/images/hospitals'),$filename);
                $userObj->update(['image' => '/assets/images/hospitals/'.$filename]);
            }

            if ($request->has('website') && $hospitalObj->website != $request->website && $request->website != '')
            {
                $hospitalObj->update(['website' => $request->website]);
            }

            // if ($userObj->role == 'hospital' && auth()->user()->id == $userObj->id)
            // {
            //     if ($request->has('calendly') && $request->calendly != '')
            //     {
            //         $hospitalObj->update(['calendly' => $request->calendly]);
            //     }
            // }
            if ($request->has('address') && $userObj->address != $request->address)
            {
                $userObj->update(['address' => $request->address]);
            }
    
            if ($request->has('city') && $userObj->city != $request->city)
            {
                $userObj->update(['city' => $request->city]);
            }
    
            if ($request->has('state') && $userObj->state != $request->state)
            {
                $userObj->update(['state' => $request->state]);
            }
            
            if ($request->has('country') && $userObj->country != $request->country)
            {
                $userObj->update(['country' => $request->country]);
            }        
    
            if ($request->has('zipcode') && $userObj->zipcode != $request->zipcode)
            {
                $userObj->update(['zipcode' => $request->zipcode]);
            }
    
            if ($request->has('latitude') && $userObj->latitude != $request->latitude)
            {
                $userObj->update(['latitude' => $request->latitude]);
            }
    
            if ($request->has('longitude') && $userObj->longitude != $request->longitude)
            {
                $userObj->update(['longitude' => $request->longitude]);
            }

            $userObj->save();
            $hospitalObj->save();

            return Redirect::back()->with(['success' => 'Hospital updated successfully!']);
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function delete (Request $request, $id)
    {
        try{
            $hospitalObj = Hospital::where('id', $id)->first();
            User::where('id', $hospitalObj->user_id)->first()->delete();
            $hospitalObj->delete();

            return Redirect::to('/hospitals');
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

}
