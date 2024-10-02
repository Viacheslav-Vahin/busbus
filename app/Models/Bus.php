<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

    protected $casts = [
        'has_operation_days' => 'boolean',
        'operation_days' => 'array',
        'has_off_days' => 'boolean',
        'off_days' => 'array',
        'seat_configuration' => 'array',
        'seat_layout' => 'array',
        'schedule_type' => 'string',
        'weekly_operation_days' => 'array',
    ];
    protected $fillable = [
        'name',
        'seats_count',
        'registration_number',
        'description',
        'seat_layout',
        'has_operation_days',
        'operation_days',
        'has_off_days',
        'off_days',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->has_operation_days = $model->has_operation_days ?? false;
            $model->has_off_days = $model->has_off_days ?? false;
        });

        static::saving(function ($bus) {
            if (!is_array($bus->seat_layout)) {
                $bus->seat_layout = json_encode($bus->seat_layout);
            }
        });
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function schedules()
    {
        return $this->hasMany(RouteSchedule::class);
    }

    public function operationDays()
    {
        return $this->hasMany(OperationDay::class);
    }

    public function offDays()
    {
        return $this->hasMany(OffDay::class);
    }

    public function stops()
    {
        return $this->belongsToMany(Stop::class, 'bus_stops')
            ->withPivot('type', 'time')
            ->withTimestamps();
    }

    public function busStops()
    {
        return $this->hasMany(BusStop::class);
    }
    public function boarding_points()
    {
        return $this->hasMany(BusStop::class)->where('type', 'boarding');
    }

    public function droppingPoints()
    {
        return $this->hasMany(BusStop::class)->where('type', 'dropping');
    }

}

