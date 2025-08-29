<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * URI, які треба виключити з перевірки CSRF.
     *
     * @var array<int, string>
     */
    protected $except = [
        'payment/return',
        'payment/wayforpay/webhook',
        'payment/*',
        'payment/wayforpay/webhook*',
        'payment/return*',
        'api/telegram/webhook',
        // 'payment/liqpay/callback',
        // 'payment/mono/webhook',
    ];
}
