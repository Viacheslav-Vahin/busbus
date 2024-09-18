<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $fillable = ['start_point', 'end_point', 'date'];

    public function buses()
    {
        return $this->hasMany(Bus::class);
    }

    public function schedules()
    {
        return $this->hasMany(RouteSchedule::class);
    }

}

