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
                                @if (auth()->user()->role == 'retirement-home' || auth()->user()->role == 'admin' )
                                <form method="POST" action="/my-account/upload-gallery/{{auth()->user()->id}}" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="mb-3">
                                                <h4>Upload Gallery Images</h4>
                                                <div class="custom-file">
                                                    <input
                                                        type="file"
                                                        name="gallery_images[]"
                                                        class="custom-file-input"
                                                        id="file"
                                                        multiple
                                                        onchange="javascript:updateList()"
                                                    />
                                                    <label class="custom-file-label" for="file">
                                                        <img
                                                            width="20"
                                                            src=" data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAOEAAADhCAMAAAAJbSJIAAAAQlBMVEX///8AAABhYWFlZWWSkpL19fW9vb01NTXf398kJCTw8PBRUVGdnZ1dXV3m5uZ0dHR8fHzExMSMjIzU1NSxsbEhISGIc9b1AAADv0lEQVR4nO2d607jMBhEa1pa6AVaLu//qgixq2+XxmlSe+IZa85vazQjhZMCarJaGWOMMcYYc8WxdQE0m7RpXQHLNqW0bV0CyVP65ql1DRyP6YfH1kVg7P4s3LUugmKd/rJuXQXDJgVdCnWb/qVDoT6l/+lOqI/pN70JdXe1sDOhrq8GdibUzcDAroS6HRzYkVB/a7Q7oV5rtDehXms06EKoQxoNOhDqsEYDeaHmNBqICzWv0UBaqGMaDZSFOqbRQFio4xoNZIV6S6OBqFBvazSQFOoUjQaCQp2m0UBPqNM0GsgJdapGAzGhTtdoICXUORoNhIQ6T6OBjFDnajRQEepcjQYiQp2v0UBCqPdoNBAQ6n0aDeiFeq9GA3Kh3q/RgFuo92s0oBZqiUYDYqGWaTSgFWqpRgNSoZZrNKAUag2NBoxCraHRgFCodTQa0Am1lkYDMqHW02hAJdSaGg2IhFpXowGPUOtqNKARam2NBiRCra/RgEKoCI0GBELFaDRoLlSURoPWQkVpNGgsVJxGg6ZCRWo0aChUrEaDZkJFazRoJFS8RoM2QsVrNGgi1NOCA1M6LT9we3iYzPp5sPXzenrEgeDj2xjDt02S3xyq8DC48KF1rYp4oT5eqI8X6uOF+nihPl6ojxfq44X6eKE+XqiPF+rjhfp4oT5eqI8X6uOF+nihPl6ojxfq44X6eKE+XqiPF+rjhfp4oT5eqI8XLsVhsMehQjJu4bzOuB4sySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wySw9cMksPXDJLD1wybgew8/nq/EcPZaFb6eBx+id3ioksyzE4YUlpznwwpLTHHhhyWkOvLDkNAdeWHKaAy8sOc2BF5ac5sALS05z4IUlpznwwpLTHNRYyP2OhuH3SsxbOOc9G4uTeTdIbuESr6dahtx1d25drBrnzMJj62LVOGYWXloXq8Yls3Dfulg19pmFq8/WzSrxnBu40Kvw8ORftvfSulolXrILM99XVGPsO6HvrctV4X1k4cKvi8Mw/s/zHm4Y2VvFDx+t+xXzMT5wtXpt3bCQ11sD1X066bv1yhMnPjxA90KdcIn+oKqbm5IJ9or3xdON28Qv3tV+Gg+jn2QGedkM/5WHkc/NyIftMfaX45n5L23frM/Hy7zL0xhjjDHGEPIFcc477O4fZUsAAAAASUVORK5CYII="
                                                        /> Upload here</label
                                                    >
                                                </div>
                                                <ul id="fileList" class="file-list"></ul>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary mt-3" >Submit</button>

                                </form>
                                @endif
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
<script>
    $(document).ready(function(){
        fetchGallery();
        function fetchGallery(){
            $.ajax({
                type: "GET",
                url: "/my-account/fetch-gallery",
                dataType: "JSON",
                success: function (response){
                    $(".gallery").html("");
                    console.log(response.gallery.length);
                    if(response.gallery.length == 0){
                        $(".gallery").append('<h5 class="text-muted fw-normal mt-0 text-truncate">Gallery is empty.</h5>')
                    }
                    else{
                        $.each(response.gallery, function(key, item){
                            $(".gallery").append('<div class="del_gallery">\
                                <span class="btn btn-danger mb-1 deleteGall" id="'+item.id+'">\
                                    <i class="mdi mdi-delete text-danger"></i>\
                                    Delete</span>\
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
        $(document).on('click', '.deleteGall', function (e){
            image_id = this.id;

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')
                }
            });

            $.ajax({
                type: "Delete",
                url: "/my-account/delete-gallery/"+image_id,
                data: "data",
                dataType: "JSON",
                async: true,
                cache: false,
                success: function(response){
                    fetchGallery();
                }
            });
        });
    });
</script>
@include('retirement_homes.edit_script')


@endpush

