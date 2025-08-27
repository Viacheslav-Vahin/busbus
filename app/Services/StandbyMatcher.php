<?php

namespace App\Services;

use App\Models\{StandbyRequest, Booking, Trip};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class StandbyMatcher
{
    public function tryMatch(int $tripId, string $date): void
    {
        DB::transaction(function () use ($tripId, $date) {
            $queue = StandbyRequest::where('trip_id',$tripId)
                ->whereDate('date',$date)
                ->where('status','authorized')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            if ($queue->isEmpty()) return;

            [$freeSeats, $busId] = $this->freeSeats($tripId, $date);
            if (empty($freeSeats)) return;

            foreach ($queue as $sr) {
                $need = (int)$sr->seats_requested;
                if (count($freeSeats) < $need && !$sr->allow_partial) continue;

                $take  = min($need, count($freeSeats));
                $seats = array_splice($freeSeats, 0, $take);

                // Створимо броні (по твоїй моделі Booking)
                $bookingIds = [];
                $perSeatAmountUAH = round($sr->amount_uah / max($sr->seats_requested,1), 2);
                $perSeatAmount    = round($sr->amount     / max($sr->seats_requested,1), 2);

                foreach ($seats as $seat) {
                    $b = new Booking();
                    $b->trip_id       = $tripId;
                    $b->route_id      = $sr->route_id;
                    $b->bus_id        = $busId;
                    $b->date          = $date;
                    $b->seat_number   = (int)$seat;
                    $b->status        = 'pending';
                    $b->price_uah     = $perSeatAmountUAH;
                    $b->price         = $perSeatAmount;
                    $b->currency_code = $sr->currency_code;
                    $b->fx_rate       = $sr->fx_rate ?: 1;
                    $b->passengers    = json_encode([[
                        'first_name'   => $sr->name,
                        'last_name'    => $sr->surname,
                        'phone_number' => $sr->phone,
                        'email'        => $sr->email,
                    ]], JSON_UNESCAPED_UNICODE);
                    $b->ticket_uuid   = (string) Str::uuid();
                    $b->save();

                    $bookingIds[] = $b->id;
                }

                // CAPTURE стільки, скільки реально підібрали
                $captureAmount   = round($sr->amount * ($take / $sr->seats_requested), 2);
                $captureCurrency = $sr->currency_code;

                $resp = app(WayForPayStandby::class)->capture($sr->order_reference, $captureAmount, $captureCurrency);

                // WayForPay успіх зазвичай має reasonCode = 1100
                if ((int)($resp['reasonCode'] ?? 0) === 1100) {
                    foreach ($bookingIds as $id) {
                        $b = Booking::find($id);
                        $b->status = 'paid';
                        $b->paid_at = now();
                        $b->payment_method = 'wayforpay';
                        $b->save();

                        // PDF + QR
                        app(\App\Services\TicketService::class)->build($b);
                    }

                    $sr->status      = 'captured';
                    $sr->matched_at  = now();
                    $sr->captured_at = now();
                    $sr->booking_ids = $bookingIds;
                    $sr->save();

                    // TODO: якщо потрібно — відправ лист із квитками менеджеру/клієнту
                } else {
                    // Не вдалося захопити — відкотимо броні
                    Booking::whereIn('id',$bookingIds)->delete();
                    Log::warning('WayForPay capture failed for standby', ['sr'=>$sr->id, 'resp'=>$resp]);
                }

                if (empty($freeSeats)) break;
            }
        }, 5);
    }

    private function freeSeats(int $tripId, string $date): array
    {
        $trip = Trip::with('bus')->findOrFail($tripId);
        $bus  = $trip->bus;

        $all = collect($bus->seat_layout ?? [])
            ->filter(fn($s) => ($s['type'] ?? '') === 'seat')
            ->pluck('number')
            ->map(fn($n) => (string)$n)
            ->values()
            ->all();

        $locked = Booking::where('trip_id',$tripId)
            ->whereDate('date',$date)
            ->whereIn('status',['hold','pending','paid'])
            ->pluck('seat_number')
            ->map(fn($n)=>(string)$n)
            ->all();

        $free = array_values(array_diff($all, $locked));
        sort($free, SORT_NATURAL);
        return [$free, $bus->id];
    }
}
