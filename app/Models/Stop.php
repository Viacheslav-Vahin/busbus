<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stop extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function buses()
    {
        return $this->belongsToMany(Bus::class, 'bus_stops')
            ->withPivot('type', 'time')
            ->withTimestamps();
    }

    public function busStops()
    {
        return $this->hasMany(BusStop::class);
    }

    public function getBoardingPointsAttribute()
    {
        return $this->busStops()->where('type', 'boarding')->get();
    }

    public function getDroppingPointsAttribute()
    {
        return $this->busStops()->where('type', 'dropping')->get();
    }

    public function getBoardingPointsTimeAttribute()
    {
        return $this->boardingPoints->pluck('time');
    }

    public function getDroppingPointsTimeAttribute()
    {
        return $this->droppingPoints->pluck('time');
    }

    public function getBoardingPointsTimeFormattedAttribute()
    {
        return $this->boardingPointsTime->map(function ($time) {
            return date('h:i A', strtotime($time));
        });
    }

    public function getDroppingPointsTimeFormattedAttribute()
    {
        return $this->droppingPointsTime->map(function ($time) {
            return date('h:i A', strtotime($time));
        });
    }

    public function getBoardingPointsTimeFormattedStringAttribute()
    {
        return $this->boardingPointsTimeFormatted->implode(', ');
    }

    public function getDroppingPointsTimeFormattedStringAttribute()
    {
        return $this->droppingPointsTimeFormatted->implode(', ');
    }

    public function getBoardingPointsTimeFormattedArrayAttribute()
    {
        return $this->boardingPointsTimeFormatted->toArray();
    }

    public function getDroppingPointsTimeFormattedArrayAttribute()
    {
        return $this->droppingPointsTimeFormatted->toArray();
    }
}


