<?php

// app/Models/SeatType.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SeatType extends Model {
    protected $fillable = ['code','name','modifier_type','modifier_value','icon'];
}
