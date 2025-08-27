<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'bot_token'   => env('TELEGRAM_BOT_TOKEN'),
        'bot_username'=> env('TELEGRAM_BOT_USERNAME'), // для deep-link
    ],
    'smsclub' => [ // Viber через SMSClub (відправка по номеру)
        'token'  => env('SMSCLUB_TOKEN'),
        'sender' => env('SMSCLUB_SENDER', 'MAXBUS'),
        'ttl'    => env('SMSCLUB_TTL', 3600),         // час життя повідомлення
        'route'  => env('SMSCLUB_ROUTE', 'viber'),    // 'viber' або 'viber+sms'
        'callback'=> env('SMSCLUB_CALLBACK_URL', null),
    ],

    'ga4' => [
        'enabled' => env('GA4_ENABLED', true),
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
        'api_secret' => env('GA4_API_SECRET'),
    ],

//    'w4p' => [
//        'account' => env('W4P_MERCHANT_ACCOUNT', ''),   // напр. 'test_merch_n1'
//        'secret' => env('W4P_SECRET_KEY', ''),         // SecretKey
//        'domain' => env('W4P_DOMAIN', ''),             // напр. 'maxbus.example.com'
//        'currency' => env('W4P_CURRENCY', 'UAH'),
//        'lang' => env('W4P_LANG', 'UA'),
//        'service_url' => env('W4P_SERVICE_URL', ''),        // якщо хочеш перезаписати URL вебхука
//    ],

    'w4p' => [
        'account' => env('WAYFORPAY_MERCHANT_ACCOUNT'),
        'key'     => env('WAYFORPAY_SECRET_KEY'),
        'domain'  => env('WAYFORPAY_DOMAIN', parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'),
        'fake'        => env('W4P_FAKE_MODE', false),
    ],
    'wayforpay' => [
        'merchant' => env('WAYFORPAY_MERCHANT_LOGIN'),
        'secret'   => env('WAYFORPAY_MERCHANT_SECRET'),
    ],
];
