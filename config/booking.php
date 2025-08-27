<?php

return [
    // Скільки секунд триває hold
    'hold_ttl_seconds' => env('BOOKING_HOLD_TTL', 600), // 10 хв
    'child_discount_pct'   => 10.0,
    'solo_discount_pct'    => 20.0,
    'extras' => [
        'coffee'  => 30.0,
        'blanket' => 50.0,
        'service' => 20.0,
    ],
    'extras_map' => [
        'coffee'  => '1',
        'blanket' => '2',
        'service' => '3',
    ],
];
