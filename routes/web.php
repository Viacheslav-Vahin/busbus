<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteScheduleController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test', function () {
    return 'Filament is working';
});
Route::get('/admin/routes', [RouteController::class, 'index'])->name('filament.admin.resources.routes.index');
//Route::get('/admin/resources/routes', [\App\Filament\Resources\RouteResource::class, 'index'])
//    ->name('filament.admin.resources.routes.index');
Route::post('/get-buses-by-date', [RouteScheduleController::class, 'getBusesByDate']);
