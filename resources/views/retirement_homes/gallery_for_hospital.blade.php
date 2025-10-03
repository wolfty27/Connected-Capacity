@extends('dashboard.layout')

@push('additional_css')
    <link rel="stylesheet" href="/assets/css/style.css">
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
                            <h4 class="page-title">View Gallery</h4>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row refresh-gallery-class">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <div class="gallery">
                                                            @foreach ($data['galleries'] as $key => $gallery)
                                                                <img
                                                                src={{$gallery['gallery_image']}}
                                                                alt="post-img"
                                                                class="rounded me-1 mb-3 mb-sm-2 img-fluid"
                                                            />
                                                            @endforeach
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
                            </div>
                            <!-- end card-body -->
                        </div>
                        <!-- end card -->
                    </div>
                    <!-- end col -->
                </div>
                <!-- end row -->
            </div>
            <!-- container -->
        </div>

        @include('dashboard.footer')

    </div>
@endsection

@push('additional_scripts')
{{-- <script>
    $(document).ready(function(){
        var RetirementHomeUserId = document.getElementById("retirement_home_user_id").value;
        fetchGallery(RetirementHomeUserId);
        function fetchGallery(id){
            $.ajax({
                type: "GET",
                url: "/my-account/fetch-gallery-for-admin/"+id,
                dataType: "JSON",
                success: function (response){
                    $(".gallery").html("");
                    // console.log(response.gallery.length);
                    if(response.gallery.length == 0){
                        $(".gallery").append('<h5 class="text-muted fw-normal mt-0 text-truncate">Gallery is empty.</h5>')
                    }
                    else{
                        $.each(response.gallery, function(key, item){
                            $(".gallery").append('<div class="del_gallery">\
                                    <img\
                                    id="'+item.id+'"\
                                    src="'+item.gallery_image+'"\
                                    class="rounded me-1 mb-3 mb-sm-2 img-fluid"\
                                    alt="post-img"\
                                    >\
                                </div>');
                        });
                    }
                }
                

            });
        }
    });
</script> --}}
@include('retirement_homes.edit_script')


@endpush

