<?php
namespace App\Exceptions;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;

class Handler
{
    public function register(): void
    {
        $this->renderable(function (TokenMismatchException $e, $request) {
            Log::channel('payments')->warning('CSRF 419', [
                'path' => $request->path(),
                'route' => optional($request->route())->getName(),
                'method' => $request->method(),
                'has_session_cookie' => $request->hasCookie(config('session.cookie')),
                'headers' => [
                    'origin' => $request->header('origin'),
                    'referer' => $request->header('referer'),
                ],
            ]);
            // залишимо дефолтну відповідь 419
        });
    }
}
