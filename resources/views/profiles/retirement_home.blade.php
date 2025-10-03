@extends('dashboard.layout')

@push('additional_css')
    <link rel="stylesheet" href="assets/css/style.css">
@endpush

@section('dashboard_content')

    <div class="content-page">
        <div class="content">
            <!-- Start Content-->
            <div class="container-fluid">
                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <a class="btn btn-primary" href="{{ url()->previous() }}" >Back</a>
                            </div>
                            <h5 class="page-title">Retirement Home Profile</h5>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

                @if (Session::has('errors'))
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <div>{{ Session::get('errors') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (Session::has('success'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <div>{{ Session::get('success') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="row">
                    <div class="col-sm-12">
                        <!-- Profile -->
                        <div class="card bg-primary ">
                            <div class="card-body profile-user-box bg-dark  bg-gradient rounded-2">

                                <div class="row">
                                    <div class="col-sm-8">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="avatar-lg">
                                                    <img height="80" width="80" src={{$data['logo']}} alt="" class="rounded-circle">
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div>
                                                    <h5 class="mt-1 mb-1 text-white">{{ $data['name'] }}</h5>
                                                    {{--                                                    <p class="font-13 text-white-50"> Lorem Ipsum is simply dummy text of the printing and typesetting industry.</p>--}}


                                                </div>
                                            </div>
                                        </div>
                                    </div> <!-- end col-->

                                    <div class="col-sm-4 profile_top_btn">
                                        <div class="text-center mt-sm-0 mt-3 mb-3 text-sm-end">
                                            {{--                                            <a type="button" class="btn btn-light btn-sm" href="edit-hospital.html">--}}
                                            {{--                                                <i class="mdi mdi-account-edit me-1"></i> Edit--}}
                                            {{--                                            </a>--}}
                                            {{--                                            <a type="button" class="icon_white btn btn-danger btn-sm" href="javascript: void(0);">--}}
                                            {{--                                                <i class="mdi mdi-delete  me-1"></i> Delete--}}
                                            {{--                                            </a>--}}
                                        </div>

                                    </div> <!-- end col-->
                                </div> <!-- end row -->

                            </div> <!-- end card-body/ profile-user-box-->
                        </div>
                        <!--end profile/ card -->
                    </div> <!-- end col-->
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">

                                <div class="row profile-body">
                                    <div class="col-lg-12">
                                        <div class="mb-3 mt-3">
                                            <div class="row">
                                                <div class="row">
                                                    <div class="col-lg-12 position-relative">
                                                        <form action="/my-account/update/{{ $data['user_id'] }}" method="POST" enctype="multipart/form-data">
                                                            @csrf
                                                            <div class="form">
                                                                <div class="row">
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="name" class="form-label">Full Name</label>
                                                                            <input type="text" name="name" placeholder="Enter Name" class="form-control" value="{{ $data['name'] }}" disabled>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="email" class="form-label">Email</label>
                                                                            <input type="email" name="email" placeholder="Enter Email" class="form-control" disabled value="{{ $data['email'] }}">
                                                                        </div>
                                                                    </div>
                                                                    {{-- <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="password" class="form-label">New Password</label>
                                                                            <div class="input-group input-group-merge">
                                                                                <input type="password" id="password" class="form-control" placeholder="Enter your password" name="password">
                                                                                <div class="input-group-text" data-password="false">
                                                                                    <span class="password-eye"></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="password" class="form-label">Confirm Password</label>
                                                                            <div class="input-group input-group-merge">
                                                                                <input type="password" id="password" class="form-control" placeholder="Enter your password" name="password_confirmation">
                                                                                <div class="input-group-text" data-password="false">
                                                                                    <span class="password-eye"></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div> --}}


                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="phone" class="form-label">Phone</label>
                                                                            <input type="tel" name="phone"  placeholder="Enter Phone" class="form-control" value="{{ $data['phone_number'] }}" >
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="website" class="form-label">Website </label>
                                                                            <input type="text" name="website" placeholder="Enter Website" class="form-control" value="{{ $data['website'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="role" class="form-label"> Role</label>
                                                                            <input type="text" name="role" placeholder="" class="form-control" value="{{ $data['role'] }}" disabled>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="zipcode" class="form-label">ZIP Code </label>
                                                                            <input type="number" name="zipcode" placeholder="Enter Zip Code" class="form-control" value="{{ $data['zipcode'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-12">
                                                                        <div class="mb-3">
                                                                            <label for="address" class="form-label"> Address</label>
                                                                            <input type="text" name="address" placeholder="Enter Address" class="form-control" value="{{ $data['address'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="state" class="form-label"> State</label>
                                                                            <input type="text" name="state" placeholder="Enter State" class="form-control" value="{{ $data['state'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="city" class="form-label"> City</label>
                                                                            <input type="text" name="city" placeholder="Enter City" class="form-control" value="{{ $data['city'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="timezone" class="form-label"> Timezone</label>
                                                                            <input type="text" name="timezone" placeholder="Enter Time Zone" class="form-control" value="{{ $data['timezone'] }}">
                                                                        </div>
                                                                        
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
{{--                                                                            <label for="cusername" class="form-label">Calendly User Name </label>--}}
{{--                                                                            <input type="text" name="calendly" placeholder="New york" class="form-control" value="{{ $data['calendly'] }}">--}}
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="lat" class="form-label">Latitude </label>
                                                                            <input type="number" name="latitude" id="lat" placeholder="Enter Latitude" class="form-control" value="{{ $data['latitude'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="long" class="form-label">Longitude </label>
                                                                            <input type="number" name="longitude" id="long" placeholder="Enter Longitude" class="form-control" value="{{ $data['longitude'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="example-fileinput" class="form-label">Update
                                                                                Logo</label>
                                                                            <input type="file" id="example-fileinput" class="form-control" name="logo" accept=".png, .jpeg, .jpg">
                                                                            <img id="imgPreview" src="#" alt="pic" width="70" height="70" style="margin-top: 10px; border-radius: 0.25rem;" />
                                                                        </div>
                                                                    </div>                                                                      
                                                                    {{-- <div class="col-lg-6"> --}}
                                                                        <div class="mb-3">
                                                                            <div class="row">
                                                                                <div class="col-md-12 col-sm-12">
                                                                                    {{-- @if($data['longitude'] != '' && $data['latitude'] != '')
                                                                                        <div id="googleMap" style="width:100%;height:300px; background: var(--ct-gray-100);"></div>
                                                                                    @else
                                                                                        <div class="alert alert-danger alert-dismissible" role="alert">
                                                                                            <div>Update latitude and longitude to view the location on map</div>
                                                                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                                                        </div>
                                                                                    @endif --}}
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    {{-- </div> --}}

                                                                    <div class="row">
                                                                        <div class="col-lg-6">
                                                                            <div class="">
                                                                                <h5>AMENITIES AND FEATURES</h5>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck1"
                                                                                    @if(in_array('Central Dining Room', $data['amenities_features'])) checked @endif
                                                                                    name="central_dining_room"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck1"
                                                                                >Central Dining Room</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck2"
                                                                                    @if(in_array('Private Dining Room', $data['amenities_features'])) checked @endif
                                                                                    name="private_dining_room"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck2"
                                                                                >Private Dining Room</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck3"
                                                                                    @if(in_array('Concierge', $data['amenities_features'])) checked @endif
                                                                                    name="concierge"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck3"
                                                                                >Concierge</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck4"
                                                                                    @if(in_array('Hairdresser/Barber Studio', $data['amenities_features'])) checked @endif
                                                                                    name="hairdresser"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck4"
                                                                                >Hairdresser/Barber Studio</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck5"
                                                                                    @if(in_array('Library', $data['amenities_features'])) checked @endif
                                                                                    name="library"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck5"
                                                                                >Library</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck6"
                                                                                    @if(in_array('Movie Theater', $data['amenities_features'])) checked @endif
                                                                                    name="movie_theatre"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck6"
                                                                                >Movie Theater</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck7"
                                                                                    @if(in_array('Pets Allowed', $data['amenities_features'])) checked @endif
                                                                                    name="pets_allowed"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck7"
                                                                                >Pets Allowed</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck8"
                                                                                    @if(in_array('Pool', $data['amenities_features'])) checked @endif
                                                                                    name="pool"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck8"
                                                                                >Pool</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck9"
                                                                                    @if(in_array('Special Outings', $data['amenities_features'])) checked @endif
                                                                                    name="special_outings"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck9"
                                                                                >Special Outings</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck10"
                                                                                    @if(in_array('Tuck Shop', $data['amenities_features'])) checked @endif
                                                                                    name="tuck_shop"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck10"
                                                                                >Tuck Shop</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck11"
                                                                                    @if(in_array('Bar/Lounge', $data['amenities_features'])) checked @endif
                                                                                    name="bar"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck11"
                                                                                >Bar/Lounge</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck12"
                                                                                    @if(in_array('Computer Lounge', $data['amenities_features'])) checked @endif
                                                                                    name="computer_lounge"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck12"
                                                                                >Computer Lounge</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck13"
                                                                                    @if(in_array('Gym/Fitness Room', $data['amenities_features'])) checked @endif
                                                                                    name="gym"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck13"
                                                                                >Gym/Fitness Room</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck14"
                                                                                    @if(in_array('Hobby/Art Studio', $data['amenities_features'])) checked @endif
                                                                                    name="art_studio"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck14"
                                                                                >Hobby/Art Studio</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck15"
                                                                                    @if(in_array('Sun Room', $data['amenities_features'])) checked @endif
                                                                                    name="sun_room"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck15"
                                                                                >Sun Room</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck16"
                                                                                    @if(in_array('Wellness Centre', $data['amenities_features'])) checked @endif
                                                                                    name="wellness_centre"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck16"
                                                                                >Wellness Centre</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck17"
                                                                                    @if(in_array('Religious Centre', $data['amenities_features'])) checked @endif
                                                                                    name="religious_centre"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck17"
                                                                                >Religious Centre</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->

                                                                        <div class="col-lg-3 mt-3">
                                                                            <div class="form-check">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    class="form-check-input"
                                                                                    id="customCheck18"
                                                                                    @if(in_array('Garden/Outdoor Amenity Area', $data['amenities_features'])) checked @endif
                                                                                    name="outdoor_area"
                                                                                />
                                                                                <label
                                                                                    class="form-check-label"
                                                                                    for="customCheck18"
                                                                                >Garden/Outdoor Amenity Area</label
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                        <!-----End checkbox col---->
                                                                    </div>

                                                                </div>

                                                                <div class="row">
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3 mt-3">
                                                                            <h5>DEFINE YOUR TYPE OF RETIREMENT HOME</h5>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="row">
                                                                    <div class="col-lg-12">
                                                                        <div class="form-check mb-2">
                                                                            <input
                                                                                type="radio"
                                                                                id="customRadio1"
                                                                                class="form-check-input"
                                                                                @if($data['type'] == 'Independent Living') checked @endif
                                                                                name="cat_1"
                                                                            />
                                                                            <label
                                                                                class="form-check-label"
                                                                                for="customRadio1"
                                                                            >Independent Living - Provides only light
                                                                                care</label
                                                                            >
                                                                        </div>
                                                                        <div class="form-check mb-2">
                                                                            <input
                                                                                type="radio"
                                                                                id="customRadio2"
                                                                                class="form-check-input"
                                                                                @if($data['type'] == 'Independent & Assisted Living') checked @endif
                                                                                name="cat_2"
                                                                            />
                                                                            <label
                                                                                class="form-check-label"
                                                                                for="customRadio2"
                                                                            >Independent & Assisted Living - Provides a
                                                                                full spectrum of care for those who need
                                                                                physical and medical support, but does not
                                                                                support those with cognitive impairment (i.e.
                                                                                Alzheimer's and other forms of
                                                                                dementia)</label
                                                                            >
                                                                        </div>
                                                                        <div class="form-check mb-2">
                                                                            <input
                                                                                type="radio"
                                                                                id="customRadio3"
                                                                                class="form-check-input"
                                                                                @if($data['type'] == 'Independent, Assisted and Memory Care Living') checked @endif
                                                                                name="cat_3"
                                                                            />
                                                                            <label
                                                                                class="form-check-label"
                                                                                for="customRadio3"
                                                                            >Independent, Assisted and Memory Care Living
                                                                                - Provides a full spectrum of care for those
                                                                                who need physical support, medical support and
                                                                                support for the cognitively impaired (i.e.
                                                                                Alzheimer's and other forms of
                                                                                dementia)</label
                                                                            >
                                                                        </div>
                                                                    </div>
                                                                </div>

{{--                                                                <div class="row">--}}
{{--                                                                    <div class="col-lg-6">--}}
{{--                                                                        <div class="mb-3">--}}
{{--                                                                            <div class="upload__box">--}}
{{--                                                                                <div class="upload__btn-box">--}}
{{--                                                                                    <label class="upload__btn form-label" for="files">--}}
{{--                                                                                        <p>Uploaded Files</p>--}}

{{--                                                                                    </label>--}}
{{--                                                                                    <input type="file" multiple="" id="files upload__inputfile" data-max_length="20" class=" form-control" name="files">--}}

{{--                                                                                </div>--}}

{{--                                                                            </div>--}}
{{--                                                                        </div>--}}

{{--                                                                    </div>--}}
{{--                                                                </div>--}}
                                                                {{-- Gallery images start --}}
                                                                <div class="row">
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3 mt-3">
                                                                            <h5>YOUR GALLERY</h5>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row refresh-gallery-class">
                                                                    <div class="col-12">
                                                                        <div class="card">
                                                                            <div class="card-body">
                                                                                <div class="row">
                                                                                    <div class="col-lg-12">
                                                                                        <div class="gallery">
                                                                                            @if(sizeof($data['galleries']) == 0)
                                                                                            <h5 class="text-muted fw-normal mt-0 text-truncate">
                                                                                                Gallery is Empty.
                                                                                            </h5>    
                                                                                            @endif
                                                                                            @foreach($data['galleries'] as $gallery)
                                                                                                <img
                                                                                                id={{ $gallery['gallery_id'] }}
                                                                                                src={{ $gallery['gallery_image'] }}
                                                                                                alt="post-img"
                                                                                                class="rounded me-1 mb-3 mb-sm-2 img-fluid"
                                                                                                />
                                                                                            @endforeach
                                                                                        </div>
                                                                                        <a class="see-all" href="/retirement-homes/gallery/{{ $data['user_id'] }}" style="float: left;">Edit Gallery</a>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <!-- end card-body -->
                                                                        </div>
                                                                        <!-- end card -->
                                                                    </div>
                                                                    <!-- end col -->
                                                                </div>
                                                                <!-- end row -->
                                                                {{-- Gallery images end --}}

                                                                <div class="row">
                                                                    <div class="col-lg-12">

                                                                        <button type="submit" class="btn btn-primary mt-3" >Update</button>

                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end card-body -->
                </div>
                <!-- end card -->
            </div>
            <!-- end col -->
        </div>
        <!-- end row -->
    </div>


    <!-- Footer Start -->
    @include('dashboard.footer')
    <!-- end Footer -->

    </div>
@endsection

@push('additional_scripts')
    <script>
        let files = [{url:'https://image.shutterstock.com/image-vector/medical-concept-hospital-building-doctor-260nw-588196298.jpg',name:'test'}]

        window.onload = function(){

            console.log('files.length',files.length);
            if(files.length==1)renderImage(files)
            if(window.File && window.FileList && window.FileReader)
            {

                var filesInput = document.getElementById("files");



                filesInput.addEventListener("change", function(event){

                    files=[...files,...event.target.files]
                    renderImage(files)
                    console.log('addEventListener');

                });
            }
            else
            {
                alert("Your browser does not support File API");
            }
        }

        const renderImage = (files)=>{
            console.log('renderImage',files);
            var output = document.getElementById("imgThumbnailPreview");
            output.innerHTML= '';
            files.map((file,index)=>{
                if (file.type){
                    var picReader = new FileReader();

                    picReader.addEventListener("load",function(event){

                        var picSrc = event?.target?.result;

                        var imgThumbnailElem = `<div class='imgThumbContainer'><div class='IMGthumbnail' ><div class='close_btn' onclick="removeimage(${index})"><p>x</p></div><img  src=${picSrc}
                                    "title=${file.name}/><div></div>`;

                        output.innerHTML = output.innerHTML + imgThumbnailElem;

                    });

                    //Read the image
                    picReader.readAsDataURL(file);
                }

                else {
                    var picSrc = file.url;

                    // var imgThumbnailElem = "<div class='imgThumbContainer'><div class='IMGthumbnail' ><div class='close_btn' onclick=removeimage(index)'><p>x</p></div><img  src='" + picSrc + "'" +
                    //         "title='"+file.name + "'/><div></div>";

                    var imgThumbnailElem = `<div class='imgThumbContainer'><div class='IMGthumbnail' ><div class='close_btn' onclick="removeimage(${index})"><p>x</p></div><img  src=${picSrc}
                                    "title=${file.name}/><div></div>`;

                    output.innerHTML = output.innerHTML + imgThumbnailElem;
                }
            })
        }

        const removeimage =(index)=>{
            files = files.filter((f,ind)=>ind!==index)
            renderImage(files)
        }




        // In the following example, markers appear when the user clicks on the map.
        // The markers are stored in an array.
        // The user can then click an option to hide, show or delete the markers.
        var map;
        var markers = [];

        function initMap() {
            var haightAshbury = {lat: 23.2748308, lng: 77.4519248};

            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 16.3,                        // Set the zoom level manually
                center: haightAshbury,
                mapTypeId: 'terrain'
            });

            // This event listener will call addMarker() when the map is clicked.
            map.addListener('click', function(event) {
                if (markers.length >= 1) {
                    deleteMarkers();
                }

                addMarker(event.latLng);
                document.getElementById('lat').value = event.latLng.lat();
                document.getElementById('long').value =  event.latLng.lng();
            });
        }

        // Adds a marker to the map and push to the array.
        function addMarker(location) {
            var marker = new google.maps.Marker({
                position: location,
                map: map
            });
            markers.push(marker);
        }

        // Sets the map on all markers in the array.
        function setMapOnAll(map) {
            for (var i = 0; i < markers.length; i++) {
                markers[i].setMap(map);
            }
        }

        // Removes the markers from the map, but keeps them in the array.
        function clearMarkers() {
            setMapOnAll(null);
        }

        // Deletes all markers in the array by removing references to them.
        function deleteMarkers() {
            clearMarkers();
            markers = [];
        }


    </script>

    <!-- Code Highlight js -->
    <script src="assets/vendor/highlightjs/highlight.pack.min.js"></script>
    <script src="assets/js/hyper-syntax.js"></script>

    <!-- Input Mask js -->
    <script src="assets/vendor/jquery-mask-plugin/jquery.mask.min.js"></script>

    @include('components.google_map_script')
    @include('retirement_homes.edit_script')

@endpush
