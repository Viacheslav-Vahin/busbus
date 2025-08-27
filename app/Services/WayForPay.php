<?php
namespace App\Services;

class WayForPay
{
    public static function signature(array $data, string $secret): string
    {
        $fields = [
            $data['merchantAccount'],
            $data['merchantDomainName'],
            $data['orderReference'],
            $data['orderDate'],
            $data['amount'],
            $data['currency'],
        ];

        foreach ($data['productName'] as $pn)   { $fields[] = $pn; }
        foreach ($data['productCount'] as $pc)  { $fields[] = $pc; }
        foreach ($data['productPrice'] as $pp)  { $fields[] = $pp; }

        return hash_hmac('md5', implode(';', $fields), $secret);
    }
}
