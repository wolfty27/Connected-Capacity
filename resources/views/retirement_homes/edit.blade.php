@extends('dashboard.layout')
@push('additional_css')
    <link rel="stylesheet" href="/assets/css/style.css">
@endpush
@section('dashboard_content')
    @include('retirement_homes.edit_style')
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
                            <h4 class="page-title">Retirement Homes</h4>
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
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="/retirement-homes/update/{{ $data['id'] }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label">Full Name</label
                                                        >
                                                        <input
                                                            type="text"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Name"
                                                            value="{{ $data['name'] }}"
                                                            name="name"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="example-email" class="form-label"
                                                        >Email</label
                                                        >
                                                        <input
                                                            disabled
                                                            type="email"
                                                            id="example-email"
                                                            name="email"
                                                            class="form-control"
                                                            placeholder="Email"
                                                            value="{{ $data['email'] }}"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Phone</label>
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            data-toggle="input-mask"
                                                            data-mask-format="(000) 000-0000"
                                                            placeholder="(000) 000-0000"
                                                            value="{{ $data['phone'] }}"
                                                            name="phone"
                                                        />
                                                        <span class="font-13 text-muted"
                                                        >e.g "(xxx) xxx-xxxx"</span
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Website</label
                                                        >
                                                        <input
                                                            type="url"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="https://www.xyz.com"
                                                            value="{{ $data['website'] }}"
                                                            name="website"
                                                        />
                                                    </div>
                                                </div>
                                            </div>


                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="inputAddress" class="form-label"
                                                        >Address</label
                                                        >
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            id="inputAddress"
                                                            placeholder="1234 Main St"
                                                            value="{{ $data['address'] }}"
                                                            name="address"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    
                                                    <div class="mb-3">
                                                        <label for="inputCountry" class="form-label"
                                                        >Country</label
                                                        >
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            id="inputCountry"
                                                            placeholder="Country"
                                                            value="{{ $data['country'] }}"
                                                            name="country"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row g-2">
                                                <div class="mb-3 col-md-6">
                                                    <label for="inputCity" class="form-label"
                                                    >City</label
                                                    >
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        id="inputCity"
                                                        placeholder="City"
                                                        value="{{ $data['city'] }}"
                                                        name="city"
                                                    />
                                                </div>
                                                <div class="mb-3 col-md-4">
                                                    <label for="inputState" class="form-label"
                                                    >State</label
                                                    >
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        id="inputState"
                                                        placeholder="State"
                                                        value="{{ $data['state'] }}"
                                                        name="state"
                                                    />
                                                </div>
                                                <div class="mb-3 col-md-2">
                                                    <label class="form-label">ZIP Code</label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        data-toggle="input-mask"
                                                        data-mask-format="00000-000"
                                                        placeholder="00000-000"
                                                        value="{{ $data['zipcode'] }}"
                                                        name="zipcode"
                                                    />
                                                    <span class="font-13 text-muted"
                                                    >e.g "xxxxx-xxx"</span
                                                    >
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="inputCity" class="form-label"
                                                        >Latitude</label
                                                        >
                                                        <input
                                                            type="number"
                                                            class="form-control"
                                                            placeholder="0.000000"
                                                            value="{{ $data['latitude'] }}"
                                                            name="latitude"
                                                        />
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="inputCity" class="form-label"
                                                        >Longitude</label
                                                        >
                                                        <input
                                                            type="number"
                                                            class="form-control"
                                                            placeholder="0.000000"
                                                            value="{{ $data['longitude'] }}"
                                                            name="longitude"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label
                                                            for="example-fileinput"
                                                            class="form-label"
                                                        >Upload Logo</label
                                                        >
                                                        <input
                                                            type="file"
                                                            id="example-fileinput"
                                                            class="form-control"
                                                            name="logo"
                                                        />
                                                        <img id="imgPreview" 
                                                            src="#" alt="pic"
                                                            width="70"
                                                            height="70" 
                                                            style="margin-top: 10px; border-radius: 0.25rem;" />
                                                    </div>
                                                </div>
                                            </div>


                                            <h4 class="header-title mt-5 mt-lg-3">
                                                Amenities and Features
                                            </h4>
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

                                            <!-- Checkboxes-->

                                            <h4 class="header-title mt-5 mt-lg-3">
                                                Define your type of retirement home
                                            </h4>

                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="form-check mb-2">
                                                        <input
                                                            type="radio"
                                                            id="customRadio"
                                                            class="form-check-input"
                                                            value="Independent Living - Provides only light care"
                                                            @if($data['type'] == "Independent Living - Provides only light care") checked @endif
                                                            name="type"
                                                        />
                                                        <label
                                                            class="form-check-label"
                                                            for="customRadio"
                                                        >Independent Living - Provides only light
                                                            care</label
                                                        >
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input
                                                            type="radio"
                                                            id="customRadio"
                                                            class="form-check-input"
                                                            value="Independent & Assisted Living - Provides a full spectrum of care for those who need physical and medical support, but does not support those with cognitive impairment (i.e. Alzheimer's and other forms of dementia)"
                                                            @if($data['type'] == "Independent & Assisted Living - Provides a full spectrum of care for those who need physical and medical support, but does not support those with cognitive impairment (i.e. Alzheimer's and other forms of dementia)") checked @endif
                                                            name="type"
                                                        />
                                                        <label
                                                            class="form-check-label"
                                                            for="customRadio"
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
                                                            id="customRadio"
                                                            class="form-check-input"
                                                            value="Independent, Assisted, and Memory Care Living - Provides a full spectrum of care for those who need physical support, medical support and support for the cognitively impaired (i.e. Alzheimer's and other forms of dementia)"
                                                            @if($data['type'] == "Independent, Assisted, and Memory Care Living - Provides a full spectrum of care for those who need physical support, medical support and support for the cognitively impaired (i.e. Alzheimer's and other forms of dementia)") checked @endif
                                                            name="type"
                                                        />
                                                        <label
                                                            class="form-check-label"
                                                            for="customRadio"
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

                                            <!-- tiers start -->

                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="mb-3 mt-3">
                                                        <h4>Add Tiers</h4>
                                                        <span class="font-13 text-muted">*Amount payable to Retirement Homes will always be less than or equal to than Amount Receivable from Hospitals (i.e. Amount Payable <= Amount Receivable)</span>
                                                        <div
                                                            class="col-lg-12"

                                                        >
                                                            <div class="table-responsive">
                                                                <table
                                                                    id="test-table"
                                                                    class="table table-condensed"
                                                                >
                                                                    <thead>
                                                                    <tr>
                                                                        <th>Tier Title</th>
                                                                        <th>Amount Payable to Retirement Home</th>
                                                                        <th>Amount Receivable from Hospital</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody id="test-body">
                                                                        @if (array_key_exists('tiers', $data))
                                                                            @foreach($data['tiers'] as $row => $tierDetails)
                                                                                <tr id="{{ 'row'.$row }}">
                                                                                <td>
                                                                                    <input
                                                                                        name="tier[]"
                                                                                        placeholder="Tier"
                                                                                        type="text"
                                                                                        class="form-control"
                                                                                        value="{{ $tierDetails[0] }}"
                                                                                    />
                                                                                </td>
                                                                                <td>
                                                                                    <input
                                                                                        name="retirement_home_price[]"
                                                                                        placeholder="500"
                                                                                        type="number"
                                                                                        class="form-control input-md"
                                                                                        value="{{ $tierDetails[1] }}"
                                                                                    />
                                                                                </td>
                                                                                <td>
                                                                                    <input
                                                                                        name="hospital_price[]"
                                                                                        placeholder="700"
                                                                                        type="number"
                                                                                        class="form-control input-md"
                                                                                        value="{{ $tierDetails[2] }}"
                                                                                    />
                                                                                </td>
                                                                                <td>
                                                                                    <input
                                                                                        class="delete-row btn btn-primary"
                                                                                        type="button"
                                                                                        value="Delete"
                                                                                    />
                                                                                </td>
                                                                            </tr>
                                                                            @endforeach
                                                                        @else
                                                                            <tr id="row0">
                                                                                <td>
                                                                                    <input name="tier[]" placeholder="Tier" required
                                                                                           type="text" class="form-control"/>
                                                                                </td>
                                                                                <td>
                                                                                    <input name="retirement_home_price[]" placeholder="500" required
                                                                                           type="number" class="form-control input-md"/>
                                                                                </td>
                                                                                <td>
                                                                                    <input name="hospital_price[]" placeholder="700" required
                                                                                           type="number" class="form-control input-md"/>
                                                                                </td>
                                                                                <td>
                                                                                    <input class="delete-row btn btn-primary" type="button"
                                                                                           value="Delete"/>
                                                                                </td>
                                                                            </tr>
                                                                        @endif
                                                                    </tbody>
                                                                </table>
                                                                <input
                                                                    id="add-row"
                                                                    class="btn btn-primary"
                                                                    type="button"
                                                                    value="Add"
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- tiers end -->
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3 mt-3">
                                                        <h5>GALLERY</h5>
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
                                            {{-- <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <h4>Upload Gallery Images</h4>
                                                        <div class="custom-file">
                                                            <input
                                                                type="file"
                                                                class="custom-file-input"
                                                                id="file"
                                                                multiple
                                                                onchange="javascript:updateList()"
                                                            />
                                                            <label class="custom-file-label" for="file">
                                                                <img
                                                                    width="20"
                                                                    src=" data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAOEAAADhCAMAAAAJbSJIAAAAQlBMVEX///8AAABhYWFlZWWSkpL19fW9vb01NTXf398kJCTw8PBRUVGdnZ1dXV3m5uZ0dHR8fHzExMSMjIzU1NSxsbEhISGIc9b1AAADv0lEQVR4nO2d607jMBhEa1pa6AVaLu//qgixq2+XxmlSe+IZa85vazQjhZMCarJaGWOMMcYYc8WxdQE0m7RpXQHLNqW0bV0CyVP65ql1DRyP6YfH1kVg7P4s3LUugmKd/rJuXQXDJgVdCnWb/qVDoT6l/+lOqI/pN70JdXe1sDOhrq8GdibUzcDAroS6HRzYkVB/a7Q7oV5rtDehXms06EKoQxoNOhDqsEYDeaHmNBqICzWv0UBaqGMaDZSFOqbRQFio4xoNZIV6S6OBqFBvazSQFOoUjQaCQp2m0UBPqNM0GsgJdapGAzGhTtdoICXUORoNhIQ6T6OBjFDnajRQEepcjQYiQp2v0UBCqPdoNBAQ6n0aDeiFeq9GA3Kh3q/RgFuo92s0oBZqiUYDYqGWaTSgFWqpRgNSoZZrNKAUag2NBoxCraHRgFCodTQa0Am1lkYDMqHW02hAJdSaGg2IhFpXowGPUOtqNKARam2NBiRCra/RgEKoCI0GBELFaDRoLlSURoPWQkVpNGgsVJxGg6ZCRWo0aChUrEaDZkJFazRoJFS8RoM2QsVrNGgi1NOCA1M6LT9we3iYzPp5sPXzenrEgeDj2xjDt02S3xyq8DC48KF1rYp4oT5eqI8X6uOF+nihPl6ojxfq44X6eKE+XqiPF+rjhfp4oT5eqI8X6uOF+nihPl6ojxfq44X6eKE+XqiPF+rjhfp4oT5eqI8XLsVhsMehQjJu4bzOuB4sySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wybgew8/nq/EcPZaFb6eBx+id3ioksyzE4YUlpznwwpLTHHhhyWkOvLDkNAdeWHKaAy8sOc2BF5ac5sALS05z4IUlpznwwpLTHNRYyP2OhuH3SsxbOOc9G4uTeTdIbuESr6dahtx1d25drBrnzMJj62LVOGYWXloXq8Yls3Dfulg19pmFq8/WzSrxnBu40Kvw8ORftvfSulolXrILM99XVGPsO6HvrctV4X1k4cKvi8Mw/s/zHm4Y2VvFDx+t+xXzMT5wtXpt3bCQ11sD1X066bv1yhMnPjxA90KdcIn+oKqbm5IJ9or3xdON28Qv3tV+Gg+jn2QGedkM/5WHkc/NyIftMfaX45n5L23frM/Hy7zL0xhjjDHGEPIFcc477O4fZUsAAAAASUVORK5CYII="
                                                                /> Upload here</label
                                                            >
                                                        </div>
                                                        <ul id="fileList" class="file-list"></ul>
                                                    </div>
                                                </div>
                                            </div> --}}

                                            <button class="btn btn-primary mt-3" type="submit">
                                                Update Retirement Home
                                            </button>
                                        </div>
                                        <!-- end col -->
                                    </div>
                                    <!-- end row-->
                                </form>
                                <!-- end Form-->
                            </div>
                            <!-- end card-body -->
                        </div>
                        <!-- end card -->
                    </div>
                    <!-- end col -->
                </div>
                <!-- end row -->
            </div>
            <!-- container -->
        </div>

        @include('dashboard.footer')

    </div>
@endsection

@push('additional_scripts')
    @include('retirement_homes.edit_script')
    @if (array_key_exists('tiers', $data))
        @include('components.multiple_rows_script', [$row => sizeof($data['tiers'])])
    @else
        @include('components.multiple_rows_script')
    @endif
@endpush

