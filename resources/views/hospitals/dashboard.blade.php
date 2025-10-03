@extends('dashboard.layout')

@section('dashboard_content')
    <div class="content-page">
        <div class="content">

            <!-- Start Content-->
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                {{-- <a class="btn btn-primary" href="{{ url()->previous() }}" >Back</a> --}}
                            </div>
                            <h4 class="page-title">Dashboard</h4>
                        </div>
                    </div>
                </div>
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
                    <div class="col-xl-3 col-lg-4">
                        <div class="card tilebox-one">
                            <div class="card-body">
                                <i class='uil uil-users-alt float-end'></i>
                                <h6 class="text-uppercase mt-0">Active Patients</h6>
                                <h2 class="my-2" id="">{{ $patientCount }}</h2>
                                {{-- <p class="mb-0 text-muted">
                                    <span class="text-success me-2"><span class="mdi mdi-arrow-up-bold"></span> 5.27%</span>
                                    <span class="text-nowrap">Since last month</span>
                                </p> --}}
                            </div> <!-- end card-body-->
                        </div>
                        <!--end card-->

                        <div class="card tilebox-one">
                            <div class="card-body">
                                <i class='uil uil-window-restore float-end'></i>
                                <h6 class="text-uppercase mt-0">Appointments</h6>
                                <h2 class="my-2">{{ $AppointmentCount }}</h2>
                                {{-- <p class="mb-0 text-muted">
                                    <span class="text-danger me-2"><span class="mdi mdi-arrow-down-bold"></span> 1.08%</span>
                                    <span class="text-nowrap">Since previous week</span>
                                </p> --}}
                            </div> <!-- end card-body-->
                        </div>
                        <!--end card-->

                        <div class="card tilebox-one">
                            <div class="card-body">
                                <i class='uil uil-money-bill float-end'></i>
                                <h6 class="text-uppercase mt-0">Offers</h6>
                                <h2 class="my-2">{{ $offerCount }}</h2>
                                {{-- <p class="mb-0 text-muted">
                                    <span class="text-danger me-2"><span class="mdi mdi-arrow-down-bold"></span> 1.08%</span>
                                    <span class="text-nowrap">Since previous week</span>
                                </p> --}}
                            </div> <!-- end card-body-->
                        </div>
                        <!--end card-->                        
{{-- 
                        <div class="card cta-box overflow-hidden">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <h3 class="m-0 fw-normal cta-box-title">Enhance your <b>Campaign</b> for better outreach <i class="mdi mdi-arrow-right"></i></h3>
                                    </div>
                                    <img class="ms-3" src="/assets/images/svg/email-campaign.svg" width="92" alt="Generic placeholder image">
                                </div>
                            </div>
                            <!-- end card-body -->
                        </div> --}}
                    </div> <!-- end col -->

                    <div class="col-xl-9 col-lg-8">
                        <div class="card card-h-100">
                            <div class="card-body">
                                {{-- <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    Property HY1xx is not receiving hits. Either your site is not receiving any sessions or it is not tagged correctly.
                                </div> --}}
                                {{-- <ul class="nav float-end d-none d-lg-flex">
                                    <li class="nav-item">
                                        <a class="nav-link text-muted" href="#">Today</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link text-muted" href="#">7d</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link active" href="#">15d</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link text-muted" href="#">1m</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link text-muted" href="#">1y</a>
                                    </li>
                                </ul> --}}
                                <h4 class="header-title mb-3">Offers Per Month</h4>
                                {{-- <h4 class="header-title mb-3">{{print_r($offers)}}</h4> --}}
                                <div id="chart"></div>
                                {{-- <div dir="ltr">
                                    <div id="sessions-overview" class="apex-charts mt-3" data-colors="#0acf97"></div>
                                </div> --}}
                            </div> <!-- end card-body-->
                        </div> <!-- end card-->
                    </div>
                </div>
                <!-- end row -->

                <!-- end row -->
            </div>
            <!-- container -->
        </div>
        <!-- content -->

        <!-- Footer Start -->
        @include('dashboard.footer')
        <!-- end Footer -->

    </div>
    <script>
        var options = {
            colors:['#10c469'],
            stroke: {
            curve: 'smooth',
            },            
            chart: {
                type: 'area'
            },
            series: [{
                name: 'Offers',
                data: []
            }],
            xaxis: {
                categories: []
            }
        }
        var arrayHospitalData = @json($hospitalData);
        for(i=0; i<=arrayHospitalData.length-1; i++){
            options.series[0].data.push(arrayHospitalData[i]['count']);
            options.xaxis.categories.push(arrayHospitalData[i]['month_name']);
        }     
        var chart = new ApexCharts(document.querySelector("#chart"), options);
        chart.render();        
    </script>
@endsection

@push('additional_scripts')
    @include('hospitals.dashboard_scripts')
@endpush
