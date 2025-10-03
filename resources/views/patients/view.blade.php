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
                            <h4 class="page-title">Patient Detail</h4>
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
                    <div class="col-xl-8 col-lg-7 m-auto">
                        <div class="card text-center ribbon-box">
                            <div class="card-body" style="text-align: left;">
                                @if($data['status'] == 'Available')
                                    <div class="ribbon ribbon-success float-end"><i class="mdi mdi-access-point me-1"></i> Available</div>
                                @elseif($data['status'] == 'Placement Made')
                                    <div class="ribbon ribbon-primary float-end"><i class="mdi mdi-access-point me-1"></i> Placement Made</div>
                                @elseif($data['status'] == 'Inactive')
                                    <div class="ribbon ribbon-danger float-end"><i class="mdi mdi-access-point me-1"></i> Inactive</div>
                                @elseif($data['status'] == 'Application Progress')
                                    <div class="ribbon ribbon-info float-end"><i class="mdi mdi-access-point me-1"></i> Application In Progress</div>
                                @elseif($data['status'] == 'In person Assessment')
                                    <div class="ribbon ribbon-warning float-end"><i class="mdi mdi-access-point me-1"></i> In Person Assessment</div>
                                @endif

                                <img src="{{ $data['image'] }}" class="rounded-circle avatar-lg img-thumbnail"
                                     alt="profile-image">
                                <h4 class="mb-0 mt-2">{{ $data['name'] }}</h4>
                                <p class="text-muted font-14">{{ $data['gender'] }}</p>

                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <h4>Hospital name</h4>
                                            <p>{{ $data['hospital'] }}</p>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <h4>Retirement home name</h4>
                                            <p>{{ $data['retirement_home'] }}</p>
                                        </div>
                                    </div>
                                </div>

                                @if(auth()->user()->role == 'admin' || auth()->user()->role == 'hospital')
                                    <a href="/patients/edit/{{ $data['id'] }}"><button type="button" class="btn btn-primary btn-sm mb-2">Edit</button></a>
                                @endif
                                @if(auth()->user()->role == 'retirement-home' && $data['status'] == 'Available')
                                        @if ($data['calendly'])
                                            <a href="/book-appointment/{{ $data['id'] }}" class="action-icon" data-bs-toggle="tooltip" data-bs-placement="top"
                                            data-bs-title=""><button type="button" class="btn btn-success btn-sm mb-2">Book Now</button></a>
                                            {{-- <a href="" class="action-icon"  data-bs-toggle="tooltip" data-bs-placement="top"
                                               data-bs-title="Book Appointment" onclick="Calendly.initPopupWidget({url: 'https://calendly.com/{{ $data['calendly'] }}?hide_gdpr_banner=1'});return false;"> <button type="button" class="btn btn-success btn-sm mb-2">Book Now</button></a> --}}
                                        @else
                                            <a href="/book-appointment/{{ $data['id'] }}" class="action-icon" data-bs-toggle="tooltip" data-bs-placement="top"
                                               data-bs-title="Calendly account not mentioned"><button type="button" class="btn btn-danger btn-sm mb-2">Book Now</button></a>
                                        @endif
                                @endif

                            </div> <!-- end card-body -->
                        </div> <!-- end card -->






                    </div> <!-- end col-->


                </div>
                <!-- end row-->


            </div> <!-- container -->

        </div> <!-- content -->

        <!-- Footer Start -->
        @include('dashboard.footer')
        <!-- end Footer -->

    </div>
@endsection

@push('additional_scripts')
    @include('datatables.scripts')
@endpush
