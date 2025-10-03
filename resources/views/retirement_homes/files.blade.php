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
                            <h4 class="page-title">Files</h4>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div id="alternative-page-datatable_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer">

                                    <div class="row">
                                        <div class="col-sm-12">
                                            <table id="alternative-page-datatable" class="table dt-responsive nowrap w-100 dataTable no-footer dtr-inline" aria-describedby="alternative-page-datatable_info" style="width: 1102px">
                                                <thead>
                                                <tr>


                                                    <th class="sorting" tabindex="0" aria-controls="alternative-page-datatable" rowspan="1" colspan="1" style="width: 141.8px" aria-label="Gender: activate to sort column ascending">
                                                        File Name
                                                    </th>
                                                    <th class="sorting" tabindex="0" aria-controls="alternative-page-datatable" rowspan="1" colspan="1" style="width: 235.8px" aria-label="Status: activate to sort column ascending">
                                                        File Size
                                                    </th>
                                                    <th class="sorting" tabindex="0" aria-controls="alternative-page-datatable" rowspan="1" colspan="1" style="width: 198.8px" aria-label="Actions: activate to sort column ascending">
                                                        File Download
                                                    </th>
                                                </tr>
                                                </thead>

                                                <tbody>
                                                <tr class="odd">

                                                    <td class="table-action fw-bolder">Hyper-admin-design.zip</td>
                                                    <td class="table-action">2.3 MB</td>

                                                    <td class="table-action">



                                                        <a href="javascript:void(0);" class="btn  lh-1 p-0 font-16 btn-link btn-lg text-muted" data-bs-toggle="tooltip" data-bs-placement="top"
                                                           data-bs-title="Download">
                                                            <i class="ri-download-2-line"></i>
                                                        </a>

                                                    </td>
                                                </tr>
                                                <tr class="even">

                                                    <td class="table-action fw-bolder">Dashboard-design.jpg</td>
                                                    <td class="table-action">3.25 MB</td>

                                                    <td class="table-action">



                                                        <a href="javascript:void(0);" class="btn lh-1 p-0 font-16 btn-link btn-lg text-muted" data-bs-toggle="tooltip" data-bs-placement="top"
                                                           data-bs-title="Download">
                                                            <i class="ri-download-2-line"></i>
                                                        </a>

                                                    </td>
                                                </tr>
                                                <tr class="odd">

                                                    <td class="table-action fw-bolder">Admin-bug-report.mp4</td>
                                                    <td class="table-action">7.05 MB</td>

                                                    <td class="table-action">



                                                        <a href="javascript:void(0);" class="btn lh-1 p-0 font-16 btn-link btn-lg text-muted" data-bs-toggle="tooltip" data-bs-placement="top"
                                                           data-bs-title="Download">
                                                            <i class="ri-download-2-line"></i>
                                                        </a>

                                                    </td>
                                                </tr>

                                                </tbody>
                                            </table>
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
            <!-- container -->
        </div>

        @include('dashboard.footer')

    </div>
@endsection

@push('additional_scripts')
    @include('datatables.scripts')
@endpush

