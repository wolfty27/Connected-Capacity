<!DOCTYPE html>
<html lang="en">

{{--@include('components.head', ['title' => $title ?? 'Connected Capacity'])--}}

<body class="loading" data-layout-config='{@if(auth()->check()) {"leftSidebarCondensed":false,"darkMode":false, "showRightSidebarOnStart": true} @else "darkMode":false} @endif'>


{{--    @include('components.navbar')--}}

{{--    @yield('content')--}}

{{--    @include('dashboard.footer')--}}

{{--<!-- bundle -->--}}
{{--<script src="assets/js/vendor.min.js"></script>--}}
{{--<script src="assets/js/app.min.js"></script>--}}

{{--@stack('scripts_bottom')--}}

LANDING PAGE <br>
<a href="/login">login</a>

</body>

</html>
