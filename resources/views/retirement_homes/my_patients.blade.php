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
                            <h4 class="page-title">My Patients</h4>
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
                                    </div>
                                    <div class="col-sm-8">
                                        <div class="text-sm-end">
                                        </div>
                                    </div><!-- end col-->
                                </div>

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
                                    @foreach($data['my_patients'] as $patient)
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{ $patient['photo'] }}" alt="table-user" class="me-2 rounded-circle">
                                            </td>
                                            <td>{{ $patient['name'] }}</td>
                                            <td>{{ $patient['gender'] }}</td>
                                            <td>
                                                @if($patient['status'] == 'Available')
                                                    <span class="badge badge-success-lighten">Available</span>
                                                @elseif($patient['status'] == 'Placement Made')
                                                    <span class="badge badge-warning-lighten">Placement Made</span>
                                                @elseif($patient['status'] == 'Inactive')
                                                    <span class="badge badge-danger-lighten">Inactive</span>
                                                @elseif($patient['status'] == 'Application Progress')
                                                    <span class="badge badge-info-lighten">Application In Progress</span>
                                                @elseif($patient['status'] == 'In person Assessment')
                                                    <span class="badge badge-info-lighten">In Person Assessment</span>
                                                @endif
                                            </td>
                                            <td class="table-action">
                                                <a href="/patients/view/{{ $patient['id'] }}" class="action-icon"> <i class="mdi mdi-eye text-warning"></i></a>
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
