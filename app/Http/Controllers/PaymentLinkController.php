<?php
// app/Http/Controllers/PaymentLinkController.php
namespace App\Http\Controllers;

use App\Models\Booking;

class PaymentLinkController extends Controller
{
    public function show(string $order)
    {
        $items = Booking::where('order_id', $order)->whereIn('status',['pending'])->get();
        abort_if($items->isEmpty(), 404);

        $totalUah = round($items->sum('price_uah'), 2);
        $user = optional($items->first()->user);
        $orderDate = time();
        $merchantDomainName = parse_url(config('app.url'), PHP_URL_HOST) ?? config('app.url');

        $fields = [
            'merchantAccount'      => env('WAYFORPAY_MERCHANT_LOGIN'),
            'merchantAuthType'     => 'SimpleSignature',
            'merchantDomainName'   => $merchantDomainName,
            'orderReference'       => $order,
            'orderDate'            => $orderDate,
            'amount'               => number_format($totalUah, 2, '.', ''),
            'currency'             => 'UAH',
            'orderTimeout'         => '49000',
            'productName'          => ['Квитки на автобус'],
            'productPrice'         => [number_format($totalUah, 2, '.', '')],
            'productCount'         => [1],
            'clientFirstName'      => $user->name,
            'clientLastName'       => $user->surname,
            'clientEmail'          => $user->email,
            'clientPhone'          => $user->phone ?? '',
            'defaultPaymentSystem' => 'card',
            'serviceUrl'           => route('wayforpay.webhook'),
            'returnUrl'            => url('/payment/return'),
        ];

        $fields['merchantSignature'] = BookingController::generateWayForPaySignature(
            $fields,
            config('services.wayforpay.secret') // краще через config, а не env()
        );

        // мінімалістична сторінка авто-submit
        return response()->view('pay.redirect', ['fields'=>$fields]);
    }
}
