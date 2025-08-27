<?php
// routes/api.php
use App\Http\Controllers\SeatController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SeatMapController;
use App\Models\Currency;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DriverApiController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\DriverMobileAuthController;
use App\Http\Controllers\StandbyController;
use App\Http\Controllers\DevPaymentsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


Route::get('/routes', [RouteController::class, 'apiIndex']);
Route::get('/routes/{route}/available-dates', [RouteController::class, 'availableDates']);
Route::get('/trip/{trip}/bus-info', [\App\Http\Controllers\BookingController::class, 'getBusInfo']);
Route::post('/book-seat', [\App\Http\Controllers\BookingController::class, 'bookSeat']);
//Route::post('/wayforpay/webhook', [\App\Http\Controllers\WayforpayWebhookController::class, 'handle'])
//    ->name('wayforpay.webhook');
Route::get('/search', [SearchController::class, 'index']); // ?from=&to=&date=
Route::get('/trips/{trip}/seats', [SeatMapController::class, 'seats']); // layout + зайнятість

Route::get('/trips/{trip}/seats', [SeatMapController::class, 'seats']);
Route::post('/trips/{trip}/hold', [SeatMapController::class, 'hold']);       // {seatNumber, solo:boolean}
Route::post('/hold/prolong', [SeatMapController::class, 'prolong']);         // {token}
Route::post('/hold/release', [SeatMapController::class, 'release']);         // {token}
Route::post('/checkout', [SeatMapController::class, 'checkout']);

Route::get('/currencies', function () {
    return Currency::where('is_active', 1)
        ->orderBy('sort')          // якщо є
        ->get(['code','rate','symbol','rate']);
});
Route::get('/promo/check', [BookingController::class, 'checkPromo']);

Route::get('/currencies', [CurrencyController::class, 'index']);
Route::get('/promo/check', [PromoController::class, 'check']);

Route::prefix('driver')->group(function () {
    Route::post('login', [DriverMobileAuthController::class, 'login']); // без auth
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [DriverMobileAuthController::class, 'logout']);
        // нижче – захисти всі driver API:
        Route::post('scan/verify', [\App\Http\Controllers\DriverApiController::class, 'verify']);
        Route::get('shift/active', [\App\Http\Controllers\DriverApiController::class, 'activeShift']);
        Route::post('shift/open', [\App\Http\Controllers\DriverApiController::class, 'openShift']);
        Route::post('shift/close', [\App\Http\Controllers\DriverApiController::class, 'closeShift']);
        Route::post('boarding', [\App\Http\Controllers\DriverApiController::class, 'boarding']);
        Route::post('cash/collect', [\App\Http\Controllers\DriverApiController::class, 'collect']);
        Route::get('manifest', [\App\Http\Controllers\DriverApiController::class, 'manifest']);
    });
});


Route::post('/standby', [StandbyController::class, 'store'])->name('standby.store');

Route::post('/standby/start', [StandbyController::class, 'start'])->name('standby.start');
Route::post('/standby/cancel/{orderReference}', [StandbyController::class, 'cancel']);

Route::post('/dev/mock-paid', [DevPaymentsController::class, 'mockPaid'])
    ->middleware('throttle:30,1');
//Route::match(['GET','POST'],'/payment/return', function (Request $r) {
//    Log::info('WFP RETURN POST → redirect', ['all' => $r->all()]);
//    $order = $r->input('orderReference') ?: $r->input('order');
//    return redirect()->route('payment.return', ['order' => $order]);
//});
use App\Http\Controllers\PaymentReturnController;

Route::post('/payment/wayforpay/webhook', [PaymentReturnController::class, 'webhook'])
    ->name('payment.wfp.webhook');   // /api/payment/wayforpay/webhook
