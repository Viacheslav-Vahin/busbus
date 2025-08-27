<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramSender
{
    public static function sendInvoice(int|string $chatId, string $message, array $extra = []): bool
    {
        $token = config('services.telegram.bot_token');
        if (!$token || !$chatId) {
            Log::warning('Telegram: missing token or chat_id', compact('chatId'));
            return false;
        }

        // HTML простіше ніж MarkdownV2
        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $extra);

        $resp = Http::asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
//        $response = Http::post($url, [
//            'chat_id' => $chatId,
//            'text' => $message,
//            'parse_mode' => 'HTML',
//        ]);
        Log::info('Telegram sendMessage', ['payload' => $payload, 'status' => $resp->status(), 'body' => $resp->json()]);
        return $resp->ok() && ($resp->json()['ok'] ?? false);
    }

    // зручно зберігати chat_id через webhook
    public static function setWebhook(string $url): array
    {
        $token = config('services.telegram.bot_token');
        return Http::asForm()->post("https://api.telegram.org/bot{$token}/setWebhook", ['url' => $url])->json();
    }
}
