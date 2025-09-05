<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_id',
        'route_id',          // nullable
        'start_stop_id',     // FK -> stops.id
        'end_stop_id',       // FK -> stops.id
        'start_location',    // лишаємо для back-compat
        'end_location',
        'departure_time',
        'arrival_time',
        'price',
    ];

    protected $casts = [
        'departure_time' => 'string',
        'arrival_time'   => 'string',
        'price'          => 'float',
    ];

    /* ===== Relations ===== */

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function route()
    {
        // якщо в trips є route_id — використовуємо; інакше беремо через bus->route
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function startStop()
    {
        return $this->belongsTo(Stop::class, 'start_stop_id');
    }

    public function endStop()
    {
        return $this->belongsTo(Stop::class, 'end_stop_id');
    }

    /* ===== Helpers ===== */

    public function calculatePrice(): float
    {
        if (!empty($this->price)) {
            return (float)$this->price;
        }

        if ($this->relationLoaded('route') && $this->route) {
            return (float)($this->route->ticket_price ?? 0);
        }

        $bus = $this->relationLoaded('bus') ? $this->bus : Bus::find($this->bus_id);
        if ($bus && $bus->route) {
            return (float)($bus->route->ticket_price ?? 0);
        }

        return 0.0;
    }
}
