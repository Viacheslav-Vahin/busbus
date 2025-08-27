<?php

namespace App\Http\Controllers;

use App\Models\{Trip, User, Booking};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DevPaymentsController extends Controller
{
    public function mockPaid(Request $r)
    {
        // На проді заборонено
        if (app()->environment('production')) {
            abort(403, 'DEV mock is disabled on production.');
        }

        $data = $r->validate([
            'order_reference' => 'nullable|string',
            'trip_id'         => 'required|exists:trips,id',
            'date'            => 'required|date_format:Y-m-d',
            'seats'           => 'required|array|min:1',
            'seats.*'         => 'integer|min:1',
            'passengers'      => 'array',
            'passengers.*.seat'       => 'integer',
            'passengers.*.first_name' => 'nullable|string|max:120',
            'passengers.*.last_name'  => 'nullable|string|max:120',
            'passengers.*.doc_number' => 'nullable|string|max:120',
            'passengers.*.category'   => 'nullable|string',
            'passengers.*.extras'     => 'array',
            'promo_code'      => 'nullable|string|max:255',
            'discount_amount' => 'nullable|numeric',
            'currency_code'   => 'nullable|string|in:UAH,EUR,PLN',
            'user.name'       => 'required|string|max:120',
            'user.surname'    => 'required|string|max:120',
            'user.email'      => 'required|email',
            'user.phone'      => 'required|string|max:32',
            'user.password'   => 'required|string|min:6',
        ]);

        $trip = Trip::with(['bus','route'])->findOrFail($data['trip_id']);
        $bus  = $trip->bus;

        // Тексти для прев’ю
        $routeLabel = ($trip->start_location && $trip->end_location)
            ? ($trip->start_location.' → '.$trip->end_location)
            : ($trip->route->title ?? $trip->route->name ?? 'Маршрут');

        $busName = $bus->name ?? $bus->title ?? $bus->number ?? 'Автобус';

        // мапа номер->мета (ціна)
        $seatMeta = collect($bus->seat_layout ?? [])
            ->filter(fn($s) => ($s['type'] ?? '') === 'seat' && isset($s['number']))
            ->keyBy(fn($s) => (int)$s['number']);

        // сума по місцях
        $amountUAH = collect($data['seats'])->sum(fn($n) => (float)($seatMeta[(int)$n]['price'] ?? 0));
        $amountUAH = round($amountUAH, 2);

        // знижка (якщо передали прев’ю промокоду з фронта)
        $discount = isset($data['discount_amount']) ? (float)$data['discount_amount'] : 0.0;
        $totalUAH = max(0, $amountUAH - $discount);

        $u = User::firstOrCreate(
            ['email' => $data['user']['email']],
            [
                'name'     => $data['user']['name'],
                'surname'  => $data['user']['surname'],
                'phone'    => $data['user']['phone'],
                'password' => Hash::make($data['user']['password']),
            ]
        );

        $orderRef     = $data['order_reference'] ?: (string) Str::uuid(); // під вашу колонку order_id
        $ticketUuid   = (string) Str::uuid();
        $ticketSerial = $this->makeTicketSerial();
        $firstSeat    = (int) ($data['seats'][0] ?? 0);
        $currency     = $data['currency_code'] ?? 'UAH';

        // passengers JSON (якщо не прислали – зробимо мінімальний)
        $passengers = collect($data['passengers'] ?? [])->map(function($p){
            return [
                'seat'       => (int)($p['seat'] ?? 0),
                'first_name' => $p['first_name'] ?? '',
                'last_name'  => $p['last_name'] ?? '',
                'doc_number' => $p['doc_number'] ?? '',
                'category'   => $p['category'] ?? 'adult',
                'extras'     => array_values($p['extras'] ?? []),
            ];
        })->values()->all();

        // additional_services: зберемо всі обрані extras
        $extrasBySeat = [];
        foreach ($passengers as $p) {
            if (!empty($p['seat'])) {
                $extrasBySeat[$p['seat']] = $p['extras'] ?? [];
            }
        }

        // pricing – легкий брейкдаун
        $pricing = [
            'seats'      => array_map('intval', $data['seats']),
            'subtotal'   => $amountUAH,
            'discount'   => $discount,
            'total'      => $totalUAH,
            'currency'   => $currency,
        ];

        DB::beginTransaction();
        try {
            /** @var Booking $booking */
            $booking = Booking::create([
                'trip_id'       => $trip->id,
                'bus_id'        => $bus->id,
                'route_id'      => $trip->route_id ?? null,         // <- щоб у списку не було N/A
                'date'          => $data['date'],
                'user_id'       => $u->id,

                'status'        => 'paid',
                'paid_at'       => now(),
                'payment_method'=> 'dev',                           // видно в адмінці

                // суми / валюта під вашу схему
                'price'         => $totalUAH,                       // головне поле з дампа
                'price_uah'     => $totalUAH,                       // якщо використовуєте — теж заповнимо
                'currency_code' => $currency,
                'discount_amount'=> $discount,
                'promo_code'    => $data['promo_code'] ?? null,

                // місця
                'seat_number'   => $firstSeat,                      // required NOT NULL
                'selected_seat' => implode(',', array_map('intval', $data['seats'])),

                // довідкове
                'ticket_uuid'   => $ticketUuid,
                'ticket_serial' => $ticketSerial,
                'order_id'      => $orderRef,                       // у вас саме order_id (char36)
                'passengers'    => $passengers ? json_encode($passengers) : null,
                'additional_services' => $extrasBySeat ? json_encode($extrasBySeat) : null,
                'pricing'       => json_encode($pricing),
            ]);

            DB::commit();

            return response()->json([
                'ok'              => true,
                'booking_id'      => $booking->id,
                'order_reference' => $orderRef,
                'amount_uah'      => $totalUAH,
                'currency_code'   => $currency,

                // Прев’ю для фронта
                'ticket_preview'  => [
                    'route'     => $routeLabel,
                    'bus'       => $busName,
                    'date'      => $data['date'],
                    'seats'     => array_map('intval', $data['seats']),
                    'name'      => $data['user']['name'],
                    'surname'   => $data['user']['surname'],
                    'price'     => $totalUAH,
                    'price_uah' => $totalUAH,
                    'currency'  => $currency,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 500);
        }
    }

    private function makeTicketSerial(): string
    {
        // MAX-YYYY-000123 (простий лічильник для DEV)
        $year = now()->format('Y');
        $seq  = str_pad((string) (DB::table('bookings')->count() + 1), 6, '0', STR_PAD_LEFT);
        return "MAX-$year-$seq";
    }
}
