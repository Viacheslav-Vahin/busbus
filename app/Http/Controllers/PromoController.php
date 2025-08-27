<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PromoCode;

class PromoController extends Controller
{
    public function check(Request $r)
    {
        $code     = trim((string)$r->query('code', ''));
        $subtotal = (float)$r->query('subtotal_uah', 0);

        if ($code === '' || $subtotal <= 0) {
            return ['ok' => false, 'discount_uah' => 0];
        }

        $promo = PromoCode::active()
            ->where('code', PromoCode::normalize($code))
            ->first();

        if (! $promo || ($promo->min_amount && $subtotal < (float)$promo->min_amount)) {
            return ['ok' => false, 'discount_uah' => 0];
        }

        $discount = $promo->type === 'percent'
            ? round($subtotal * ((float)$promo->value)/100, 2)
            : min(round((float)$promo->value, 2), $subtotal);

        return ['ok' => true, 'discount_uah' => $discount];
    }
}
