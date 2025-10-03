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
                                {{-- <a class="btn btn-primary" href="{{ url()->previous() }}" >Back</a> --}}
                            </div>
                            <h4 class="page-title">Placed Patients</h4>
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

                                <div class="row mb-2">
                                    <div class="col-sm-4">
                                        @if(auth()->user()->role == 'hospital')
                                            <a href="/patients/create" class="btn btn-primary mb-2"><i class="mdi mdi-plus-circle me-2"></i> Add Patient</a>
                                        @endif
                                    </div>
                                    <div class="col-sm-8">
                                        <div class="text-sm-end">
{{--                                            <button type="button" class="btn btn-success mb-2 me-1"><i class="mdi mdi-cog"></i></button>--}}
{{--                                            <button type="button" class="btn btn-light mb-2 me-1">Import</button>--}}
{{--                                            <button type="button" class="btn btn-light mb-2">Export</button>--}}
                                        </div>
                                    </div><!-- end col-->
                                </div>

                                <table id="alternative-page-datatable" class="table dt-responsive nowrap w-100 ">
                                    <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Full Name</th>
                                        {{-- <th>Retirement Home</th> --}}
                                        <th>Gender</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>


                                    <tbody>
                                    @foreach($data as $record)
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{ $record['photo'] }}" alt="table-user" class="me-2 rounded-circle">
                                            </td>
                                            <td>{{ $record['name'] }}</td>
                                            {{-- <td>{{ $record['retirementHome'] }}</td> --}}
                                            <td>{{ $record['gender'] }}</td>
                                            <td>
                                                @if($record['status'] == 'Available')
                                                    <span class="badge badge-success-lighten">Available</span>
                                                @elseif($record['status'] == 'Placement Made')
                                                    <span class="badge badge-warning-lighten">Placement Made</span>
                                                @elseif($record['status'] == 'Inactive')
                                                    <span class="badge badge-danger-lighten">Inactive</span>
                                                @elseif($record['status'] == 'Application Progress')
                                                    <span class="badge badge-secondary-lighten">Application In Progress</span>
                                                @elseif($record['status'] == 'In person Assessment')
                                                    <span class="badge badge-info-lighten">In Person Assessment</span>
                                                @endif
                                            </td>
                                            <td class="table-action">
                                                <a href="/patients/view/{{ $record['id'] }}" class="action-icon"> <i class="mdi mdi-eye text-warning"></i></a>
                                                @if(auth()->user()->role == 'hospital')
                                                    <a href="/patients/delete/{{ $record['id'] }}" class="action-icon" onclick="return confirm('Are you sure you want to delete this item?');"> <i class="mdi mdi-close text-danger"></i></a>
                                                @endif
                                                @if(auth()->user()->role == 'hospital')
                                                    <a href="/patients/edit/{{ $record['id'] }}" class="action-icon"> <i class="mdi mdi-pencil text-primary"></i></a>
                                                @endif

{{--                                                check if $record['calendly'] exists, if not, return an error msg--}}
                                                @if(auth()->user()->role == 'retirement-home' && $record['status'] == 'Available')
                                                    @if ($record['calendly'])
                                                        <a href="" class="action-icon"  data-bs-toggle="tooltip" data-bs-placement="top"
                                                           data-bs-title="Book Appointment" onclick="Calendly.initPopupWidget({url: 'https://calendly.com/{{ $record['calendly'] }}?hide_gdpr_banner=1'});return false;"> <i class="mdi mdi-account-check-outline text-info"></i></a>
                                                    @else
                                                        <a href="javascript: void(0);" class="action-icon" data-bs-toggle="tooltip" data-bs-placement="top"
                                                           data-bs-title="Calendly account not mentioned"> <i class="mdi mdi-account-check-outline text-info"></i></a>
                                                    @endif
                                                @endif
                                                @if(auth()->user()->role == 'hospital')
                                                <a href="/patient/{{ $record['id'] }}/assessment-form">Assessment Form</a>
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
    @include('components.calendly_script')
@endpush
