<?php
// app/Services/BusLayoutSyncer.php
namespace App\Services;

use App\Models\Bus;
use App\Models\BusSeat;
use App\Models\Route;
use App\Models\Trip;
use App\Models\SeatType;
use App\Models\BusLayoutElement;
use App\Services\Pricing\SeatPriceService;
use Illuminate\Support\Facades\DB;

class BusLayoutSyncer
{
    /** Базова ціна для автобуса: routes.ticket_price (якщо numeric) інакше trips.price */
    public static function basePriceFor(Bus $bus): ?float
    {
        $base = null;
        if ($bus->route_id) {
            $route = Route::find($bus->route_id);
            if ($route && is_numeric($route->ticket_price)) {
                $base = (float) $route->ticket_price;
            }
        }
        if ($base === null) {
            $trip = Trip::where('bus_id', $bus->id)->orderBy('id')->first();
            if ($trip) $base = (float) $trip->price;
        }
        return $base;
    }

    /** Імпорт сидінь із buses.seat_layout у bus_seats. $prune=true — видаляти відсутні */
    public static function importFromSeatLayout(Bus $bus, bool $prune = true): void
    {
        $layout = is_string($bus->seat_layout) ? json_decode($bus->seat_layout, true) : ($bus->seat_layout ?? []);
        $base = self::basePriceFor($bus);
        $seatNumbers = []; $elementHashes = [];

        DB::transaction(function () use ($bus, $layout, $base, $prune, &$seatNumbers, &$elementHashes) {
            foreach ($layout as $item) {
                $type = $item['type'] ?? 'seat';
                $x = (int)($item['column'] ?? 0);
                $y = (int)($item['row'] ?? 0);

                if ($type === 'seat') {
                    $num = (string)($item['number'] ?? '');
                    if ($num === '') continue;
                    $seatNumbers[] = $num;

                    // seat_type: code -> id
                    $code = $item['seat_type'] ?? null; // classic/recliner/panoramic
                    $seatTypeId = $code ? optional(SeatType::where('code',$code)->first())->id : null;

                    $seatPrice = isset($item['price']) && is_numeric($item['price']) ? (float)$item['price'] : null;
                    $modAbs = ($seatPrice !== null && $base !== null)
                        ? (abs($seatPrice - $base) < 0.01 ? null : round($seatPrice - $base, 2))
                        : null;

                    $seat = BusSeat::firstOrNew(['bus_id'=>$bus->id,'number'=>$num]);
                    $seat->fill([
                        'x'=>$x,'y'=>$y,
                        'seat_type_id'=>$seatTypeId,
                        'price_modifier_abs'=>$modAbs,
                        'is_active'=>true,
                        'meta'=>['ticket_category'=>$item['ticket_category'] ?? null],
                    ])->save();
                } else {
                    // службові елементи
                    $w = (int)($item['w'] ?? 1);
                    $h = (int)($item['h'] ?? 1);
                    $hash = "{$type}:{$x}:{$y}";
                    $elementHashes[] = $hash;

                    $el = BusLayoutElement::firstOrNew(['bus_id'=>$bus->id,'type'=>$type,'x'=>$x,'y'=>$y]);
                    $el->fill([
                        'w'=>$w,'h'=>$h,
                        'label'=>$item['label'] ?? null,
                        'meta'=>$item['meta'] ?? null,
                    ])->save();
                }
            }

            if ($prune) {
                BusSeat::where('bus_id',$bus->id)->whereNotIn('number', $seatNumbers ?: ['__none__'])->delete();
                BusLayoutElement::where('bus_id',$bus->id)->get()->each(function($el) use($elementHashes){
                    $hash = "{$el->type}:{$el->x}:{$el->y}";
                    if (!in_array($hash,$elementHashes,true)) $el->delete();
                });
            }

            $bus->seats_count = BusSeat::where('bus_id',$bus->id)->count();
            $bus->save();
        });
    }

    /** Експорт сидінь із bus_seats у buses.seat_layout (зберігаємо не-сидіння з існуючого JSON) */
    public static function exportToSeatLayout(Bus $bus): void
    {
        $base = self::basePriceFor($bus);
        $json = [];

        // службові
        foreach (BusLayoutElement::where('bus_id',$bus->id)->get() as $e) {
            $json[] = [
                'type'=>$e->type,
                'row'=>$e->y, 'column'=>$e->x,
                'w'=>$e->w, 'h'=>$e->h,
                'label'=>$e->label, 'meta'=>$e->meta, 'price'=>0,
            ];
        }

        // сидіння
        $seats = BusSeat::where('bus_id',$bus->id)->orderByRaw('CAST(number AS UNSIGNED)')->get();
        foreach ($seats as $s) {
            $price = $base !== null ? \App\Services\Pricing\SeatPriceService::compute($base, $s) : null;
            $json[] = [
                'type' => 'seat',
                'row'  => $s->y, 'column' => $s->x,
                'number' => $s->number,
                'ticket_category' => $s->meta['ticket_category'] ?? 'adult',
                'price' => $price !== null ? number_format($price, 2, '.', '') : null,
                'seat_type' => optional($s->seatType)->code, // classic/recliner/panoramic
            ];
        }

        $bus->seat_layout = $json;
        $bus->seats_count = $seats->count();
        $bus->save();
    }
}
