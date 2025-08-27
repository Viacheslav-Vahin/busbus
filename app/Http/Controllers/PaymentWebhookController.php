<?php
// app/Http/Controllers/PaymentWebhookController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PaymentFinalizer;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookController extends Controller
{
    public function wayforpay(Request $r, PaymentFinalizer $finalizer)
    {
        // 1) Перевірка підпису (псевдо — підставте свій метод)
        if (!$this->isValidWayforpaySignature($r->all())) {
            Log::warning('W4P bad signature', $r->all());
            return response('bad signature', Response::HTTP_FORBIDDEN);
        }

        // 2) Важливе: робимо ідемпотентно по orderReference
        $payload = [
            'provider'        => 'wayforpay',
            'order_reference' => $r->input('orderReference'),
            'amount'          => (float)$r->input('amount'),
            'currency'        => $r->input('currency'),
            // ↓ те, що ви передавали у створенні платежу (custom fields / merchant data)
            'meta' => [
                'trip_id' => (int)$r->input('merchantTransactionSecure.trip_id'),
                'date'    => $r->input('merchantTransactionSecure.date'),
                'seats'   => array_map('intval', explode(',', (string)$r->input('merchantTransactionSecure.seats'))),
                'user'    => [
                    'name'    => $r->input('merchantTransactionSecure.name'),
                    'surname' => $r->input('merchantTransactionSecure.surname'),
                    'email'   => $r->input('merchantTransactionSecure.email'),
                    'phone'   => $r->input('merchantTransactionSecure.phone'),
                ],
                'promo_code' => $r->input('merchantTransactionSecure.promo_code'),
            ],
        ];

        $finalizer->run($payload); // зробить create/update всього й розішле лист

        return response('OK');
    }

    private function isValidWayforpaySignature(array $data): bool
    {
        // TODO: реальна перевірка згідно з документацією WayForPay
        return true;
    }

    public function liqpay(Request $r, PaymentFinalizer $finalizer)
    {
        // TODO: декодування data/sign, перевірка підпису
        // Сформуйте $payload у такому самому форматі, далі:
        // $finalizer->run($payload);
        return response('OK');
    }
}
