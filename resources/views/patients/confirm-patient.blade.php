@extends('dashboard.layout')

@push('additional_css')
    @include('datatables.css')
@endpush

@section('dashboard_content')
<div class="content-page">
    <div class="content">
        <!-- Start Content-->
        <div class="container-fluid">
        <!-- Modal -->
        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleModalLabel">Proceed Process</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="/in-person-assessment/store">
                        @csrf
                        <input type="hidden" id="patient_id" name="patient_id">
                        <input type="hidden" id="booking_id" name="booking_id">
                        <input type="hidden" id="status" name="status" value="accepted">
                    <select class="form-select mb-3" name="tier_id">
                        <option selected>Select Tier</option>
                    @foreach($some['mytiers'] as $s)
                        <option value={{$s['tier_id']}}>{{$s['tier']}}  {{$s['retirement_home_price']}}</option>
                    @endforeach
                    </select>
                    <a href="javascript:void(0)"><button type="submit" class="btn btn-success">Proceed</button></a>
                    </form>
                </div>
                <div class="modal-footer">
                <button type="submit" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                {{-- <a href="/in-person-assessment/store/"><button type="button" class="btn btn-success">Proceed</button></a> --}}
                </div>
            </div>
            </div>
        </div>
        <!-- Modal End-->            
        <!-- start page title -->
        <div class="row">
            <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <a class="btn btn-primary" href="{{ url()->previous() }}" >Back</a>
                </div>
                <h4 class="page-title">Assesment Form Details</h4>
            </div>
            </div>
        </div>
        <!-- end page title -->

        <div class="row">
            <div class="col-xxl-12 col-lg-12">
            <!-- project card -->
            <div class="card d-block ribbon-box">
                <div class="card-body">
                {{-- <div class="ribbon ribbon-success float-end">
                    <i class="mdi mdi-access-point me-1"></i> Discharged
                </div> --}}
                <div
                    class="d-flex justify-content-between align-items-center mb-3"
                >
                    <img
                    src={{$data['image']}}
                    class="rounded-circle avatar-lg img-thumbnail"
                    alt="profile-image"
                    />
                    <!-- <h3 class="px-1">John Doe</h3> -->

                    {{-- <div class="dropdown">
                        <a
                            href="#"
                            class="dropdown-toggle arrow-none card-drop"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            <i class="ri-more-fill"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <!-- item-->
                            <a href="javascript:void(0);" class="dropdown-item"
                            ><i class="mdi mdi-pencil me-1"></i>Edit</a
                            >
                            <!-- item-->
                            <a href="javascript:void(0);" class="dropdown-item"
                            ><i class="mdi mdi-email-outline me-1"></i
                            >Deactive</a
                            >
                        </div>
                    </div> --}}

                    <!-- project title-->
                </div>
                <!--<div class="badge bg-success text-light mb-3">Active</div>-->

                

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <h4 class="mb-1">Patient Information</h4>
                            </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <h5 class="mb-1">
                            <i class="mdi mdi-account-edit text-primary"></i> Full Name
                            </h5>
                            <p class="mb-0 font-13">{{$data['name']}}</p>
                        </div>
                        </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <h5 class="mb-1">
                            <i class="mdi mdi-account-circle text-primary"></i> Gender
                            </h5>
                            <p class="mb-0 font-13">{{$data['gender']}}</p>
                        </div>
                        </div>
                        {{-- <div class="col-md-3">
                        <div class="mb-3">
                            <h5 class="mb-1">
                            <i class="mdi mdi-phone text-primary"></i> Phone
                            </h5>
                            <p class="mb-0 font-13">{{$data['phone']}}</p>
                        </div>
                        </div>
                    <div class="col-md-3">
                    <div class="mb-3">
                        <h5 class="mb-1">
                        <i class="mdi mdi-email text-primary"></i> Email
                        </h5>
                        <p class="mb-0 font-13">{{$data['email']}}</p>
                    </div>
                    </div> --}}
                    
                
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <h4 class="mb-1">Secondary Contact Information</h4>
                            </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <h5 class="mb-1">
                            <i class="mdi mdi-account-edit text-primary"></i> Full Name
                            </h5>
                            <p class="mb-0 font-13">{{$data['patient_form']->secondary_contact_name}}</p>
                        </div>
                        </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <h5 class="mb-1">
                            <i class="mdi mdi-lifebuoy text-primary"></i> Relationship to Patient
                            </h5>
                            <p class="mb-0 font-13">{{$data['patient_form']->secondary_contact_relationship}}</p>
                        </div>
                        </div>
                        <div class="col-md-3">
                        <div class="mb-3">
                            <h5 class="mb-1">
                            <i class="mdi mdi-phone text-primary"></i> Phone
                            </h5>
                            <p class="mb-0 font-13">{{$data['patient_form']->secondary_contact_phone}}</p>
                        </div>
                        </div>
                    <div class="col-md-3">
                    <div class="mb-3">
                        <h5 class="mb-1">
                        <i class="mdi mdi-email text-primary"></i> Email
                        </h5>
                        <p class="mb-0 font-13">{{$data['patient_form']->secondary_contact_email}}</p>
                    </div>
                    </div>
                    
                
                </div>

                
                </div>
                <!-- end card-body-->
            </div>
            <!-- end card-->
            </div>
            <!-- end col -->

        </div>
        <!-- end row -->

    

        <!------Amenities and features--->

        <div class="row">
            <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                <h4 class="card-title mb-3">Assessment</h4>

                <div class="row">
                    <div class="col-md-4">
                    <div class="mb-3">
                        <p class="mb-1 text-13 bold">
                            1. Has this patient been designated ALC?
                        </p>
                        @if($data['patient_form']->designated_alc=='yes' )
                            <p><b>Yes</b></p>
                        @elseif($data['patient_form']->designated_alc=='no' )
                            <p><b>No</b></p>
                        @else
                            <p><b></b></p>
                        @endif
                    </div>
                    </div>
                    <!---End column-------->
                    <div class="col-md-4">
                    <div class="mb-3">
                        <p class="mb-1 text-13 bold">
                            2. Has the patient been considered medically stable for at least 3 days
                            @if($data['patient_form']->least_3_days=='yes' )
                                <p><b>Yes</b></p>
                            @elseif($data['patient_form']->least_3_days=='no' )
                                <p><b>No</b></p>
                            @else
                                <p><b></b></p>
                            @endif
                        </p>
                    </div>
                    </div>
                    <!---End column-------->
                    <div class="col-md-4">
                    <div class="mb-3">
                        <p class="mb-1 text-13 bold">
                            3. Does this patient have a negative PCR COVID test?
                            @if($data['patient_form']->pcr_covid_test=='yes' )
                                <p><b>Yes</b></p>
                            @elseif($data['patient_form']->pcr_covid_test=='no' )
                                <p><b>No</b></p>
                            @else
                                <p><b></b></p>
                            @endif
                        </p>
                    </div>
                    </div>
                    <!---End column-------->
                <div class="col-md-4">
                    <div class="mb-3">
                        <p class="mb-1 text-13 bold">
                            4. Is there a post-acute care destination/plan in place?
                            @if($data['patient_form']->post_acute=='yes' )
                                <p><b>Yes</b></p>
                            @elseif($data['patient_form']->post_acute=='no' )
                                <p><b>No</b></p>
                            @else
                                <p><b></b></p>
                            @endif
                        </p>
                    </div>
                    </div>
                    <!---End column-------->
                    <div class="col-md-4">
                    <div class="mb-3">
                        <p class="mb-1 text-13 bold">
                        5. If Yes to 4, What is the planned post-acute care destination?
                        <p><b>{{$data['patient_form']->if_yes}}</b></p>
                        </p>
                    </div>
                    </div>
                    <!---End column-------->
                    <div class="col-md-4">
                    <div class="mb-3">
                        <p class="mb-1 text-13 bold">
                            6. What is the estimated length of need (in days) for post-acute care?
                            <p>
                                @if($data['patient_form']->length=='1')
                                    <b>30 Days</b>
                                @elseif($data['patient_form']->length=='2')
                                    <b>30 - 45 Days</b>
                                @elseif($data['patient_form']->length=='3')
                                    <b>45 - 60 Days</b>
                                @elseif($data['patient_form']->length=='4')
                                    <b>65 - 70 Days</b>
                                @elseif($data['patient_form']->length=='5')
                                    <b>75 - 90 Days</b>                                                                        
                                @endif
                            </p>
                        </p>
                    </div>
                    </div>
                    <!---End column-------->
                
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <p class="mb-1 text-13 bold">
                            7. What post-acute care needs does the patient have? Check all that apply. NPC (Nursing and Personal Care) and AR (Activation and Recreation) are included.
                            </p>
                        </div>
                    </div>
                    @foreach(explode(',', $data['patient_form']->npc) as $npc)
                    <div class="col-md-4">
                        <div class="mb-3">
                            <p><i class="uil-check-circle text-primary"></i> <b>{{$npc}}</b></p>
                        </div>
                    </div>
                    @endforeach

                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <p class="mb-1 text-13 bold">
                            8. Acceptable Patient Characteristics (Check all that apply)
                            </p>
                        </div>
                    </div>
                    @foreach(explode(',', $data['patient_form']->apc) as $apc)
                    <div class="col-md-4">
                        <div class="mb-3">
                            <p><i class="uil-check-circle text-primary"></i> <b>{{$apc}}</b></p>
                        </div>
                    </div>
                    @endforeach

                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <p class="mb-1 text-13 bold">
                            9. You confirm that, to the best of your knowledge, the patient does not have ANY of the below characteristics. (Check any that the patient has)
                            </p>
                        </div>
                        </div>
                        @foreach(explode(',', $data['patient_form']->bk) as $bk)
                        <div class="col-md-4">
                        <div class="mb-3">
                            <p><i class="uil-check-circle text-primary"></i> <b>{{$bk}}</b></p>
                        </div>
                        </div>
                        @endforeach  
                
                </div>

                </div>
            </div>
            </div>
        </div>
        {{-- <h1>{{ $data['patient_id'] }}</h1>
        <h1>{{ $data['booking_id'] }}</h1> --}}
        
        <!-------End Amenities and features------------>
        {{-- <div class="float-end"> --}}
            @if($data['booking_status'] == "In person Assessment")
            <span class="btn btn-success mb-2" data-bs-toggle="modal" data-bs-target="#exampleModal" onclick="setid({{ $data['patient_id'] }}, {{ $data['booking_id'] }})">Make Offer
            </span>
            <form method="POST" action="/in-person-assessment/reject">
                @csrf
                <input type="hidden" id="patient_id_rej" name="patient_id_rej">
                <input type="hidden" id="booking_id_rej" name="booking_id_rej">
                <input type="hidden" id="status_rej" name="status_rej" value="rejected">
                <button class="btn btn-danger mb-2" type="submit" 
                onclick="setidrej({{ $data['patient_id'] }}, {{ $data['booking_id'] }})">Reject   
                    
                </button>
            </form>

            @endif
   
        {{-- </div> --}}

    
        <!-- end row-->
        </div>
        <!-- container -->
    </div>
</div> 
<script>
    function setid(pid, bid){
        let patient_field = document.getElementById("patient_id").value = pid;
        let booking_field = document.getElementById("booking_id").value = bid;  
    }
    function setidrej(pidr, bidr){
        let patient_field_rej = document.getElementById("patient_id_rej").value = pidr;
        let booking_field_rej = document.getElementById("booking_id_rej").value = bidr;  
    }
</script>
@endsection

@push('additional_scripts')
    @include('datatables.scripts')
@endpush
