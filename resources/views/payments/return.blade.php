{{-- resources/views/payments/return.blade.php --}}
    <!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Статус оплати — MaxBus</title>

    {{-- Підключи свій CSS (Tailwind + власні стилі) як у проєкті --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Якщо очікуємо оплату — автооновлення раз на 5 секунд --}}
    @unless($paid)
        <meta http-equiv="refresh" content="5">
    @endunless
</head>
<body class="antialiased">
<div class="flex flex-col min-h-screen bg-gray-50 text-gray-900 relative">

    {{-- Navigation --}}
    <nav class="bg-white shadow-md">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <a href="{{ url('/') }}" class="header-logo inline-flex items-center gap-2">
                <img src="{{ asset('images/Asset-21.svg') }}" alt="MaxBus" class="h-8">
                <span class="sr-only">MaxBus</span>
            </a>
            <ul class="flex space-x-8 text-sm md:text-base">
                <li><a href="{{ url('/') }}" class="hover:text-brand-light transition">Головна</a></li>
                <li><a href="{{ url('/#about') }}" class="hover:text-brand-light transition">Про нас</a></li>
                <li><a href="{{ url('/#contacts') }}" class="hover:text-brand-light transition">Контакти</a></li>
            </ul>
        </div>
    </nav>

    {{-- Hero / Content --}}
    <header class="main-header bg-gradient-to-r from-brand to-brand-dark text-white flex-1 flex items-center">
        <div class="header-container container mx-auto px-6 py-20 md:py-32 grid grid-cols-1 md:grid-cols-2 gap-12 items-center w-full">
            <div>
                @if($paid)
                    <div class="inline-flex items-center gap-3 bg-white/10 rounded-xl px-4 py-2 mb-4">
                        {{-- check icon --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-300" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-7.778 7.778a1 1 0 01-1.414 0L3.293 10.95a1 1 0 111.414-1.414l3.1 3.1 7.071-7.071a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="uppercase tracking-wider text-sm">Оплата успішна</span>
                    </div>
                    <h1 class="text-4xl md:text-5xl font-extrabold mb-4 leading-tight heading">
                        Дякуємо за оплату!
                    </h1>
                    <p class="text-lg mb-6 max-w-xl text-white/90">
                        Номер замовлення: <span class="font-semibold">{{ $order }}</span>.
                        Нижче — квитки для завантаження у PDF.
                    </p>
                @else
                    <div class="inline-flex items-center gap-3 bg-white/10 rounded-xl px-4 py-2 mb-4">
                        {{-- spinner icon --}}
                        <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <span class="uppercase tracking-wider text-sm">Очікуємо підтвердження</span>
                    </div>
                    <h1 class="text-4xl md:text-5xl font-extrabold mb-4 leading-tight heading">
                        Оплата обробляється…
                    </h1>
                    <p class="text-lg mb-6 max-w-xl text-white/90">
                        Номер замовлення: <span class="font-semibold">{{ $order }}</span>.
                        Сторінка оновлюється автоматично кожні 5&nbsp;секунд.
                    </p>
                @endif

                <div class="flex flex-wrap gap-3">
                    <a href="{{ url('/') }}"
                       class="inline-block bg-white text-brand-dark font-semibold px-6 py-3 rounded-lg shadow hover:shadow-lg transition">
                        На головну
                    </a>
                    @if($paid && !empty($tickets))
                        {{-- Кнопка на перший квиток як CTA --}}
                        <a href="{{ route('tickets.pdf', ['uuid' => $tickets[0]]) }}"
                           class="inline-block bg-emerald-500 text-white font-semibold px-6 py-3 rounded-lg shadow hover:shadow-lg transition">
                            Завантажити квиток
                        </a>
                    @endif
                </div>
            </div>

            <div class="flex justify-center">
                <div class="bg-white/10 rounded-2xl p-6 md:p-8 w-full max-w-md backdrop-blur">
                    <div class="hcard">
                        <span class="icon">
                          <i class="fa fa-phone"></i>
                        </span>
                        <div class="content-wrap">
                            <span class="item-title block font-semibold text-white mb-2">
                                Звʼяжіться з нами
                            </span>
                            <p class="text-white/90 space-y-1">
                                <a href="tel:+380930510795" class="block hover:underline">+38093 051 0795</a>
                                <a href="tel:+48223906203" class="block hover:underline">+48 22 390 62 03</a>
                                <a href="tel:+380972211099" class="block hover:underline">+38097 221 10 99</a>
                            </p>
                        </div>
                    </div>

                    @if($paid && !empty($tickets))
                        <div class="mt-6 bg-white rounded-xl p-4 text-gray-800">
                            <h3 class="font-semibold mb-3 heading">Ваші квитки</h3>
                            <ul class="space-y-2 list-disc list-inside">
                                @foreach($tickets as $uuid)
                                    <li>
                                        <a class="text-brand hover:underline" href="{{ route('tickets.pdf', ['uuid' => $uuid]) }}">
                                            Завантажити квиток ({{ Str::of($uuid)->limit(8, '') }})
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    {{-- Footer --}}
    <footer class="bg-white text-gray-600">
        <div class="container mx-auto px-6 py-10 grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <a href="{{ url('/') }}" class="footer-logo inline-flex items-center gap-2">
                    <img src="{{ asset('images/Asset-21.svg') }}" alt="MaxBus" class="h-8">
                    <span class="sr-only">MaxBus</span>
                </a>
                <p class="mt-3 text-sm">© {{ date('Y') }} MaxBus. Всі права захищені.</p>
            </div>
            <div>
                <h4 class="font-semibold mb-2 heading">Посилання</h4>
                <ul class="space-y-1">
                    <li><a href="{{ url('/') }}" class="hover:text-gray-900 transition">Головна</a></li>
                    <li><a href="{{ url('/#about') }}" class="hover:text-gray-900 transition">Про нас</a></li>
                    <li><a href="{{ url('/#contacts') }}" class="hover:text-gray-900 transition">Контакти</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-2 heading">Контакти</h4>
                <p>info@maxbus.com.ua</p>
                <p>+380 93 051 0795</p>
            </div>
        </div>
    </footer>
</div>
</body>
</html>
