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
                            <h4 class="page-title">Bookings</h4>
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



                                <table id="alternative-page-datatable" class="table dt-responsive nowrap w-100 ">
                                    <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Hospital</th>
                                        <th>Retirement Home</th>
                                        <th>Status</th>
                                        {{-- <th>Tier</th>
                                        <th>Price</th> --}}
                                        <th>Actions</th>
                                    </tr>
                                    </thead>


                                    <tbody>
                                    @foreach($data as $booking)
                                        <tr>
                                            <td>{{ $booking['patient_name'] }}</td>
                                            <td>{{ $booking['hospital_name'] }}</td>
                                            <td>{{ $booking['retirement_home_name'] }}</td>
                                            <td>
                                                @if($booking['status'] == 'Available')
                                                    <span class="badge badge-success-lighten">Available</span>
                                                @elseif($booking['status'] == 'Placement Made')
                                                    <span class="badge badge-warning-lighten">Placement Made</span>
                                                @elseif($booking['status'] == 'Inactive')
                                                    <span class="badge badge-danger-lighten">Inactive</span>
                                                @elseif($booking['status'] == 'Application Progress')
                                                    <span class="badge badge-info-lighten">Application In Progress</span>
                                                @elseif($booking['status'] == 'In person Assessment')
                                                    <span class="badge badge-info-lighten">In Person Assessment</span>
                                                @endif
                                            </td>
                                            {{-- <td>{{ $booking['tier'] }}</td>
                                            <td>{{ $booking['price'] }}</td> --}}
                                            <td class="table-action">
                                                <a href="/view-booking/{{ $booking['booking_id'] }}" class="action-icon"> <i class="mdi mdi-eye text-warning"></i></a>
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
        {{-- @include('dashboard.footer') --}}
        <!-- end Footer -->

    </div>
@endsection

@push('additional_scripts')
    @include('datatables.scripts')
@endpush


