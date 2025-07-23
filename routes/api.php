<?php
use App\Http\Controllers\SeatController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RouteController;

Route::get('/routes', [RouteController::class, 'apiIndex']);
Route::get('/routes/{route}/available-dates', [RouteController::class, 'availableDates']);
Route::get('/trip/{trip}/bus-info', [\App\Http\Controllers\BookingController::class, 'getBusInfo']);
Route::post('/book-seat', [\App\Http\Controllers\BookingController::class, 'bookSeat']);

//Route::prefix('buses')->group(function () {
//    Route::get('{id}/seats', [SeatController::class, 'getSeats']); // Отримати план сидінь
//    Route::post('{id}/seat', [SeatController::class, 'reserveSeat']); // Вибрати місце
//    Route::post('bookings', [SeatController::class, 'confirmBooking']); // Підтвердити бронювання
////    Route::get('/routes', [RouteController::class, 'apiIndex']);
////    Route::get('/routes/{route}/available-dates', [RouteController::class, 'availableDates']);
//});

?>
<?php
//
//use Illuminate\Support\Facades\Route;
//use App\Http\Controllers\RouteController;
//
//Route::prefix('routes')->group(function () {
//    Route::get('/',            [RouteController::class, 'apiIndex']);          // GET /api/routes
//    Route::get('{route}/available-dates', [RouteController::class, 'availableDates']); // GET /api/routes/{id}/available-dates
//});


