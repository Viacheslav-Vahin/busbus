<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // Сторінка куди повертає WayForPay після платіжки (тільки UI)
    public function return(Request $r)
    {
        // WayForPay інколи додає orderReference у query, але не завжди.
        $orderId = $r->input('orderReference') ?? $r->input('order_id');

        $paid = false;
        if ($orderId) {
            $paid = Booking::where('order_id', $orderId)->where('status', 'paid')->exists();
        }

        return view('payment.return', [
            'orderId' => $orderId,
            'paid'    => $paid,
        ]);
    }

    // Вебхук підтвердження (істина!) — тут міняємо статус на paid
    public function wayforpayWebhook(Request $r)
    {
        $data = $r->all();
        Log::info('WFP webhook', $data);

        $orderId = $data['orderReference'] ?? null;
        $status  = $data['transactionStatus'] ?? null;

        // Перевірка підпису (спрощена; підлаштуй під свою версію WFP)
        try {
            $secret = env('WAYFORPAY_MERCHANT_SECRET');
            $fields = [
                $data['merchantAccount'] ?? '',
                $orderId ?? '',
                $data['amount'] ?? '',
                $data['currency'] ?? '',
                $status ?? '',
                $data['reasonCode'] ?? '',
            ];
            $calc = hash_hmac('md5', implode(';', $fields), $secret);
            if (!hash_equals($calc, $data['merchantSignature'] ?? '')) {
                return response()->json(['reason' => 'bad signature'], 403);
            }
        } catch (\Throwable $e) {
            Log::warning('WFP signature calc failed', ['e' => $e->getMessage()]);
        }

        if ($orderId && $status === 'Approved') {
            $bookings = Booking::where('order_id', $orderId)->get();
            foreach ($bookings as $b) {
                $b->status  = 'paid';
                $b->paid_at = now();
                $b->save();
            }
            // WayForPay очікує JSON з accept
            return response()->json(['orderReference' => $orderId, 'status' => 'accept']);
        }

        return response()->json(['orderReference' => $orderId, 'status' => 'rejected']);
    }
}
