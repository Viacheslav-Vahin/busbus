<?php
// app/Http/Controllers/DriverApiController.php
namespace App\Http\Controllers;

use App\Models\{Booking, DriverShift, CashboxTransaction, BoardingEvent, Bus, Route as BusRoute};
use Illuminate\Http\Request;

class DriverApiController extends Controller
{
    public function activeShift(Request $r)
    {
        $shift = DriverShift::where('driver_id', $r->user()->id)
            ->where('status','open')->latest()->first();

        return response()->json(['shift' => $shift]);
    }

    public function openShift(Request $r)
    {
        $data = $r->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'service_date' => 'required|date',
            'opening_cash' => 'nullable|numeric|min:0',
        ]);

        $shift = DriverShift::create([
            'driver_id' => $r->user()->id,
            'bus_id' => $data['bus_id'],
            'route_id' => $data['route_id'],
            'service_date' => $data['service_date'],
            'started_at' => now(),
            'opening_cash' => $data['opening_cash'] ?? 0,
            'status' => 'open',
        ]);

        return response()->json(['ok'=>true, 'shift'=>$shift]);
    }

    public function closeShift(Request $r)
    {
        $data = $r->validate([
            'shift_id' => 'required|exists:driver_shifts,id',
            'closing_cash' => 'required|numeric|min:0',
            'terminal_deposit' => 'nullable|numeric|min:0',
        ]);

        $shift = DriverShift::whereKey($data['shift_id'])
            ->where('driver_id',$r->user()->id)->firstOrFail();

        $shift->ended_at = now();
        $shift->terminal_deposit = $data['terminal_deposit'] ?? 0;
        $shift->status = 'closed';
        $shift->save();

        return response()->json(['ok'=>true, 'shift'=>$shift]);
    }

    // Перевірка QR (онлайн)
    public function verify(Request $r)
    {
        $r->validate(['qr' => 'required|string']);
        $payload = json_decode(base64_decode($r->input('qr')), true) ?: [];
        $uuid = (string)($payload['u'] ?? '');

        $booking = Booking::where('ticket_uuid', $uuid)->first();
        if (!$booking) return response()->json(['ok'=>false,'error'=>'not_found'], 404);

        // мінімальна відповідь:
        return response()->json([
            'ok'=>true,
            'booking'=>[
                'id' => $booking->id,
                'seat' => $booking->seat_number,
                'name' => $booking->passengers[0]['last_name'].' '.$booking->passengers[0]['first_name'],
                'status' => $booking->status,
                'paid' => (bool)$booking->paid_at,
                'price_uah' => $booking->price_uah,
                'currency' => $booking->currency_code,
                'price' => $booking->price,
            ]
        ]);
    }

    // Посадка/відмова
    public function boarding(Request $r)
    {
        $data = $r->validate([
            'shift_id' => 'required|exists:driver_shifts,id',
            'booking_id' => 'required|exists:bookings,id',
            'status' => 'required|in:boarded,denied,refunded',
            'lat' => 'nullable|numeric', 'lng' => 'nullable|numeric',
        ]);

        $booking = Booking::findOrFail($data['booking_id']);
        $shift   = DriverShift::whereKey($data['shift_id'])->where('driver_id',$r->user()->id)->firstOrFail();

        // прості правила
        if ($data['status']==='boarded') {
            $booking->boarded_at = now();
            $booking->boarded_by = $r->user()->id;
            if ($booking->status!=='paid') $booking->status = 'paid'; // якщо касою закрили – буде paid вже
            $booking->save();

            $shift->increment('passengers_boarded');
        }

        BoardingEvent::create([
            'shift_id' => $shift->id,
            'booking_id' => $booking->id,
            'ticket_uuid' => $booking->ticket_uuid,
            'boarded_at' => now(),
            'driver_id' => $r->user()->id,
            'status' => $data['status'],
            'payment_method' => $booking->payment_method ?? ($booking->paid_at ? 'paid_before' : null),
            'amount_uah' => $booking->price_uah,
            'amount' => $booking->price,
            'currency_code' => $booking->currency_code,
            'fx_rate' => $booking->fx_rate ?? 1,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
        ]);

        return response()->json(['ok'=>true]);
    }

    // Прийом готівки
    public function collect(Request $r)
    {
        $data = $r->validate([
            'shift_id' => 'required|exists:driver_shifts,id',
            'booking_id' => 'required|exists:bookings,id',
            'amount_uah' => 'required|numeric|min:0',
        ]);

        $shift   = DriverShift::whereKey($data['shift_id'])->where('driver_id',$r->user()->id)->firstOrFail();
        $booking = Booking::findOrFail($data['booking_id']);

        // фіксуємо оплату
        $booking->paid_at = $booking->paid_at ?: now();
        $booking->payment_method = 'cash';
        $booking->status = 'paid';
        $booking->save();

        CashboxTransaction::create([
            'shift_id' => $shift->id,
            'booking_id' => $booking->id,
            'driver_id' => $r->user()->id,
            'type' => 'collect_cash',
            'amount_uah' => $data['amount_uah'],
            'amount' => round($data['amount_uah'] * ($booking->fx_rate ?? 1), 2),
            'currency_code' => $booking->currency_code ?? 'UAH',
            'fx_rate' => $booking->fx_rate ?? 1,
        ]);

        $shift->increment('cash_collected', $data['amount_uah']);
        $shift->increment('tickets_count');

        return response()->json(['ok'=>true]);
    }

    // Список пасажирів (маніфест) по зміні
    public function manifest(Request $r)
    {
        $r->validate(['shift_id' => 'required|exists:driver_shifts,id']);
        $shift = DriverShift::whereKey($r->input('shift_id'))->where('driver_id',$r->user()->id)->firstOrFail();

        $items = Booking::query()
            ->where('bus_id', $shift->bus_id)
            ->whereDate('date', $shift->service_date)
            ->orderBy('seat_number')
            ->get(['id','seat_number','status','price_uah','ticket_uuid','passengers','paid_at']);

        return response()->json(['ok'=>true, 'manifest'=>$items]);
    }
}
