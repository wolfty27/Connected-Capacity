@extends('dashboard.layout')

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
                            <h4 class="page-title">Add Hospital</h4>
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

                                <form method="POST" action="/hospitals/store" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">
                                        <div class="col-lg-12">

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label">Full Name</label>
                                                        <input required type="text" id="simpleinput" class="form-control"
                                                               placeholder="Name" name="name" value="{{ old('name') }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="example-email" class="form-label">Email</label>
                                                        <input required type="email" id="example-email" name="email"
                                                               class="form-control" placeholder="Email" value="{{ old('email') }}">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Phone</label>
                                                        <input required type="number" class="form-control" data-toggle="input-mask"
                                                               data-mask-format="(000) 000-0000"
                                                               placeholder="(000) 000-0000" name="phone" value="{{ old('phone') }}">
                                                        <span class="font-13 text-muted">e.g "(xxx) xxx-xxxx"</span>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label">Website</label>
                                                        <input required type="url" id="simpleinput" class="form-control"
                                                               placeholder="https://www.xyz.com" name="website" value="{{ old('website') }}">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="password" class="form-label">Password</label>
                                                        <div class="input-group input-group-merge">
                                                            <input required type="password" id="password" class="form-control"
                                                                   placeholder="Enter your password" name="password" value="{{ old('password') }}">
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
                                                            <input required type="password" id="password" class="form-control"
                                                                   placeholder="Retype your password" name="password_confirmation"  value="{{ old('password_confirmation') }}">
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
                                                        <label for="example-fileinput" class="form-label">Upload
                                                            Logo</label>
                                                        <input type="file" id="example-fileinput" class="form-control" name="logo" accept=".png, .jpeg, .jpg">
                                                        <img id="imgPreview" src="#" alt="pic" width="70" height="70" style="margin-top: 10px; border-radius: 0.25rem;" />
                                                    </div>
                                                </div>
                                                {{-- <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="calendly" class="form-label">Calendly User Name</label>
                                                        <div class="input-group input-group-merge">
                                                            <div class="input-group-text" data-password="false">
                                                                calendly.com/
                                                            </div>
                                                            <input required type="text" id="calendly" class="form-control"
                                                                   placeholder="username" name="calendly" value="{{ old('calendly') }}">
                                                        </div>
                                                    </div>
                                                </div> --}}
                                            </div>

                                            <button class="btn btn-primary" type="submit">Register Hospital</button>


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
    @include('components.code_highlight_js')
    @include('components.input_mask_js')
@endpush
