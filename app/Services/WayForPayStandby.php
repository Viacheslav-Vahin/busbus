<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WayForPayStandby
{
    public function buildPreauthForm(array $payload): string
    {
        // ПЛАТІЖ: transactionType=AUTH (hold)
        // Заповнюємо стандартний набір + підпис
        $data = array_merge([
            'merchantAccount'      => config('services.w4p.account'),
            'merchantDomainName'   => config('services.w4p.domain'),
            'orderDate'            => time(),
            'apiVersion'           => 1,
            'serviceUrl'           => route('wayforpay.standby.webhook'),
            'transactionType'      => 'AUTH',  // головне — пред-авторизація
        ], $payload);

        $data['merchantSignature'] = $this->signature($data, [
            // сигнатура для checkout (порядок з офіційної доки W4P)
            'merchantAccount','merchantDomainName','orderReference','orderDate',
            'amount','currency',
            // далі flatten масивів product* у послідовності
            // productName[], productCount[], productPrice[]
        ]);

        // HTML форма як у тебе з бронями
        $fields = '';
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vv) {
                    $fields .= '<input type="hidden" name="'.e($k).'[]" value="'.e($vv).'">';
                }
            } else {
                $fields .= '<input type="hidden" name="'.e($k).'" value="'.e($v).'">';
            }
        }

        return <<<HTML
<form method="post" action="https://secure.wayforpay.com/pay" id="w4p-preauth">$fields</form>
<script>document.getElementById('w4p-preauth').submit();</script>
HTML;
    }

    public function capture(string $orderReference, float $amount, string $currency='UAH'): array
    {
        // Захоплення пред-авторизації
        $payload = [
            'transactionType'    => 'CAPTURE',
            'merchantAccount'    => config('services.w4p.account'),
            'orderReference'     => $orderReference,
            'amount'             => round($amount,2),
            'currency'           => $currency,
            'apiVersion'         => 1,
        ];
        $payload['merchantSignature'] = $this->signature($payload, [
            'merchantAccount','orderReference','amount','currency'
        ]);

        return Http::post('https://api.wayforpay.com/api', $payload)->json();
    }

    public function void(string $orderReference, float $amount, string $currency='UAH'): array
    {
        // Відміна холду (reverse)
        $payload = [
            'transactionType'    => 'REVERSE',
            'merchantAccount'    => config('services.w4p.account'),
            'orderReference'     => $orderReference,
            'amount'             => round($amount,2),
            'currency'           => $currency,
            'apiVersion'         => 1,
        ];
        $payload['merchantSignature'] = $this->signature($payload, [
            'merchantAccount','orderReference','amount','currency'
        ]);

        return Http::post('https://api.wayforpay.com/api', $payload)->json();
    }

    private function signature(array $data, array $order): string
    {
        $secret = config('services.w4p.key');

        $flat = [];
        foreach ($order as $key) {
            if (str_ends_with($key, '[]')) continue;
            if (isset($data[$key])) $flat[] = $data[$key];
        }

        // спеціальна частина для масивів productName/Count/Price згідно доки
        if (isset($data['productName'], $data['productCount'], $data['productPrice'])) {
            foreach ($data['productName'] as $v) $flat[] = $v;
            foreach ($data['productCount'] as $v) $flat[] = $v;
            foreach ($data['productPrice'] as $v) $flat[] = $v;
        }

        $str = implode(';', $flat);
        return hash_hmac('md5', $str, $secret);
    }
}
