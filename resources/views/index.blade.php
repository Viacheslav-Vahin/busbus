{{-- resources/views/index.blade.php --}}
    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>MaxBus - міжнародні пасажирські перевезення</title>
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicons/favicon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicons/favicon.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicons/favicon.png') }}">
    <meta name="facebook-domain-verification" content="4crrzrx4soy0yj26ii4yqg13qzyyed" />
    @viteReactRefresh
    @vite(['resources/js/index.tsx', 'resources/css/app.css'])
</head>
<body class="antialiased">
<div id="app"></div>
</body>
</html>

