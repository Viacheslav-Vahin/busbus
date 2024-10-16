<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\Bus;
use App\Models\Stop;

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
}
