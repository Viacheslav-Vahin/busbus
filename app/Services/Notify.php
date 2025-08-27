<?php
// app/Services/Notify.php
namespace App\Services;

class Notify
{
    public static function sms(?string $phone, string $text): void
    {
        if (!$phone) return;
        // приклад Twilio або будь-який SMPP/провайдер
        // \App\Services\SmsSender::send($phone, $text);
    }

    public static function viber(?string $phone, string $text): void
    {
        if (!$phone || !class_exists(\App\Services\ViberSender::class)) return;
        \App\Services\ViberSender::sendInvoice($phone, $text);
    }

    public static function telegram(?string $tg, string $text): void
    {
        if (!$tg || !class_exists(\App\Services\TelegramSender::class)) return;
        \App\Services\TelegramSender::sendInvoice($tg, $text);
    }
}
