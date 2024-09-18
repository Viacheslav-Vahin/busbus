<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RouteSchedule;

class RouteScheduleController extends Controller
{
    public function getBusesByDate(Request $request)
    {
        $buses = RouteSchedule::where('route_id', $request->route_id)
            ->where('date', $request->date)
            ->with('bus')
            ->get();

        return response()->json($buses);
    }
}

