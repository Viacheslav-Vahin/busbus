<?php
// app/Http/Controllers/TicketController.php
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;

class TicketController extends Controller
{
//    public function show(string $uuid)
//    {
//        $b = Booking::where('ticket_uuid', $uuid)->firstOrFail()->load(['bus','route','user','currency']);
//        return view('tickets.public-show', compact('b'));
//    }

    public function pdfByUuid(Request $request, string $uuid)
    {
        $booking = Booking::where('ticket_uuid', $uuid)->firstOrFail();
        return $this->sendBookingPdf($request, $booking);
    }

    public function pdfById(Request $request, int $id)
    {
        $booking = Booking::findOrFail($id);
        return $this->sendBookingPdf($request, $booking);
    }

    private function sendBookingPdf(Request $request, Booking $booking)
    {
        // fallback: проставити uuid, якщо раптом порожній у старих записах
        if (empty($booking->ticket_uuid)) {
            $booking->ticket_uuid = (string) Str::uuid();
            $booking->save();
        }

        // якщо PDF ще не згенеровано або файл відсутній — будуємо
        if (! $booking->ticket_pdf_path || ! Storage::disk('public')->exists($booking->ticket_pdf_path)) {
            app(TicketService::class)->build($booking);
            $booking->refresh();
        }

        if (! $booking->ticket_pdf_path || ! Storage::disk('public')->exists($booking->ticket_pdf_path)) {
            abort(404, 'Ticket PDF not found');
        }

        $absolutePath = Storage::disk('public')->path($booking->ticket_pdf_path);
        $filename = 'ticket_'.$booking->ticket_serial.'.pdf';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->file($absolutePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, no-cache',
        ]);
    }
    public function show(string $uuid, TicketService $tickets): StreamedResponse
    {
        $booking = Booking::where('ticket_uuid', $uuid)->firstOrFail();

        // Переконаймося, що актуальна ревізія згенерована
        [$qrPath, $pdfPath] = $tickets->build($booking);

        abort_unless(Storage::disk('public')->exists($pdfPath), 404);

        // Віддаємо файл з public-диска
        return Storage::disk('public')->download($pdfPath, $uuid.'.pdf');
    }

    public function scanner() // проста сторінка з input або веб-камерою (пізніше)
    {
        return view('tickets.scanner');
    }

//    public function checkin(Request $req, string $uuid)
//    {
//        $b = Booking::where('ticket_uuid', $uuid)->firstOrFail();
//
//        if ($b->checked_in_at) {
//            return response()->json(['ok' => false, 'message' => 'Квиток вже відмічений'], 409);
//        }
//        if ($b->status !== 'paid') {
//            return response()->json(['ok' => false, 'message' => 'Квиток не оплачений'], 422);
//        }
//
//        $b->checked_in_at = now();
//        $b->checked_in_by = $req->user()->id;
//        $b->checkin_place = $req->input('place');
//        $b->save();
//
//        return response()->json(['ok' => true, 'message' => 'OK, пасажира посаджено']);
//    }
    public function checkin(Request $req, string $uuid)
    {
        $b = Booking::where('ticket_uuid', $uuid)->first();

        if (!$b) {
            return response()->json(['ok' => false, 'message' => 'Квиток не знайдено'], 404);
        }

        if ($b->status !== 'paid') {
            return response()->json([
                'ok' => false,
                'message' => 'Квиток не оплачений',
                'booking' => [
                    'id'    => $b->id,
                    'seat'  => $b->seat_number,
                    'name'  => $b->passengers[0]['first_name'].' '.$b->passengers[0]['last_name'],
                    'route' => $b->route->name ?? ($b->bus->name ?? null),
                    'date'  => $b->date,
                ],
            ], 422);
        }

        if ($b->checked_in_at) {
            return response()->json([
                'ok' => false,
                'message' => 'Квиток вже відмічений',
                'booking' => [
                    'id'    => $b->id,
                    'seat'  => $b->seat_number,
                    'name'  => $b->passengers[0]['first_name'].' '.$b->passengers[0]['last_name'],
                    'route' => $b->route->name ?? ($b->bus->name ?? null),
                    'date'  => $b->date,
                    'checked_in_at' => $b->checked_in_at,
                ],
            ], 409);
        }

        $b->checked_in_at = now();
        $b->checked_in_by = $req->user()->id ?? null;
        $b->checkin_place = $req->input('place');
        $b->save();

        return response()->json([
            'ok' => true,
            'message' => 'OK, пасажира посаджено',
            'booking' => [
                'id'    => $b->id,
                'seat'  => $b->seat_number,
                'name'  => $b->passengers[0]['first_name'].' '.$b->passengers[0]['last_name'],
                'route' => $b->route->name ?? ($b->bus->name ?? null),
                'date'  => $b->date,
            ],
        ]);
    }
}
