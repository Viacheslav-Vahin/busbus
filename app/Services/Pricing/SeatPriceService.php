<?php
// app/Services/Pricing/SeatPriceService.php
namespace App\Services\Pricing;
use App\Models\BusSeat;

class SeatPriceService {
    /**
     * @param float $basePrice — базова ціна рейсу/маршруту
     */
    public static function compute(float $basePrice, BusSeat $seat): float {
        $price = $basePrice;
        if ($type = $seat->seatType) {
            if ($type->modifier_type === 'percent') {
                $price += $basePrice * ($type->modifier_value/100);
            } else {
                $price += $type->modifier_value;
            }
        }
        if (!is_null($seat->price_modifier_abs)) {
            $price += $seat->price_modifier_abs;
        }
        if (!is_null($seat->price_modifier_pct)) {
            $price += $basePrice * ($seat->price_modifier_pct/100);
        }
        return round(max($price, 0), 2);
    }
}
