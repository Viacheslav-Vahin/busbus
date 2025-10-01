<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Bus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BookingRuntimeController extends Controller
{
    private int $ttlSeconds = 600; // 10 хв тримаємо HOLD

    /* ------------ Keys ------------ */
    private function idxKey(int $busId, string $date): string
    {
        return "holdindex:bus:$busId:$date"; // список токенів по дню/автобусу
    }
    private function tokenKey(string $token): string
    {
        return "holdtoken:$token"; // дані по конкретному токену
    }

    /* ------------ Helpers ------------ */

    private function arr($v): array
    {
        if (is_array($v)) return $v;
        if (is_string($v)) {
            $d = json_decode($v, true);
            return is_array($d) ? $d : [];
        }
        return [];
    }

    private function normalizeLayout($layoutRaw): array
    {
        $layout = $this->arr($layoutRaw);
        // гарантуємо, що поле type/number приведені
        return array_map(function ($x) {
            if (!is_array($x)) return $x;
            $x['type']   = strtolower((string)($x['type'] ?? ''));
            $x['number'] = isset($x['number']) ? (string)$x['number'] : null;
            return $x;
        }, $layout);
    }

    private function seatNumbersFromLayout(array $layout): array
    {
        return collect($layout)
            ->where('type', 'seat')
            ->pluck('number')
            ->filter()
            ->map(fn($n) => (string)$n)
            ->values()
            ->all();
    }

    /** Витягнути заброньовані місця з моделі Booking (підтримує різні назви полів) */
    private function bookedSeats(int $busId, string $date): array
    {
        $rows = Booking::where('bus_id', $busId)
            ->whereDate('date', $date)
            ->get();

        $res = [];
        foreach ($rows as $row) {
            $a = $row->toArray();

            // можливі варіанти:
            // - seat_numbers (array/json)
            // - seats (array/json)
            // - seat_number / seat (scalar)
            foreach (['seat_numbers', 'seats'] as $k) {
                if (array_key_exists($k, $a)) {
                    $vals = $this->arr($a[$k]);
                    foreach ($vals as $v) {
                        if (is_array($v) && isset($v['seat'])) $res[] = (string)$v['seat'];
                        else $res[] = (string)$v;
                    }
                }
            }
            foreach (['seat_number', 'seat', 'seat_no'] as $k) {
                if (!empty($a[$k])) $res[] = (string)$a[$k];
            }
        }

        return array_values(array_unique(array_map('strval', $res)));
    }

    /** Усі актуальні HELD місця по bus+date */
    private function heldSeats(int $busId, string $date): array
    {
        $idxKey  = $this->idxKey($busId, $date);
        $tokens  = Cache::get($idxKey, []);
        $tokens  = is_array($tokens) ? $tokens : [];

        $held = [];
        $changed = false;

        foreach ($tokens as $i => $token) {
            $payload = Cache::get($this->tokenKey($token));
            if (!$payload) {
                // токен протух — вичистимо з індексу
                unset($tokens[$i]);
                $changed = true;
                continue;
            }
            if ((int)$payload['bus_id'] !== $busId || $payload['date'] !== $date) {
                continue;
            }
            $held = array_merge($held, array_map('strval', $payload['seats'] ?? []));
        }

        if ($changed) {
            Cache::put($idxKey, array_values($tokens), now()->addMinutes(24 * 60));
        }

        return array_values(array_unique($held));
    }

    private function validateDate(string $date): string
    {
        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            abort(422, 'Invalid date');
        }
    }

    /* ------------ Endpoints ------------ */

    // GET /api/trips/{id}/bus-info?date=YYYY-MM-DD
    public function busInfo($id, Request $r)
    {
        $date = $this->validateDate((string)$r->query('date', now()->toDateString()));
        $bus  = Bus::findOrFail((int)$id);

        $layout = $this->normalizeLayout($bus->seat_layout);
        $booked = $this->bookedSeats((int)$bus->id, $date);
        $held   = $this->heldSeats((int)$bus->id, $date);

        return response()->json([
            'bus' => [
                'id'          => $bus->id,
                'name'        => $bus->name,
                'seat_layout' => $layout,
            ],
            'booked_seats' => $booked,
            'held_seats'   => $held,
        ]);
    }

    // GET /api/trips/{id}/seats?date=YYYY-MM-DD
    public function seats($id, Request $r)
    {
        $date = $this->validateDate((string)$r->query('date', now()->toDateString()));
        $bus  = Bus::findOrFail((int)$id);

        return response()->json([
            'booked_seats' => $this->bookedSeats((int)$bus->id, $date),
            'held_seats'   => $this->heldSeats((int)$bus->id, $date),
        ]);
    }

    // POST /api/trips/{id}/hold  { date, seats:[], token?, solo? }
    public function hold($id, Request $r)
    {
        $bus  = Bus::findOrFail((int)$id);
        $date = $this->validateDate((string)$r->input('date'));

        $layout   = $this->normalizeLayout($bus->seat_layout);
        $allSeats = $this->seatNumbersFromLayout($layout);

        $seatsReq = array_values(array_unique(array_map('strval', (array)$r->input('seats', []))));
        if (empty($seatsReq)) {
            return response()->json(['message' => 'No seats provided'], 422);
        }

        // валідність місць
        $bad = array_values(array_diff($seatsReq, $allSeats));
        if (!empty($bad)) {
            return response()->json(['message' => 'Unknown seat(s)', 'invalid' => $bad], 422);
        }

        // конфлікти
        $booked = $this->bookedSeats((int)$bus->id, $date);
        $held   = $this->heldSeats((int)$bus->id, $date);

        $conflicts = array_values(array_intersect($seatsReq, array_unique(array_merge($booked, $held))));
        if (!empty($conflicts) && !$r->filled('token')) {
            return response()->json(['message' => 'Seat(s) already taken', 'held_seats' => $held, 'booked_seats' => $booked], 409);
        }

        // існуючий або новий токен
        $token = (string)$r->input('token') ?: Str::uuid()->toString();
        $payload = [
            'token'   => $token,
            'bus_id'  => (int)$bus->id,
            'date'    => $date,
            'seats'   => $seatsReq,
            'solo'    => (bool)$r->boolean('solo', false),
        ];

        // зберігаємо сам токен
        Cache::put($this->tokenKey($token), $payload, now()->addSeconds($this->ttlSeconds));

        // додаємо в індекс дня/автобуса
        $idxKey = $this->idxKey((int)$bus->id, $date);
        $idx = Cache::get($idxKey, []);
        $idx = is_array($idx) ? $idx : [];
        if (!in_array($token, $idx, true)) {
            $idx[] = $token;
            Cache::put($idxKey, $idx, now()->addHours(24));
        }

        // відповідаємо
        return response()->json([
            'token'       => $token,
            'ttl_seconds' => $this->ttlSeconds,
            'expires_at'  => now()->addSeconds($this->ttlSeconds)->toIso8601String(),
            'held_seats'  => $this->heldSeats((int)$bus->id, $date),
        ]);
    }

    // POST /api/hold/prolong { token }
    public function prolong(Request $r)
    {
        $token = (string)$r->input('token');
        if (!$token) return response()->json(['message' => 'Token required'], 422);

        $tKey = $this->tokenKey($token);
        $payload = Cache::get($tKey);
        if (!$payload) return response()->json(['message' => 'Token not found'], 404);

        Cache::put($tKey, $payload, now()->addSeconds($this->ttlSeconds));

        return response()->json([
            'token'       => $token,
            'ttl_seconds' => $this->ttlSeconds,
            'expires_at'  => now()->addSeconds($this->ttlSeconds)->toIso8601String(),
        ]);
    }

    // POST /api/hold/release { token }
    public function release(Request $r)
    {
        $token = (string)$r->input('token');
        if (!$token) return response()->json(['message' => 'Token required'], 422);

        $payload = Cache::get($this->tokenKey($token));
        if ($payload) {
            Cache::forget($this->tokenKey($token));
            // прибираємо з індексу
            $idxKey = $this->idxKey((int)$payload['bus_id'], (string)$payload['date']);
            $idx = Cache::get($idxKey, []);
            if (is_array($idx)) {
                $idx = array_values(array_filter($idx, fn($t) => $t !== $token));
                Cache::put($idxKey, $idx, now()->addHours(24));
            }
        }

        return response()->json(['ok' => true]);
    }
}
