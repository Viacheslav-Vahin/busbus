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
    /* ===================== Helpers ===================== */

    private function arr($v): array
    {
        if (is_array($v)) return $v;
        if (is_string($v)) {
            $d = json_decode($v, true);
            return is_array($d) ? $d : [];
        }
        return [];
    }

    private function busRunsOnDate(Bus $bus, Carbon $date): bool
    {
        $ds  = $date->toDateString();
        $dow = strtolower($date->locale('en')->isoFormat('dddd')); // monday..sunday

        // 1) off_days блокують роботу
        $offDays = collect($this->arr($bus->off_days))
            ->map(fn($x) => is_array($x) ? ($x['date'] ?? null) : null)
            ->filter()->values()->all();
        if (in_array($ds, $offDays, true)) {
            return false;
        }

        // 2) головне джерело — operation_days
        $opDays = collect($this->arr($bus->operation_days))
            ->map(fn($x) => is_array($x) ? ($x['date'] ?? null) : null)
            ->filter()->values()->all();
        if (!empty($opDays)) {
            return in_array($ds, $opDays, true);
        }

        // 3) weekly як фолбек
        $weekly = array_map(fn($x) => strtolower((string)$x), $this->arr($bus->weekly_operation_days));
        if (!empty($weekly)) {
            return in_array($dow, $weekly, true);
        }

        // 4) schedules як ще один фолбек
        if (method_exists($bus, 'schedules')) {
            return $bus->schedules()->whereDate('date', $ds)->exists();
        }

        // 5) без джерел — не працює
        return false;
    }

    private function busCapacity(Bus $bus): int
    {
        // використовуємо метод моделі, якщо є
        if (method_exists($bus, 'capacity')) {
            return (int)$bus->capacity();
        }

        // фолбек: seats_count або рахувати з seat_layout
        $count = (int)($bus->seats_count ?? 0);
        if ($count > 0) return $count;

        $layout = $this->arr($bus->seat_layout);
        if (!$layout) return 0;

        $seatCount = 0;
        foreach ($layout as $item) {
            $type = is_array($item) ? strtolower((string)($item['type'] ?? '')) : '';
            if ($type === 'seat' || $type === 'chair' || $type === 's') {
                $seatCount++;
            }
        }
        return $seatCount;
    }

    /* ===================== Endpoints ===================== */

    public function getBusesByDate(Request $request)
    {
        $routeId = $request->input('route_id');
        $date    = $request->input('date');

        $route   = BusRoute::findOrFail($routeId);
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
                ->min() ?? 0.0;

            $bookedSeats = Booking::where('bus_id', $bus->id)
                ->whereDate('date', $dateObj->toDateString())
                ->count();

            $capacity  = $this->busCapacity($bus);
            $freeSeats = max(0, $capacity - $bookedSeats);
            if ($freeSeats <= 0) {
                continue;
            }

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

        return response()->json(array_values($results));
    }

    public function getAvailableDates(Request $request, $routeId)
    {
        $route   = BusRoute::findOrFail($routeId);
        $days    = max(1, min(120, (int)$request->query('days', 60)));
        $start   = Carbon::today();
        $dates   = [];

        // шукаємо ті самі автобуси, що й у getBusesByDate
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

        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            $ds = $d->toDateString();

            $hasAny = false;
            foreach ($buses as $bus) {
                if (!$this->busRunsOnDate($bus, $d)) continue;

                $booked = Booking::where('bus_id', $bus->id)
                    ->whereDate('date', $ds)
                    ->count();

                $capacity = $this->busCapacity($bus);
                $free     = max(0, $capacity - $booked);

                if ($free > 0) { $hasAny = true; break; }
            }

            if ($hasAny) {
                $dates[] = $ds; // 'YYYY-MM-DD'
            }
        }

        return response()->json(array_values(array_unique($dates)));
    }
}
