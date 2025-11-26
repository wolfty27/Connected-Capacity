<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="antialiased">
    <div id="app">
        <div style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: sans-serif; color: #666;">
            Loading Connected Capacity 2.0...
        </div>
    </div>
</body>
</html>
