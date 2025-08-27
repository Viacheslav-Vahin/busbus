<?php
// app/Services/PaymentFinalizer.php
namespace App\Services;

use App\Models\{Trip, User, Booking};
use Illuminate\Support\Facades\{DB, Hash, Mail, Storage, Schema};
use Illuminate\Support\Str;
use App\Mail\TicketPaidMail;

class PaymentFinalizer
{
    /**
     * @param array{
     *  provider:string, order_reference:string, amount:float, currency:string,
     *  meta: array{trip_id:int,date:string,seats:int[],user:array{email:string,name?:string,surname?:string,phone?:string},promo_code?:string}
     * } $p
     */
    public function run(array $p): Booking
    {
        $trip = Trip::with('bus','route')->findOrFail($p['meta']['trip_id']);
        $bus  = $trip->bus;
        $seats = $p['meta']['seats'];

        // карта місця->ціна
        $seatMeta = collect($bus->seat_layout ?? [])
            ->filter(fn($s) => ($s['type'] ?? '') === 'seat' && isset($s['number']))
            ->keyBy(fn($s) => (int)$s['number']);

        $amountUAH = collect($seats)->sum(fn($n) => (float)($seatMeta[(int)$n]['price'] ?? 0));
        // якщо провайдер прислав іншу суму — можна логувати, але приймаємо провайдера як істину:
        $amountUAH = $p['amount'] ?? $amountUAH;

        // 1) idempotency: знайдемо бронювання по order_reference
        $existing = Booking::query()
            ->where('order_id', $p['order_reference'])
            ->orWhere('invoice_number', $p['order_reference'])
            ->orWhere('ticket_uuid', $p['order_reference']) // на випадок інших збережень
            ->first();
        if ($existing && $existing->status === 'paid') {
            return $existing; // уже все зроблено
        }

        // 2) користувач (або створюємо — як у dev)
        $u = User::firstOrCreate(
            ['email' => $p['meta']['user']['email']],
            [
                'name'     => $p['meta']['user']['name']    ?? 'Passenger',
                'surname'  => $p['meta']['user']['surname'] ?? '',
                'phone'    => $p['meta']['user']['phone']   ?? '',
                'password' => Hash::make(Str::random(12)),  // якщо не передавали пароль
            ]
        );
        // роль
        if (!$u->hasRole('passenger')) $u->assignRole('passenger');

        // 3) booking + items + payment + pdf + email
        return DB::transaction(function () use ($p, $trip, $bus, $u, $seats, $seatMeta, $amountUAH) {
            $firstSeat = (int)($seats[0] ?? 0);
            $ticketUuid = (string) Str::uuid();
            $ticketSerial = "MAX-".now()->format('Y')."-".str_pad((string)(DB::table('bookings')->count()+1), 6,'0', STR_PAD_LEFT);

            $bookingData = [
                'trip_id'       => $trip->id,
                'bus_id'        => $bus->id,
                'date'          => $p['meta']['date'],
                'user_id'       => $u->id,
                'status'        => 'paid',
                'paid_at'       => now(),
                'ticket_uuid'   => $ticketUuid,
                'ticket_serial' => $ticketSerial,
                'seat_number'   => $firstSeat,
                'selected_seat' => implode(',', $seats),
                'price'         => $amountUAH,
                'currency_code' => 'UAH',
                'order_id'      => $p['order_reference'],
                'promo_code'    => $p['meta']['promo_code'] ?? null,
                'payment_method'=> $p['provider'],
            ];
            // підкинемо route_id якщо є
            if (Schema::hasColumn('bookings','route_id') && isset($trip->route_id)) {
                $bookingData['route_id'] = $trip->route_id;
            }

            /** @var Booking $booking */
            $booking = Booking::create($bookingData);

            // booking_items (якщо є таблиця)
            if (Schema::hasTable('booking_items')) {
                foreach ($seats as $n) {
                    DB::table('booking_items')->insert([
                        'booking_id'  => $booking->id,
                        'seat_number' => (int)$n,
                        'price_uah'   => (float)($seatMeta[(int)$n]['price'] ?? 0),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            // payments (якщо є)
            if (Schema::hasTable('payments')) {
                DB::table('payments')->updateOrInsert(
                    ['order_reference' => $p['order_reference']],
                    [
                        'booking_id'    => $booking->id,
                        'provider'      => $p['provider'],
                        'amount'        => $amountUAH,
                        'currency_code' => 'UAH',
                        'status'        => 'paid',
                        'paid_at'       => now(),
                        'updated_at'    => now(),
                        'created_at'    => now(),
                    ]
                );
            }

            // 4) PDF квиток
            $pdfPath = $this->makeTicketPdf($booking);
            if (Schema::hasColumn('bookings','ticket_pdf_path')) {
                $booking->update(['ticket_pdf_path' => $pdfPath]);
            }

            // 5) e-mail з квитком
            Mail::to($booking->user->email)->queue(new TicketPaidMail($booking, $pdfPath));

            return $booking;
        });
    }

    private function makeTicketPdf(Booking $b): string
    {
        $routeLabel = ($b->trip->start_location && $b->trip->end_location)
            ? ($b->trip->start_location.' → '.$b->trip->end_location)
            : optional($b->trip->route)->title;

        $qrPng = \QrCode::format('png')->size(300)->generate(route('tickets.verify', $b->ticket_uuid));
        $qrPath = "tickets/qr/{$b->ticket_uuid}.png";
        Storage::put($qrPath, $qrPng);

        $pdf = \PDF::loadView('tickets.pdf', [
            'booking' => $b,
            'routeLabel' => $routeLabel,
            'qrPath' => Storage::path($qrPath),
        ])->setPaper('a4');

        $pdfPath = "tickets/pdf/{$b->ticket_serial}.pdf";
        Storage::put($pdfPath, $pdf->output());

        return $pdfPath; // storage/app/...
    }
}
