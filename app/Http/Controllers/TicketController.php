<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function generateTicket($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        $pdf = Pdf::loadView('tickets.ticket', compact('booking'));
        return $pdf->download('ticket_' . $booking->id . '.pdf');
    }
}
