<?php

return [

    // До яких шляхів застосовується CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Дозволені origin-и (для продакшну звузь список!)
    'allowed_origins' => [
        'http://localhost',
        'http://127.0.0.1',
        'capacitor://localhost',
        'ionic://localhost',
        // якщо тестуєш із телефона в локальній мережі — додай свою IP-адресу
        // 'http://192.168.0.42:3000',
    ],

    // Або патерни (коли IP/порт змінюються)
    'allowed_origins_patterns' => [
        // '#^http://192\.168\.\d+\.\d+(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,

    // Якщо плануєш cookie-сесію для SPA — true.
    // Для мобільного застосунку з Bearer-токенами можна залишити false.
    'supports_credentials' => false,
];
