@extends('dashboard.layout')
@inject('carbon', 'Carbon\Carbon')
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
                            <h4 class="page-title">Appointments</h4>
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
                                        <th>Patient Name</th>
                                        <th>Retirement Home</th>
                                        <th>Date</th>
                                        <th>Slot</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($bookings as $booking)
                                            <tr>
                                                <td>{{ $booking->patient->user->name }}</td>
                                                <td>{{ $booking->retirement->user->name }}</td>
                                                <td>{{ $carbon::parse($booking->start_time)->ToDateString() }}</td>
                                                <td>{{ $carbon::parse($booking->start_time)->format('h:i') }} - {{ $carbon::parse($booking->end_time)->format('h:i') }}</td>
                                                {{-- <td class="table-action">
                                                    <a href="/booking/view/{{ $booking['booking_id'] }}" class="action-icon"> <i class="mdi mdi-eye text-warning"></i></a>
                                                </td> --}}
                                            <td>
                                                <a href="/patients/view/{{ $booking->patient->id }}" class="action-icon"> <i class="mdi mdi-eye text-warning"></i></a>
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
