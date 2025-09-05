<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusStop extends Model
{
    use HasFactory;

    protected $fillable = ['bus_id', 'stop_id', 'type', 'time'];

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function stop()
    {
        return $this->belongsTo(Stop::class);
    }

    /* ==== scopes ==== */
    public function scopeBoardingAt($q, int $stopId)
    {
        return $q->where('type', 'boarding')->where('stop_id', $stopId);
    }

    public function scopeDroppingAt($q, int $stopId)
    {
        return $q->where('type', 'dropping')->where('stop_id', $stopId);
    }
}
