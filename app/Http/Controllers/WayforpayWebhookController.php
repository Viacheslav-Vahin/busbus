<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WayforpayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->all();                 // WFP шле JSON або form-data
        if (empty($data)) {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        Log::info('WFP webhook raw', $data);

        // 1) Перевірка підпису запиту (orderReference;amount;currency)
        $base = implode(';', [
            $data['merchantAccount'] ?? '',
            $data['orderReference'] ?? '',
            $data['amount'] ?? '',
            $data['currency'] ?? '',
            $data['authCode'] ?? '',
            $data['cardPan'] ?? '',
            $data['transactionStatus'] ?? '',
            $data['reasonCode'] ?? '',
        ]);
        $calc = hash_hmac('md5', $base, env('WAYFORPAY_MERCHANT_SECRET'));
        if (!hash_equals($calc, ($data['merchantSignature'] ?? ''))) {
            Log::warning('WFP signature mismatch', compact('base','calc'));
            return response()->json(['message' => 'bad signature'], 400);
        }

        // 2) Знайдемо всі бронювання цього замовлення
        $orderId = $data['orderReference'] ?? null;   // ми пишемо його в Booking::order_id
        if (!$orderId) {
            return response()->json(['message' => 'no orderReference'], 400);
        }

        $bookings = Booking::where('order_id', $orderId)->get();
        if ($bookings->isEmpty()) {
            Log::warning('WFP: order not found', ['order_id' => $orderId]);
        } else {
            $status = strtolower($data['transactionStatus'] ?? '');
            // Approved / Declined / InProcessing / RefundInProcessing / Refunded / Voided ...
            $mapped = match ($status) {
                'approved'            => 'paid',
                'refunded'            => 'refunded',
                'voided', 'declined'  => 'cancelled',
                default               => 'pending',
            };

            foreach ($bookings as $b) {
                $b->status  = $mapped;
                $b->paid_at = $mapped === 'paid' ? now() : null;
                $b->save();
            }
        }

        // 3) Відповідь, яку чекає WFP: orderReference,status,time,signature
        $time   = time();
        $status = 'accept';
        $signBase = implode(';', [$orderId, $status, $time]);
        $signature = hash_hmac('md5', $signBase, env('WAYFORPAY_MERCHANT_SECRET'));

        return response()->json([
            'orderReference' => $orderId,
            'status'         => $status,
            'time'           => $time,
            'signature'      => $signature,
        ]);
    }
}
