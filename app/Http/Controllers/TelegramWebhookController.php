<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use App\Services\TelegramSender;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $update = $request->all();
        Log::info('TG webhook', $update);

        $msg = $update['message'] ?? $update['edited_message'] ?? null;
        if (!$msg) return response('ok');

        $chatId = $msg['chat']['id'] ?? null;
        $text   = trim((string)($msg['text'] ?? ''));

        // Збережи chat_id для свого користувача (напряму чи через бронювання)
        // приклад: якщо юзер пише нам номер замовлення після /start
        if ($chatId && preg_match('/^\/start(?:\s+(\S+))?$/i', $text, $m)) {
            $orderId = $m[1] ?? null; // ми передаємо order_id у deep-link: t.me/<bot>?start=<order_id>

            if (!$orderId) {
                TelegramSender::sendInvoice($chatId,
                    "Вітаємо! Надішліть, будь ласка, номер вашого замовлення (order_id) у відповідь, щоб я зміг надсилати вам сповіщення.");
                return response('ok');
            }

            // замовлення може містити кілька бронювань з однаковим order_id
            $bookings = Booking::where('order_id', $orderId)->get();

            if ($bookings->isEmpty()) {
                TelegramSender::sendInvoice($chatId, "На жаль, замовлення <b>{$orderId}</b> не знайдено.");
                return response('ok');
            }

            // зберігаємо chat_id в payment_meta кожної броні з цього замовлення
            foreach ($bookings as $b) {
                $meta = $b->payment_meta ?? [];
                if (is_string($meta)) {
                    $meta = json_decode($meta, true) ?: [];
                }
                $meta['telegram_chat_id'] = (int) $chatId;
                $b->payment_meta = $meta;
                $b->save();
            }

            TelegramSender::sendInvoice(
                $chatId,
                "Готово! Замовлення <b>{$orderId}</b> прив’язано до цього чату. "
                ."Тепер ми зможемо надсилати вам квитанції та сповіщення тут. ✅"
            );

            return response('ok');
        }

        return response('ok');
    }
}
