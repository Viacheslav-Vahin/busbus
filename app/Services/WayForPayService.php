<?php
//// app/Services/WayForPayService.php
//
//namespace App\Services;
//
//use Illuminate\Support\Arr;
//use Illuminate\Support\Facades\Http;
//use Illuminate\Support\Facades\URL;
//
//class WayForPayService
//{
//    public const API_URL   = 'https://api.wayforpay.com/api';
//    public const PAY_URL   = 'https://secure.wayforpay.com/pay';
//
//    protected string $merchantAccount;
//    protected string $merchantDomainName;
//    protected string $secretKey;
//    protected string $defaultCurrency;
//    protected string $defaultLanguage;
//
//    /** FAKE MODE: дозволяє імітувати успішну оплату без WayForPay */
//    protected bool $fakeMode = false;
//
//    public function __construct()
//    {
//        // config/services.php -> 'w4p' секція (див. приклад нижче)
//        $this->merchantAccount    = (string) config('services.w4p.account');
//        $this->merchantDomainName = (string) (config('services.w4p.domain') ?: parse_url(config('app.url'), PHP_URL_HOST));
//        $this->secretKey          = (string) config('services.w4p.secret');
//        $this->defaultCurrency    = (string) (config('services.w4p.currency') ?: 'UAH');
//        $this->defaultLanguage    = (string) (config('services.w4p.lang') ?: 'UA');
//
//        // нове: зчитуємо прапорець FAKE MODE
//        $this->fakeMode           = (bool) config('services.w4p.fake', false);
//    }
//
//    /**
//     * Звичайна оплата або PREAUTH (hold) — HTML-форма з автосабмітом.
//     *
//     * $order = [
//     *   'orderReference' => 'ORD-123',
//     *   'amount'         => 2100.00,
//     *   'currency'       => 'UAH',
//     *   'orderDate'      => time(), // опційно, якщо не передаси — підставимо
//     *   'clientEmail'    => '...',
//     *   'clientPhone'    => '...',
//     *   'serviceUrl'     => route(...), // якщо не передаси — URL із config/або /wayforpay/webhook
//     *   'returnUrl'      => route(...), // опційно
//     * ]
//     * $products = [
//     *   'productName'  => ['Seat 11'],
//     *   'productCount' => [1],
//     *   'productPrice' => [2100.00],
//     * ]
//     * $options = ['preauth' => true]  // зробити hold (AUTH) замість purchase
//     */
//    public function buildCheckoutForm(array $order, array $products, array $options = []): string
//    {
//        $isPreauth = (bool) ($options['preauth'] ?? false);
//
//        $payload = array_merge([
//            'merchantAccount'    => $this->merchantAccount,
//            'merchantDomainName' => $this->merchantDomainName,
//            'orderReference'     => $order['orderReference'] ?? ('ORD-'.time()),
//            'orderDate'          => $order['orderDate'] ?? time(),
//            'amount'             => (float) ($order['amount'] ?? 0),
//            'currency'           => $order['currency'] ?? $this->defaultCurrency,
//            'language'           => $order['language'] ?? $this->defaultLanguage,
//            'serviceUrl'         => $order['serviceUrl'] ?? (config('services.w4p.service_url') ?: URL::to('/wayforpay/webhook')),
//        ], $products);
//
//        // Параметр, що вмикає режим пред-авторизації (hold)
//        if ($isPreauth) {
//            // у різних акаунтах це поле може називатись по-різному — відправимо обидва
//            $payload['transactionType']         = 'AUTH';
//            $payload['merchantTransactionType'] = 'auth';
//        }
//
//        // Додаткові поля, якщо були
//        foreach (['clientEmail','clientPhone','returnUrl'] as $k) {
//            if (!empty($order[$k])) $payload[$k] = $order[$k];
//        }
//
//        // --- FAKE MODE ---
//        // Замість реальної форми WayForPay — згенеруємо валідний callback "Approved"
//        // і відправимо його POST'ом на serviceUrl (твій webhook),
//        // щоб далі спрацювала стандартна серверна логіка після оплати.
//        if ($this->fakeMode === true) {
//            $fake = $this->fakeApprovedCallbackPayload([
//                'merchantAccount' => $payload['merchantAccount'],
//                'orderReference'  => $payload['orderReference'],
//                'amount'          => $payload['amount'],
//                'currency'        => $payload['currency'],
//            ]);
//            // відправляємо саме на serviceUrl:
//            $serviceUrl = $payload['serviceUrl'];
//            return $this->htmlForm($serviceUrl, $fake);
//        }
//        // --- END FAKE MODE ---
//
//        // Реальний підпис для checkout
//        $payload['merchantSignature'] = $this->signatureCheckout($payload);
//
//        return $this->htmlForm(self::PAY_URL, $payload);
//    }
//
//    /**
//     * PREAUTH (коротка обгортка над buildCheckoutForm)
//     */
//    public function buildPreauthForm(array $order, array $products): string
//    {
//        return $this->buildCheckoutForm($order, $products, ['preauth' => true]);
//    }
//
//    /**
//     * CAPTURE заблокованих коштів (повністю або частково).
//     */
//    public function capture(string $orderReference, float $amount, string $currency = null): array
//    {
//        $currency = $currency ?: $this->defaultCurrency;
//
//        $payload = [
//            'transactionType'   => 'CAPTURE',
//            'merchantAccount'   => $this->merchantAccount,
//            'orderReference'    => $orderReference,
//            'amount'            => round($amount, 2),
//            'currency'          => $currency,
//            'apiVersion'        => 1,
//        ];
//        $payload['merchantSignature'] = $this->signatureByFields(
//            ['merchantAccount','orderReference','amount','currency'],
//            $payload
//        );
//
//        return Http::post(self::API_URL, $payload)->json() ?? [];
//    }
//
//    /**
//     * REVERSE / VOID — скасувати hold (якщо місця так і не з’явились).
//     */
//    public function void(string $orderReference, float $amount, string $currency = null): array
//    {
//        $currency = $currency ?: $this->defaultCurrency;
//
//        $payload = [
//            'transactionType'   => 'REVERSE',
//            'merchantAccount'   => $this->merchantAccount,
//            'orderReference'    => $orderReference,
//            'amount'            => round($amount, 2),
//            'currency'          => $currency,
//            'apiVersion'        => 1,
//        ];
//        $payload['merchantSignature'] = $this->signatureByFields(
//            ['merchantAccount','orderReference','amount','currency'],
//            $payload
//        );
//
//        return Http::post(self::API_URL, $payload)->json() ?? [];
//    }
//
//    /**
//     * REFUND — повернення (якщо вже було CAPTURE).
//     */
//    public function refund(string $orderReference, float $amount, string $currency = null, string $comment = ''): array
//    {
//        $currency = $currency ?: $this->defaultCurrency;
//
//        $payload = [
//            'transactionType'   => 'REFUND',
//            'merchantAccount'   => $this->merchantAccount,
//            'orderReference'    => $orderReference,
//            'amount'            => round($amount, 2),
//            'currency'          => $currency,
//            'comment'           => $comment,
//            'apiVersion'        => 1,
//        ];
//        $payload['merchantSignature'] = $this->signatureByFields(
//            ['merchantAccount','orderReference','amount','currency'],
//            $payload
//        );
//
//        return Http::post(self::API_URL, $payload)->json() ?? [];
//    }
//
//    /**
//     * CHECK_STATUS — статус замовлення у W4P.
//     */
//    public function checkStatus(string $orderReference): array
//    {
//        $payload = [
//            'transactionType'  => 'CHECK_STATUS',
//            'merchantAccount'  => $this->merchantAccount,
//            'orderReference'   => $orderReference,
//            'apiVersion'       => 1,
//        ];
//        $payload['merchantSignature'] = $this->signatureByFields(
//            ['merchantAccount','orderReference'],
//            $payload
//        );
//
//        return Http::post(self::API_URL, $payload)->json() ?? [];
//    }
//
//    /**
//     * Перевірка підпису callback/webhook від WayForPay.
//     * Повертає true, якщо підпис коректний.
//     */
//    public function verifyCallback(array $payload): bool
//    {
//        $remote = (string) ($payload['merchantSignature'] ?? '');
//        if ($remote === '') return false;
//
//        // Варіант 1 (найчастіший для Approved): набір полів як у їхньому прикладі
//        $v1Fields = ['merchantAccount','orderReference','amount','currency','authCode','cardPan','transactionStatus','reasonCode'];
//        $v1 = $this->signatureByFields($v1Fields, $payload);
//
//        if (hash_equals($remote, $v1)) {
//            return true;
//        }
//
//        // Варіант 2 (дещо інші відповіді)
//        $v2Fields = ['orderReference','amount','currency','authCode','cardPan','transactionStatus','reasonCode'];
//        $v2 = $this->signatureByFields($v2Fields, $payload);
//
//        return hash_equals($remote, $v2);
//    }
//
//    // ----------------- НИЖЧЕ — ДОПОМІЖНІ МЕТОДИ -----------------
//
//    /**
//     * Підпис для checkout (HTML-форма) — включає масиви productName/productCount/productPrice.
//     */
//    protected function signatureCheckout(array $data): string
//    {
//        $fields = [
//            'merchantAccount',
//            'merchantDomainName',
//            'orderReference',
//            'orderDate',
//            'amount',
//            'currency',
//        ];
//
//        // productName[i], productCount[i], productPrice[i] — у заданому порядку
//        $pn = Arr::wrap($data['productName']  ?? []);
//        $pc = Arr::wrap($data['productCount'] ?? []);
//        $pp = Arr::wrap($data['productPrice'] ?? []);
//
//        // Вони мають однакову довжину
//        $len = min(count($pn), count($pc), count($pp));
//
//        $signValues = [];
//        foreach ($fields as $f) {
//            $signValues[] = $this->scalar($data[$f] ?? '');
//        }
//        for ($i = 0; $i < $len; $i++) {
//            $signValues[] = $this->scalar($pn[$i]);
//        }
//        for ($i = 0; $i < $len; $i++) {
//            $signValues[] = $this->scalar($pc[$i]);
//        }
//        for ($i = 0; $i < $len; $i++) {
//            $signValues[] = $this->scalar($pp[$i]);
//        }
//
//        $signString = implode(';', $signValues);
//        return $this->hmacMd5($signString);
//    }
//
//    /**
//     * Підпис за довільним набором полів у заданому порядку.
//     */
//    protected function signatureByFields(array $fields, array $data): string
//    {
//        $signValues = [];
//        foreach ($fields as $f) {
//            // Якщо поля немає — додаємо порожній рядок (так радить W4P)
//            $signValues[] = $this->scalar(Arr::get($data, $f, ''));
//        }
//        return $this->hmacMd5(implode(';', $signValues));
//    }
//
//    /**
//     * Власне HMAC-MD5 із секретом мерчанта (класична вимога WayForPay).
//     */
//    protected function hmacMd5(string $message): string
//    {
//        return hash_hmac('md5', $message, $this->secretKey);
//    }
//
//    /**
//     * Нормалізація значення до скаляра, щоб не зламати підпис.
//     */
//    protected function scalar(mixed $v): string|int|float
//    {
//        if (is_bool($v))   return $v ? 1 : 0;
//        if (is_int($v))    return $v;
//        if (is_float($v))  return $v;
//        if (is_string($v)) return $v;
//        return (string) $v;
//    }
//
//    /**
//     * Генерація безпечної HTML-форми з автосабмітом.
//     */
//    protected function htmlForm(string $action, array $fields): string
//    {
//        $inputs = [];
//        foreach ($fields as $k => $v) {
//            if (is_array($v)) {
//                foreach ($v as $vv) {
//                    $inputs[] = '<input type="hidden" name="'.e($k).'[]" value="'.e((string)$vv).'">';
//                }
//            } else {
//                $inputs[] = '<input type="hidden" name="'.e($k).'" value="'.e((string)$v).'">';
//            }
//        }
//
//        $html = <<<HTML
//<form method="post" action="{$action}" id="w4p-form" accept-charset="utf-8">
//  %s
//</form>
//<script>document.getElementById('w4p-form').submit();</script>
//HTML;
//
//        return sprintf($html, implode("\n  ", $inputs));
//    }
//
//    /**
//     * FAKE MODE: зібрати payload успішного Approved callback з валідним підписом.
//     * Цей набір полів узгоджений з verifyCallback().
//     */
//    protected function fakeApprovedCallbackPayload(array $base): array
//    {
//        $payload = [
//            'merchantAccount'   => $base['merchantAccount'],
//            'orderReference'    => $base['orderReference'],
//            'amount'            => (float) $base['amount'],
//            'currency'          => $base['currency'],
//            'authCode'          => 'FAKE'.mt_rand(1000,9999),
//            'cardPan'           => '411111******1111',
//            'transactionStatus' => 'Approved',
//            'reasonCode'        => 1100, // success
//            'processingDate'    => time(),
//        ];
//
//        // підпис, який пройде твою verifyCallback()
//        $payload['merchantSignature'] = $this->signatureByFields(
//            ['merchantAccount','orderReference','amount','currency','authCode','cardPan','transactionStatus','reasonCode'],
//            $payload
//        );
//
//        return $payload;
//    }
//}
// app/Services/WayForPayService.php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class WayForPayService
{
    public const API_URL = 'https://api.wayforpay.com/api';
    public const PAY_URL = 'https://secure.wayforpay.com/pay';

    protected string $merchantAccount;
    protected string $merchantDomainName;
    protected string $secretKey;
    protected string $defaultCurrency;
    protected string $defaultLanguage;

    public function __construct()
    {
        // config/services.php -> 'w4p' секція (див. приклад нижче)
        $this->merchantAccount = (string)config('services.w4p.account');
        $this->merchantDomainName = (string)(config('services.w4p.domain') ?: parse_url(config('app.url'), PHP_URL_HOST));
        $this->secretKey = (string)config('services.w4p.secret');
        $this->defaultCurrency = (string)(config('services.w4p.currency') ?: 'UAH');
        $this->defaultLanguage = (string)(config('services.w4p.lang') ?: 'UA');
    }

    /**
     * Звичайна оплата або PREAUTH (hold) — HTML-форма з автосабмітом.
     *
     * $order = [
     *   'orderReference' => 'ORD-123',
     *   'amount'         => 2100.00,
     *   'currency'       => 'UAH',
     *   'orderDate'      => time(), // опційно, якщо не передаси — підставимо
     *   'clientEmail'    => '...',
     *   'clientPhone'    => '...',
     *   'serviceUrl'     => route(...), // якщо не передаси — підставимо URL із конfig
     *   'returnUrl'      => route(...), // опційно
     * ]
     * $products = [
     *   'productName'  => ['Seat 11'],
     *   'productCount' => [1],
     *   'productPrice' => [2100.00],
     * ]
     * $options = ['preauth' => true]  // зробити hold (AUTH) замість purchase
     */
    public function buildCheckoutForm(array $order, array $products, array $options = []): string
    {
        $isPreauth = (bool)($options['preauth'] ?? false);

        $payload = array_merge([
            'merchantAccount' => $this->merchantAccount,
            'merchantDomainName' => $this->merchantDomainName,
            'orderReference' => $order['orderReference'] ?? ('ORD-' . time()),
            'orderDate' => $order['orderDate'] ?? time(),
            'amount' => (float)($order['amount'] ?? 0),
            'currency' => $order['currency'] ?? $this->defaultCurrency,
            'language' => $order['language'] ?? $this->defaultLanguage,
            'serviceUrl' => $order['serviceUrl'] ?? (config('services.w4p.service_url') ?: URL::to('/wayforpay/webhook')),
        ], $products);

        // Параметр, що вмикає режим пред-авторизації (hold)
        if ($isPreauth) {
            // у різних акаунтах це поле може називатись по-різному — відправимо обидва
            $payload['transactionType'] = 'AUTH';
            $payload['merchantTransactionType'] = 'auth';
        }

        // Додаткові поля, якщо були
        foreach (['clientEmail', 'clientPhone', 'returnUrl'] as $k) {
            if (!empty($order[$k])) $payload[$k] = $order[$k];
        }

        // Підпис: checkout має окремий набір полів (із масивами product*)
        $payload['merchantSignature'] = $this->signatureCheckout($payload);

        return $this->htmlForm(self::PAY_URL, $payload);
    }

    /**
     * PREAUTH (коротка обгортка над buildCheckoutForm)
     */
    public function buildPreauthForm(array $order, array $products): string
    {
        return $this->buildCheckoutForm($order, $products, ['preauth' => true]);
    }

    /**
     * CAPTURE заблокованих коштів (повністю або частково).
     */
    public function capture(string $orderReference, float $amount, string $currency = null): array
    {
        $currency = $currency ?: $this->defaultCurrency;

        $payload = [
            'transactionType' => 'CAPTURE',
            'merchantAccount' => $this->merchantAccount,
            'orderReference' => $orderReference,
            'amount' => round($amount, 2),
            'currency' => $currency,
            'apiVersion' => 1,
        ];
        $payload['merchantSignature'] = $this->signatureByFields(
            ['merchantAccount', 'orderReference', 'amount', 'currency'],
            $payload
        );

        return Http::post(self::API_URL, $payload)->json() ?? [];
    }

    /**
     * REVERSE / VOID — скасувати hold (якщо місця так і не з’явились).
     */
    public function void(string $orderReference, float $amount, string $currency = null): array
    {
        $currency = $currency ?: $this->defaultCurrency;

        $payload = [
            'transactionType' => 'REVERSE',
            'merchantAccount' => $this->merchantAccount,
            'orderReference' => $orderReference,
            'amount' => round($amount, 2),
            'currency' => $currency,
            'apiVersion' => 1,
        ];
        $payload['merchantSignature'] = $this->signatureByFields(
            ['merchantAccount', 'orderReference', 'amount', 'currency'],
            $payload
        );

        return Http::post(self::API_URL, $payload)->json() ?? [];
    }

    /**
     * REFUND — повернення (якщо вже було CAPTURE).
     */
    public function refund(string $orderReference, float $amount, string $currency = null, string $comment = ''): array
    {
        $currency = $currency ?: $this->defaultCurrency;

        $payload = [
            'transactionType' => 'REFUND',
            'merchantAccount' => $this->merchantAccount,
            'orderReference' => $orderReference,
            'amount' => round($amount, 2),
            'currency' => $currency,
            'comment' => $comment,
            'apiVersion' => 1,
        ];
        $payload['merchantSignature'] = $this->signatureByFields(
            ['merchantAccount', 'orderReference', 'amount', 'currency'],
            $payload
        );

        return Http::post(self::API_URL, $payload)->json() ?? [];
    }

    /**
     * CHECK_STATUS — статус замовлення у W4P.
     */
    public function checkStatus(string $orderReference): array
    {
        $payload = [
            'transactionType' => 'CHECK_STATUS',
            'merchantAccount' => $this->merchantAccount,
            'orderReference' => $orderReference,
            'apiVersion' => 1,
        ];
        $payload['merchantSignature'] = $this->signatureByFields(
            ['merchantAccount', 'orderReference'],
            $payload
        );

        return Http::post(self::API_URL, $payload)->json() ?? [];
    }

    /**
     * Перевірка підпису callback/webhook від WayForPay.
     * Повертає true, якщо підпис коректний.
     */
    public function verifyCallback(array $payload): bool
    {
        $remote = (string)($payload['merchantSignature'] ?? '');
        if ($remote === '') return false;

        // Варіант 1 (найчастіший для Approved): набір полів як у їхньому прикладі
        $v1Fields = ['merchantAccount', 'orderReference', 'amount', 'currency', 'authCode', 'cardPan', 'transactionStatus', 'reasonCode'];
        $v1 = $this->signatureByFields($v1Fields, $payload);

        if (hash_equals($remote, $v1)) {
            return true;
        }

        // Варіант 2 (дещо інші відповіді)
        $v2Fields = ['orderReference', 'amount', 'currency', 'authCode', 'cardPan', 'transactionStatus', 'reasonCode'];
        $v2 = $this->signatureByFields($v2Fields, $payload);

        return hash_equals($remote, $v2);
    }

    // ----------------- НИЖЧЕ — ДОПОМІЖНІ МЕТОДИ -----------------

    /**
     * Підпис для checkout (HTML-форма) — включає масиви productName/productCount/productPrice.
     */
    protected function signatureCheckout(array $data): string
    {
        $fields = [
            'merchantAccount',
            'merchantDomainName',
            'orderReference',
            'orderDate',
            'amount',
            'currency',
        ];

        // productName[i], productCount[i], productPrice[i] — у заданому порядку
        $pn = Arr::wrap($data['productName'] ?? []);
        $pc = Arr::wrap($data['productCount'] ?? []);
        $pp = Arr::wrap($data['productPrice'] ?? []);

        // Вони мають однакову довжину
        $len = min(count($pn), count($pc), count($pp));

        $signValues = [];
        foreach ($fields as $f) {
            $signValues[] = $this->scalar($data[$f] ?? '');
        }
        for ($i = 0; $i < $len; $i++) {
            $signValues[] = $this->scalar($pn[$i]);
        }
        for ($i = 0; $i < $len; $i++) {
            $signValues[] = $this->scalar($pc[$i]);
        }
        for ($i = 0; $i < $len; $i++) {
            $signValues[] = $this->scalar($pp[$i]);
        }

        $signString = implode(';', $signValues);
        return $this->hmacMd5($signString);
    }

    /**
     * Підпис за довільним набором полів у заданому порядку.
     */
    protected function signatureByFields(array $fields, array $data): string
    {
        $signValues = [];
        foreach ($fields as $f) {
            // Якщо поля немає — додаємо порожній рядок (так радить W4P)
            $signValues[] = $this->scalar(Arr::get($data, $f, ''));
        }
        return $this->hmacMd5(implode(';', $signValues));
    }

    /**
     * Власне HMAC-MD5 із секретом мерчанта (класична вимога WayForPay).
     */
    protected function hmacMd5(string $message): string
    {
        return hash_hmac('md5', $message, $this->secretKey);
    }

    /**
     * Нормалізація значення до скаляра, щоб не зламати підпис.
     */
    protected function scalar(mixed $v): string|int|float
    {
        if (is_bool($v)) return $v ? 1 : 0;
        if (is_int($v)) return $v;
        if (is_float($v)) return $v;
        if (is_string($v)) return $v;
        return (string)$v;
    }

    /**
     * Генерація безпечної HTML-форми з автосабмітом.
     */
    protected function htmlForm(string $action, array $fields): string
    {
        $inputs = [];
        foreach ($fields as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vv) {
                    $inputs[] = '<input type="hidden" name="' . e($k) . '[]" value="' . e((string)$vv) . '">';
                }
            } else {
                $inputs[] = '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
            }
        }

        $html = <<<HTML
<form method="post" action="{$action}" id="w4p-form" accept-charset="utf-8">
  %s
</form>
<script>document.getElementById('w4p-form').submit();</script>
HTML;

        return sprintf($html, implode("\n  ", $inputs));
    }
}
