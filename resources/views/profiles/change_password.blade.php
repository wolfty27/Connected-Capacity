@extends('dashboard.layout')

@push('additional_css')
    <link rel="stylesheet" href="assets/css/style.css">
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
                        <h5 class="page-title">Change Password</h5>
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
                            <div class="row profile-body">
                                <div class="col-lg-12">
                                    <div class="mb-3 mt-3">
                                        <div class="row">
                                            <div class="col-lg-12 position-relative">
                                                <form method="POST" action="/my-account/update-password/{{$data['id']}}">
                                                    @csrf
                                                    <div class="form">
                                                        <div class="row"> 
                                                            <div class="col-lg-6">
                                                                <div class="mb-3">
                                                                    <label for="email" class="form-label">Email</label>
                                                                    <input type="email" name="email" placeholder="gpelelis@gmail.com" class="form-control" disabled value="{{ $data['email'] }}">
                                                                </div>
                                                            </div> 
                                                            <div class="col-lg-6">
                                                                <div class="mb-3">
                                                                    <label for="current_password" class="form-label">Current Password</label>
                                                                    <div class="input-group input-group-merge">
                                                                        <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Enter your password" name="password">
                                                                        <div class="input-group-text" data-password="false">
                                                                            <span class="password-eye"></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-6">
                                                                <div class="mb-3">
                                                                    <label for="new_password" class="form-label">New Password</label>
                                                                    <div class="input-group input-group-merge">
                                                                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Enter your password" name="password">
                                                                        <div class="input-group-text" data-password="false">
                                                                            <span class="password-eye"></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>  
                                                            <div class="col-lg-6">
                                                                <div class="mb-3">
                                                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                                                    <div class="input-group input-group-merge">
                                                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Enter your password" name="password">
                                                                        <div class="input-group-text" data-password="false">
                                                                            <span class="password-eye"></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div> 
                                                            <div class="col-lg-6">
                                                                <div class="mb-3">
                                                                    <button type="submit" class="btn btn-primary mt-3">Update</button>
                                                                </div>
                                                            </div>                                                                                                                                                                                                                                              
                                                        </div>
                                                    </div>                                                  
                                                </form>
                                            </div> 
                                        </div> 
                                    </div> 
                                </div> 
                            </div> 
                        </div> 
                    </div> 
                </div>
            </div>                                             
        </div>
    </div>
</div>                        
    <!-- Footer Start -->
    @include('dashboard.footer')
    <!-- end Footer -->

@endsection

@push('additional_scripts')
    

    <!-- Code Highlight js -->
    <script src="assets/vendor/highlightjs/highlight.pack.min.js"></script>
    <script src="assets/js/hyper-syntax.js"></script>

    <!-- Input Mask js -->
    <script src="assets/vendor/jquery-mask-plugin/jquery.mask.min.js"></script>

    {{-- @include('components.google_map_script') --}}
    @include('retirement_homes.edit_script')

@endpush
