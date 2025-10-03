<!DOCTYPE html>
<html lang="en" data-layout-mode="detached" data-topbar-color="light" data-sidenav-color="light" data-sidenav-user="true">

@include('components.head')

<body>

    <div class="wrapper">

    @include('components.navbar')

    @include('dashboard.left_side_bar')

    @yield('dashboard_content')

    @include('dashboard.scripts')

    </div>

</body>
</html>
