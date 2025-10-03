@extends('dashboard.layout')

@push('additional_css')
    @include('datatables.css')
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

                                <form action="/retirement-homes/store" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">
                                        <div class="col-lg-12">

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label">Full Name</label>
                                                        <input required type="text" id="simpleinput" class="form-control" placeholder="Name" name="name"  value="{{ old('name') }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="example-email" class="form-label">Email</label>
                                                        <input required type="email" id="example-email" class="form-control" placeholder="Email" name="email"  value="{{ old('email') }}">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Phone</label>
                                                        <input required type="number" class="form-control" data-toggle="input-mask" data-mask-format="(000) 000-0000" name="phone" placeholder="(000) 000-0000"  value="{{ old('phone') }}">
                                                        <span class="font-13 text-muted">e.g "(xxx) xxx-xxxx"</span>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label">Website</label>
                                                        <input required type="url" id="simpleinput" class="form-control"  placeholder="https://www.xyz.com" name="website"  value="{{ old('website') }}">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="password" class="form-label">Password</label>
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
                                                        <label for="password" class="form-label">Retype Password</label>
                                                        <div class="input-group input-group-merge">
                                                            <input type="password" id="password" class="form-control" placeholder="Retype your password" name="password_confirmation">
                                                            <div class="input-group-text" data-password="false">
                                                                <span class="password-eye"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="inputAddress" class="form-label">Address</label>
                                                        <input required type="text" class="form-control" id="inputAddress" placeholder="1234 Main St" name="address"  value="{{ old('address') }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="inputCountry" class="form-label">Country</label>
                                                        <input required name="country" type="text" class="form-control" id="inputCountry" placeholder="Country"  value="{{ old('country') }}">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row g-2">
                                                <div class="mb-3 col-md-6">
                                                    <label for="inputCity" class="form-label">City</label>
                                                    <input required name="city" type="text" class="form-control" id="inputCity" placeholder="City"  value="{{ old('city') }}">
                                                </div>
                                                <div class="mb-3 col-md-4">
                                                    <label for="inputState" class="form-label">State</label>
                                                    <input required name="state" type="text" class="form-control" id="inputState" placeholder="State" value="{{ old('state') }}">
                                                </div>
                                                <div class="mb-3 col-md-2">
                                                    <label class="form-label">ZIP Code</label>
                                                    <input required type="text" class="form-control" name="zipcode" data-toggle="input-mask" data-mask-format="00000-000" placeholder="00000-000" value="{{ old('zipcode') }}">
                                                    <span class="font-13 text-muted">e.g "xxxxx-xxx"</span>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="inputCity" class="form-label">Latitude</label>
                                                        <input required type="number" class="form-control"  name="latitude" placeholder="0.000000"  value="{{ old('latitude') }}">
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="inputCity" class="form-label">Longitude</label>
                                                        <input required name="longitude" type="number" class="form-control"  placeholder="0.000000"  value="{{ old('longitude') }}">
                                                    </div>
                                                </div>
                                            </div>


                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="example-fileinput" class="form-label">Upload Logo</label>
                                                        <input type="file" accept=".png, .jpeg, .jpg" name="logo" id="example-fileinput" class="form-control"  value="{{ old('logo') }}">
                                                        <img id="imgPreview" src="#" alt="pic" width="70" height="70" style="margin-top: 10px; border-radius: 0.25rem;" />
                                                    </div>
                                                </div>


                                            </div>

                                            <h4 class="header-title mt-5 mt-lg-3">Amenities and Features </h4>
                                            <div class="row">
                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('central_dining_room') == "on") checked @endif type="checkbox" name="central_dining_room" class="form-check-input" id="customCheck1"  >
                                                        <label class="form-check-label" for="customCheck1">Central Dining Room</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('private_dining_room') == "on") checked @endif type="checkbox" class="form-check-input" name="private_dining_room" id="customCheck2" >
                                                        <label class="form-check-label" for="customCheck2">Private Dining Room</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('concierge') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck3" name="concierge" >
                                                        <label class="form-check-label" for="customCheck3">Concierge</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->



                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('hairdresser') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck4" name="hairdresser" >
                                                        <label class="form-check-label" for="customCheck4">Hairdresser/Barber Studio</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('library') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck5" name="library" >
                                                        <label class="form-check-label" for="customCheck5">Library</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('movie_theatre') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck6" name="movie_theatre" >
                                                        <label class="form-check-label" for="customCheck6">Movie Theatre</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('pets_allowed') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck7" name=pets_allowed >
                                                        <label class="form-check-label" for="customCheck7">Pets Allowed</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('pool') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck8" name="pool" >
                                                        <label class="form-check-label" for="customCheck8">Pool</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('special_outings') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck9" name="special_outings" >
                                                        <label class="form-check-label" for="customCheck9">Special Outings</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('tuck_shop') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck10" name="tuck_shop">
                                                        <label class="form-check-label" for="customCheck10">Tuck Shop</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('bar') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck11" name="bar" >
                                                        <label class="form-check-label" for="customCheck11">Bar/Lounge</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('computer_lounge') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck12" name="computer_lounge" >
                                                        <label class="form-check-label" for="customCheck12">Computer Lounge</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('gym') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck13" name="gym" >
                                                        <label class="form-check-label" for="customCheck13">Gym/Fitness Room</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('art_studio') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck14" name="art_studio">
                                                        <label class="form-check-label" for="customCheck14">Hobby/Art Studio</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('sun_room') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck15" name="sun_room" >
                                                        <label class="form-check-label" for="customCheck15">Sun Room</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('wellness_centre') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck16" name="wellness_centre" >
                                                        <label class="form-check-label" for="customCheck16">Wellness Centre</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('religious_centre') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck17" name="religious_centre" >
                                                        <label class="form-check-label" for="customCheck17">Religious Centre</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->

                                                <div class="col-lg-3 mt-3">
                                                    <div class="form-check">
                                                        <input @if(old('outdoor_area') == "on") checked @endif type="checkbox" class="form-check-input" id="customCheck18" name="outdoor_area" >
                                                        <label class="form-check-label" for="customCheck18">Garden/Outdoor Amenity Area</label>
                                                    </div>
                                                </div>
                                                <!-----End checkbox col---->



                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="mb-3 mt-3">
                                                        <h3>Add Tiers</h3>
                                                        <span class="font-13 text-muted">*Amount payable to Retirement Homes will always be less than or equal to than Amount Receivable from Hospitals (i.e. Amount Payable <= Amount Receivable)</span>
                                                        <div class="col-lg-12">
                                                            <div class="table-responsive">
                                                                <table id="test-table"
                                                                    class="table table-condensed">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Tier Title</th>
                                                                            <th>Amount Payable to Retirement Home</th>
                                                                            <th>Amount Receivable from Hospital</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="test-body">
                                                                        <tr id="row0">
                                                                            <td>
                                                                                <input name="tier[]" placeholder="Tier" required
                                                                                    type="text" class="form-control" value="{{ old('tier[]') }}"/>
                                                                            </td>
                                                                            <td>
                                                                                <input name="retirement_home_price[]" placeholder="500" required
                                                                                    type="number" min="0" class="form-control input-md" value="{{ old('retirement_home_price[]') }}"/>
                                                                            </td>
                                                                            <td>
                                                                                <input name="hospital_price[]" placeholder="700" required
                                                                                    type="number" min="200" class="form-control input-md" value="{{ old('hospital_price[]') }}"/>
                                                                            </td>
                                                                            <td>
                                                                                <input class="delete-row btn btn-primary" type="button"
                                                                                    value="Delete"/>
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                                <input
                                                                    id="add-row" class="btn btn-primary" type="button"
                                                                    value="Add"/>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <h4 class="header-title mt-5 mt-lg-3">Define your type of retirement home</h4>

                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="form-check mb-2">
                                                        <input type="radio" id="customRadio" name="independent" value="Independent Living - Provides only light care" class="form-check-input">
                                                        <label class="form-check-label" for="customRadio">Independent Living - Provides only light care</label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input type="radio" id="customRadio" name="independent" value="Independent & Assisted Living - Provides a full spectrum of care for those who need physical and medical support, but does not support those with cognitive impairment (i.e. Alzheimer's and other forms of dementia)" class="form-check-input">
                                                        <label class="form-check-label" for="customRadio">Independent & Assisted Living - Provides a full spectrum of care for those who need physical and medical support, but does not support those with cognitive impairment (i.e. Alzheimer's and other forms of dementia)</label>
                                                    </div>
                                                    <div class="form-check mb-2">
                                                        <input type="radio" id="customRadio" name="independent" value="Independent, Assisted, and Memory Care Living - Provides a full spectrum of care for those who need physical support, medical support and support for the cognitively impaired (i.e. Alzheimer's and other forms of dementia)" class="form-check-input">
                                                        <label class="form-check-label" for="customRadio">Independent, Assisted, and Memory Care Living - Provides a full spectrum of care for those who need physical support, medical support and support for the cognitively impaired (i.e. Alzheimer's and other forms of dementia)</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <button class="btn btn-primary mt-3" type="submit">Register Retirement Home</button>

                                        </div> <!-- end col -->


                                    </div>
                                    <!-- end row-->
                                </form>
                                <!-- end Form-->


                            </div> <!-- end card-body -->
                        </div> <!-- end card -->
                    </div><!-- end col -->
                </div><!-- end row -->

            </div> <!-- container -->

        </div> <!-- content -->

        <!-- Footer Start -->
        @include('dashboard.footer')
        <!-- end Footer -->

    </div>
@endsection

@push('additional_scripts')
    @include('datatables.scripts')
    @include('components.multiple_rows_script')
@endpush
