<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Hospital;
use App\Models\RetirementHome;
use App\Models\User;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class MyProfileController extends Controller
{
    public function profile (Request $request)
    {
        try {
            $user = Auth::user();
            if ($user->role == 'admin')
            {
                return $this->adminProfile($request, $user);
            }
            elseif ($user->role == 'hospital')
            {
                return $this->hospitalProfile($request, $user);
            }
            elseif ($user->role == 'retirement-home')
            {
                return $this->retirementHomeProfile($request, $user);
            }
            else
            {
                return Redirect::back()->with(['errors' => 'Invalid Role']);
            }
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage()]);
        }
    }

    public function adminProfile ($request, $userObj)
    {
        $adminObj = Admin::where('user_id', $userObj->id)->first();

        $data['name'] = $userObj->name ?? '';
        $data['email'] = $userObj->email ?? '';
        $data['phone_number'] = $userObj->phone_number ?? '';
        $data['role'] = 'Admin';
        $data['zipcode'] = $userObj->zipcode ?? '';
        $data['address'] = $userObj->address ?? '';
        $data['country'] = $userObj->country ?? '';
        $data['city'] = $userObj->city ?? '';
        $data['timezone'] = $userObj->timezone ?? '';
        $data['latitude'] = $userObj->latitude ?? '';
        $data['longitude'] = $userObj->longitude ?? '';
        $data['user_id'] =  $userObj->id ?? '';
        $data['admin_id'] =  $adminObj->id ?? '';
        $data['image'] =  $userObj->image ?? '';


        return view('profiles.admin', compact('data'));
    }

    public function hospitalProfile ($request, $userObj)
    {
        $hospitalObj = Hospital::where('user_id', $userObj->id)->first();

        $data['name'] = $userObj->name ?? '';
        $data['email'] = $userObj->email ?? '';
        $data['phone_number'] = $userObj->phone_number ?? '';
        $data['website'] = $hospitalObj->website ?? '';
        $data['role'] = 'Hospital';
        $data['zipcode'] = $userObj->zipcode ?? '';
        $data['address'] = $userObj->address ?? '';
        $data['country'] = $userObj->country ?? '';
        $data['state'] = $userObj->state ?? '';
        $data['city'] = $userObj->city ?? '';
        $data['timezone'] = $userObj->timezone ?? '';
        $data['calendly'] = $hospitalObj->calendly ?? '';
        $data['latitude'] = $userObj->latitude ?? '';
        $data['longitude'] = $userObj->longitude ?? '';
        $data['user_id'] =  $userObj->id ?? '';
        $data['hospital_id'] =  $hospitalObj->id ?? '';
        $data['logo'] =  $userObj->image ?? '';


        return view('profiles.hospital', compact('data'));
    }

    public function retirementHomeProfile ($request, $userObj)
    {
        $retirementHomeObj = RetirementHome::where('user_id', $userObj->id)->first();
        $retirementHomeGalleryObj = Gallery::where('user_id', $userObj->id)->get();

        $data['name'] = $userObj->name ?? '';
        $data['email'] = $userObj->email ?? '';
        $data['phone_number'] = $userObj->phone_number ?? '';
        $data['website'] = $retirementHomeObj->website ?? '';
        $data['role'] = 'Retirement Home';
        $data['zipcode'] = $userObj->zipcode ?? '';
        $data['address'] = $userObj->address ?? '';
        $data['state'] = $userObj->state ?? '';
        $data['city'] = $userObj->city ?? '';
        $data['timezone'] = $userObj->timezone ?? '';
        $data['latitude'] = $userObj->latitude ?? '';
        $data['longitude'] = $userObj->longitude ?? '';
        $data['user_id'] =  $userObj->id ?? '';
        $data['logo'] =  $userObj->image ?? '';
        $data['retirement_home_id'] =  $retirementHomeObj->id ?? '';
        $data['type'] = $retirementHomeObj->type ?? '';
        $data['galleries'] = [];
        foreach ($retirementHomeGalleryObj as $gallery)
        {
            $arr['gallery_id'] = $gallery->id;
            $arr['gallery_user_id'] = $gallery->user_id;
            $arr['gallery_retirement_home_id'] = $gallery->retirement_home_id;
            $arr['gallery_image'] = $gallery->gallery_image;

            $data['galleries'][] = $arr;
        }        

        $retirementHomeControllerObj = new RetirementHomeController();
        $data['amenities_features'] = $retirementHomeControllerObj->amenitiesAndFeatures($retirementHomeObj);

        return view('profiles.retirement_home', compact('data'));
    }

    public function updateProfile (Request $request, $id)
    {
        // dd($request->all());
        try {
            $validation = Validator::make($request->all(), [
                // 'password' => 'nullable|confirmed',
                // 'phone' => 'required',
                // 'zipcode' => 'required',
                // 'address' => 'required',
                // 'country' => 'required',
                // 'timezone' => 'required',
                // 'city' => 'required',
                // 'latitude' => 'required',
                // 'longitude' => 'required',
            ]);

            if ($validation->fails())
            {
                return Redirect::back()->with(['errors' => $validation->errors()->first()])->withInput();
            }
            else
            {
                $userObj = User::where('id', $id)->first();

                if ($request->has('phone') && $userObj->phone_number != $request->phone) {
                    $userObj->update(['phone_number' => $request->phone]);
                }
                if ($request->has('zipcode') && $userObj->zipcode != $request->zipcode) {
                    $userObj->update(['zipcode' => $request->zipcode]);
                }
                if ($request->has('address') && $userObj->address != $request->address) {
                    $userObj->update(['address' => $request->address]);
                }

                if ($request->has('state') && $userObj->state != $request->state) {
                    $userObj->update(['state' => $request->state]);
                }

                if ($request->has('city') && $userObj->city != $request->city) {
                    $userObj->update(['city' => $request->city]);
                }

                if ($request->has('timezone') && $userObj->timezone != $request->timezone) {
                    $userObj->update(['timezone' => $request->timezone]);
                }

                if ($request->has('latitude') && $userObj->latitude != $request->latitude) {
                    $userObj->update(['latitude' => $request->latitude]);
                }

                if ($request->has('longitude') && $userObj->longitude != $request->longitude) {
                    $userObj->update(['longitude' => $request->longitude]);
                }

                $userObj->save();

                if ($userObj->role == 'hospital')
                {
                    return $this->updateHospital($request, $id);
                }
                elseif ($userObj->role == 'retirement-home')
                {
                    return $this->updateRetirementHome($request, $id);
                }
                elseif ($userObj->role == 'admin')
                {
                    return $this->updateAdmin($request, $id);
                }
                else
                {
                    return Redirect::back()->with(['errors' => 'Invalid role.'])->withInput();
                }
            }
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage()])->withInput();
        }
    }

    public function updateHospital ($request, $id)
    {
        try {
            $validation = Validator::make($request->all(), [
                'website' => 'required',
                // 'calendly' => 'required',
            ]);

            if ($validation->fails())
            {
                return Redirect::back()->with(['errors' => $validation->errors()->first()])->withInput();
            }
            else {
                $userObj = User::where('id', $id)->first();
                $hospitalObj = Hospital::where('user_id', $userObj->id)->first();

                //work on this
                // if ($request->hasFile('files')) {
                //     $logo = $request->file('files');
                //     $filename = time() . '.' . $logo->getClientOriginalExtension();
                //     $logo->move(public_path('/assets/images/hospitals'), $filename);
                //     $userObj->update(['image' => '/assets/images/hospitals/' . $filename]);
                // }

                if ($request->hasFile('logo'))
                {
                    $logo = $request->file('logo');
                    $filename = time().'.'.$logo->getClientOriginalExtension();
                    $logo->move(public_path('/assets/images/hospital'),$filename);
                    $userObj->update(['image' =>'/assets/images/hospital/'.$filename]);
                }                  

                if ($request->has('website') && $hospitalObj->website != $request->website) {
                    $hospitalObj->update(['website' => $request->website]);
                }
                if ($userObj->role == 'hospital' && auth()->user()->id == $userObj->id) {
                    if ($request->has('password') && $request->has('password_confirmation')) {
                        if ($request->password == $request->password_confirmation) {
                            $userObj->update(['password' => Hash::make($request->password)]);
                        } else {
                            return Redirect::back()->with(['errors' => 'Password and Confirm Password do not match.'])->withInput();
                        }
                    }
                    // if ($request->has('calendly')) {
                    //     $hospitalObj->update(['calendly' => $request->calendly]);
                    // }
                }

                $userObj->save();
                $hospitalObj->save();

                return Redirect::back()->with(['success' => 'Profile updated successfully!'])->withInput();
            }
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage()])->withInput();
        }
    }
    public function uploadGallery(Request $request, $id){
        // dd($request->all());
        try{
            $userObj = User::where('id', $id)->first();
            $retirementHomeObj = RetirementHome::where('user_id', $userObj->id)->first();

            // code by wali
            $image = array();
            if($files = $request->file('gallery_images')){
                foreach ($files as $file) { 
                    $image_name = md5(rand(1000, 10000));
                    $ext = strtolower($file->getClientOriginalExtension());
                    $image_full_name = $image_name.'.'.$ext;
                    $upload_path = public_path('/assets/images/retirement-homes/');
                    $image_url = '/assets/images/retirement-homes/'.$image_full_name;
                    $file->move($upload_path, $image_full_name);
                    Gallery::insert([
                        'user_id' => $userObj->id,
                        'retirement_home_id' => $retirementHomeObj->id,
                        'gallery_image' => $image_url,
                    ]);
                }
            }
            return Redirect::back()->with(['success' => 'Gallery updated successfully!'])->withInput();
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage()])->withInput();
        }
        
    }
    public function updateRetirementHome ($request, $id)
    {
        try {
            $validation = Validator::make($request->all(), [
                'website' => 'required',
            ]);

            if ($validation->fails())
            {
                return Redirect::back()->with(['errors' => $validation->errors()->first()])->withInput();
            }
            else {
                $userObj = User::where('id', $id)->first();
                $retirementHomeObj = RetirementHome::where('user_id', $userObj->id)->first();

                //work on this
                // if ($request->hasFile('files')) {
                //     $logo = $request->file('files');
                //     $filename = time() . '.' . $logo->getClientOriginalExtension();
                //     $logo->move(public_path('/assets/images/hospitals'), $filename);
                //     $userObj->update(['image' => '/assets/images/hospitals/' . $filename]);
                // }
                if ($request->hasFile('logo'))
                {
                    $logo = $request->file('logo');
                    $filename = time().'.'.$logo->getClientOriginalExtension();
                    $logo->move(public_path('/assets/images/retirement-homes'),$filename);
                    $userObj->update(['image' =>'/assets/images/retirement-homes/'.$filename]);
                }                 

                if ($request->has('website') && $retirementHomeObj->website != $request->website) {
                    $retirementHomeObj->update(['website' => $request->website]);
                }
                if ($userObj->role == 'retirement-home' && auth()->user()->id == $userObj->id) {
                    if ($request->has('password') && $request->has('password_confirmation')) {
                        if ($request->password == $request->password_confirmation) {
                            $userObj->update(['password' => Hash::make($request->password)]);
                        } else {
                            return Redirect::back()->with(['errors' => 'Password and Confirm Password do not match.'])->withInput();
                        }
                    }
                }

                $retirementHomeControllerObj = new RetirementHomeController();
                $retirementHomeControllerObj->updatingAmenitiesAndFeatures($request, $userObj, $retirementHomeObj);

                $userObj->save();
                $retirementHomeObj->save();

                return Redirect::back()->with(['success' => 'Profile updated successfully!'])->withInput();
            }
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage()])->withInput();
        }
    }

    public function updateAdmin ($request, $id)
    {
        try {
            $userObj = User::where('id', $id)->first();
            if ($userObj->role == 'admin' && auth()->user()->id == $userObj->id) {
                if ($request->has('password') && $request->has('password_confirmation')) {
                    if ($request->password == $request->password_confirmation) {
                        $userObj->update(['password' => Hash::make($request->password)]);
                    } else {
                        return Redirect::back()->with(['errors' => 'Password and Confirm Password do not match.'])->withInput();
                    }
                }

                if ($request->hasFile('logo'))
                {
                    $logo = $request->file('logo');
                    $filename = time().'.'.$logo->getClientOriginalExtension();
                    $logo->move(public_path('/assets/images/admin'),$filename);
                    $userObj->update(['image' =>'/assets/images/admin/'.$filename]);
                }                
                
            }

            $userObj->save();

            return Redirect::back()->with(['success' => 'Profile updated successfully!'])->withInput();
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage()])->withInput();
        }
    }

    public function deleteGallery(Request $request, $id){
        try{
            $galleryObj = Gallery::find($id);
            $galleryObj->delete();
            
            return response()->json([
                'status'=>200,
                'message'=>"Image Deleted Successfully"
            ]);
            // return Redirect::back()->with(['success' => 'Image Deleted Successfully!']);
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function getGallery(Request $request){
        $user = Auth::user();
        $gallery = Gallery::where('user_id', $user->id)->get();
        return response()->json([
            'gallery'=>$gallery
        ]);

    }
    public function getGalleryForAdmin(Request $request, $id){
        $gallery = Gallery::where('user_id', $id)->get();
        return response()->json([
            'gallery'=>$gallery
        ]);

    }
    public function changePassword(Request $request){
        try{
            $userObj = User::where('id', Auth::user()->id)->first();

            $data['id'] = $userObj->id;
            $data['name'] = $userObj->name;
            $data['email'] = $userObj->email;
            $data['password'] = $userObj->password;

            return view('profiles.change_password', compact('data'));

        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }        
        
    }
    
    public function updatePassword(Request $request, $id){
        try {
            $userObj = User::where('id', $id)->first();
            if(Hash::check($request->current_password, $userObj->password) == true){
                if($request->new_password == $request->confirm_password){
                    $userObj->update(['password' => Hash::make($request->new_password)]);
                }
                else{
                    return Redirect::back()->with(['errors' => 'Password and Confirm Password do not match.'])->withInput();
                }
            }
            $userObj->save();

            return Redirect::back()->with(['success' => 'Password updated successfully!'])->withInput();
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage()])->withInput();
        }        
    }
}
