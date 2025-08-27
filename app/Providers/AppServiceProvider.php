<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Http\Livewire\SeatSelector;
use App\Models\Trip;
use App\Observers\TripObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::component('seat-selector', \App\Http\Livewire\SeatSelector::class);
        Trip::observe(TripObserver::class);
    }
}
