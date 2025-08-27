<?php
// app/Console/Commands/ImportSeatLayout.php
namespace App\Console\Commands;

use App\Models\Bus;
use App\Models\BusSeat;
use App\Models\Route;
use App\Models\Trip;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportSeatLayout extends Command
{
    protected $signature = 'seats:import {--bus=} {--dry}';
    protected $description = 'Імпорт seats із buses.seat_layout у bus_seats';

    public function handle()
    {
        $query = Bus::query();
        if ($this->option('bus')) {
            $query->whereKey($this->option('bus'));
        }

        $buses = $query->get();
        $count = 0;

        foreach ($buses as $bus) {
            if (empty($bus->seat_layout)) continue;
            $layout = is_string($bus->seat_layout) ? json_decode($bus->seat_layout, true) : $bus->seat_layout;
            if (!is_array($layout)) continue;

            // Спроба знайти base_price (спочатку з routes.ticket_price, інакше trips.price)
            $basePrice = null;
            if ($bus->route_id) {
                $route = Route::find($bus->route_id);
                if ($route && is_numeric($route->ticket_price)) {
                    $basePrice = (float) $route->ticket_price;
                }
            }
            if ($basePrice === null) {
                $trip = Trip::where('bus_id', $bus->id)->orderBy('id')->first();
                if ($trip) $basePrice = (float) $trip->price;
            }

            foreach ($layout as $item) {
                // Працюємо тільки з сидіннями
                if (($item['type'] ?? '') !== 'seat') continue;

                $number = (string) ($item['number'] ?? '');
                if ($number === '') continue;

                $x = isset($item['column']) && is_numeric($item['column']) ? (int)$item['column'] : null;
                $y = isset($item['row']) && is_numeric($item['row']) ? (int)$item['row'] : null;

                $seatPrice = null;
                if (isset($item['price']) && is_numeric($item['price'])) {
                    $seatPrice = (float) $item['price'];
                }

                $modifierAbs = null;
                if ($seatPrice !== null && $basePrice !== null) {
                    $modifierAbs = round($seatPrice - $basePrice, 2);
                    // якщо різниця майже 0 — вважаємо, що модифікатора немає
                    if (abs($modifierAbs) < 0.01) $modifierAbs = null;
                }

                $exists = BusSeat::where('bus_id', $bus->id)->where('number', $number)->exists();
                if ($exists) continue;

                $payload = [
                    'bus_id'             => $bus->id,
                    'number'             => $number,
                    'seat_type_id'       => null,            // за замовчуванням classic → залишаємо null, FE покаже "Класичне"
                    'x'                  => $x,
                    'y'                  => $y,
                    'price_modifier_abs' => $modifierAbs,
                    'price_modifier_pct' => null,
                    'is_active'          => true,
                    'meta'               => ['ticket_category' => $item['ticket_category'] ?? null],
                ];

                if ($this->option('dry')) {
                    $this->line('DRY: '.json_encode($payload, JSON_UNESCAPED_UNICODE));
                } else {
                    BusSeat::create($payload);
                    $count++;
                }
            }
        }

        $this->info(($this->option('dry') ? 'Перевірено' : 'Імпортовано') . " сидінь: {$count}");
        return self::SUCCESS;
    }
}
