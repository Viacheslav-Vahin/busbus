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
            Filament::registerTheme(mix('css/filament.css'));
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Реєструйте будь-які послуги, пов'язані з Filament тут
    }
}
?>
