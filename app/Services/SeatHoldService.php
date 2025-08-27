<?php
// app/Services/SeatHoldService.php
namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeatHoldService
{
    public function ttlSeconds(): int { return 8 * 60; } // 8 хвилин

    /**
     * Спробувати утримати (поставити на hold) місце.
     */
    public function hold(int $tripId, int $busId, string $seatNumber, string $currency, ?int $userId = null, array $meta = []): array
    {
        $key = "hold:trip:$tripId:bus:$busId:seat:$seatNumber";
        $token = (string) Str::uuid();
        $ttl = $this->ttlSeconds();

        // 1) Redis lock (ідеально)
        $ok = Cache::store('redis')->add($key, $token, $ttl);
        if (!$ok) {
            // якщо вже зайнято — пробуємо перевірити чи прострочено в БД
            $exists = DB::table('bookings')
                ->where(['trip_id'=>$tripId,'bus_id'=>$busId,'seat_number'=>$seatNumber])
                ->whereIn('status',['hold','pending','paid']) // pending/paid теж блокують
                ->where(function($q){ $q->whereNull('held_until')->orWhere('held_until','>',now()); })
                ->exists();
            if ($exists) return ['ok'=>false,'reason'=>'taken'];
        }

        // 2) Створюємо booking зі статусом hold
        $orderId = (string) Str::uuid();
        DB::table('bookings')->insert([
            'route_id' => null,
            'destination_id' => null,
            'trip_id' => $tripId,
            'bus_id' => $busId,
            'selected_seat' => $seatNumber,
            'passengers' => json_encode([]),
            'date' => now()->toDateString(),
            'user_id' => $userId,
            'seat_number' => (int) $seatNumber,
            'price' => 0,
            'additional_services' => json_encode($meta['services'] ?? []),
            'currency_code' => $currency,
            'status' => 'hold',
            'hold_token' => $token,
            'order_id' => $orderId,
            'held_until' => now()->addSeconds($ttl),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['ok'=>true,'token'=>$token,'order_id'=>$orderId,'expires_at'=>now()->addSeconds($ttl)];
    }

    /**
     * Подовжити hold (heartbeat).
     */
    public function prolong(string $token): bool
    {
        $row = DB::table('bookings')->where('hold_token',$token)->first();
        if (!$row) return false;

        $key = "hold:trip:{$row->trip_id}:bus:{$row->bus_id}:seat:{$row->seat_number}";
        Cache::store('redis')->put($key, $token, $this->ttlSeconds());
        DB::table('bookings')->where('hold_token',$token)->update(['held_until'=>now()->addSeconds($this->ttlSeconds())]);
        return true;
    }

    /**
     * Зняти hold (коли користувач скасовує).
     */
    public function release(string $token): void
    {
        $row = DB::table('bookings')->where('hold_token',$token)->first();
        if ($row) {
            $key = "hold:trip:{$row->trip_id}:bus:{$row->bus_id}:seat:{$row->seat_number}";
            Cache::store('redis')->forget($key);
            DB::table('bookings')->where('hold_token',$token)->update(['status'=>'expired','held_until'=>now()]);
        }
    }
}
