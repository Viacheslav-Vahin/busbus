<?php
// app/Models/SeatHold.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatHold extends Model
{
    protected $fillable = ['bus_id','date','seat_number','token','expires_at'];
    protected $casts = [
        'date' => 'date',
        'expires_at' => 'datetime',
    ];
}

