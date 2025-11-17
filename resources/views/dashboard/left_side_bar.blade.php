<div class="leftside-menu">

    <!-- Logo Light -->
    <a href="/" class="logo logo-light">
                    <span class="logo-lg">
                        <img src="assets/images/logo.png" alt="logo" height="22">
                    </span>
        <span class="logo-sm">
                        <img src="assets/images/logo-sm.png" alt="small logo" height="22">
                    </span>
    </a>

    <!-- Logo Dark -->
    <a href="/" class="logo logo-dark">
                    <span class="logo-lg">
                        <img src="assets/images/logo-dark.png" alt="dark logo" height="22">
                    </span>
        <span class="logo-sm">
                        <img src="assets/images/logo-dark-sm.png" alt="small logo" height="22">
                    </span>
    </a>

    <!-- Sidebar Hover Menu Toggle Button -->
    <button type="button" class="bg-transparent button-sm-hover p-0" data-bs-toggle="tooltip" data-bs-placement="right" title="Show Full Sidebar">
        <i class="ri-checkbox-blank-circle-line align-middle"></i>
    </button>

    <!-- Sidebar -left -->
    <div class="h-100" id="leftside-menu-container" data-simplebar>
        <!-- Leftbar User -->
        <div class="leftbar-user">
            <a href="javascript:void(0);">
                <img src="{{ auth()->user()->image ?? '/assets/images/users/avatar-1.jpg' }}" alt="user-image" height="50" width="50"
                     class="rounded-circle shadow-sm">
                <span class="leftbar-user-name">{{auth()->user()->name}}</span>
            </a>
        </div>

        <!--- Sidemenu -->
        <ul class="side-nav">

            @php($featureToggle = app(\App\Services\FeatureToggle::class))

            <li class="side-nav-title side-nav-item">Legacy Placement</li>

            <li class="side-nav-item">
                <a href="/" aria-expanded="false"
                   aria-controls="sidebarDashboards" class="side-nav-link">
                    <i class="uil-home-alt"></i>
                    {{--                    <span class="badge bg-success float-end">5</span>--}}
                    <span> Dashboard </span>
                </a>
            </li>

            @if (auth()->user()->role == 'admin')
            <li class="side-nav-item">
                <a href="/hospitals" aria-expanded="false"
                   aria-controls="sidebarDashboards" class="side-nav-link">
                    <i class="uil-medical-square"></i>
                    <span> Hospitals </span>
                </a>
            </li>
            @endif


            @if (auth()->user()->role == 'admin' || auth()->user()->role == 'hospital')
            <li class="side-nav-item">
                <a href="/retirement-homes" aria-expanded="false"
                   aria-controls="sidebarDashboards" class="side-nav-link">
                    <i class="mdi mdi-home-city-outline"></i>
                    <span> Retirement Homes </span>
                </a>
            </li>
            @endif



            <li class="side-nav-item">
                <a  href="/patients" aria-expanded="false"
                    aria-controls="sidebarDashboards" class="side-nav-link">
                    <i class="mdi mdi-shield-account"></i>
                    @if (auth()->user()->role == 'retirement-home')
                    <span> All Patients </span>
                    @elseif(auth()->user()->role == 'admin')
                    <span>Available Patients</span>
                    @else
                    <span>Patients</span>
                    @endif
                </a>
            </li>

            @if (auth()->user()->role == 'admin')
                <li class="side-nav-item">
                    <a  href="/placed-patients" aria-expanded="false"
                        aria-controls="sidebarDashboards" class="side-nav-link">
                        <i class="mdi mdi-shield-account"></i>
                        <span>Placed Patients</span>
                    </a>
                </li>
            @endif           

            @if (auth()->user()->role == 'retirement-home' || auth()->user()->role == 'hospital')
                <li class="side-nav-item">
                    <a  href="/bookings" aria-expanded="false"
                        aria-controls="sidebarDashboards" class="side-nav-link">
                        <i class="mdi mdi-card-account-details"></i>
                        @if(auth()->user()->role == 'hospital')
                        <span> Offers </span>
                        @else
                        <span>Appointments</span>
                        @endif
                    </a>
                </li>
                @else
            @endif

            @if (auth()->user()->role == 'hospital')
                <li class="side-nav-item">
                    <a  href="/bookings/hospital" aria-expanded="false"
                        aria-controls="sidebarDashboards" class="side-nav-link">
                        <i class="mdi mdi-card-account-details"></i>
                        <span>Appointments</span>
                    </a>
                </li>

                {{-- <li class="side-nav-item">
                    <a  href="/my-calendly" aria-expanded="false"
                        aria-controls="sidebarDashboards" class="side-nav-link">
                        <i class="mdi mdi-calendar-clock"></i>
                        <span>Scheduled Events</span>
                    </a>
                </li>                 --}}
                
            @endif            

            @if (auth()->user()->role == 'retirement-home')
                <li class="side-nav-item">
                    <a href="/retirement-homes/{{ auth()->user()->id }}/patients" aria-expanded="false"
                       aria-controls="sidebarDashboards" class="side-nav-link">
                        <i class="mdi mdi-shield-account"></i>
                        <span> My Patients  </span>
                    </a>
                </li>
            @endif



        </ul>

        @if ($featureToggle->enabled('cc2.enabled'))
            <ul class="side-nav mt-3">
                <li class="side-nav-title side-nav-item">CC2 Coordination</li>
                <li class="side-nav-item">
                    <a href="{{ route('cc2.dashboard') }}" class="side-nav-link">
                        <i class="mdi mdi-hexagon-multiple-outline"></i>
                        <span>CC2 Workspace</span>
                    </a>
                </li>
            </ul>
        @endif
        <!--- End Sidemenu -->
        <div class="clearfix"></div><br><br><br>
        <div class="d-flex p-2 justify-content-around "><p>Made With <i class="mdi mdi-cards-heart text-danger"></i> By <a class="nav-links" href="https://www.anideos.com/" target="_blank">Anideos</a></p></div>
    </div>
</div>
