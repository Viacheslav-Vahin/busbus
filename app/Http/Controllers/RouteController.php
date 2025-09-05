<?php
// app/Http/Controllers/RouteController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Route as BusRoute;
use App\Models\Bus;
use App\Models\Trip;
use App\Models\BusStop;
use App\Models\Stop;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RouteController extends Controller
{
    public function index()
    {
        $routes = BusRoute::all();
        return view('admin.routes.index', compact('routes'));
    }

    /**
     * API: всі маршрути (id, start_point, end_point)
     */
    public function apiIndex()
    {
        $routes = BusRoute::select('id', 'start_point', 'end_point')->get();
        return response()->json($routes);
    }

    /**
     * API: доступні дати для обраного маршруту
     * Об’єднуємо робочі дні всіх автобусів, які обслуговують пару зупинок (start -> end).
     */
    public function availableDates($routeId)
    {
        $route = BusRoute::findOrFail($routeId);

        $buses = $this->busesServingRoute($route->start_point, $route->end_point);

        $start = Carbon::today();
        $end   = (clone $start)->addDays(60);

        $dates = [];
        for ($d = $start->copy(); $d <= $end; $d->addDay()) {
            foreach ($buses as $bus) {
                if ($this->busRunsOnDate($bus, $d)) {
                    $dates[] = $d->toDateString();
                    break;
                }
            }
        }

        return response()->json(array_values($dates));
    }

    /**
     * API: рейси на конкретну дату для обраного маршруту
     */
    public function getBusesByDate(Request $request): \Illuminate\Http\JsonResponse
    {
        $routeId = $request->input('route_id');
        $date    = $request->input('date');

        $route  = BusRoute::findOrFail($routeId);
        $dateObj = Carbon::parse($date);

        $buses = $this->busesServingRoute($route->start_point, $route->end_point);

        $results = [];
        foreach ($buses as $bus) {
            if (!$this->busRunsOnDate($bus, $dateObj)) {
                continue;
            }

            // знайдемо конкретні зупинки для цієї пари
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

            // Trip (якщо ціни/часи там)
            $trip = Trip::where('bus_id', $bus->id)
                ->where('start_location', $route->start_point)
                ->where('end_location', $route->end_point)
                ->first();

            // мінімальна ціна по місцях
            $seatLayout = is_string($bus->seat_layout) ? json_decode($bus->seat_layout, true) : $bus->seat_layout;
            $minPrice = collect($seatLayout ?: [])
                ->where('type', 'seat')
                ->pluck('price')
                ->map(fn($v) => (float)$v)
                ->filter()
                ->min() ?? 0;

            // вільні місця
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

    /**
     * Повертає всі Bus, які мають boarding=startName і dropping=endName
     * (незалежно від buses.route_id)
     */
    private function busesServingRoute(string $startName, string $endName)
    {
        // підзапит для автобусів з boarding=startName
        $boardingBusIds = DB::table('bus_stops')
            ->join('stops', 'stops.id', '=', 'bus_stops.stop_id')
            ->where('bus_stops.type', 'boarding')
            ->where('stops.name', $startName)
            ->pluck('bus_stops.bus_id');

        // підзапит для автобусів з dropping=endName
        $droppingBusIds = DB::table('bus_stops')
            ->join('stops', 'stops.id', '=', 'bus_stops.stop_id')
            ->where('bus_stops.type', 'dropping')
            ->where('stops.name', $endName)
            ->pluck('bus_stops.bus_id');

        return Bus::whereIn('id', $boardingBusIds)
            ->whereIn('id', $droppingBusIds)
            ->get();
    }

    /**
     * Чи працює автобус у конкретну дату
     */
    private function busRunsOnDate(Bus $bus, Carbon $date): bool
    {
        $type = $bus->schedule_type ?? 'weekly';

        if ($type === 'daily') {
            return true;
        }

        if ($type === 'weekly') {
            $weekday = $date->format('l'); // Sunday/Monday/...
            $days = is_string($bus->weekly_operation_days)
                ? json_decode($bus->weekly_operation_days, true)
                : ($bus->weekly_operation_days ?? []);
            return in_array($weekday, $days ?? [], true);
        }

        // custom: існування запису у зв’язаних schedules на цю дату (якщо є зв’язок)
        if (method_exists($bus, 'schedules')) {
            return $bus->schedules()->whereDate('date', $date->toDateString())->exists();
        }

        return false;
    }
}
