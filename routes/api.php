<?php
// routes/api.php
use App\Http\Controllers\SeatController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteScheduleController;
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
// routes/api.php (краще api, без сесій/куків)
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\Api\CmsController;
use App\Models\CmsPage;
use App\Http\Controllers\Api\CmsPageController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\InstagramFeedController;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');
Route::get('/routes', [RouteController::class, 'apiIndex']);
//Route::get('/routes/{route}/available-dates', [RouteController::class, 'availableDates']);
Route::get('/routes/{route}/available-dates', [RouteScheduleController::class, 'getAvailableDates']);
Route::post('/get-buses-by-date', [RouteScheduleController::class, 'getBusesByDate']);

Route::get('/trip/{trip}/bus-info', [\App\Http\Controllers\BookingController::class, 'getBusInfo']);
//Route::get('/trips/{trip}/bus-info', [\App\Http\Controllers\BookingController::class, 'getBusInfo']);
// busId-варіант (те, що викликає фронт: /api/trips/{bus}/bus-info?date=YYYY-MM-DD)
Route::get('/trips/{bus}/bus-info', [\App\Http\Controllers\BookingController::class, 'getBusInfoByBusId'])
    ->whereNumber('bus');


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

Route::prefix('cms')->group(function () {
    Route::get('/page/{slug}', [CmsController::class, 'page']);          // ?locale=uk
    Route::get('/menus/{key}', [CmsController::class, 'menu']);          // header/footer
    Route::get('/settings',   [CmsController::class, 'settings']);       // ?keys[]=phone&keys[]=email
});

Route::get('/cms/pages/{slug}', [CmsPageController::class, 'show']);
//Route::get('/cms/pages/{key}', function (string $key) {
//    $page = CmsPage::where('key', $key)->firstOrFail();
//    return [
//        'title'   => $page->title,
//        'slug'    => $page->slug,
//        'content' => $page->content,
//        'meta'    => [
//            'title' => $page->meta_title,
//            'description' => $page->meta_description,
//        ],
//    ];
//});


Route::get('/gallery', [GalleryController::class, 'index']);

Route::middleware('auth:sanctum')->group(function() {
    Route::post('/admin/gallery', [GalleryController::class, 'store']);
    Route::patch('/admin/gallery/{photo}', [GalleryController::class, 'update']); // ← нове
    Route::delete('/admin/gallery/{photo}', [GalleryController::class, 'destroy']);
});
Route::get('/instagram-feed', [InstagramFeedController::class, 'index']);
