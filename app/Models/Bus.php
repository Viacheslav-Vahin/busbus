<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

    protected $casts = [
        'seat_configuration' => 'array',
        'seat_layout' => 'array',
    ];
    protected $fillable = [
        'name',
        'seats_count',
        'registration_number',
        'description',
        'seat_layout',
    ];

    public static function boot()
    {
        parent::boot();

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


}

