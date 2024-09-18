<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Bus;
use Illuminate\Http\Request;
use App\Models\RouteSchedule;
class RouteController extends Controller
{
    public function index()
    {
        // Отримуємо всі маршрути для відображення у випадаючому списку
        $routes = Route::all();

        return view('admin.routes.index', compact('routes'));
    }

    public function getBusesByDate(Request $request)
    {
        // Отримуємо маршрут та дату з запиту
        $routeId = $request->input('route_id');
        $date = $request->input('date');

        // Отримуємо автобуси, які працюють на цьому маршруті в цю дату
        $buses = Bus::where('route_id', $routeId)
            ->whereHas('schedules', function ($query) use ($date) {
                $query->where('date', $date);
            })
            ->get();

        // Повертаємо автобуси у форматі JSON
        return response()->json($buses);
    }
}

