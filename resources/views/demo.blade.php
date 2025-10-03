<!DOCTYPE html>
<html
    lang="en"
    data-layout-mode="detached"
    data-topbar-color="light"
    data-sidenav-color="light"
    data-sidenav-user="true"
    data-topbar-color="light"
>
<head>
    <meta charset="utf-8" />
    <title>Connected Capacity</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta
        content="A fully featured admin theme which can be used to build CRM, CMS, etc."
        name="description"
    />
    <meta content="Coderthemes" name="author" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico" />

    <!-- Theme Config Js -->
    <script src="assets/js/hyper-config.js"></script>

    <!-- Icons css -->
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />

    <!--Custom css-->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- App css -->
    <link
        href="assets/css/app-modern.min.css"
        rel="stylesheet"
        type="text/css"
        id="app-style"
    />
</head>

<body>
<!-- Begin page -->
<div class="wrapper">
    <!-- ========== Topbar Start ========== -->
    <div class="navbar-custom topnav-navbar">
        <div class="container-fluid detached-nav">
            <!-- Topbar Logo -->
            <div class="logo-topbar">
                <!-- Logo light -->
                <a href="index.html" class="logo-light">
              <span class="logo-lg">
                <img src="assets/images/logo02.png" alt="logo" height="50" />
              </span>
                    <span class="logo-sm">
                <img
                    src="assets/images/logo02.png"
                    alt="small logo"
                    height="50"
                />
              </span>
                </a>

                <!-- Logo Dark -->
                <a href="index.html" class="logo-dark">
              <span class="logo-lg">
                <img
                    src="assets/images/logo01.png"
                    alt="dark logo"
                    height="50"
                />
              </span>
                    <span class="logo-sm">
                <img
                    src="assets/images/logo01.png"
                    alt="small logo"
                    height="50"
                />
              </span>
                </a>
            </div>

            <!-- Sidebar Menu Toggle Button -->
            <button class="button-toggle-menu">
                <i class="mdi mdi-menu"></i>
            </button>

            <!-- Horizontal Menu Toggle Button -->
            <button
                class="navbar-toggle"
                data-bs-toggle="collapse"
                data-bs-target="#topnav-menu-content"
            >
                <div class="lines">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </button>

            <ul class="list-unstyled topbar-menu float-end mb-0">
                <li class="dropdown notification-list d-lg-none">
                    <a
                        class="nav-link dropdown-toggle arrow-none"
                        data-bs-toggle="dropdown"
                        href="#"
                        role="button"
                        aria-haspopup="false"
                        aria-expanded="false"
                    >
                        <i class="ri-search-line noti-icon"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-animated dropdown-lg p-0">
                        <form class="p-3">
                            <input
                                type="search"
                                class="form-control"
                                placeholder="Search ..."
                                aria-label="Recipient's username"
                            />
                        </form>
                    </div>
                </li>

                <li class="dropdown notification-list topbar-dropdown">
                    <a
                        class="nav-link dropdown-toggle arrow-none"
                        data-bs-toggle="dropdown"
                        href="#"
                        role="button"
                        aria-haspopup="false"
                        aria-expanded="false"
                    >
                        <img
                            src="assets/images/flags/us.jpg"
                            alt="user-image"
                            class="me-0 me-sm-1"
                            height="12"
                        />
                        <span class="align-middle d-none d-lg-inline-block"
                        >English</span
                        >
                        <i
                            class="mdi mdi-chevron-down d-none d-sm-inline-block align-middle"
                        ></i>
                    </a>
                    <div
                        class="dropdown-menu dropdown-menu-end dropdown-menu-animated topbar-dropdown-menu"
                    >
                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item">
                            <img
                                src="assets/images/flags/us.jpg"
                                alt="user-image"
                                class="me-1"
                                height="12"
                            />
                            <span class="align-middle">English</span>
                        </a>
                    </div>
                </li>

                <li class="dropdown notification-list">
                    <a
                        class="nav-link dropdown-toggle arrow-none"
                        data-bs-toggle="dropdown"
                        href="#"
                        role="button"
                        aria-haspopup="false"
                        aria-expanded="false"
                    >
                        <i class="ri-notification-3-line noti-icon"></i>
                        <span class="noti-icon-badge"></span>
                    </a>
                    <div
                        class="dropdown-menu dropdown-menu-end dropdown-menu-animated dropdown-lg py-0"
                    >
                        <div
                            class="p-2 border-top-0 border-start-0 border-end-0 border-dashed border"
                        >
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="m-0 font-16 fw-semibold">Notification</h6>
                                </div>
                                <div class="col-auto">
                                    <a
                                        href="javascript: void(0);"
                                        class="text-dark text-decoration-underline"
                                    >
                                        <small>Clear All</small>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- All-->
                        <a
                            href="javascript:void(0);"
                            class="dropdown-item text-center text-primary notify-item border-top border-light py-2"
                        >
                            View All
                        </a>
                    </div>
                </li>

                <li class="notification-list d-none d-sm-inline-block">
                    <a
                        class="nav-link"
                        href="javascript:void(0)"
                        id="light-dark-mode"
                    >
                        <i class="ri-moon-line noti-icon"></i>
                    </a>
                </li>

                <li class="notification-list d-none d-md-inline-block">
                    <a class="nav-link" href="#" data-toggle="fullscreen">
                        <i class="ri-fullscreen-line noti-icon"></i>
                    </a>
                </li>

                <li class="dropdown notification-list">
                    <a
                        class="nav-link dropdown-toggle nav-user arrow-none me-0"
                        data-bs-toggle="dropdown"
                        href="#"
                        role="button"
                        aria-haspopup="false"
                        aria-expanded="false"
                    >
                <span class="account-user-avatar">
                  <img
                      src="assets/images/users/avatar-1.jpg"
                      alt="user-image"
                      class="rounded-circle"
                  />
                </span>
                        <span>
                  <span class="account-user-name">Dominic Keller</span>
                  <span class="account-position">Founder</span>
                </span>
                    </a>
                    <div
                        class="dropdown-menu dropdown-menu-end dropdown-menu-animated topbar-dropdown-menu profile-dropdown"
                    >
                        <!-- item-->
                        <div class="dropdown-header noti-title">
                            <h6 class="text-overflow m-0">Welcome !</h6>
                        </div>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item">
                            <i class="mdi mdi-account-circle me-1"></i>
                            <span>My Account</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item">
                            <i class="mdi mdi-account-edit me-1"></i>
                            <span>Settings</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item">
                            <i class="mdi mdi-lifebuoy me-1"></i>
                            <span>Support</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item">
                            <i class="mdi mdi-lock-outline me-1"></i>
                            <span>Lock Screen</span>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item">
                            <i class="mdi mdi-logout me-1"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </li>
            </ul>

            <!-- Topbar Search Form -->
            <div class="app-search dropdown">
                <form>
                    <div class="input-group">
                        <input
                            type="search"
                            class="form-control dropdown-toggle"
                            placeholder="Search..."
                            id="top-search"
                        />
                        <span class="mdi mdi-magnify search-icon"></span>
                        <button class="input-group-text btn btn-primary" type="submit">
                            Search
                        </button>
                    </div>
                </form>

                <div
                    class="dropdown-menu dropdown-menu-animated dropdown-lg"
                    id="search-dropdown"
                >
                    <!-- item-->
                    <div class="dropdown-header noti-title">
                        <h5 class="text-overflow mb-2">
                            Found <span class="text-danger">17</span> results
                        </h5>
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
                                <img
                                    class="d-flex me-2 rounded-circle"
                                    src="assets/images/users/avatar-2.jpg"
                                    alt="Generic placeholder image"
                                    height="32"
                                />
                                <div class="w-100">
                                    <h5 class="m-0 font-14">Erwin Brown</h5>
                                    <span class="font-12 mb-0">UI Designer</span>
                                </div>
                            </div>
                        </a>

                        <!-- item-->
                        <a href="javascript:void(0);" class="dropdown-item notify-item">
                            <div class="d-flex">
                                <img
                                    class="d-flex me-2 rounded-circle"
                                    src="assets/images/users/avatar-5.jpg"
                                    alt="Generic placeholder image"
                                    height="32"
                                />
                                <div class="w-100">
                                    <h5 class="m-0 font-14">Jacob Deo</h5>
                                    <span class="font-12 mb-0">Developer</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- ========== Topbar End ========== -->

    <!-- ========== Left Sidebar Start ========== -->
    <div class="leftside-menu">
        <!-- Logo Light -->
        <a href="index.html" class="logo logo-light">
          <span class="logo-lg">
            <img src="assets/images/logo.png" alt="logo" height="22" />
          </span>
            <span class="logo-sm">
            <img src="assets/images/logo-sm.png" alt="small logo" height="22" />
          </span>
        </a>

        <!-- Logo Dark -->
        <a href="index.html" class="logo logo-dark">
          <span class="logo-lg">
            <img
                src="assets/images/logo-dark.png"
                alt="dark logo"
                height="22"
            />
          </span>
            <span class="logo-sm">
            <img
                src="assets/images/logo-dark-sm.png"
                alt="small logo"
                height="22"
            />
          </span>
        </a>

        <!-- Sidebar Hover Menu Toggle Button -->
        <button
            type="button"
            class="bg-transparent button-sm-hover p-0"
            data-bs-toggle="tooltip"
            data-bs-placement="right"
            title="Show Full Sidebar"
        >
            <i class="ri-checkbox-blank-circle-line align-middle"></i>
        </button>

        <!-- Sidebar -left -->
        <div class="h-100" id="leftside-menu-container" data-simplebar>
            <!-- Leftbar User -->
            <div class="leftbar-user">
                <a href="pages-profile.html">
                    <img
                        src="assets/images/users/avatar-1.jpg"
                        alt="user-image"
                        height="42"
                        class="rounded-circle shadow-sm"
                    />
                    <span class="leftbar-user-name">Dominic Keller</span>
                </a>
            </div>

            <!--- Sidemenu -->
            <ul class="side-nav">
                <li class="side-nav-title side-nav-item">Navigation</li>

                <li class="side-nav-item">
                    <a
                        href="index.html"
                        aria-expanded="false"
                        aria-controls="sidebarDashboards"
                        class="side-nav-link"
                    >
                        <i class="uil-home-alt"></i>
                        <span class="badge bg-success float-end">5</span>
                        <span> Dashboard </span>
                    </a>
                </li>

                <li class="side-nav-item">
                    <a
                        href="hospitals.html"
                        aria-expanded="false"
                        aria-controls="sidebarDashboards"
                        class="side-nav-link"
                    >
                        <i class="uil-medical-square"></i>
                        <span> Hospitals </span>
                    </a>
                </li>

                <li class="side-nav-item">
                    <a
                        href="retirement-homes.html"
                        aria-expanded="false"
                        aria-controls="sidebarDashboards"
                        class="side-nav-link"
                    >
                        <i class="mdi mdi-home-city-outline"></i>
                        <span> Retirement Homes </span>
                    </a>
                </li>

                <li class="side-nav-item">
                    <a
                        href="patients.html"
                        aria-expanded="false"
                        aria-controls="sidebarDashboards"
                        class="side-nav-link"
                    >
                        <i class="mdi mdi-shield-account"></i>
                        <span> Patients </span>
                    </a>
                </li>
            </ul>
            <!--- End Sidemenu -->

            <div class="clearfix"></div>
        </div>
    </div>
    <!-- ========== Left Sidebar End ========== -->

    <!-- ============================================================== -->
    <!-- Start Page Content here -->
    <!-- ============================================================== -->

    <div class="content-page">
        <div class="content">
            <!-- Start Content-->
            <div class="container-fluid">
                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item">
                                        <a href="javascript: void(0);">Connected Capcity</a>
                                    </li>
                                    <li class="breadcrumb-item">
                                        <a href="javascript: void(0);">Assesment</a>
                                    </li>
                                    <li class="breadcrumb-item active">Assesment Form</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Assesment Form</h4>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form>
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <h3>Patient Information</h3>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Full Name</label
                                                        >
                                                        <input
                                                            disabled
                                                            type="text"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="David"
                                                        />
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="example-email" class="form-label"
                                                        >Gender</label
                                                        >
                                                        <select class="form-select mb-3" disabled>
                                                            <option selected="">Male</option>
                                                            <option value="1">Male</option>
                                                            <option value="2">Female</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Phone Number</label
                                                        >
                                                        <input
                                                            type="tel"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Phone Number"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Email</label
                                                        >
                                                        <input
                                                            type="email"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Email"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <h5>Status</h5>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="searching_for_placement"
                                                            name="status"
                                                            value="Searching for Placement"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="searching_for_placement"
                                                        >Searching for Placement</label
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="placement_options_presented"
                                                            name="status"
                                                            value="Placement Options Presented"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="placement_options_presented"
                                                        >Placement Options Presented</label
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="discharged"
                                                            name="status"
                                                            value="Discharged"
                                                            class="form-check-input"
                                                        />
                                                          <label for="discharged">Discharged</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="placement_selected_at"
                                                            name="status"
                                                            value="Placement Selected at"
                                                            class="form-check-input"
                                                        />

                                                        <label for="placement_selected_at"
                                                        >Placement Selected at</label
                                                        >
                                                        <br />
                                                        <label for="simpleinput" class="form-label"
                                                        >Retirement Home Name</label
                                                        >
                                                        <input
                                                            type="text"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            disabled

                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <h3>Secondary Contact Information</h3>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Full Name</label
                                                        >
                                                        <input
                                                            type="text"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="First name"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Relationship to Patient</label
                                                        >
                                                        <input
                                                            type="text"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Relationship to Patient"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Phone Number</label
                                                        >
                                                        <input
                                                            type="tel"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Phone Number"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Email</label
                                                        >
                                                        <input
                                                            type="email"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Email"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <h3>Questionnaire</h3>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <p>1. Has this patient been designated ALC?</p>
                                                        <input
                                                            type="radio"
                                                            id="yes"
                                                            name="designated_alc"
                                                            value="yes"
                                                            class="form-check-input"
                                                        />
                                                        <label for="yes">Yes</label>
                                                        <input
                                                            type="radio"
                                                            id="no"
                                                            name="designated_alc"
                                                            value="no"
                                                            class="form-check-input"
                                                        />
                                                        <label for="no">No</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <p>
                                                            2. Has the patient been considered medically
                                                            stable for at least 3 days
                                                        </p>
                                                        <input
                                                            type="radio"
                                                            id="yes"
                                                            name="least_3_days"
                                                            value="yes"
                                                            class="form-check-input"
                                                        />
                                                        <label for="yes">Yes</label>
                                                        <input
                                                            type="radio"
                                                            id="no"
                                                            name="least_3_days"
                                                            value="no"
                                                            class="form-check-input"
                                                        />
                                                        <label for="no">No</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <p>
                                                            3. Does this patient have a negative PCR COVID
                                                            test?
                                                        </p>
                                                        <input
                                                            type="radio"
                                                            id="yes"
                                                            name="pcr_covid_test"
                                                            value="yes"
                                                            class="form-check-input"
                                                        />
                                                        <label for="yes">Yes</label>
                                                        <input
                                                            type="radio"
                                                            id="no"
                                                            name="pcr_covid_test"
                                                            value="no"
                                                            class="form-check-input"
                                                        />
                                                        <label for="no">No</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <p>
                                                            4. Is there a post-acute care destination/plan
                                                            in place?
                                                        </p>
                                                        <input
                                                            type="radio"
                                                            id="yes"
                                                            name="post_acute"
                                                            value="yes"
                                                            class="form-check-input"
                                                        />
                                                        <label for="yes">Yes</label>
                                                        <input
                                                            type="radio"
                                                            id="no"
                                                            name="post_acute"
                                                            value="no"
                                                            class="form-check-input"
                                                        />
                                                        <label for="no">No</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <p>
                                                        5. If Yes to 4, What is the planned post-acute
                                                        care destination?
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="offer_in_place"
                                                            name="if_yes"
                                                            value="offer_in_place"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="offer_in_place"
                                                        >LTC: Offer In-Place</label
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="program_support"
                                                            name="if_yes"
                                                            value="program_support"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="program_support"
                                                        >Home without Program Support</label
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="retirement-home"
                                                            name="if_yes"
                                                            value="retirement-home"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="retirement-home"
                                                        >Retirement Home</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="waitlist"
                                                            name="if_yes"
                                                            value="waitlist"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="waitlist">LTC: On Waitlist</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-8">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="home_with_program_Support"
                                                            name="if_yes"
                                                            value="home_with_program_Support"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="home_with_program_Support"
                                                        >Home with Program Support (ie Hospital at
                                                            Home, Community Care)</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <p>
                                                            6. What is the estimated length of need (in
                                                            days) for post-acute care?
                                                        </p>
                                                        <div>
                                                            <div class="range">
                                                                <input
                                                                    type="range"
                                                                    min="1"
                                                                    max="5"
                                                                    steps="1"
                                                                    value="1"
                                                                />
                                                            </div>

                                                            <ul class="range-labels">
                                                                <li class="active selected">30 Days</li>
                                                                <li>30 - 45 Days</li>
                                                                <li>45 - 60 Days</li>
                                                                <li>60 - 75 Days</li>
                                                                <li>75 - 90 Days</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12 mt-3">
                                                    <p>
                                                        7. What post-acute care needs does the patient
                                                        have? Check all that apply. NPC (Nursing and
                                                        Personal Care) and AR (Activation and
                                                        Recreation) are included.
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="npc1"
                                                            name="npc"
                                                            value="Physical Rehabilitation"
                                                            class="form-check-input"
                                                        />
                                                        <label for="npc1">
                                                            Physical Rehabilitation: Mild to Moderate
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="npc2"
                                                            name="npc"
                                                            value="Behavioural Support"
                                                            class="form-check-input"
                                                        />
                                                        <label for="npc2"> Behavioural Support </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="npc3"
                                                            name="npc"
                                                            value="Physical Rehabilitation Advanced"
                                                            class="form-check-input"
                                                        />
                                                        <label for="npc3">
                                                            Physical Rehabilitation: Advanced
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="npc4"
                                                            name="npc"
                                                            value="Caregiver Support"
                                                            class="form-check-input"
                                                        />
                                                        <label for="npc4"> Caregiver Support</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12 mt-3">
                                                    <p>
                                                        8. Acceptable Patient Characteristics (Check all
                                                        that apply)
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc1"
                                                            name="apc"
                                                            value="Non-weight bearing"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc1"> Non-weight bearing</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc2"
                                                            name="apc"
                                                            value="Mobility Restricted/Bedrest"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc2"
                                                        >Mobility Restricted/Bedrest
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc3"
                                                            name="apc"
                                                            value="Requires 2+ person transfer"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc3"
                                                        >Requires 2+ person transfer</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc4"
                                                            name="apc"
                                                            value="Caregiver experiencing burden or burnout"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc4">
                                                            Caregiver experiencing burden or
                                                            burnout</label
                                                        >
                                                    </div>
                                                </div>

                                                <div class="col-lg-8">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc5"
                                                            name="apc"
                                                            value="Early to moderate"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc5"
                                                        >Early to moderate wound care (dressings 30
                                                            minutes or less)</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc6"
                                                            name="apc"
                                                            value="Bedside oxygen"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc6"> Bedside oxygen</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc7"
                                                            name="apc"
                                                            value="PICC line already inserted"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc7"
                                                        >PICC line already inserted
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc8"
                                                            name="apc"
                                                            value="Stable IV for antibiotics
                                  and other therapies"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc8"
                                                        >Stable IV for antibiotics and other
                                                            therapies</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc9"
                                                            name="apc"
                                                            value="Mild to moderate"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc9"
                                                        >Mild to moderate cognitive impairment such as
                                                            Dementia, resolving delirium, behavioural
                                                            supports with behaviour plan in place
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12 mt-3">
                                                    <p>
                                                        9. You confirm that, to the best of your
                                                        knowledge, the patient does not have ANY of the
                                                        below characteristics. (Check any that the
                                                        patient has)
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk1"
                                                            name="bk"
                                                            value="Has high elopement risk"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk1">
                                                            Has high elopement risk</label
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk2"
                                                            name="bk"
                                                            value="Exhibits physically responsive behaviours"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk2"
                                                        >Exhibits physically responsive behaviours
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk3"
                                                            name="bk"
                                                            value="Requires acute palliative
                                  care"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk3"
                                                        >Requires acute palliative care</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk4"
                                                            name="bk"
                                                            value="Is fully immobilized/
                                  in traction"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk4">
                                                            Is fully immobilized/ in traction</label
                                                        >
                                                    </div>
                                                </div>

                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk5"
                                                            name="bk"
                                                            value="Requires administration of blood products"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk5"
                                                        >Requires administration of blood
                                                            products</label
                                                        >
                                                    </div>
                                                </div>

                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk6"
                                                            name="bk"
                                                            value="Has Active TB / CDiff /
                                COVID positive"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk6">
                                                            Has Active TB / CDiff / COVID positive</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">

                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk7"
                                                            name="bk"
                                                            value="Has acute medical"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk7"
                                                        >Has acute medical needs, e.g. trach,
                                                            suctioning, aerosol generating procedures,
                                                            ventilator, etc.
                                                        </label>
                                                    </div>
                                                </div>

                                            </div>

                                            <button class="btn btn-primary" type="submit">
                                                Submit
                                            </button>
                                        </div>
                                        <!-- end col -->
                                    </div>
                                    <!-- end row-->
                                </form>
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
        <!-- content -->

        <!-- Footer Start -->
        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6">
                        <script>
                            document.write(new Date().getFullYear());
                        </script>
                        © Connected Capacity
                    </div>
                    <div class="col-md-6">
                        <div class="text-md-end footer-links d-none d-md-block">
                            <a href="javascript: void(0);">About</a>
                            <a href="javascript: void(0);">Support</a>
                            <a href="javascript: void(0);">Contact Us</a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        <!-- end Footer -->
    </div>

    <!-- ============================================================== -->
    <!-- End Page content -->
    <!-- ============================================================== -->
</div>
<!-- END wrapper -->

<!-- Vendor js -->
<script src="assets/js/vendor.min.js"></script>

<!-- Code Highlight js -->
<script src="assets/vendor/highlightjs/highlight.pack.min.js"></script>
<script src="assets/js/hyper-syntax.js"></script>

<!-- Input Mask js -->
<script src="assets/vendor/jquery-mask-plugin/jquery.mask.min.js"></script>

<!-- App js -->
<script src="assets/js/app.min.js"></script>

<script>
    var sheet = document.createElement("style"),
        $rangeInput = $(".range input"),
        prefs = ["webkit-slider-runnable-track", "moz-range-track", "ms-track"];

    document.body.appendChild(sheet);

    var getTrackStyle = function (el) {
        var curVal = el.value,
            val = (curVal - 1) * 24.666666667,
            style = "";

        // Set active label
        $(".range-labels li").removeClass("active selected");

        var curLabel = $(".range-labels").find("li:nth-child(" + curVal + ")");

        curLabel.addClass("active selected");
        curLabel.prevAll().addClass("selected");

        // Change background gradient
        for (var i = 0; i < prefs.length; i++) {
            style +=
                ".range {background: linear-gradient(to right, #536de6 0%, #536de6 " +
                val +
                "%, #fff " +
                val +
                "%, #fff 100%)}";
            style +=
                ".range input::-" +
                prefs[i] +
                "{background: linear-gradient(to right, #536de6 0%, #536de6 " +
                val +
                "%, #b2b2b2 " +
                val +
                "%, #b2b2b2 100%)}";
        }

        return style;
    };

    $rangeInput.on("input", function () {
        sheet.textContent = getTrackStyle(this);
    });

    // Change input value on label click
    $(".range-labels li").on("click", function () {
        var index = $(this).index();

        $rangeInput.val(index + 1).trigger("input");
    });
</script>
</body>
</html>
