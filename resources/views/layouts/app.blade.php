<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">

    <meta name="application-name" content="{{ config('app.name') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name') }}</title>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    @filamentStyles
{{--    @vite(['resources/css/app.css', 'resources/js/app.js'])--}}
    @vite(['resources/js/index.tsx', 'resources/css/app.css'])
    @livewireStyles
</head>

<body class="antialiased">

{{ $slot }}
<div style="background: yellow;">My Custom Layout</div>
@filamentScripts
@livewireScripts
@if(config('services.ga4.measurement_id') && config('services.ga4.enabled', true))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.ga4.measurement_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('services.ga4.measurement_id') }}');
    </script>
@endif
</body>
</html>
