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
                            <h4 class="page-title">Booking Details</h4>
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
                    <div class="col-xxl-8 col-lg-6">
                        <!-- project card -->
                        <div class="card d-block ribbon-box">
                            <div class="card-body">
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
                                <div class="d-flex justify-content-between align-items-center mb-3">


                                    <img src="{{ $data['image'] }}" class="rounded-circle avatar-lg img-thumbnail" alt="profile-image">


{{--                                    <div class="dropdown">--}}
{{--                                        <a href="#" class="dropdown-toggle arrow-none card-drop" data-bs-toggle="dropdown" aria-expanded="false">--}}
{{--                                            <i class="ri-more-fill"></i>--}}
{{--                                        </a>--}}
{{--                                        <div class="dropdown-menu dropdown-menu-end">--}}
{{--                                            <!-- item-->--}}
{{--                                            <a href="javascript:void(0);" class="dropdown-item"><i class="mdi mdi-pencil me-1"></i>Edit</a>--}}
{{--                                            <!-- item-->--}}
{{--                                            <a href="javascript:void(0);" class="dropdown-item"><i class="mdi mdi-email-outline me-1"></i>Deactive</a>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}

                                    <!-- project title-->
                                </div>
                                <!--<div class="badge bg-success text-light mb-3">Active</div>-->




                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">

                                            <h5 class="mb-1"><i class="mdi mdi-shield-account text-muted"></i> Patient</h5>
                                            <p class="mb-0 font-13">{{ $data['patient_name'] }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">

                                            <h5 class="mb-1"><i class="mdi mdi-eye text-muted"></i> Gender</h5>
                                            <p class="mb-0 font-13">{{ $data['gender'] }}</p>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <h5 class="mb-1"><i class="mdi mdi-home-city-outline text-muted"></i>  Retirement Home</h5>
                                            <p class="mb-0 font-13">{{ $data['retirement_home'] }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <h5 class="mb-1"><i class="uil uil-medical-square text-muted"></i> Hospital</h5>
                                            <p class="mb-0 font-13">{{ $data['hospital'] }}</p>
                                        </div>
                                    </div>


                                </div>






                            </div> <!-- end card-body-->

                        </div> <!-- end card-->


                    </div> <!-- end col -->

                    <div class="col-lg-6 col-xxl-4">

                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Tier</h5>

                                <div class="card mb-1 shadow-none border">
                                    <div class="p-2">
                                        <div class="row align-items-center">

                                            <div class="col ps-2 ">
                                                <span class="fw-normal">Type </span>
                                            </div>
                                            <div class="text-right col ps-2 ">
                                                <span class="fw-normal">{{ $data['tier'] }}</span>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Price</h5>

                                @if(auth()->user()->role == 'hospital')
                                    <div class="card mb-1 shadow-none border">
                                        <div class="p-2">
                                            <div class="row align-items-center">

                                                <div class="col ps-2 ">
                                                    <span class="fw-normal">Payable </span>
                                                </div>
                                                <div class="text-right col ps-2 ">
                                                    <span class="fw-normal">{{ $data['hospital_price'] }}</span>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if(auth()->user()->role == 'retirement-home')
                                    <div class="card mb-1 shadow-none border">
                                        <div class="p-2">
                                            <div class="row align-items-center">

                                                <div class="col ps-2 ">
                                                    <span class="fw-normal">Receivable </span>
                                                </div>
                                                <div class="text-right col ps-2 ">
                                                    <span class="fw-normal">{{ $data['retirement_home_price'] }}</span>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if(auth()->user()->role == 'admin')
                                    <div class="card mb-1 shadow-none border">
                                        <div class="p-2">
                                            <div class="row align-items-center">

                                                <div class="col ps-2 ">
                                                    <span class="fw-normal">Hospital Price </span>
                                                </div>
                                                <div class="text-right col ps-2 ">
                                                    <span class="fw-normal">{{ $data['hospital_price'] }}</span>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <div class="card mb-1 shadow-none border">
                                        <div class="p-2">
                                            <div class="row align-items-center">

                                                <div class="col ps-2 ">
                                                    <span class="fw-normal">Retirement Home Price </span>
                                                </div>
                                                <div class="text-right col ps-2 ">
                                                    <span class="fw-normal">{{ $data['retirement_home_price'] }}</span>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
                <!-- end row -->

            </div> <!-- container -->

        </div> <!-- content -->

        <!-- Footer Start -->
        @include('dashboard.footer')
        <!-- end Footer -->

    </div>
@endsection
