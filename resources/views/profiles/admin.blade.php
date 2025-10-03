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
                            <h5 class="page-title">Admin Profile</h5>
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
                    <div class="col-sm-12">
                        <!-- Profile -->
                        <div class="card bg-primary ">
                            <div class="card-body profile-user-box bg-dark  bg-gradient rounded-2">

                                <div class="row">
                                    <div class="col-sm-8">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="avatar-lg">
                                                    <img height="80" width="80" src={{ $data['image'] }} alt="" class="rounded-circle">
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div>
                                                    <h5 class="mt-1 mb-1 text-white">{{ $data['name'] }}</h5>
                                                    {{--                                                    <p class="font-13 text-white-50"> Lorem Ipsum is simply dummy text of the printing and typesetting industry.</p>--}}


                                                </div>
                                            </div>
                                        </div>
                                    </div> <!-- end col-->

                                    <div class="col-sm-4 profile_top_btn">
                                        <div class="text-center mt-sm-0 mt-3 mb-3 text-sm-end">
                                            {{--                                            <a type="button" class="btn btn-light btn-sm" href="edit-hospital.html">--}}
                                            {{--                                                <i class="mdi mdi-account-edit me-1"></i> Edit--}}
                                            {{--                                            </a>--}}
                                            {{--                                            <a type="button" class="icon_white btn btn-danger btn-sm" href="javascript: void(0);">--}}
                                            {{--                                                <i class="mdi mdi-delete  me-1"></i> Delete--}}
                                            {{--                                            </a>--}}
                                        </div>

                                    </div> <!-- end col-->
                                </div> <!-- end row -->

                            </div> <!-- end card-body/ profile-user-box-->
                        </div>
                        <!--end profile/ card -->
                    </div> <!-- end col-->
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">

                                <div class="row profile-body">
                                    <div class="col-lg-12">
                                        <div class="mb-3 mt-3">
                                            <div class="row">
                                                <div class="row">
                                                    <div class="col-lg-12 position-relative">
                                                        <form action="/my-account/update/{{ $data['user_id'] }}" method="POST" enctype="multipart/form-data">
                                                            @csrf
                                                            <div class="form">
                                                                <div class="row">
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="name" class="form-label">Full Name</label>
                                                                            <input type="text" name="name" placeholder="john" class="form-control" value="{{ $data['name'] }}" disabled>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="email" class="form-label">Email</label>
                                                                            <input type="email" name="email" placeholder="gpelelis@gmail.com" class="form-control" disabled value="{{ $data['email'] }}">
                                                                        </div>
                                                                    </div>
                                                                    {{-- <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="password" class="form-label">New Password</label>
                                                                            <div class="input-group input-group-merge">
                                                                                <input type="password" id="password" class="form-control" placeholder="Enter your password" name="password">
                                                                                <div class="input-group-text" data-password="false">
                                                                                    <span class="password-eye"></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="password" class="form-label">Confirm Password</label>
                                                                            <div class="input-group input-group-merge">
                                                                                <input type="password" id="password" class="form-control" placeholder="Enter your password" name="password_confirmation">
                                                                                <div class="input-group-text" data-password="false">
                                                                                    <span class="password-eye"></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div> --}}


                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="phone" class="form-label">Phone</label>
                                                                            <input type="tel" name="phone"  placeholder="Enter Phone" class="form-control" value="{{ $data['phone_number'] }}" >
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="timezone" class="form-label"> Timezone</label>
                                                                            <input type="text" name="timezone" placeholder="Enter Time Zone" class="form-control" value="{{ $data['timezone'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="role" class="form-label"> Role</label>
                                                                            <input type="text" name="role" placeholder="" class="form-control" value="{{ $data['role'] }}" disabled>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="zipcode" class="form-label">ZIP Code </label>
                                                                            <input type="number" name="zipcode" placeholder="Enter Zip Code" class="form-control" value="{{ $data['zipcode'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-12">
                                                                        <div class="mb-3">
                                                                            <label for="address" class="form-label"> Address</label>
                                                                            <input type="text" name="address" placeholder="Enter Address" class="form-control" value="{{ $data['address'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="country" class="form-label"> Country</label>
                                                                            <input type="text" name="country" placeholder="Enter Country" class="form-control" value="{{ $data['country'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="city" class="form-label"> City</label>
                                                                            <input type="text" name="city" placeholder="Enter City" class="form-control" value="{{ $data['city'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="lat" class="form-label">Latitude </label>
                                                                            <input type="number" name="latitude" id="lat" placeholder="Enter Latitude" class="form-control" value="{{ $data['latitude'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="long" class="form-label">Longitude </label>
                                                                            <input type="number" name="longitude" id="long" placeholder="Enter Longitude" class="form-control" value="{{ $data['longitude'] }}">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6">
                                                                        <div class="mb-3">
                                                                            <label for="example-fileinput" class="form-label">Update
                                                                                Logo</label>
                                                                            <input type="file" id="example-fileinput" class="form-control" name="logo" accept=".png, .jpeg, .jpg">
                                                                            <img id="imgPreview" src="#" alt="pic" width="70" height="70" style="margin-top: 10px; border-radius: 0.25rem;" />
                                                                        </div>
                                                                    </div>                                                              
                                                                    {{-- <div class="col-lg-6"> --}}
                                                                        <div class="mb-3">
                                                                            <div class="row">
                                                                                <div class="col-md-12 col-sm-12">
                                                                                    {{-- @if($data['longitude'] != '' && $data['latitude'] != '')
                                                                                        <div id="googleMap" style="width:100%;height:300px; background: var(--ct-gray-100);"></div>
                                                                                    @else
                                                                                        <div class="alert alert-danger alert-dismissible" role="alert">
                                                                                            <div>Update latitude and longitude to view the location on map</div>
                                                                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                                                        </div>
                                                                                    @endif --}}
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    {{-- </div> --}}

                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-lg-12">

                                                                        <button type="submit" class="btn btn-primary mt-3" >submit</button>

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
                    <!-- end card-body -->
                </div>
                <!-- end card -->
            </div>
            <!-- end col -->
        </div>
        <!-- end row -->
    </div>


    <!-- Footer Start -->
    @include('dashboard.footer')
    <!-- end Footer -->

    </div>
@endsection

@push('additional_scripts')
    <script>
        let files = [{url:'https://image.shutterstock.com/image-vector/medical-concept-hospital-building-doctor-260nw-588196298.jpg',name:'test'}]

        window.onload = function(){



            console.log('f,iles.length',files.length);
            if(files.length==1)renderImage(files)
            if(window.File && window.FileList && window.FileReader)
            {

                var filesInput = document.getElementById("files");



                filesInput.addEventListener("change", function(event){

                    files=[...files,...event.target.files]
                    renderImage(files)
                    console.log('addEventListener');

                });
            }
            else
            {
                alert("Your browser does not support File API");
            }
        }

        const renderImage = (files)=>{
            console.log('renderImage',files);
            var output = document.getElementById("imgThumbnailPreview");
            output.innerHTML= '';
            files.map((file,index)=>{
                if (file.type){
                    var picReader = new FileReader();

                    picReader.addEventListener("load",function(event){

                        var picSrc = event?.target?.result;

                        var imgThumbnailElem = `<div class='imgThumbContainer'><div class='IMGthumbnail' ><div class='close_btn' onclick="removeimage(${index})"><p>x</p></div><img  src=${picSrc}
                                    "title=${file.name}/><div></div>`;

                        output.innerHTML = output.innerHTML + imgThumbnailElem;

                    });

                    //Read the image
                    picReader.readAsDataURL(file);
                }

                else {
                    var picSrc = file.url;

                    // var imgThumbnailElem = "<div class='imgThumbContainer'><div class='IMGthumbnail' ><div class='close_btn' onclick=removeimage(index)'><p>x</p></div><img  src='" + picSrc + "'" +
                    //         "title='"+file.name + "'/><div></div>";

                    var imgThumbnailElem = `<div class='imgThumbContainer'><div class='IMGthumbnail' ><div class='close_btn' onclick="removeimage(${index})"><p>x</p></div><img  src=${picSrc}
                                    "title=${file.name}/><div></div>`;

                    output.innerHTML = output.innerHTML + imgThumbnailElem;
                }
            })
        }

        const removeimage =(index)=>{
            files = files.filter((f,ind)=>ind!==index)
            renderImage(files)
        }




        // In the following example, markers appear when the user clicks on the map.
        // The markers are stored in an array.
        // The user can then click an option to hide, show or delete the markers.
        var map;
        var markers = [];

        function initMap() {
            var haightAshbury = {lat: 23.2748308, lng: 77.4519248};

            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 16.3,                        // Set the zoom level manually
                center: haightAshbury,
                mapTypeId: 'terrain'
            });

            // This event listener will call addMarker() when the map is clicked.
            map.addListener('click', function(event) {
                if (markers.length >= 1) {
                    deleteMarkers();
                }

                addMarker(event.latLng);
                document.getElementById('lat').value = event.latLng.lat();
                document.getElementById('long').value =  event.latLng.lng();
            });
        }

        // Adds a marker to the map and push to the array.
        function addMarker(location) {
            var marker = new google.maps.Marker({
                position: location,
                map: map
            });
            markers.push(marker);
        }

        // Sets the map on all markers in the array.
        function setMapOnAll(map) {
            for (var i = 0; i < markers.length; i++) {
                markers[i].setMap(map);
            }
        }

        // Removes the markers from the map, but keeps them in the array.
        function clearMarkers() {
            setMapOnAll(null);
        }

        // Deletes all markers in the array by removing references to them.
        function deleteMarkers() {
            clearMarkers();
            markers = [];
        }


    </script>

    <!-- Code Highlight js -->
    <script src="assets/vendor/highlightjs/highlight.pack.min.js"></script>
    <script src="assets/js/hyper-syntax.js"></script>

    <!-- Input Mask js -->
    <script src="assets/vendor/jquery-mask-plugin/jquery.mask.min.js"></script>

    @include('components.google_map_script')

@endpush
