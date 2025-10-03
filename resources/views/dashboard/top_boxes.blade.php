<div class="row">
    <div class="col-lg-6 col-xl-4">
        <div class="card tilebox-one">
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <h5 class="text-muted fw-normal mt-0 text-truncate" title="Hospitals">Hospitals</h5>
                        <h3 class="my-2 py-1">{{ $hospitalsCount ?? 0 }}</h3>
                        {{-- <p class="mb-0 text-muted">
                            <span class="text-success me-2"><i class="mdi mdi-arrow-up-bold"></i> 3.27%</span>
                        </p> --}}
                    </div>
                    <div class="col-6">
                        <div class="text-end">
                            <i class="mdi mdi-hospital-building"></i>
                            {{-- <div id="campaign-sent-chart" data-colors="#536de6"></div> --}}
                        </div>
                    </div>
                </div> <!-- end row-->
            </div> <!-- end card-body -->
        </div> <!-- end card -->
    </div> <!-- end col -->

    <div class="col-lg-6 col-xl-4">
        <div class="card tilebox-one">
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <h5 class="text-muted fw-normal mt-0 text-truncate" title="Retirement Homes" style="overflow: visible">Retirement Homes</h5>
                        <h3 class="my-2 py-1">{{ $retirementHomesCount ?? 0 }}</h3>
                        {{-- <p class="mb-0 text-muted">
                            <span class="text-danger me-2"><i class="mdi mdi-arrow-down-bold"></i> 5.38%</span>
                        </p> --}}
                    </div>
                    <div class="col-6">
                        <div class="text-end">
                            <i class="mdi mdi-home-group-plus"></i>
                            {{-- <div id="new-leads-chart" data-colors="#10c469"></div> --}}
                        </div>
                    </div>
                </div> <!-- end row-->
            </div> <!-- end card-body -->
        </div> <!-- end card -->
    </div> <!-- end col -->



    <div class="col-lg-6 col-xl-4">
        <div class="card tilebox-one">
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <h5 class="text-muted fw-normal mt-0 text-truncate" title="Patients">Patients</h5>
                        <h3 class="my-2 py-1">{{ $patientsCount ?? 0 }}</h3>
                        {{-- <p class="mb-0 text-muted">
                            <span class="text-success me-2"><i class="mdi mdi-arrow-up-bold"></i> 11.7%</span>
                        </p> --}}
                    </div>
                    <div class="col-6">
                        <div class="text-end">
                            <i class='uil uil-users-alt float-end'></i>
                            {{-- <div id="booked-revenue-chart" data-colors="#10c469"></div> --}}
                        </div>
                    </div>
                </div> <!-- end row-->
            </div> <!-- end card-body -->
        </div> <!-- end card -->
    </div> <!-- end col -->
</div>
