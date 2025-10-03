<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hospital;
use App\Models\InPersonAssessment;
use App\Models\Patient;
use App\Models\RetirementHome;
use App\Models\Tier;
use App\Models\User;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class RetirementHomeController extends Controller
{

    public function index (Request $request)
    {
        try{
            $data = [];
            $retirementHomesObj = RetirementHome::all();
            foreach ($retirementHomesObj as $retirementHome)
            {
                $userObj = User::where('id', $retirementHome->user_id)->first();
                $logo = $userObj->image ?? '/assets/images/retirement-homes/default.jpg';
                $name = $userObj->name ?? '';
                $email = $userObj->email ?? '';
                $phone = $userObj->phone_number ?? '';
                $website = $retirementHome->website ?? '';

                $retirementHomeData = [
                    'logo' => $logo,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'website' => $website,
                    'user_id' => $retirementHome->user_id,
                    'id' => $retirementHome->id,
                ];

                $data[] = $retirementHomeData;
            }

            return view('retirement_homes.read', compact('data'));
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function create (Request $request)
    {
        $data = [];

        return view('retirement_homes.create', $data);
    }

    public function store (Request $request)
    {
        // dd($request->independent);
        try{
            $validation = Validator::make($request->all(), [
                'name' => 'required',
                'password' => 'required|confirmed|min:6',
                'email' => 'required|unique:users,email',
                'website' => 'required|unique:hospitals,website',
                'tier.*' => 'required|distinct',
                'retirement_home_price.*' => 'required|integer',
                'hospital_price.*' => 'required|integer',
                'phone' => 'required',
                'address' => 'required',
                'city' => 'required',
                'state' => 'required',
                'country' => 'required',
                'zipcode' => 'required',
                'latitude' => 'required',
                'longitude' => 'required',
                'central_dining_room' => 'nullable',
                'private_dining_room' => 'nullable',
                'concierge' => 'nullable',
                'hairdresser' => 'nullable',
                'library' => 'nullable',
                'movie_theatre' => 'nullable',
                'pets_allowed' => 'nullable',
                'pool' => 'nullable',
                'special_outings' => 'nullable',
                'tuck_shop' => 'nullable',
                'bar' => 'nullable',
                'computer_lounge' => 'nullable',
                'gym' => 'nullable',
                'art_studio' => 'nullable',
                'sun_room' => 'nullable',
                'wellness_centre' => 'nullable',
                'religious_centre' => 'nullable',
                'outdoor_area' => 'nullable',
                'independent' => 'required',
                'logo' => 'nullable|image|max:2048'
            ]);

            if ($validation->fails())
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
                    $logo->move(public_path('/assets/images/retirement-homes'),$filename);
                }

                if ($request->has('tier') && sizeof($request->tier) > 0)
                {
                    $tiers = $request->tier;
                    $retirementHomePrices = $request->retirement_home_price;
                    $hospitalPrices = $request->hospital_price;
                    
                    foreach ($tiers as $index => $tier)
                    {
                        if ($hospitalPrices[$index] < $retirementHomePrices[$index])
                        {
                            return Redirect::back()->with(['errors' => 'Amount received from the hospital will always be greater than or equal to the amount received from the retirement home.'])->withInput();
                        }
                        
                    }

                }
                $userObj = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'retirement-home',
                    'phone_number' => $request->phone,
                    'image' => '/assets/images/retirement-homes/'.$filename,
                    'address' => $request->address,
                    'city' => $request->city,
                    'state' => $request->state,
                    'country' => $request->country,
                    'zipcode' => $request->zipcode,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ]);

                $retirementHomeObj = RetirementHome::create([
                    'user_id' => $userObj->id,
                    'website' => $request->website,
                    'status' => '1',
                    'central_dining_room' => $request->central_dining_room ? 1 : 0,
                    'private_dining_room' => $request->private_dining_room ? 1 : 0,
                    'concierge' => $request->concierge ? 1 : 0,
                    'hairdresser' => $request->hairdresser ? 1 : 0,
                    'library' => $request->library ? 1 : 0,
                    'movie_theatre' => $request->movie_theatre ? 1 : 0,
                    'pets_allowed' => $request->pets_allowed ? 1 : 0,
                    'pool' => $request->pool ? 1 : 0,
                    'special_outings' => $request->special_outings ? 1 : 0,
                    'tuck_shop' => $request->tuck_shop ? 1 : 0,
                    'bar' => $request->bar ? 1 : 0,
                    'computer_lounge' => $request->computer_lounge ? 1 : 0,
                    'gym' => $request->gym ? 1 : 0,
                    'art_studio' => $request->art_studio ? 1 : 0,
                    'sun_room' => $request->sun_room ? 1 : 0,
                    'wellness_centre' => $request->wellness_centre ? 1 : 0,
                    'religious_centre' => $request->religious_centre ? 1 : 0,
                    'outdoor_area' => $request->outdoor_area ? 1 : 0,
                    'type' => $request->independent,
                ]);

                if ($request->has('tier') && sizeof($request->tier) > 0)
                {
                    $tiers = $request->tier;
                    $retirementHomePrices = $request->retirement_home_price;
                    $hospitalPrices = $request->hospital_price;
                    foreach ($tiers as $index => $tier)
                    {
                        Tier::create([
                            'retirement_home_id' => $retirementHomeObj->id,
                            'tier' => $tier,
                            'retirement_home_price' => $retirementHomePrices[$index] ?? 0,
                            'hospital_price' => $hospitalPrices[$index] ?? 200,
                        ]);
                    }
                }

                return Redirect::to('/retirement-homes')->with(['success' => 'Retirement Home registered successfully!']);
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
            $retirementHomeObj = RetirementHome::where('id', $id)->first();
            $userObj = User::where('id', $retirementHomeObj->user_id)->first();
            $retirementHomeGalleryObj = Gallery::where('user_id', $retirementHomeObj->user_id)->get();


            $data['name'] = $userObj->name ?? '';
            $data['address'] = $userObj->address ?? '';
            $data['city'] = $userObj->city ?? '';
            $data['state'] = $userObj->state ?? '';
            $data['country'] = $userObj->country ?? '';
            $data['zipcode'] = $userObj->zipcode ?? '';
            $data['latitude'] = $userObj->latitude ?? '';
            $data['longitude'] = $userObj->longitude ?? '';
            $data['email'] = $userObj->email ?? '';
            $data['phone'] = $userObj->phone_number ?? '';
            $data['website'] = $retirementHomeObj->website ?? '';
            $data['id'] = $retirementHomeObj->id ?? '';
            $data['user_id'] = $retirementHomeObj->user_id ?? '';
            $data['amenities_features'] = $this->amenitiesAndFeatures($retirementHomeObj);
            $data['type'] = $retirementHomeObj->type ?? '';

            $tiers = Tier::where('retirement_home_id', $retirementHomeObj->id)->get();
            if ($tiers->count() > 0)
            {
                $arr = [];
                $count = 0;
                foreach ($tiers as $tier)
                {
                    $title = $tier->tier;
                    $retirementHomePrice = $tier->retirement_home_price;
                    $hospitalPrice = $tier->hospital_price;

                    $arr[$count] = [$title, $retirementHomePrice, $hospitalPrice];
                    $count++;
                }
                $data['tiers'] = $arr;
            }
            $data['galleries'] = [];
            foreach ($retirementHomeGalleryObj as $gallery)
            {
                $arr['gallery_id'] = $gallery->id;
                $arr['gallery_user_id'] = $gallery->user_id;
                $arr['gallery_retirement_home_id'] = $gallery->retirement_home_id;
                $arr['gallery_image'] = $gallery->gallery_image;
    
                $data['galleries'][] = $arr;
            }
            // dd($data);

            return view('retirement_homes.edit', compact('data'));
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function update (Request $request, $id)
    {
        // dd($request->type);

        try{

            $validation = Validator::make($request->all(), [
                // 'name' => 'required',
                // 'website' => 'required',
                // 'phone' => 'required',
                // 'address' => 'required',
                // 'city' => 'required',
                // 'state' => 'required',
                // 'zipcode' => 'required',
                // 'latitude' => 'required',
                // 'longitude' => 'required',
                // 'central_dining_room' => 'nullable',
                // 'private_dining_room' => 'nullable',
                // 'concierge' => 'nullable',
                // 'hairdresser' => 'nullable',
                // 'library' => 'nullable',
                // 'movie_theatre' => 'nullable',
                // 'pets_allowed' => 'nullable',
                // 'pool' => 'nullable',
                // 'special_outings' => 'nullable',
                // 'tuck_shop' => 'nullable',
                // 'bar' => 'nullable',
                // 'computer_lounge' => 'nullable',
                // 'gym' => 'nullable',
                // 'art_studio' => 'nullable',
                // 'sun_room' => 'nullable',
                // 'wellness_centre' => 'nullable',
                // 'religious_centre' => 'nullable',
                // 'outdoor_area' => 'nullable',
                // 'independent' => 'nullable',
                // 'independent_assisted' => 'nullable',
                // 'independent_assisted_care' => 'nullable',
                // 'logo' => 'nullable|image|max:2048',
                // 'tier.*' => 'required|distinct',
                // 'retirement_home_price.*' => 'required|integer',
                // 'hospital_price.*' => 'required|integer',
            ]);

            if ($validation->fails())
            {
                return Redirect::back()->with(['errors' => $validation->errors()->first()])->withInput();
            }
            else
            {
                $retirementHomeObj = RetirementHome::where('id', $id)->first();
                $userObj = User::where('id', $retirementHomeObj->user_id)->first();

                if ($request->has('name') && $userObj->name != $request->name)
                {
                    $userObj->update(['name' => $request->name]);
                }

                if ($request->has('phone') && $userObj->phone_number != $request->phone)
                {
                    $userObj->update(['phone_number' => $request->phone]);
                }

                if ($request->hasFile('logo'))
                {
                    $logo = $request->file('logo');
                    $filename = time().'.'.$logo->getClientOriginalExtension();
                    $logo->move(public_path('/assets/images/retirement-homes'),$filename);
                    $userObj->update(['image' => '/assets/images/retirement-homes/'.$filename]);
                }

                if ($request->has('website') && $retirementHomeObj->website != $request->website)
                {
                    $retirementHomeObj->update(['website' => $request->website]);
                }


                $this->updatingAddressFields($request, $userObj);

                $this->updatingAmenitiesAndFeatures($request, $userObj, $retirementHomeObj);

                if ($request->has('tier') && sizeof($request->tier) > 0)
                {
                    $tiers = $request->tier;
                    $retirementHomePrices = $request->retirement_home_price;
                    $hospitalPrices = $request->hospital_price;

                    $tiersObj = Tier::where('retirement_home_id', $retirementHomeObj->id)->get();
                    if (sizeof($tiersObj) == 0)
                    {
                        foreach ($tiers as $index => $tier)
                        {
                            Tier::create([
                                'retirement_home_id' => $retirementHomeObj->id,
                                'tier' => $tier,
                                'retirement_home_price' => $retirementHomePrices[$index] ?? 0,
                                'hospital_price' => $hospitalPrices[$index] ?? 200,
                            ]);
                        }
                    }
                    else
                    {
                        $oldTiers = $tiersObj->pluck('tier')->toArray();
                        foreach ($oldTiers as $oldTier)
                        {
                            if (!in_array($oldTier, $tiers))
                            {
                                Tier::where('retirement_home_id', $retirementHomeObj->id)->where('tier', $oldTier)->first()->delete();
                            }
                        }
                        foreach ($tiers as $index => $tier)
                        {
                            if (in_array($tier, $oldTiers))
                            {
                                $oldTierObj = Tier::where('retirement_home_id', $retirementHomeObj->id)->where('tier', $tier)->first();
                                if ($oldTierObj->retirement_home_price != $retirementHomePrices[$index])
                                {
                                    $oldTierObj->update(['retirement_home_price' => $retirementHomePrices[$index]]);
                                }
                                if ($oldTierObj->hospital_price != $hospitalPrices[$index])
                                {
                                    $oldTierObj->update(['hospital_price' => $hospitalPrices[$index]]);
                                }
                                $oldTierObj->save();
                            }
                            else
                            {
                                Tier::create([
                                    'retirement_home_id' => $retirementHomeObj->id,
                                    'tier' => $tier,
                                    'retirement_home_price' => $retirementHomePrices[$index] ?? -1,
                                    'hospital_price' => $hospitalPrices[$index] ?? -1,
                                ]);
                            }
                        }
                    }
                }

                return Redirect::back()->with(['success' => 'Retirement Home updated successfully!']);
            }
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function updatingAddressFields ($request, $userObj)
    {
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
    }

    public function updatingAmenitiesAndFeatures ($request, $userObj, $retirementHomeObj)
    {
        if ($request->central_dining_room && $retirementHomeObj->central_dining_room == 0)
        {
            $retirementHomeObj->update(['central_dining_room' => 1]);
        }
        elseif(!$request->central_dining_room && $retirementHomeObj->central_dining_room == 1)
        {
            $retirementHomeObj->update(['central_dining_room' => 0]);
        }

        if ($request->private_dining_room && $retirementHomeObj->private_dining_room == 0)
        {
            $retirementHomeObj->update(['private_dining_room' => 1]);
        }
        elseif(!$request->private_dining_room && $retirementHomeObj->private_dining_room == 1)
        {
            $retirementHomeObj->update(['private_dining_room' => 0]);
        }

        if ($request->concierge && $retirementHomeObj->concierge == 0)
        {
            $retirementHomeObj->update(['concierge' => 1]);
        }
        elseif(!$request->concierge && $retirementHomeObj->concierge == 1)
        {
            $retirementHomeObj->update(['concierge' => 0]);
        }

        if ($request->hairdresser && $retirementHomeObj->hairdresser == 0)
        {
            $retirementHomeObj->update(['hairdresser' => 1]);
        }
        elseif(!$request->hairdresser && $retirementHomeObj->hairdresser == 1)
        {
            $retirementHomeObj->update(['hairdresser' => 0]);
        }

        if ($request->library && $retirementHomeObj->library == 0)
        {
            $retirementHomeObj->update(['library' => 1]);
        }
        elseif(!$request->library && $retirementHomeObj->library == 1)
        {
            $retirementHomeObj->update(['library' => 0]);
        }

        if ($request->movie_theatre && $retirementHomeObj->movie_theatre == 0)
        {
            $retirementHomeObj->update(['movie_theatre' => 1]);
        }
        elseif(!$request->movie_theatre && $retirementHomeObj->movie_theatre == 1)
        {
            $retirementHomeObj->update(['movie_theatre' => 0]);
        }

        if ($request->pets_allowed && $retirementHomeObj->pets_allowed == 0)
        {
            $retirementHomeObj->update(['pets_allowed' => 1]);
        }
        elseif(!$request->pets_allowed && $retirementHomeObj->pets_allowed == 1)
        {
            $retirementHomeObj->update(['pets_allowed' => 0]);
        }

        if ($request->pool && $retirementHomeObj->pool == 0)
        {
            $retirementHomeObj->update(['pool' => 1]);
        }
        elseif(!$request->pool && $retirementHomeObj->pool == 1)
        {
            $retirementHomeObj->update(['pool' => 0]);
        }

        if ($request->special_outings && $retirementHomeObj->special_outings == 0)
        {
            $retirementHomeObj->update(['special_outings' => 1]);
        }
        elseif(!$request->special_outings && $retirementHomeObj->special_outings == 1)
        {
            $retirementHomeObj->update(['special_outings' => 0]);
        }

        if ($request->tuck_shop && $retirementHomeObj->tuck_shop == 0)
        {
            $retirementHomeObj->update(['tuck_shop' => 1]);
        }
        elseif(!$request->tuck_shop && $retirementHomeObj->tuck_shop == 1)
        {
            $retirementHomeObj->update(['tuck_shop' => 0]);
        }

        if ($request->bar && $retirementHomeObj->bar == 0)
        {
            $retirementHomeObj->update(['bar' => 1]);
        }
        elseif(!$request->bar && $retirementHomeObj->bar == 1)
        {
            $retirementHomeObj->update(['bar' => 0]);
        }

        if ($request->computer_lounge && $retirementHomeObj->computer_lounge == 0)
        {
            $retirementHomeObj->update(['computer_lounge' => 1]);
        }
        elseif(!$request->computer_lounge && $retirementHomeObj->computer_lounge == 1)
        {
            $retirementHomeObj->update(['computer_lounge' => 0]);
        }

        if ($request->gym && $retirementHomeObj->gym == 0)
        {
            $retirementHomeObj->update(['gym' => 1]);
        }
        elseif(!$request->gym && $retirementHomeObj->gym == 1)
        {
            $retirementHomeObj->update(['gym' => 0]);
        }

        if ($request->art_studio && $retirementHomeObj->art_studio == 0)
        {
            $retirementHomeObj->update(['art_studio' => 1]);
        }
        elseif(!$request->art_studio && $retirementHomeObj->art_studio == 1)
        {
            $retirementHomeObj->update(['art_studio' => 0]);
        }

        if ($request->sun_room && $retirementHomeObj->sun_room == 0)
        {
            $retirementHomeObj->update(['sun_room' => 1]);
        }
        elseif(!$request->sun_room && $retirementHomeObj->sun_room == 1)
        {
            $retirementHomeObj->update(['sun_room' => 0]);
        }

        if ($request->wellness_centre && $retirementHomeObj->wellness_centre == 0)
        {
            $retirementHomeObj->update(['wellness_centre' => 1]);
        }
        elseif(!$request->wellness_centre && $retirementHomeObj->wellness_centre == 1)
        {
            $retirementHomeObj->update(['wellness_centre' => 0]);
        }

        if ($request->religious_centre && $retirementHomeObj->religious_centre == 0)
        {
            $retirementHomeObj->update(['religious_centre' => 1]);
        }
        elseif(!$request->religious_centre && $retirementHomeObj->religious_centre == 1)
        {
            $retirementHomeObj->update(['religious_centre' => 0]);
        }

        if ($request->outdoor_area && $retirementHomeObj->outdoor_area == 0)
        {
            $retirementHomeObj->update(['outdoor_area' => 1]);
        }
        elseif(!$request->outdoor_area && $retirementHomeObj->outdoor_area == 1)
        {
            $retirementHomeObj->update(['outdoor_area' => 0]);
        }

        if ($request->type)
        {
            $retirementHomeObj->update(['type' => $request->type]);
        }
        // elseif ($request->cat_2)
        // {
        //     $retirementHomeObj->update(['type' => 'Independent & Assisted Living']);
        // }
        // elseif ($request->cat_1)
        // {
        //     $retirementHomeObj->update(['type' => 'Independent Living']);
        // }

        $userObj->save();
        $retirementHomeObj->save();
    }

    public function amenitiesAndFeatures ($retirementHomeObj)
    {
        $amenitiesAndFeatures = [];

        if($retirementHomeObj->central_dining_room == 1) $amenitiesAndFeatures[] = 'Central Dining Room';
        if($retirementHomeObj->private_dining_room == 1) $amenitiesAndFeatures[] = 'Private Dining Room';
        if($retirementHomeObj->concierge == 1) $amenitiesAndFeatures[] = 'Concierge';
        if($retirementHomeObj->hairdresser == 1) $amenitiesAndFeatures[] = 'Hairdresser/Barber Studio';
        if($retirementHomeObj->library == 1) $amenitiesAndFeatures[] = 'Library';
        if($retirementHomeObj->movie_theatre == 1) $amenitiesAndFeatures[] = 'Movie Theater';
        if($retirementHomeObj->pets_allowed == 1) $amenitiesAndFeatures[] = 'Pets Allowed';
        if($retirementHomeObj->pool == 1) $amenitiesAndFeatures[] = 'Pool';
        if($retirementHomeObj->special_outings == 1) $amenitiesAndFeatures[] = 'Special Outings';
        if($retirementHomeObj->tuck_shop == 1) $amenitiesAndFeatures[] = 'Tuck Shop';
        if($retirementHomeObj->bar == 1) $amenitiesAndFeatures[] = 'Bar/Lounge';
        if($retirementHomeObj->computer_lounge == 1) $amenitiesAndFeatures[] = 'Computer Lounge';
        if($retirementHomeObj->gym == 1) $amenitiesAndFeatures[] = 'Gym/Fitness Room';
        if($retirementHomeObj->art_studio == 1) $amenitiesAndFeatures[] = 'Hobby/Art Studio';
        if($retirementHomeObj->sun_room == 1) $amenitiesAndFeatures[] = 'Sun Room';
        if($retirementHomeObj->wellness_centre == 1) $amenitiesAndFeatures[] = 'Wellness Centre';
        if($retirementHomeObj->religious_centre == 1) $amenitiesAndFeatures[] = 'Religious Centre';
        if($retirementHomeObj->outdoor_area == 1) $amenitiesAndFeatures[] = 'Garden/Outdoor Amenity Area';

        return $amenitiesAndFeatures;
    }

    public function view (Request $request, $id)
    {
        try{
            $retirementHomeObj = RetirementHome::where('user_id', $id)->first();
            if ($retirementHomeObj)
            {
                $userObj = User::where('id', $retirementHomeObj->user_id)->first();
                $retirementHomeGalleryObj = Gallery::where('user_id', $retirementHomeObj->user_id)->get();

                $data['id'] = $retirementHomeObj->id;
                $data['user_id'] = $retirementHomeObj->user_id;
                $data['name'] = $userObj->name;
                $data['image'] = $userObj->image;
                $data['website'] = $userObj->website ?? 'N/A';
                $data['phone'] = $userObj->phone_number;
                $data['email'] = $userObj->email;
                $data['address'] = $userObj->address;
                $data['type'] = $retirementHomeObj->type;
                $data['latitude'] = $userObj->latitude ?? 0;
                $data['longitude'] = $userObj->longitude ?? 0;
                $data['status'] = $retirementHomeObj->status;
                $tiersObj = Tier::where('retirement_home_id', $retirementHomeObj->id)->get();
                $data['tiers'] = [];
                foreach ($tiersObj as $tier)
                {
                    $arr['tier'] = $tier->tier;
                    $arr['retirement_home_price'] = $tier->retirement_home_price;
                    $arr['hospital_price'] = $tier->hospital_price;

                    $data['tiers'][] = $arr;
                }

                $data['amenitiesAndFeatures'] = $this->amenitiesAndFeatures($retirementHomeObj);;

                $bookingsObj = Booking::where('retirement_home_id', $retirementHomeObj->id)
                    ->where('status', 'accept')->get();
                $patientsObj = Patient::all();
                $usersObj = User::all();

                $patients = [];
                foreach ($bookingsObj as $booking)
                {
                    $patientObj = $patientsObj->where('id', $booking->patient_id)->first();
                    $userObj = $usersObj->where('id', $patientObj->user_id)->first();
                    $photo = $userObj->image;
                    $name = $userObj->name;
                    $gender = $patientObj->gender;
                    $status = 'Placement Made';
                    $inPersonAssessmentObj = InPersonAssessment::where('booking_id', $booking->id)->first();
                    $tier = 'N/A';
                    if ($inPersonAssessmentObj)
                    {
                        $tierObj = Tier::where('id', $inPersonAssessmentObj->tier_id)->first();
                        $tier = $tierObj->name ?? 'N/A';
                    }
                    $id = $patientObj->id;

                    $patients [] = [
                        'photo' => $photo,
                        'name' => $name,
                        'gender' => $gender,
                        'status' => $status,
                        'tier' => $tier,
                        'id' => $id,
                    ];

                }
                $data['patients'] = $patients;

                $data['galleries'] = [];
                foreach ($retirementHomeGalleryObj as $gallery)
                {
                    $arr['gallery_id'] = $gallery->id;
                    $arr['gallery_user_id'] = $gallery->user_id;
                    $arr['gallery_retirement_home_id'] = $gallery->retirement_home_id;
                    $arr['gallery_image'] = $gallery->gallery_image;
        
                    $data['galleries'][] = $arr;
                } 
                // dd($data);

                return view('retirement_homes.view', compact('data'));
            }
            else
            {
                return Redirect::back()->with(['errors' => 'This Retirement Home does not exist.'])->withInput();
            }
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function delete (Request $request, $id)
    {
        try{
            $retirementHomeObj = RetirementHome::where('id', $id)->first();
            User::where('id', $retirementHomeObj->user_id)->first()->delete();
            $retirementHomeObj->delete();

            return Redirect::to('/retirement-homes');
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function myPatients (Request $request, $id)
    {
        try {

            $userObj = User::where('id', $id)->first();
            if ($userObj && $userObj->role == 'retirement-home')
            {
                $retirementHomeObj = RetirementHome::where('user_id', $id)->first();
                $bookingsObj = Booking::where('retirement_home_id', $retirementHomeObj->id)->where('status', 'accept')->get();
                $patientsObj = Patient::all();

                $patients = [];
                foreach ($bookingsObj as $booking)
                {
                    $patientObj = $patientsObj->where('id', $booking->patient_id)->first();
                    $patientUserObj = User::where('id', $patientObj->user_id)->first();

                    $arr['photo'] = $patientUserObj->image ?? '';
                    $arr['name'] = $patientUserObj->name ?? '';
                    $arr['gender'] = $patientObj->gender ?? '';
                    $arr['status'] = $patientObj->status ?? 'Placement Made';
                    $arr['id'] = $patientObj->id;

                    $patients[] = $arr;
                }

                $data['my_patients'] = $patients;

                return view('retirement_homes.my_patients', compact('data'));
            }
            else
            {
                return Redirect::back()->with(['errors' => 'Invalid User.'])->withInput();
            }
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage()])->withInput();
        }
    }

    public function files (Request $request, $id)
    {
        try {
            $retirementHomeObj = RetirementHome::where('id', $id)->first();
            $retirementHomeUserObj = User::where('id', $retirementHomeObj->user_id)->first();

            $data['name'] = $retirementHomeUserObj->name;

            return view ('retirement_homes.files', compact('data'));
        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function gallery (Request $request, $id)
    {
        try {
            $retirementHomeGalleryObj = Gallery::where('user_id', $id)->get();

            $data['user_id'] = $id;
            $data['galleries'] = [];
            foreach ($retirementHomeGalleryObj as $gallery)
            {
                $arr['gallery_id'] = $gallery->id;
                $arr['gallery_user_id'] = $gallery->user_id;
                $arr['gallery_retirement_home_id'] = $gallery->retirement_home_id;
                $arr['gallery_image'] = $gallery->gallery_image;

                $data['galleries'][] = $arr;
            }
                return view ('retirement_homes.gallery_for_admin', compact('data'));

        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function galleryJustView (Request $request, $id)
    {
        try {
            $retirementHomeGalleryObj = Gallery::where('user_id', $id)->get();

            $data['user_id'] = $id;
            $data['galleries'] = [];
            foreach ($retirementHomeGalleryObj as $gallery)
            {
                $arr['gallery_id'] = $gallery->id;
                $arr['gallery_user_id'] = $gallery->user_id;
                $arr['gallery_retirement_home_id'] = $gallery->retirement_home_id;
                $arr['gallery_image'] = $gallery->gallery_image;

                $data['galleries'][] = $arr;
            }
                return view ('retirement_homes.gallery_for_hospital', compact('data'));

        }
        catch (\Exception $e)
        {
            return Redirect::back()->with(['errors' => $e->getMessage().' Please contact admin.'])->withInput();
        }
    }

    public function getRetirementHomeStatus(Request $request, $id){
        $retirementHomeStatusobj = RetirementHome::where('id', $id)->get();
        return response()->json([
            'rethomests'=>$retirementHomeStatusobj
        ]);

    }    

    public function changeRetirementHomeStatus(Request $request, $id, $status){
        $retirementHomeStatusobj = RetirementHome::find($id);
        $retirementHomeStatusobj->status = $status;
        $retirementHomeStatusobj->save();
        return response()->json([
            'success'=>'Status Change Successfully'
        ]);

    } 

}
