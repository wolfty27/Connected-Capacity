@extends('dashboard.layout')

@push('additional_css')
    @include('datatables.css')
@endpush

@section('dashboard_content')
    <style>
        .see-all:hover{
            text-decoration: underline;
        }
    </style>
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
                            <h4 class="page-title">Retirement Home Details</h4>
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
                                @if(Auth::user()->role == 'admin')
                                    <input type="hidden" value={{ $data['id'] }} id="retidhidden" name="retidhidden">
                                    {{-- <div class="ribbon float-end"><i class="mdi mdi-access-point me-1"></i></div> --}}
                                    <div  class="alpha">

                                    </div>
                                @endif
                                <div class="d-flex justify-content-between align-items-center mb-3">

                                    <img src="{{ $data['image'] }}" class="rounded-circle avatar-lg img-thumbnail" alt="profile-image">
                                    <h3 class="px-1"> {{ $data['name'] }}</h3>
                                    @if(Auth::user()->role == 'admin')
                                        <div class="dropdown">
                                            <a href="#" class="dropdown-toggle arrow-none card-drop" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="ri-more-fill"></i>
                                            </a>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <!-- item-->
                                                <a href="/retirement-homes/edit/{{ $data['id'] }}" class="dropdown-item"><i class="mdi mdi-pencil me-1"></i>Edit</a>
                                                <!-- item-->
    {{--                                            <a href="javascript:void(0);" class="dropdown-item"><i class="mdi mdi-email-outline me-1"></i>Deactive</a>--}}
                                            </div>
                                        </div>
                                        <div class="float-end statusdiv">
                                            <input @if($data['status'] == '1') checked @endif class="toggle-class" type="checkbox" id="switch1" data-switch="success" data-id="{{$data['id']}}"/>
                                            <label for="switch1" data-on-label="" data-off-label=""></label>
                                        </div>
                                    @endif

                                    <!-- project title-->
                                </div>
                                <!--<div class="badge bg-success text-light mb-3">Active</div>-->

                                {{-- <h5>Overview:</h5>

                                <p class="mb-2">
                                    With supporting text below as a natural lead-in to additional contenposuere erat a ante. Voluptates, illo, iste itaque voluptas
                                    corrupti ratione reprehenderit magni similique? Tempore, quos delectus asperiores libero voluptas quod perferendis! Voluptate,
                                    quod illo rerum? Lorem ipsum dolor sit amet.
                                </p> --}}


                                <div class="row">
                                    @if(Auth::user()->role == 'admin')
                                        <div class="col-md-4">
                                            <div class="mb-3">

                                                <h5 class="mb-1"><i class="mdi mdi-email text-muted"></i> Email</h5>
                                                <p class="mb-0 font-13">{{ $data['email'] }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <h5 class="mb-1"><i class="mdi mdi-phone text-muted"></i> Phone</h5>
                                                <p class="mb-0 font-13">{{ $data['phone'] }}</p>
                                            </div>
                                        </div>
                                    @endif
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <h5 class="mb-1"><i class="mdi mdi-web text-muted"></i> Website</h5>
                                            <p class="mb-0 font-13">{{ $data['website'] }}</p>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <h5 class="mb-1">Retirement Home Type</h5>
                                            <p class="mb-0 font-13">{{ $data['type'] }}</p>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <h5 class="mb-1">Address</h5>
                                            <p class="mb-0 font-13">{{ $data['address'] }}</p>
                                        </div>
                                    </div>
                                </div>

{{--                                <div class="row">--}}
{{--                                    <div class="col-md-12">--}}


{{--                                        <div id="gmaps-basic" class="gmaps"></div>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                                <div class="row">--}}
{{--                                    <div class="col-md-12 col-sm-12">--}}
{{--                                        <img src="/assets/images/map.jpeg" class="img-thumbnail" alt="maps">--}}
{{--                                    </div>--}}
{{--                                </div>--}}
                                {{-- <div class="row">
                                    <div class="col-md-12 col-sm-12">
                                        <div id="googleMap" style="width:100%;height:300px; background: var(--ct-gray-100);"></div>
                                    </div>
                                </div> --}}



                            </div> <!-- end card-body-->

                        </div> <!-- end card-->


                    </div> <!-- end col -->

                    <div class="col-lg-6 col-xxl-4">

                        {{-- <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Files</h5>

                                <div class="card mb-1 shadow-none border">
                                    <div class="p-2">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="avatar-sm">
                                                            <span class="avatar-title rounded">
                                                                .ZIP
                                                            </span>
                                                </div>
                                            </div>
                                            <div class="col ps-0">
                                                <a href="javascript:void(0);" class="text-muted fw-bold">Hyper-admin-design.zip</a>
                                                <p class="mb-0">2.3 MB</p>
                                            </div>
                                            <div class="col-auto">
                                                <!-- Button -->
                                                <a href="javascript:void(0);" class="btn btn-link btn-lg text-muted">
                                                    <i class="ri-download-2-line"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-1 shadow-none border">
                                    <div class="p-2">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <img src="/assets/images/projects/project-1.jpg" class="avatar-sm rounded" alt="file-image" />
                                            </div>
                                            <div class="col ps-0">
                                                <a href="javascript:void(0);" class="text-muted fw-bold">Dashboard-design.jpg</a>
                                                <p class="mb-0">3.25 MB</p>
                                            </div>
                                            <div class="col-auto">
                                                <!-- Button -->
                                                <a href="javascript:void(0);" class="btn btn-link btn-lg text-muted">
                                                    <i class="ri-download-2-line"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-0 shadow-none border">
                                    <div class="p-2">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="avatar-sm">
                                                            <span class="avatar-title bg-secondary text-light rounded">
                                                                .MP4
                                                            </span>
                                                </div>
                                            </div>
                                            <div class="col ps-0">
                                                <a href="javascript:void(0);" class="text-muted fw-bold">Admin-bug-report.mp4</a>
                                                <p class="mb-0">7.05 MB</p>
                                            </div>
                                            <div class="col-auto">
                                                <!-- Button -->
                                                <a href="javascript:void(0);" class="btn btn-link btn-lg text-muted">
                                                    <i class="ri-download-2-line"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @if (auth()->user()->role == 'retirement-home' || auth()->user()->role == 'admin')
                                <a class="see-all" href="/retirement-homes/files/{{ $data['id'] }}" style="float: right;">see all...</a>
                                @endif
                            </div>
                        </div> --}}

                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-2">Gallery</h5>
                                <div class="row">
                                    @if(sizeof($data['galleries']) == 0)
                                    <h5 class="text-muted fw-normal mt-0 text-truncate">
                                        Gallery is Empty.
                                    </h5>    
                                    @endif
                                    @foreach(array_slice($data['galleries'], 0, 2) as $key => $gallery)
                                        <div class="col">
                                            <img src={{$gallery['gallery_image']}} alt="post-img" class="rounded me-1 mb-3 mb-sm-0 img-fluid">
                                        </div>
                                    @endforeach 
                                    {{-- <div class="col-sm-8">
                                        <img src="/assets/images/small/small-1.jpg" alt="post-img" class="rounded me-1 mb-3 mb-sm-2 img-fluid">
                                        <img src="/assets/images/small/small-4.jpg" alt="post-img" class="rounded me-1 mb-3 mb-sm-0 img-fluid">

                                    </div>
                                    <div class="col">
                                        <img src="/assets/images/small/small-2.jpg" alt="post-img" class="rounded me-1 img-fluid mb-3">
                                        <img src="/assets/images/small/small-3.jpg" alt="post-img" class="rounded me-1 img-fluid mb-3">
                                        <img src="/assets/images/small/small-4.jpg" alt="post-img" class="rounded me-1 mb-3 mb-sm-0 img-fluid">

                                    </div> --}}
                                </div>
                                @if (auth()->user()->role == 'retirement-home' || auth()->user()->role == 'admin')
                                <a class="see-all" href="/retirement-homes/gallery/{{ $data['user_id'] }}" style="float: right;">see all...</a>
                                @elseif(auth()->user()->role == 'hospital')
                                <a class="see-all" href="/retirement-homes/gallery/justview/{{ $data['user_id'] }}" style="float: right;">See All...</a>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
                <!-- end row -->
            @if(Auth::user()->role == 'admin')

                <!------Tiers--->

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Tiers</h5>

                                <div class="row">
                                    <div class="col-lg-12">
                                        <table
                                            id="alternative-page-datatable"
                                            class="table dt-responsive nowrap w-100 dataTable no-footer dtr-inline"
                                            aria-describedby="alternative-page-datatable_info"
                                            style="width: 1117px"
                                        >
                                            <thead>
                                            <tr>
                                                <th
                                                    class="sorting sorting_asc"
                                                    tabindex="0"
                                                    aria-controls="alternative-page-datatable"
                                                    rowspan="1"
                                                    colspan="1"
                                                    style="width: 426.8px"
                                                    aria-label="
                                File Name
                              : activate to sort column descending"
                                                    aria-sort="ascending"
                                                >
                                                    Tier Name
                                                </th>
                                                <th class="sorting"
                                                    tabindex="0"
                                                    aria-controls="alternative-page-datatable"
                                                    rowspan="1"
                                                    colspan="1"
                                                    style="width: 222.8px"
                                                    aria-label="File Size: activate to sort column ascending">
                                                    @if (auth()->user()->role == 'admin')
                                                        Amount Payable to Retirement Home ($)
                                                    @else
                                                        Amount ($)
                                                    @endif
                                                </th>
                                                @if(auth()->user()->role == 'admin')
                                                    <th
                                                        class="sorting"
                                                        tabindex="0"
                                                        aria-controls="alternative-page-datatable"
                                                        rowspan="1"
                                                        colspan="1"
                                                        style="width: 222.8px"
                                                        aria-label="
                              File Size
                            : activate to sort column ascending"
                                                    >
                                                        Amount Receivable from Hospital ($)
                                                    </th>
                                                @endif
                                            </tr>
                                            </thead>

                                            <tbody>
                                                @foreach($data['tiers'] as $tier)
                                                    <tr class="">
                                                        <td
                                                            class="table-action fw-bolder dtr-control sorting_1"
                                                            tabindex="0"
                                                        >
                                                            {{ $tier['tier'] }}
                                                        </td>
                                                        @if(auth()->user()->role == 'admin')
                                                            <td class="table-action">{{ $tier['retirement_home_price'] }}</td>
                                                        @endif
                                                        <td class="table-action">{{ $tier['hospital_price'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-------End Tiers------------>
            @endif

                <!------Amenities and features--->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Amenities and Features</h5>

                                <div class="row">
                                    @foreach($data['amenitiesAndFeatures'] as $amenityAndFeature)
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <p class="mb-1 text-13 bold"><i class="uil-check-circle text-success"></i> {{ $amenityAndFeature }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-------End Amenities and features------------>


            @if(Auth::user()->role == 'admin')
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
                                        {{-- <th>Tier</th> --}}
                                        <th>Actions</th>
                                    </tr>
                                    </thead>


                                    <tbody>
                                        @foreach($data['patients'] as $patient)
                                            <tr>
                                                <td class="table-user">
                                                    <img src="{{ $patient['photo'] }}" alt="table-user" class="me-2 rounded-circle">
                                                </td>
                                                <td>{{ $patient['name'] }}</td>
                                                <td>{{ $patient['gender'] }}</td>
                                                <td>
                                                    <span class="badge badge-warning-lighten">Placement Made</span>
                                                </td>
                                                {{-- <td>{{ $patient['tier'] }}</td> --}}
                                                <td class="table-action">
                                                    <a href="/patients/view/{{ $patient['id'] }}" class="action-icon"> <i class="mdi mdi-eye text-warning"></i></a>
                                                    {{-- <a href="/patients/delete/{{ $patient['id'] }}" class="action-icon" onclick="return confirm('Are you sure you want to delete this item?');"> <i class="mdi mdi-close text-danger"></i></a> --}}
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
            @endif


            </div> <!-- container -->

        </div> <!-- content -->

        <!-- Footer Start -->
        @include('dashboard.footer')
        <!-- end Footer -->

    </div>
   
@endsection

@push('additional_scripts')
<script type="text/javascript">
    $(document).ready(function(){
        var retirementhomeid = document.getElementById("retidhidden").value;
        fetchstatus(retirementhomeid);
        function fetchstatus(id){
            $.ajax({
                type: "GET",
                url: "/retirement-homes/fetch-status/"+id,
                dataType: "JSON",
                success: function (response){
                    $(".alpha").html("");
                    $.each(response.rethomests, function(key, item){
                        if(item.status === '1'){
                            $(".alpha").append('<div class="ribbon ribbon-success float-end"><i class="mdi mdi-access-point me-1"></i>Available</div>');
                        }
                        else{
                            $(".alpha").append('<div class="ribbon ribbon-danger float-end"><i class="mdi mdi-access-point me-1"></i>Inactive</div>');
                        }
                    });
                }
                

            });
        }
        $(function() {
            $('.toggle-class').change(function() {
                var status = $(this).prop('checked') == true ? 1 : 0; 
                var ret_id = $(this).data('id'); 
                $.ajax({
                    type: "GET",
                    dataType: "json",
                    url: '/retirement-homes/change-status/'+ret_id+'/'+status,
                    success: function(response){
                        fetchstatus(retirementhomeid);
                    }
                });
            })
        })
 
    });
</script> 
    @include('datatables.scripts')
    @include('components.google_map_script')
@endpush
