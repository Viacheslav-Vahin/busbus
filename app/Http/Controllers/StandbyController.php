<?php

namespace App\Http\Controllers;

use App\Models\{StandbyRequest, Trip};
use App\Services\{WayForPayStandby, StandbyMatcher};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB as DB;

class StandbyController extends Controller
{
    /**
     * Створити заявку та віддати HTML форми WayForPay (preauth)
     */
    public function start(Request $r, WayForPayStandby $w4p)
    {
        $data = $r->validate([
            'trip_id'       => 'required|exists:trips,id',
            'date'          => 'required|date_format:Y-m-d',
            'seats'         => 'required|integer|min:1|max:6',
            'allow_partial' => 'boolean',
            'name'          => 'nullable|string|max:120',
            'surname'       => 'nullable|string|max:120',
            'email'         => 'nullable|email',
            'phone'         => 'nullable|string|max:32',
            'currency_code' => 'nullable|string|in:UAH,EUR,PLN',
        ]);

        // Беремо конкретний Trip. Тут часи — TIME.
        $trip = Trip::with(['bus'])->findOrFail($data['trip_id']);

        // Якщо маєш прямий route_id у Trip/Bus — підтяни; якщо ні — залиш null
        $routeId = $trip->route_id ?? optional($trip->bus)->route_id ?? null;

        $currency     = $data['currency_code'] ?? 'UAH';
        $seatPriceUAH = $this->seatPriceUAH($trip, $data['date']);
        $amountUAH    = round($seatPriceUAH * $data['seats'], 2);

        $fx     = $this->fxRate($currency); // 1 для UAH
        $amount = $currency === 'UAH' ? $amountUAH : round($amountUAH / max($fx, 0.000001), 2);

        $orderRef = 'SB-' . Str::uuid();

        // ВАЖЛИВО: у trips — TIME; склеюємо дату з фронта + час з Trip
        $depTime     = $this->hms((string) $trip->departure_time);       // 'HH:MM:SS'
        $departureAt = Carbon::createFromFormat('Y-m-d H:i:s', "{$data['date']} {$depTime}");

        // Перевіряємо лише дату (час беремо з Trip)
        if ($departureAt->toDateString() !== $data['date']) {
            return response()->json([
                'message' => 'Обрана дата ' . $data['date'] . ' не відповідає даті рейсу',
            ], 422);
        }

        // Дедлайн/TTL: T−12 до відправлення
        $releaseAt  = $departureAt->copy()->subHours(12);
        $ttlSeconds = max(0, $releaseAt->diffInSeconds(now()));

        $sr = StandbyRequest::create([
            'trip_id'         => $trip->id,
            'route_id'        => $routeId,
            'date'            => $data['date'],
            'seats_requested' => $data['seats'],
            'allow_partial'   => (bool)($data['allow_partial'] ?? false),
            'name'            => $data['name'] ?? null,
            'surname'         => $data['surname'] ?? null,
            'email'           => $data['email'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'amount'          => $amount,
            'currency_code'   => $currency,
            'amount_uah'      => $amountUAH,
            'fx_rate'         => $fx,
            'order_reference' => $orderRef,
            'status'          => 'pending',
            // не перераховуємо ще раз — беремо вже порахований T−12
            'wait_until'      => $releaseAt,
        ]);

        // HTML форми пред-авторизації
        $formHtml = $w4p->buildPreauthForm([
            'orderReference' => $orderRef,
            'amount'         => $amount,
            'currency'       => $currency,
            'productName'    => ['Standby seats x' . $data['seats']],
            'productPrice'   => [$amount],
            'productCount'   => [1],
            'clientEmail'    => $sr->email,
            'clientPhone'    => $sr->phone,
        ]);

        return response()->json([
            'ok'              => true,
            'payment_form'    => $formHtml,
            'order_reference' => $orderRef,
            'release_at'      => $releaseAt->toIso8601String(),
            'ttl_seconds'     => $ttlSeconds,
        ]);
    }

    /**
     * Вебхук WayForPay для standby (сервісний URL)
     */
    public function webhook(Request $r, StandbyMatcher $matcher)
    {
        // Перевірку підпису робиш як у своєму основному WayForPay webhook.
        $orderRef = (string) $r->input('orderReference');
        $status   = (string) $r->input('transactionStatus'); // 'Approved'/'Holded'/...
        $invoiceId= $r->input('invoiceId');
        $authCode = $r->input('authCode');

        $sr = StandbyRequest::where('order_reference', $orderRef)->first();
        if (!$sr) return response('not found', 404);

        if (in_array($status, ['Approved', 'Holded', 'PurchaseComplete'], true)) {
            $sr->status        = 'authorized';
            $sr->authorized_at = now();
            if ($invoiceId) $sr->w4p_invoice_id = $invoiceId;
            if ($authCode)  $sr->w4p_auth_code  = $authCode;
            $sr->save();

            // $sr->date може бути string — парсимо явно
            $matcher->tryMatch($sr->trip_id, Carbon::parse($sr->date)->toDateString());
        } else {
            $sr->status = 'cancelled';
            $sr->save();
        }

        return response('OK');
    }

    /**
     * Ручне скасування (поки гроші на hold)
     */
    public function cancel(string $orderReference, WayForPayStandby $w4p)
    {
        $sr = StandbyRequest::where('order_reference', $orderReference)
            ->whereIn('status', ['pending', 'authorized'])
            ->firstOrFail();

        if ($sr->status === 'authorized') {
            $w4p->void($sr->order_reference, (float) $sr->amount, $sr->currency_code);
            $sr->status    = 'voided';
            $sr->voided_at = now();
        } else {
            $sr->status = 'cancelled';
        }
        $sr->save();

        return ['ok' => true];
    }

    /**
     * Порахувати тариф у гривні за 1 місце.
     * Найпростіше: мінімальна ціна серед сидінь у seat_layout автобуса.
     */
    private function seatPriceUAH(Trip $trip, string $date): float
    {
        $trip->loadMissing('bus');
        $layout = collect($trip->bus->seat_layout ?? [])
            ->filter(fn($s) => ($s['type'] ?? '') === 'seat');

        $prices = $layout->pluck('price')->filter()->map(fn($p) => (float) $p)->all();
        return !empty($prices) ? (float) min($prices) : 2100.00;
    }

    /**
     * Отримати FX-курс
     */
    private function fxRate(string $code): float
    {
        if ($code === 'UAH') return 1.0;
        $row = DB::table('currencies')->where('code', $code)->first();
        return $row ? (float) $row->rate : 1.0;
    }

    /**
     * Безпечно порахувати wait-until (T−12) від дати + часу рейсу.
     * Якщо час у Trip — TIME або повний datetime, дістанемо лише H:i:s.
     */
    private function computeWaitUntil(Trip $trip, string $date): Carbon
    {
        $raw = $trip->departure_time ?? $trip->departure_at ?? $trip->start_time ?? null;

        if ($raw instanceof \DateTimeInterface) {
            $hms = Carbon::instance($raw)->format('H:i:s');
        } else {
            $str = (string) $raw;
            // Якщо прийшов повний datetime — витягнемо лише час
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $str)) {
                $hms = Carbon::parse($str)->format('H:i:s');
            } elseif (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $str)) {
                $hms = strlen($str) === 5 ? $str . ':00' : $str;
            } else {
                $hms = '12:00:00';
            }
        }

        $dt        = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$hms}");
        $tMinus12  = $dt->copy()->subHours(12);
        $maxHold   = now()->addDays(7);

        return $tMinus12->lessThan($maxHold) ? $tMinus12 : $maxHold;
    }

    /**
     * Нормалізація 'HH:MM' -> 'HH:MM:SS'
     */
    private function hms(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }
}
