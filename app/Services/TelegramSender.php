<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramSender
{
    public static function sendInvoice($chatId, $message)
    {
        $token = config('services.telegram.bot_token'); // Задаєш у .env
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        // Якщо хочеш додати розмітку (жирний, emoji) — використовуй MarkdownV2 або HTML
        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'MarkdownV2', // Або 'MarkdownV2'
        ];

        $response = Http::post($url, $payload);

        \Log::info('Telegram invoice sent', [
            'chat_id' => $chatId,
            'message' => $message,
            'response' => $response->json(),
        ]);
    }
}
