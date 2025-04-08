<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'bus_id' => 'required|exists:buses,id',
            'date' => 'required|date',
            'selected_seat' => 'required|integer',
            'seat_number' => 'required|integer',
        ]);

        $existingBooking = Booking::where('bus_id', $validatedData['bus_id'])
            ->where('seat_number', $validatedData['selected_seat'])
            ->whereDate('date', $validatedData['date'])
            ->first();

        if ($existingBooking) {
            return back()->withErrors(['selected_seat' => 'Це місце вже заброньовано. Будь ласка, виберіть інше.']);
        }

        Booking::create($validatedData);

        return redirect()->route('booking.index')->with('success', 'Бронювання створено успішно!');
    }
}
