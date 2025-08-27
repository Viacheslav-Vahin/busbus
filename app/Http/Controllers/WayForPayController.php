<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Services\Ga4;

class WayForPayController extends Controller
{
    // Та ж схема підпису, що й у BookingController (для єдності можна винести в сервіс)
    protected function sign(array $fields, string $secret): string
    {
        return hash_hmac('md5', implode(';', $fields), $secret);
    }

    /**
     * Посилання для менеджера: за order_id збираємо суму й генеруємо автосабміт-фому.
     */
    public function pay(string $orderId)
    {
        $bookings = Booking::where('order_id', $orderId)->get();
        abort_if($bookings->isEmpty(), 404);

        // якщо вже оплачені — можна показати сторінку "вже сплачено"
        if ($bookings->every(fn($b) => $b->status === 'paid')) {
            return response('<h3>Замовлення вже сплачене</h3>', 200);
        }

        $amountUah = round($bookings->sum('price_uah'), 2); // WayForPay — UAH

        $fields = [
            'merchantAccount'      => env('WAYFORPAY_MERCHANT_LOGIN'),
            'merchantAuthType'     => 'SimpleSignature',
            'merchantDomainName'   => parse_url(config('app.url'), PHP_URL_HOST) ?? config('app.url'),
            'orderReference'       => $orderId,
            'orderDate'            => time(),
            'amount'               => number_format($amountUah, 2, '.', ''),
            'currency'             => 'UAH',
            'orderTimeout'         => '49000',
            'productName'          => ['Квитки на автобус'],
            'productPrice'         => [number_format($amountUah, 2, '.', '')],
            'productCount'         => [1],
            'clientFirstName'      => optional($bookings->first()->user)->name,
            'clientLastName'       => optional($bookings->first()->user)->surname,
            'clientEmail'          => optional($bookings->first()->user)->email,
            'clientPhone'          => optional($bookings->first()->user)->phone,
            'defaultPaymentSystem' => 'card',
            'serviceUrl'           => route('wayforpay.webhook'),
            'returnUrl'            => url('/payment/return'),
        ];

        // підпис запиту на оплату
        $sigFields = [
            $fields['merchantAccount'],
            $fields['merchantDomainName'],
            $fields['orderReference'],
            $fields['orderDate'],
            $fields['amount'],
            $fields['currency'],
            ...$fields['productName'],
            ...$fields['productCount'],
            ...$fields['productPrice'],
        ];
        $fields['merchantSignature'] = $this->sign($sigFields, env('WAYFORPAY_MERCHANT_SECRET'));

        // Формуємо html-форму й автосабмітимо
        $form = '<form id="wfp" action="https://secure.wayforpay.com/pay" method="POST" accept-charset="utf-8">';
        foreach ($fields as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vv) $form .= '<input type="hidden" name="'.$k.'[]" value="'.e((string)$vv).'">';
            } else {
                $form .= '<input type="hidden" name="'.$k.'" value="'.e((string)$v).'">';
            }
        }
        $form .= '</form><script>document.getElementById("wfp").submit();</script>';

        return response($form);
    }

    /**
     * Webhook від WayForPay — фіксуємо платіж.
     * Повертаємо "accept" із їхнім підписом.
     */
    public function webhook(Request $request)
    {
        $data = $request->all();

        // Валідація підпису callback'а (типова для W4P)
        $sigCheckFields = [
            $data['merchantAccount'] ?? '',
            $data['orderReference'] ?? '',
            $data['amount'] ?? '',
            $data['currency'] ?? '',
            $data['authCode'] ?? '',
            $data['cardPan'] ?? '',
            $data['transactionStatus'] ?? '',
            $data['reasonCode'] ?? '',
        ];
        $expected = $this->sign($sigCheckFields, env('WAYFORPAY_MERCHANT_SECRET'));
        if (!hash_equals(strtolower($expected), strtolower((string)($data['merchantSignature'] ?? '')))) {
            return response()->json(['error' => 'bad signature'], 400);
        }

        $orderId = (string)($data['orderReference'] ?? '');
        $status  = (string)($data['transactionStatus'] ?? '');

        // Мапа статусів
        $paidLike    = in_array($status, ['Approved', 'Settled'], true);
        $pendingLike = in_array($status, ['Pending', 'InProcessing'], true);
        $failLike    = in_array($status, ['Declined', 'Expired', 'Refunded', 'Voided'], true);

        $bookings = Booking::where('order_id', $orderId)->get();
        if ($bookings->isNotEmpty()) {
            if ($paidLike) {
                foreach ($bookings as $b) $b->forceFill([
                    'status'         => 'paid',
                    'paid_at'        => now(),
                    'payment_method' => 'wayforpay',
                    'invoice_number' => $data['invoiceNumber'] ?? null,
                ])->save();
            }
            elseif ($pendingLike) {
                foreach ($bookings as $b) $b->forceFill(['status' => 'pending'])->save();
            } elseif ($failLike) {
                foreach ($bookings as $b) $b->forceFill(['status' => 'cancelled'])->save();
            }
        }

        if ($paidLike && $bookings->isNotEmpty()) {
            $value = round($bookings->sum('price_uah'), 2);
            // client_id можемо взяти з e-mail чи phone як стабільний сурогат
            $clientId = (string) optional($bookings->first()->user)->email ?: (string) optional($bookings->first()->user)->phone ?: 'anon';
            Ga4::send($clientId, 'purchase', [
                'currency' => 'UAH',
                'value'    => $value,
                'coupon'   => (string) ($bookings->first()->promo_code ?? ''),
                'items'    => $bookings->map(fn($b) => [
                    'item_id'   => (string) $b->seat_number,
                    'item_name' => 'Seat '.$b->seat_number,
                    'price'     => (float) $b->price_uah,
                    'quantity'  => 1,
                ])->values()->all(),
            ]);
        }

        // Відповідь "accept" із підписом (за специфікацією W4P)
        $time   = time();
        $resp   = [
            'orderReference' => $orderId,
            'status'         => 'accept',
            'time'           => $time,
        ];
        $resp['signature'] = $this->sign([
            env('WAYFORPAY_MERCHANT_LOGIN'),
            $resp['orderReference'],
            $resp['status'],
            $resp['time'],
        ], env('WAYFORPAY_MERCHANT_SECRET'));

        return response()->json($resp);
    }
}
