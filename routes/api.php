<?php
use App\Http\Controllers\SeatController;

Route::prefix('buses')->group(function () {
    Route::get('{id}/seats', [SeatController::class, 'getSeats']); // Отримати план сидінь
    Route::post('{id}/seat', [SeatController::class, 'reserveSeat']); // Вибрати місце
    Route::post('bookings', [SeatController::class, 'confirmBooking']); // Підтвердити бронювання
});

?>
