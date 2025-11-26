<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    
    <script>
        window.onerror = function(message, source, lineno, colno, error) {
            document.getElementById('app').innerHTML = `
                <div style="padding: 20px; color: red; font-family: monospace;">
                    <h3>Global Script Error</h3>
                    <p>${message}</p>
                    <p>at ${source}:${lineno}:${colno}</p>
                </div>
            `;
        };
    </script>
</head>
<body class="antialiased">
    <div id="app">
        <div style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: sans-serif; color: #666;">
            Loading Application...
        </div>
    </div>
</body>
</html>