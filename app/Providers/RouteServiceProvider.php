<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            // 1) API‐маршрути під префіксом /api
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            // 2) Web-маршрути
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
