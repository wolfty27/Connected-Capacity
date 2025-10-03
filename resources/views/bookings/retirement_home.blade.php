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
                            @if (auth()->user()->role == 'retirement-home')
                            <h4 class="page-title">Appointments</h4>
                            @else
                            <h4 class="page-title">Bookings</h4>
                            @endif
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
                                        <th>Date</th>
                                        <th>Slot</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>


                                    <tbody>
                                    @foreach($data as $booking)
                                        <tr>
                                            <td>{{ $booking->patient->user->name }}</td>
                                            <td>{{ $booking->hospital->user->name }}</td>
                                            <td>{{ $carbon::parse($booking->start_time)->ToDateString() }}</td>
                                            <td>{{ $carbon::parse($booking->start_time)->format('h:i') }} - {{ $carbon::parse($booking->end_time)->format('h:i') }}</td>
                                            <td class="table-action">
                                                <a href="/patients/view/appointed/{{ $booking->patient->id }}/{{$booking->id}}" class="action-icon"> <i class="mdi mdi-eye text-warning"></i></a>
                                                {{-- @if (auth()->user()->role == 'retirement-home')
                                                    <a href="javascript:void(0)" onclick="setid({{ $booking['patient_id'] }}, {{ $booking['booking_id'] }})" class="action-icon"> <i class="mdi mdi-arrow-right-circle text-success" data-bs-toggle="modal" data-bs-target="#exampleModal"></i></a>
                                                    <a href="javascript:void(0)" class="action-icon"> <i class="mdi mdi-close text-danger" ></i></a>
                                                
                                                @endif --}}
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



