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
                            <h4 class="page-title">Hospital Details</h4>
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
                        <div class="card bg-primary">
                            <div class="card-body profile-user-box bg-dark  bg-gradient">

                                <div class="row">
                                    <div class="col-sm-8">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="avatar-lg">
                                                    <img src="{{ $data['logo'] }}" alt="" class="rounded-circle img-thumbnail">
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div>
                                                    <h4 class="mt-1 mb-1 text-white">{{ $data['name'] }}</h4>
{{--                                                    <p class="font-13 text-white-50"> Lorem Ipsum is simply dummy text of the printing and typesetting industry.</p>--}}

                                                    <ul class="mb-0 list-inline text-light">
                                                        <!--<li class="list-inline-item me-3">
                                                            <h5 class="mb-1">$ 25,184</h5>
                                                            <p class="mb-0 font-13 text-white-50">Total Revenue</p>
                                                        </li>-->

                                                        @if(auth()->user()->role == 'admin')
                                                            <li class="list-inline-item">
                                                                <h5 class="mb-1">Email</h5>
                                                                <p class="mb-0 font-13 text-white-50">{{ $data['email'] }}</p>
                                                            </li>
                                                            <li class="list-inline-item">
                                                                <h5 class="mb-1">Website</h5>
                                                                <p class="mb-0 font-13 text-white-50">{{ $data['website'] }}</p>
                                                            </li>

                                                            <li class="list-inline-item">
                                                                <h5 class="mb-1">Phone</h5>
                                                                <p class="mb-0 font-13 text-white-50">{{ $data['phone'] }}</p>
                                                            </li>

                                                            <li class="list-inline-item">
                                                                <h5 class="mb-1">Calendly</h5>
                                                                <p class="mb-0 font-13 text-white-50">{{ $data['calendly'] }}</p>
                                                            </li>
                                                        @endif

                                                        <!--<li class="list-inline-item">
                                                            <h5 class="mb-1">82</h5>
                                                            <p class="mb-0 font-13 text-white-50">Number of Patients</p>
                                                        </li>-->
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div> <!-- end col-->

                                    <div class="col-sm-4">
                                        <div class="text-center mt-sm-0 mt-3 mb-3 text-sm-end">
                                            @if(auth()->user()->role == 'admin')
                                                <a type="button" class="btn btn-light btn-sm" href="/hospitals/edit/{{ $data['id'] }}">
                                                    <i class="mdi mdi-account-edit me-1"></i> Edit
                                                </a>
                                                <a type="button" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this item?');" href="/hospitals/delete/{{ $data['id'] }}">
                                                    <i class="mdi mdi-delete  me-1"></i> Delete
                                                </a>
                                            @endif
                                        </div>

                                    </div> <!-- end col-->
                                </div> <!-- end row -->

                            </div> <!-- end card-body/ profile-user-box-->
                        </div><!--end profile/ card -->
                    </div> <!-- end col-->
                </div>
                <!-- end row -->

                <!-----Patient list table----->
                <div class="row">
                    <div class="col-12">
                        <div class="card">

                            <div class="card-body">

                                <h4 class="header-title mb-2">Patients</h4>

                                <table id="alternative-page-datatable" class="table dt-responsive nowrap w-100 ">
                                    <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Full Name</th>
                                        <th>Gender</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>


                                    <tbody>
                                        @foreach($data['patients'] as $patient)
                                            <tr>
                                                <td class="table-user">
                                                    <img src={{ $patient[0] }} alt="table-user" class="me-2 rounded-circle">
                                                </td>
                                                <td>{{ $patient[1] }}</td>
                                                <td>{{ $patient[2] }}</td>
                                                <td>
                                                    @if($patient[3] == 'Available')
                                                        <span class="badge badge-success-lighten">Available</span>
                                                    @elseif($patient[3] == 'Placement Made')
                                                        <span class="badge badge-warning-lighten">Placement Made</span>
                                                    @elseif($patient[3] == 'Inactive')
                                                        <span class="badge badge-danger-lighten">Inactive</span>
                                                    @elseif($patient[3] == 'Application Progress')
                                                        <span class="badge badge-info-lighten">Application In Progress</span>
                                                    @elseif($patient[3] == 'In person Assessment')
                                                        <span class="badge badge-info-lighten">In Person Assessment</span>
                                                    @endif
                                                </td>
                                                <td class="table-action">
                                                    <a href="/patients/view/{{ $patient[4] }}" class="action-icon"> <i class="mdi mdi-eye text-warning"></i></a>
                                                    @if(auth()->user()->role == 'hospital')
                                                        <a href="/patients/edit/{{ $patient[4] }}" class="action-icon"> <i class="mdi mdi-pencil text-primary"></i></a>
                                                    @endif
                                                    @if(auth()->user()->role == 'hospital')
                                                        <a href="/patients/delete/{{ $patient[4] }}" class="action-icon" onclick="return confirm('Are you sure you want to delete this item?');"> <i class="mdi mdi-close text-danger"></i></a>
                                                    @endif
                                                    @if(auth()->user()->role == 'retirement-home' && $patient[3] == 'Available')
                                                        @if ($patient[5])
                                                            <a href="" onclick="Calendly.initPopupWidget({url: 'https://calendly.com/{{ $patient[5] }}?hide_gdpr_banner=1'});return false;" class="action-icon"  data-bs-toggle="tooltip" data-bs-placement="top"
                                                               data-bs-title="Book Appointment"> <i class="mdi mdi-account-check-outline text-info"></i></a>
                                                        @else
                                                            <a href="javascript: void(0);" class="action-icon" data-bs-toggle="tooltip" data-bs-placement="top"
                                                               data-bs-title="Calendly account not mentioned"> <i class="mdi mdi-account-check-outline text-info"></i></a>
                                                        @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>



                            </div> <!-- end card body-->
                        </div> <!-- end card -->
                    </div><!-- end col-->
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
