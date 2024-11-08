<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_id',
        'start_location',
        'end_location',
        'departure_time',
        'arrival_time',
        'price',
    ];

//    public function calculatePrice()
//    {
//        $route = Route::find($this->route_id);
//        return $route ? $route->ticket_price : 0;
//    }

    public function route()
    {
        Log::info('TPrice', (array)Route::find($this->id));
        return $this->belongsTo(Route::class, 'id');
    }

    public function calculatePrice()
    {
//        Log::info('TPrice', $this->route);
        return $this->route ? $this->route->ticket_price : 0;
    }

    // Відношення до моделі Bus
    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }
}
