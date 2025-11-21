<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connected Capacity</title>
    <link href="{{ mix('css/app.css') }}" rel="stylesheet">
</head>

<body class="antialiased bg-gray-900">
    <div id="app"></div>
    <script src="{{ mix('js/app.js') }}"></script>
</body>

</html>