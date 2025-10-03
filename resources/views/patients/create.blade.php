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
                            <h4 class="page-title">Add Patient</h4>
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

                                <form action="/patients/store" METHOD="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">
                                        <div class="col-lg-12">

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label required for="simpleinput" class="form-label">Full Name</label>
                                                        <input type="text" id="simpleinput" class="form-control" placeholder="Name" name="name" value="{{ old('name') }}">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="example-email" class="form-label">Gender</label>
                                                        <select class="form-select mb-3" name="gender" required>
                                                            <option>Select Gender</option>
                                                            <option value="Male">Male</option>
                                                            <option value="Female">Female</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="example-fileinput" class="form-label">Upload Logo</label>
                                                        <input type="file" id="example-fileinput" name="image" class="form-control" accept="image/png, image/jpeg, image/jpg">
                                                        <img id="imgPreview" src="#" alt="pic" width="70" height="70" style="margin-top: 10px; border-radius: 0.25rem;" />
                                                    </div>
                                                </div>

                                            </div>

                                            <button class="btn btn-primary" type="submit">Register Patient</button>




















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


