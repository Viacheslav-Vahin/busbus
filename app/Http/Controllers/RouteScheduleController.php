<?php
// app/Http/Controllers/RouteScheduleController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Route as BusRoute;
use App\Models\Bus;
use App\Models\Trip;
use App\Models\BusStop;
use App\Models\Stop;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RouteScheduleController extends Controller
{
    public function getBusesByDate(Request $request)
    {
        $routeId = $request->input('route_id');
        $date    = $request->input('date');

        $route  = BusRoute::findOrFail($routeId);
        $dateObj = Carbon::parse($date);

        // ті ж самі критерії: пара boarding/dropping за назвами зупинок
        $boardingBusIds = DB::table('bus_stops')
            ->join('stops', 'stops.id', '=', 'bus_stops.stop_id')
            ->where('bus_stops.type', 'boarding')
            ->where('stops.name', $route->start_point)
            ->pluck('bus_stops.bus_id');

        $droppingBusIds = DB::table('bus_stops')
            ->join('stops', 'stops.id', '=', 'bus_stops.stop_id')
            ->where('bus_stops.type', 'dropping')
            ->where('stops.name', $route->end_point)
            ->pluck('bus_stops.bus_id');

        $buses = Bus::whereIn('id', $boardingBusIds)
            ->whereIn('id', $droppingBusIds)
            ->get();

        $results = [];

        foreach ($buses as $bus) {
            if (!$this->busRunsOnDate($bus, $dateObj)) {
                continue;
            }

            $startStopId = optional(Stop::where('name', $route->start_point)->first())->id;
            $endStopId   = optional(Stop::where('name', $route->end_point)->first())->id;

            $startStop = $startStopId ? BusStop::where('bus_id', $bus->id)
                ->where('type', 'boarding')
                ->where('stop_id', $startStopId)
                ->first() : null;

            $endStop = $endStopId ? BusStop::where('bus_id', $bus->id)
                ->where('type', 'dropping')
                ->where('stop_id', $endStopId)
                ->first() : null;

            $trip = Trip::where('bus_id', $bus->id)
                ->where('start_location', $route->start_point)
                ->where('end_location', $route->end_point)
                ->first();

            $seatLayout = is_string($bus->seat_layout) ? json_decode($bus->seat_layout, true) : $bus->seat_layout;
            $minPrice = collect($seatLayout ?: [])
                ->where('type', 'seat')
                ->pluck('price')
                ->map(fn($v) => (float)$v)
                ->filter()
                ->min() ?? 0;

            $bookedSeats = Booking::where('bus_id', $bus->id)
                ->whereDate('date', $dateObj->toDateString())
                ->count();
            $freeSeats = max(0, ($bus->seats_count ?? 0) - $bookedSeats);

            $results[] = [
                'id'             => $bus->id,
                'trip_id'        => $trip->id ?? null,
                'bus_id'         => $bus->id,
                'bus_name'       => $bus->name,
                'start_location' => $route->start_point,
                'end_location'   => $route->end_point,
                'departure_time' => $startStop->time ?? null,
                'arrival_time'   => $endStop->time ?? null,
                'price'          => $minPrice,
                'free_seats'     => $freeSeats,
            ];
        }

        return response()->json($results);
    }

    private function busRunsOnDate(Bus $bus, Carbon $date): bool
    {
        $type = $bus->schedule_type ?? 'weekly';

        if ($type === 'daily') {
            return true;
        }

        if ($type === 'weekly') {
            $weekday = $date->format('l');
            $days = is_string($bus->weekly_operation_days)
                ? json_decode($bus->weekly_operation_days, true)
                : ($bus->weekly_operation_days ?? []);
            return in_array($weekday, $days ?? [], true);
        }

        if (method_exists($bus, 'schedules')) {
            return $bus->schedules()->whereDate('date', $date->toDateString())->exists();
        }

        return false;
    }
}
