<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteScheduleController;
use App\Filament\Resources\BusResource\Pages\EditBus;
use App\Http\Controllers\BookingController;




//Route::get('/', function () {
//    return view('welcome');
//});
Route::get('/test', function () {
    return 'Filament is working';
});
//Route::get('/admin/routes', [RouteController::class, 'index'])->name('admin.routes.index');
//
//Route::post('/get-buses-by-date', [RouteScheduleController::class, 'getBusesByDate']);
//Route::get('/admin/routes', [RouteController::class, 'index'])->name('filament.admin.resources.routes.index');
//Route::get('/admin/resources/routes', [\App\Filament\Resources\RouteResource::class, 'index'])
//    ->name('filament.admin.resources.routes.index');
Route::post('/get-buses-by-date', [RouteScheduleController::class, 'getBusesByDate']);
//Route::post('/buses-for-route', [RouteController::class, 'getBusesForRouteAndDate']);
//Route::post('/get-buses-by-date', [RouteController::class, 'getBusesForRouteAndDate'])->name('get-buses-by-date');
//Route::post('/get-buses-by-route-date', [RouteController::class, 'getBusesByRouteAndDate'])->name('get-buses-by-route-date');
//Route::get('/admin/buses/{record}/edit', [EditBus::class, 'mount'])
//    ->name('admin.buses.edit');
Route::post('/booking/store', [BookingController::class, 'store'])->name('booking.store');
//Route::view('/', 'index')->name('home');
Route::view('/', 'index');

