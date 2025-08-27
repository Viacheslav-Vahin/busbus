<?php
// app/Services/SeatNeighborService.php
namespace App\Services;

use App\Models\BusSeat;

class SeatNeighborService
{
    /**
     * Повертає сусідній номер місця або null.
     */
    public function neighborFor(int $busId, string $seatNumber): ?string
    {
        $s = BusSeat::where(['bus_id'=>$busId,'number'=>$seatNumber])->first();
        if (!$s) return null;

        // Найчастіший кейс: пара через +1/-1 по X в одному Y
        $right = BusSeat::where('bus_id',$busId)->where('x',$s->x+1)->where('y',$s->y)->first();
        if ($right) return $right->number;

        $left  = BusSeat::where('bus_id',$busId)->where('x',$s->x-1)->where('y',$s->y)->first();
        if ($left) return $left->number;

        return null;
    }
}
