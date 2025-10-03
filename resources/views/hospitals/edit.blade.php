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
                            <h4 class="page-title">Edit Hospital</h4>
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

                                <form action="/hospitals/update/{{ $data['id'] }}}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">
                                        <div class="col-lg-12">

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label">Full Name</label>
                                                        <input name="name" type="text" id="simpleinput" class="form-control" placeholder="Name" value="{{ $data['name'] }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="example-disable" class="form-label">Email</label>
                                                        <input type="email" name="email" class="form-control" id="example-disable" disabled="" value="{{ $data['email'] }}" >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Phone</label>
                                                        <input type="number" name="phone" class="form-control" data-toggle="input-mask" data-mask-format="(000) 000-0000" placeholder="(000) 000-0000" value="{{ $data['phone'] }}">
                                                        <span class="font-13 text-muted">e.g "(xxx) xxx-xxxx"</span>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label">Website</label>
                                                        <input name="website" type="url" id="simpleinput" class="form-control"  placeholder="https://www.xyz.com" value="{{ $data['website'] }}">
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
                                                        <label for="example-fileinput" class="form-label">Upload Logo</label>
                                                        <input type="file" id="example-fileinput" name="logo" class="form-control" >
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
                                                            <input required type="text" id="calendly" class="form-control" @if(auth()->user()->role != 'hospital') disabled="" @endif
                                                                   placeholder="username" name="calendly" value="{{ $data['calendly'] }}">
                                                        </div>
                                                    </div>
                                                </div> --}}
                                                
                                            </div>

                                            <button class="btn btn-primary" type="submit">Update Hospital</button>

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
