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
                            <h4 class="page-title">Scheduled Events</h4>
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
                <!-- Modal -->
                <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Invitee Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body row">
                        
                        </div>
                        <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        {{-- <button type="button" class="btn btn-primary">Save changes</button> --}}
                        </div>
                    </div>
                    </div>
                </div>
                <!--End Modal -->
                <div class="row">
                    @foreach($response->collection as $data)
                        <div class="col-xl-4 col-lg-4">
                            <div class="card tilebox-one">
                                <div class="card-body">
                                    <span class="eyespan"><i data-bs-toggle="modal" data-bs-target="#exampleModal" data-url={{$data->uri}} class='mdi mdi-eye float-end geturi'></i></span>
                                    <h6 class="text-uppercase mt-0">{{$data->name}}</h6>
                                    <h2 class="my-2" id="active-users-count"></h2>
                                    <p class="mb-0 text-muted">
                                        @if($data->status == "active")
                                        <span class="text-success me-2">{{$data->status}}</span>
                                        @elseif($data->statys == "inactive")
                                        <span class="text-danger me-2">{{$data->status}}</span>
                                        @endif
                                        <span class="text-nowrap"></span>
                                    </p>
                                </div> <!-- end card-body-->
                            </div>
                        </div><!-- end col-->
                    @endforeach
                </div>
                <!-- end row-->
            </div> <!-- container -->

        </div> <!-- content -->

        @include('dashboard.footer')

    </div>
@endsection

@push('additional_scripts')
    @include('datatables.scripts')
    <script>
    $(document).ready(function(){
        function fetchInvitee(myuri){
            $.ajax({
                type: "GET",
                url: "/my-calendly/get-invitee-data?uri="+ encodeURI(myuri),
                dataType: "JSON",
                success: function (response){
                    $(".modal-body").html("");
                    errorTitle = response.inviteeData.title;
                    errorMessageTitle = response.inviteeData.message;
                    if(response.httpcode != "200"){
                        $(".modal-body").append('<div class="text-center"><h4 class="text-danger">Kindly Connect Calendly</h4></div>');
                    }
                    else{
                        inviteeData = response.inviteeData.collection[0];
                        scheduledEventData = response.scheduledEventData.resource;
                        
                        let dateCreated = new Date(inviteeData['created_at']);
                        let startTime = new Date(scheduledEventData['start_time']);
                        let endTime = new Date(scheduledEventData['end_time']);
    
                        createDate = dateCreated.toLocaleString();
                        startDate = startTime.toLocaleString();
                        endDate = endTime.toLocaleString();
                        
                        $(".modal-body").append('<div class="col-xl-4 col-lg-4">\
                            <div class="input-group mb-3">\
                            <span class="input-group-text" id="basic-addon1">Name</span>\
                            <input disabled type="text" class="form-control" value="'+inviteeData['name']+'" aria-describedby="basic-addon1">\
                            </div></div>\
                            <div class="col-xl-4 col-lg-4">\
                            <div class="input-group mb-3">\
                            <span class="input-group-text" id="basic-addon1">Email</span>\
                            <input disabled type="text" class="form-control" value="'+inviteeData['email']+'" aria-describedby="basic-addon1">\
                            </div></div>\
                            <div class="col-xl-4 col-lg-4">\
                            <div class="input-group mb-3">\
                            <span class="input-group-text" id="basic-addon1">Timezone</span>\
                            <input disabled type="text" class="form-control" value="'+inviteeData['timezone']+'" aria-describedby="basic-addon1">\
                            </div></div>\
                            <div class="col-xl-4 col-lg-4">\
                            <div class="input-group mb-3">\
                            <span class="input-group-text" id="basic-addon1">Created at</span>\
                            <input disabled type="text" class="form-control" value="'+createDate+'" aria-describedby="basic-addon1">\
                            </div></div>\
                            <div class="col-xl-4 col-lg-4">\
                            <div class="input-group mb-3">\
                            <span class="input-group-text" id="basic-addon1">Start Date</span>\
                            <input disabled type="text" class="form-control" value="'+startDate+'" aria-describedby="basic-addon1">\
                            </div></div>\
                            <div class="col-xl-4 col-lg-4">\
                            <div class="input-group mb-3">\
                            <span class="input-group-text" id="basic-addon1">End Date</span>\
                            <input disabled type="text" class="form-control" value="'+endDate+'" aria-describedby="basic-addon1">\
                            </div></div>\
                            <div class="col-xl-4 col-lg-4">\
                            <div class="input-group mb-3">\
                            <span class="input-group-text" id="basic-addon1">Status</span>\
                            <input disabled type="text" class="form-control text-success" value="'+inviteeData['status']+'" aria-describedby="basic-addon1">\
                            </div></div>\
                        ');
                    }
                }

            });
        }        
        $(document).on('click', '.geturi', function (e){
            let uri = $(this).data('url');
            fetchInvitee(uri);     
        });
    });
    </script>
@endpush

