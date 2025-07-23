{{-- resources/views/index.blade.php --}}
    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>MaxBus - міжнародні пасажирські перевезення</title>

    {{-- Vite will inject your React bundle here --}}
    @viteReactRefresh
    @vite('resources/js/app.tsx')
</head>
<body class="antialiased">
{{-- This is where React will mount --}}
<div id="root"></div>
</body>
</html>
