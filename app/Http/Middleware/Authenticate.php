<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Str;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Якщо гість лізе у /driver/* — ведемо на форму логіну водія
        if (Str::startsWith($request->path(), 'driver')) {
            return route('driver.login');
        }

        // Інакше — на звичайний логін (або головну, як хочеш)
        return route('login'); // або return '/';
    }
}
