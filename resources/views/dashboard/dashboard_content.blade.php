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
            
            @include('dashboard.top_boxes', [
                    'hospitalsCount' => $hospitalsCount,
                    'retirementHomesCount' => $retirementHomesCount,
                    'patientsCount' => $patientsCount,
            ])
            <!-- end row -->
            <div class="row">
                @include('dashboard.line-graph')
                {{-- @include('dashboard.map-status') --}}
            </div>
            <!-- end row -->

        </div>
        <!-- container -->
    </div>
    <!-- content -->
    
    {{-- @include('dashboard.footer') --}}

</div>
