<?php

// app/Models/BusSeat.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BusSeat extends Model {
    protected $fillable = [
        'bus_id','number','seat_type_id','x','y','price_modifier_abs','price_modifier_pct','is_active','meta'
    ];
    protected $casts = ['meta'=>'array','is_active'=>'boolean'];
    public function bus(){ return $this->belongsTo(Bus::class); }
    public function seatType(){ return $this->belongsTo(\App\Models\SeatType::class); }

}
