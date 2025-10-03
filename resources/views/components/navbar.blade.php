<div class="navbar-custom topnav-navbar">
    <div class="container-fluid detached-nav">

        <!-- Topbar Logo -->
        <div class="logo-topbar">
            <!-- Logo light -->
            <a href="/dashboard" class="logo-light">
                            <span class="logo-lg">
                                <img src="/assets/images/logo02.png" alt="logo" height="50">
                            </span>
                <span class="logo-sm">
                                <img src="/assets/images/logo02.png" alt="small logo" height="50">
                            </span>
            </a>

            <!-- Logo Dark -->
            <a href="/dashboard" class="logo-dark">
                            <span class="logo-lg">
                                <img src="/assets/images/logo01.png" alt="dark logo" height="50">
                            </span>
                <span class="logo-sm">
                                <img src="/assets/images/logo01.png" alt="small logo" height="50">
                            </span>
            </a>
        </div>

        <!-- Sidebar Menu Toggle Button -->
        <button class="button-toggle-menu">
            <i class="mdi mdi-menu"></i>
        </button>

        <!-- Horizontal Menu Toggle Button -->
        <button class="navbar-toggle" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
            <div class="lines">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>

        <ul class="list-unstyled topbar-menu float-end mb-0">
            @if(auth()->user()->role == 'hospital' && auth()->user()->calendly_status == Null)
                <li class="notification-list d-none d-sm-inline-block">
                    <a class="nav-link" href="{{config('calendly.calendly_auth_base_url')}}oauth/authorize?client_id={{config('calendly.client_id')}}&response_type=code&redirect_uri={{config('calendly.redirect_uri')}}">Connect Calendly
                        <i class="mdi mdi-creative-commons noti-icon "></i>
                    </a>
                </li>
            @elseif(auth()->user()->role == 'hospital' && auth()->user()->calendly_status == "1")
                <li class="notification-list d-none d-sm-inline-block">
                    <span class="nav-link align-middle d-none d-lg-inline-block">Calendly Connected
                       <a class="text-danger" href="/logout/calendly" ><i class="mdi mdi-logout-variant noti-icon "></i></a></span>
                </li> 
            @endif

            <li class="dropdown notification-list d-lg-none">
                <a class="nav-link dropdown-toggle arrow-none" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <i class="ri-search-line noti-icon"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-animated dropdown-lg p-0">
                    <form class="p-3">
                        <input type="search" class="form-control" placeholder="Search ..." aria-label="Recipient's username">
                    </form>
                </div>
            </li>

            {{-- <li class="dropdown notification-list topbar-dropdown">
                <a class="nav-link dropdown-toggle arrow-none" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <img src="/assets/images/flags/us.jpg" alt="user-image" class="me-0 me-sm-1" height="12">
                    <span class="align-middle d-none d-lg-inline-block">English</span> <i class="mdi mdi-chevron-down d-none d-sm-inline-block align-middle"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated topbar-dropdown-menu">



                    <!-- item-->
                    <a href="javascript:void(0);" class="dropdown-item notify-item">
                        <img src="/assets/images/flags/us.jpg" alt="user-image" class="me-1" height="12"> <span class="align-middle">English</span>
                    </a>

                </div>
            </li> --}}

            {{-- <li class="dropdown notification-list">
                <a class="nav-link dropdown-toggle arrow-none" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <i class="ri-notification-3-line noti-icon"></i>
                    <span class="noti-icon-badge"></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated dropdown-lg py-0">
                    <div class="p-2 border-top-0 border-start-0 border-end-0 border-dashed border">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0 font-16 fw-semibold"> Notification</h6>
                            </div>
                            <div class="col-auto">
                                <a href="javascript: void(0);" class="text-dark text-decoration-underline">
                                    <small>Clear All</small>
                                </a>
                            </div>
                        </div>
                    </div>



                    <!-- All-->
                    <a href="javascript:void(0);" class="dropdown-item text-center text-primary notify-item border-top border-light py-2">
                        View All
                    </a>

                </div>
            </li> --}}



            <li class="notification-list d-none d-sm-inline-block">
                <a class="nav-link" href="javascript:void(0)" id="light-dark-mode">
                    <i class="ri-moon-line noti-icon"></i>
                </a>
            </li>

            <li class="notification-list d-none d-md-inline-block">
                <a class="nav-link" href="#" data-toggle="fullscreen">
                    <i class="ri-fullscreen-line noti-icon"></i>
                </a>
            </li>

            <li class="dropdown notification-list">
                <a class="nav-link dropdown-toggle nav-user arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false"
                   aria-expanded="false">
                                <span class="account-user-avatar">
                                    <img src={{ auth()->user()->image ?? '/assets/images/users/avatar-1.jpg' }} alt="user-image" class="rounded-circle">
                                </span>
                    <span>
                                    <span class="account-user-name">{{ auth()->user()->name ?? '' }}</span>
                                    <span class="account-position">{{ auth()->user()->role == 'retirement-home' ? 'Retirement Home' : auth()->user()->role }}</span>
                                </span>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated topbar-dropdown-menu profile-dropdown">
                    <!-- item-->
                    <div class=" dropdown-header noti-title">
                        <h6 class="text-overflow m-0">Welcome !</h6>
                    </div>

                    <!-- item-->
                    <a href="/my-account" class="dropdown-item notify-item">
                        <i class="mdi mdi-account-circle me-1"></i>
                        <span>My Profile</span>
                    </a>
                    <!-- item-->
                    <a href="/my-account/change-password" class="dropdown-item notify-item">
                        <i class="mdi mdi-account-lock me-1"></i>
                        <span>Change Password</span>
                    </a>                    
                    @if (auth()->user()->role == 'retirement-home')
                        <a href="/retirement-homes/gallery/{{auth()->user()->id}}" class="dropdown-item notify-item">
                            <i class="mdi mdi-view-gallery me-1"></i>
                            <span>My Gallery</span>
                        </a>
                    @endif
                    <!-- item-->
                    {{-- @if (auth()->user()->role == 'hospital' && auth()->user()->calendly_status == '1')
                    <a href="/logout/calendly" class="dropdown-item notify-item">
                        <i class="mdi mdi-logout me-1"></i>
                        <span>Logout Calendly Account</span>
                    </a>
                    @endif --}}
                    <a href="/logout" class="dropdown-item notify-item">
                        <i class="mdi mdi-logout me-1"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </li>
        </ul>

        <!-- Topbar Search Form -->
        {{-- <div class="app-search dropdown">
            <form>
                <div class="input-group">
                    <input type="search" class="form-control dropdown-toggle"  placeholder="Search..." id="top-search">
                    <span class="mdi mdi-magnify search-icon"></span>
                    <button class="input-group-text btn btn-primary" type="submit">Search</button>
                </div>
            </form>

            <div class="dropdown-menu dropdown-menu-animated dropdown-lg" id="search-dropdown">
                <!-- item-->
                <div class="dropdown-header noti-title">
                    <h5 class="text-overflow mb-2">Found <span class="text-danger">17</span> results</h5>
                </div>

                <!-- item-->
                <a href="javascript:void(0);" class="dropdown-item notify-item">
                    <i class="uil-notes font-16 me-1"></i>
                    <span>Analytics Report</span>
                </a>

                <!-- item-->
                <a href="javascript:void(0);" class="dropdown-item notify-item">
                    <i class="uil-life-ring font-16 me-1"></i>
                    <span>How can I help you?</span>
                </a>

                <!-- item-->
                <a href="javascript:void(0);" class="dropdown-item notify-item">
                    <i class="uil-cog font-16 me-1"></i>
                    <span>User profile settings</span>
                </a>

                <!-- item-->
                <div class="dropdown-header noti-title">
                    <h6 class="text-overflow mb-2 text-uppercase">Users</h6>
                </div>

                <div class="notification-list">
                    <!-- item-->
                    <a href="javascript:void(0);" class="dropdown-item notify-item">
                        <div class="d-flex">
                            <img class="d-flex me-2 rounded-circle" src="/assets/images/users/avatar-2.jpg" alt="Generic placeholder image" height="32">
                            <div class="w-100">
                                <h5 class="m-0 font-14">Erwin Brown</h5>
                                <span class="font-12 mb-0">UI Designer</span>
                            </div>
                        </div>
                    </a>

                    <!-- item-->
                    <a href="javascript:void(0);" class="dropdown-item notify-item">
                        <div class="d-flex">
                            <img class="d-flex me-2 rounded-circle" src="/assets/images/users/avatar-5.jpg" alt="Generic placeholder image" height="32">
                            <div class="w-100">
                                <h5 class="m-0 font-14">Jacob Deo</h5>
                                <span class="font-12 mb-0">Developer</span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div> --}}


    </div>
</div>
