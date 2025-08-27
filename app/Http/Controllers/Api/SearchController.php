<?php
// app/Http/Controllers/Api/SearchController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trip;
use App\Models\Bus;

class SearchController extends Controller
{
    public function index(Request $req)
    {
        $req->validate(['from'=>'required','to'=>'required','date'=>'required|date']);
        $from = $req->string('from'); $to = $req->string('to');
        $date = $req->date('date');

        $trips = Trip::query()
            ->whereDate('departure_time', $date)
            ->where('start_location', 'like', $from.'%')
            ->where('end_location', 'like', $to.'%')
            ->with('bus:id,name,seat_layout')
            ->orderBy('departure_time')
            ->get(['id','bus_id','start_location','end_location','departure_time','arrival_time','price']);

        return response()->json($trips);
    }
}
