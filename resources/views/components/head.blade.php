<head>
    <meta charset="utf-8" />
    <title>Connected Capacity</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="" name="description" />
    <meta content="Coderthemes" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <!-- App favicon -->
    {{-- <link rel="shortcut icon" href="/assets/images/favicon.ico"> --}}
    <link rel="shortcut icon" href="{{ asset('public/assets/images/favicon.ico')}} ">

    <!-- Daterangepicker css -->
    <link rel="stylesheet" href="{{ asset('public/assets/vendor/daterangepicker/daterangepicker.css')}}">
    {{-- <link rel="stylesheet" href="/assets/vendor/daterangepicker/daterangepicker.css"> --}}

    <!-- Calendly -->
    <link href="https://assets.calendly.com/assets/external/widget.css" rel="stylesheet">

    @stack('additional_css')

    <!-- Vector Map css -->
    {{-- <link rel="stylesheet" href="/assets/vendor/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css"> --}}
    <link rel="stylesheet" href="{{ asset('public/assets/vendor/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css')}}">

    <!-- Theme Config Js -->
    {{-- <script src="/assets/js/hyper-config.js"></script> --}}
    <script src="{{ asset('public/assets/js/hyper-config.js') }}"></script>

    <!-- Icons css -->
    {{-- <link href="/assets/css/icons.min.css" rel="stylesheet" type="text/css" /> --}}
    <link href="{{ asset('public/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- App css -->
    {{-- <link href="/assets/css/app-modern.min.css" rel="stylesheet" type="text/css" id="app-style" /> --}}
    <link href="{{ asset('public/assets/css/app-modern.min.css')}}" rel="stylesheet" type="text/css" id="app-style" />
    
    <!-- Apex Chart CDN -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

</head>
