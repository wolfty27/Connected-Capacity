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
                            <h4 class="page-title">Hospitals</h4>
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
                                        @if(auth()->user()->role == 'admin')
                                            <a href="/hospitals/create" class="btn btn-primary mb-2"><i class="mdi mdi-plus-circle me-2"></i> Add Hospital</a>
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
                                        <th>Logo</th>
                                        <th>Full Name</th>
                                        @if(auth()->user()->role == 'admin')
                                            <th>Email</th>
                                            <th>Phone</th>
                                        @endif
                                        <th>Website</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                        @foreach($data as $record)
                                            <tr>
                                                <td class="table-user">
                                                    <img src="{{ $record['logo'] }}" alt="table-user" class="me-2 rounded-circle">
                                                </td>
                                                <td>{{ $record['name'] }}</td>
                                                @if(auth()->user()->role == 'admin')
                                                    <td>{{ $record['email'] }}</td>
                                                    <td>{{ $record['phone'] }}</td>
                                                @endif
                                                <td>{{ $record['website'] }}</td>
                                                <td class="table-action">
                                                    <a href="/hospitals/view/{{ $record['id'] }}" class="action-icon"> <i class="mdi mdi-eye text-warning"></i></a>
                                                    @if(auth()->user()->role == 'admin')
                                                        <a href="/hospitals/edit/{{ $record['id'] }}" class="action-icon"> <i class="mdi mdi-pencil text-primary"></i></a>
{{--                                                        <a href="/hospitals/delete/{{ $record['id'] }}" onclick="return confirm('Are you sure you want to delete this item?');" class="action-icon"> <i class="mdi mdi-delete text-danger"></i></a>--}}
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

        @include('dashboard.footer')

    </div>
@endsection

@push('additional_scripts')
    @include('datatables.scripts')
@endpush

