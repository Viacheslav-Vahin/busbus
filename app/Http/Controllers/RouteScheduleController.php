<?php
// app/Http/Controllers/RouteScheduleController
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RouteSchedule;
use Illuminate\Support\Carbon;

class RouteScheduleController extends Controller
{
//    public function getBusesByDate(Request $request): \Illuminate\Http\JsonResponse
//    {
//        $buses = RouteSchedule::where('route_id', $request->route_id)
//            ->where('date', $request->date)
//            ->with('bus')
//            ->get();
//
//        return response()->json($buses);
//    }


    public function getBusesByDate(Request $request)
    {
        $routeId = $request->input('route_id');
        $date = $request->input('date');
        $weekday = \Carbon\Carbon::parse($date)->format('l'); // Наприклад "Tuesday"

        $buses = \App\Models\Bus::where('route_id', $routeId)
            ->get();

        $results = [];

        foreach ($buses as $bus) {
            // Витягуємо зупинки
            $boarding = \App\Models\BusStop::where('bus_id', $bus->id)->where('type', 'boarding')->first();
            $dropping = \App\Models\BusStop::where('bus_id', $bus->id)->where('type', 'dropping')->first();
            $startLocation = $boarding ? \App\Models\Stop::find($boarding->stop_id)->name : '';
            $endLocation = $dropping ? \App\Models\Stop::find($dropping->stop_id)->name : '';
            $departureTime = $boarding ? $boarding->time : '';
            $arrivalTime = $dropping ? $dropping->time : '';

            // Мінімальна ціна по місцях
//            $seatLayout = json_decode($bus->seat_layout, true);
            $seatLayout = $bus->seat_layout;
            $minPrice = collect($seatLayout)->where('type', 'seat')->pluck('price')->map(fn($v)=>intval($v))->min() ?? 0;

            // Вільні місця (краще порахувати заброньовані на цю дату і цей bus_id)
            $bookedSeats = \App\Models\Booking::where('bus_id', $bus->id)->where('date', $date)->count();
            $freeSeats = $bus->seats_count - $bookedSeats;

            $results[] = [
                'id' => $bus->id,
                'bus_name' => $bus->name,
                'start_location' => $startLocation,
                'end_location' => $endLocation,
                'departure_time' => $departureTime,
                'arrival_time' => $arrivalTime,
                'price' => $minPrice,
                'free_seats' => $freeSeats,
            ];
        }

        return response()->json($results);
    }
}

