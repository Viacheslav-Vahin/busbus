<?php
// /bus-booking-system/app/Providers/Filament/FilamentServiceProvider.php
namespace App\Providers;

use Filament\Facades\Filament;
use Illuminate\Support\ServiceProvider;

class FilamentServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Filament::serving(function () {
            // Видаляємо виклик mix() і використовуємо тільки Vite
            Filament::registerViteTheme([
                'resources/css/app.css',
                'resources/js/app.js',
            ]);
        });
    }

    public function register()
    {
        // Реєструємо додаткові сервіси для Filament, якщо потрібно
    }
}

?>
