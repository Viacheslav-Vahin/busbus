<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder select(...$columns)
 */
class Route extends Model
{
    use HasFactory;
    // Route.php
    protected $fillable = ['start_point', 'end_point', 'date', 'ticket_price'];

    public function buses()
    {
        return $this->hasMany(Bus::class);
    }

    public function schedules()
    {
        return $this->hasMany(RouteSchedule::class);
    }

}

