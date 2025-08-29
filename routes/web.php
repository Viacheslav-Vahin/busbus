<?php
use Illuminate\Support\Facades\Route;
use App\Models\Booking;
use App\Http\Controllers\{
    BookingController,
    TicketController,
    WayForPayController,
    PaymentLinkController,
    DriverAuthController,
    RouteScheduleController
};
use App\Http\Controllers\StandbyController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentReturnController;
//use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Controllers\WayForPayWebhookController;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;
use App\Http\Controllers\Admin\WpBookingController;

// Домашня: після логіна розвести водія та решту
Route::get('/home', function () {
    $u = auth()->user();
    return $u?->hasRole('driver')
        ? redirect()->route('driver.scan')
        : redirect('/admin');
})->middleware('auth')->name('home');

Route::view('/', 'index');

// AJAX/формочки
Route::post('/get-buses-by-date', [RouteScheduleController::class, 'getBusesByDate']);
Route::post('/booking/store', [BookingController::class, 'store'])->name('booking.store');

// Публічні квитки
Route::get('/ticket/{uuid}', function (string $uuid) {
    $b = Booking::where('ticket_uuid', $uuid)->firstOrFail();
    return view('tickets.public-show', compact('b'));
})->name('ticket.public');

// Короткий та повний PDF
Route::get('/t/{uuid}', [TicketController::class, 'pdfByUuid'])->name('tickets.pdf.short');
Route::get('/tickets/{uuid}.pdf', [TicketController::class, 'pdfByUuid'])->name('tickets.pdf');

// Адмінський сканер (якщо треба менеджерам)
Route::middleware(['auth', 'permission:checkin'])->group(function () {
    Route::get('/admin/checkin', [TicketController::class, 'scanner'])->name('tickets.scanner');
    Route::post('/admin/checkin/{uuid}', [TicketController::class, 'checkin'])->name('tickets.checkin');
    Route::get('/admin/tickets/{id}/pdf', [TicketController::class, 'pdfById'])->name('tickets.byId');
});

// WayForPay webhook
Route::post('/wayforpay/webhook', [WayForPayController::class, 'webhook'])->name('wayforpay.webhook');

// Оплата: лишаємо один зрозумілий маршрут
Route::get('/pay/{order}', [PaymentLinkController::class, 'show'])->name('pay.show');
// Якщо потрібен builder W4P — додатковий, інший URI/нейм
// Route::get('/pay/build/{orderId}', [WayForPayController::class, 'pay'])->name('wayforpay.pay.build');

// Маршрут, який потребує auth->login редіректу
Route::get('/login', fn() => redirect()->route('driver.login'))->name('login');

// Зона водія
Route::prefix('driver')->name('driver.')->group(function () {

    // логін водія
    Route::middleware('guest')->group(function () {
        Route::get('/login', [DriverAuthController::class, 'show'])->name('login');
        Route::post('/login', [DriverAuthController::class, 'login'])->name('login.post');
    });

    // захищено
    Route::middleware(['auth', 'role:driver'])->group(function () {
        Route::get('/', fn() => redirect()->route('driver.scan'));
        Route::get('/scan', [TicketController::class, 'scanner'])->name('scan');
        Route::post('/checkin/{uuid}', [TicketController::class, 'checkin'])->name('checkin');
        Route::get('/app', fn() => view('driver.app'))->name('app');
        Route::post('/logout', [DriverAuthController::class, 'logout'])->name('logout');
    });
});

Route::post('/wayforpay/standby/webhook', [StandbyController::class, 'webhook'])
    ->name('wayforpay.standby.webhook');

Route::middleware(['auth', 'role:passenger'])->prefix('cabinet')->name('cabinet.')->group(function () {
    Route::get('/', [CabinetController::class, 'index'])->name('index');
    Route::get('/orders', [CabinetController::class, 'orders'])->name('orders');
    Route::get('/orders/{booking}/ticket', [CabinetController::class, 'ticket'])->name('ticket'); // скачати pdf
    Route::get('/profile', [CabinetController::class, 'profile'])->name('profile');
    Route::post('/profile', [CabinetController::class, 'updateProfile'])->name('profile.update');
});

// людська сторінка повернення
Route::match(['GET','POST'], '/payment/return', [PaymentReturnController::class, 'show'])
    ->name('payment.return')
    ->withoutMiddleware([FrameworkCsrf::class]);

Route::middleware(['web','auth']) // додай свої middleware/policies
->prefix('admin/wp-bookings')
    ->name('admin.wp_bookings.')
    ->group(function () {
        Route::get('/', [WpBookingController::class, 'index'])->name('index');
        Route::get('/{id}', [WpBookingController::class, 'show'])->name('show');
    });
