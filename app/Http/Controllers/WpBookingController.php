<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WpBookingClean;
use Illuminate\Http\Request;

class WpBookingController extends Controller
{
    public function index(Request $request)
    {
        $q     = (string) $request->input('q');
        $status= (string) $request->input('status');
        $type  = (string) $request->input('booker_type'); // self|third_party|manager
        $from  = (string) $request->input('from');        // YYYY-MM-DD
        $to    = (string) $request->input('to');          // YYYY-MM-DD
        $dateBy= (string) $request->input('date_by', 'booking_date'); // booking_date|start_time

        $bookings = WpBookingClean::query()
            ->when($q, fn($qq) => $qq->search($q))
            ->status($status ?: null)
            ->bookerType($type ?: null)
            ->dateBetween($from ?: null, $to ?: null, in_array($dateBy, ['booking_date','start_time']) ? $dateBy : 'booking_date')
            ->orderByDesc('booking_date')
            ->paginate(50)
            ->withQueryString();

        // Для селектів у фільтрах
        $statuses = WpBookingClean::query()
            ->select('order_status')->whereNotNull('order_status')
            ->distinct()->orderBy('order_status')->pluck('order_status');

        return view('admin.wp_bookings.index', compact('bookings','statuses','q','status','type','from','to','dateBy'));
    }

    public function show(int $id)
    {
        $b = WpBookingClean::query()->findOrFail($id);
        return view('admin.wp_bookings.show', compact('b'));
    }
}
