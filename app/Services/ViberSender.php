<?php
namespace App\Services;

class ViberSender
{
    public static function sendInvoice($phone, $message)
    {
        // Тут мав би бути реальний відправник через API
        // Наприклад, через SMSClub, Wazzup24, або просто лог
        \Log::info("Viber invoice (debug):", [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}

//// app/Services/ViberSender.php
//
//namespace App\Services;
//
//class ViberSender
//{
//    public static function sendInvoice($phone, $accountDetails, $bookingId)
//    {
//        // ПРИКЛАД! Справжню інтеграцію робити через сторонній сервіс/API
//        $message = "Вас вітає компанія MaxBus! Ось реквізити для оплати бронювання #$bookingId
//        Найменування організації (одержувач):
//        ТОВ 'Максбус'
//        Код отримувача:
//        1111111111
//        Банк: АТ КБ 'ПРИВАТБАНК'
//        Рахунок отримувача (IBAN):\n$accountDetails
//        Валюта: UAH
//        Сумма: 2300
//        Призначення платежу:
//        Оплата місця в автобусі, ПІП";
//
//        // !!! РЕАЛЬНОГО КОДУ ДЛЯ VIBER ТУТ НЕМАЄ — треба підключати офіційний API чи SMSClub/Wazzup24 тощо.
//        // Наприклад, якщо інтегрувати через smsclub.mobi:
//        /*
//        $apiKey = config('services.smsclub.key');
//        $url = 'https://im.smsclub.mobi/v1/messages/viber';
//        $payload = [
//            'recipient' => $phone,
//            'message' => $message,
//            // додаткові параметри, залежно від сервісу
//        ];
//        $response = Http::withToken($apiKey)->post($url, $payload);
//        */
//
//        // Для тесту: просто записуємо в лог
//        \Log::info("Viber invoice (debug):", ['phone' => $phone, 'message' => $message]);
//    }
//}
