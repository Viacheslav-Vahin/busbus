<?php
// app/Observers/TripObserver.php
namespace App\Observers;

use App\Models\Trip;
use App\Models\Booking;
use App\Services\Notify;
use Carbon\Carbon;

class TripObserver
{
    public function updated(Trip $trip): void
    {
        if (!$trip->isDirty(['departure_time'])) return;

        $dateFrom = Carbon::today()->toDateString();
        $affected = Booking::with(['route','user'])
            ->where('trip_id', $trip->id)
            ->whereDate('date', '>=', $dateFrom)
            ->get();

        foreach ($affected as $b) {
            $text = "Зміна розкладу: {$b->route_display}\n"
                . "Нове відправлення: ".Carbon::parse($b->date.' '.$trip->departure_time)->format('d.m.Y H:i');
            // e-mail
            \Illuminate\Support\Facades\Mail::raw($text, function ($m) use ($b) {
                if ($b->passengerEmail) $m->to($b->passengerEmail);
            });
            // смс/вайбер
            Notify::sms($b->passengerPhone, $text);
            Notify::viber($b->passengerPhone, $text);
        }
    }
}
